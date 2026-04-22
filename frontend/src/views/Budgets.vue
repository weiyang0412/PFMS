<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axiosInstance from '../lib/axios';
import { useToast } from '../composables/useToast.js';
import { useUserStore } from '../stores/userStore';
import { formatCurrencyMYR, malaysiaCurrentMonthYm } from '../lib/formatters.js';

interface BudgetItem {
  category_id: number;
  category: string;
  budget_id: number | null;
  amount: number | null;
  alert_threshold: number;
  spent: number;
  remaining: number | null;
  usage_pct: number;
  alert_level: 'none' | 'safe' | 'warning' | 'over';
  can_edit?: boolean;
}

interface BudgetSummary {
  total_budget: number;
  total_spent: number;
  warning_count: number;
  total_overspent: number;
}
interface StudentSemester {
  id: number;
  name: string;
  start_date: string;
  end_date: string;
}

const month = ref(malaysiaCurrentMonthYm());
const periodType = ref<'monthly' | 'semester'>('monthly');
const studentSemesters = ref<StudentSemester[]>([]);
const selectedSemesterId = ref<number | null>(null);
const items = ref<BudgetItem[]>([]);
const summary = ref<BudgetSummary>({ total_budget: 0, total_spent: 0, warning_count: 0, total_overspent: 0 });
const isLoading = ref(false);
const isCopying = ref(false);
const hasLoaded = ref(false);
const savingCategoryIds = ref<number[]>([]);
const deletingCategoryIds = ref<number[]>([]);
const toast = useToast();
const route = useRoute();
const router = useRouter();
const userStore = useUserStore();
const isStudentProfile = computed(() => userStore.user?.profile_type === 'student');
const storedThreshold = Number(localStorage.getItem('budget_default_threshold') || 80);
const defaultAlertThreshold = ref(Number.isFinite(storedThreshold) && storedThreshold >= 1 && storedThreshold <= 100 ? storedThreshold : 80);

const isValidMonth = (value: string) => /^\d{4}-\d{2}$/.test(value);
if (typeof route.query.month === 'string' && isValidMonth(route.query.month)) {
  month.value = route.query.month;
}
if (typeof route.query.period === 'string' && ['monthly', 'semester'].includes(route.query.period)) {
  periodType.value = route.query.period as 'monthly' | 'semester';
}
if (typeof route.query.semester_id === 'string' && Number(route.query.semester_id) > 0) {
  selectedSemesterId.value = Number(route.query.semester_id);
}

const formatMoney = (value = 0) => formatCurrencyMYR(value);
const longDate = (value = '') => {
  if (!value) return '-';
  const parsed = new Date(`${value}T00:00:00`);
  if (Number.isNaN(parsed.getTime())) return value;
  return new Intl.DateTimeFormat('en-GB', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  }).format(parsed);
};
const sortedSemesters = computed(() =>
  [...studentSemesters.value].sort((a, b) => {
    const aTime = new Date(`${a.start_date}T00:00:00`).getTime();
    const bTime = new Date(`${b.start_date}T00:00:00`).getTime();
    return aTime - bTime;
  }),
);
const selectedSemesterIndex = computed(() =>
  sortedSemesters.value.findIndex((semester) => semester.id === selectedSemesterId.value),
);
const selectedSemesterLabel = computed(() => {
  const semester = sortedSemesters.value.find((item) => item.id === selectedSemesterId.value);
  if (!semester) return 'No semester selected';
  return `${semester.name} (${longDate(semester.start_date)} to ${longDate(semester.end_date)})`;
});
const canShiftPrev = computed(() => {
  if (periodType.value !== 'semester') return true;
  return selectedSemesterIndex.value > 0;
});
const canShiftNext = computed(() => {
  if (periodType.value !== 'semester') return true;
  return selectedSemesterIndex.value >= 0 && selectedSemesterIndex.value < sortedSemesters.value.length - 1;
});

const isSaving = (categoryId: number) => savingCategoryIds.value.includes(categoryId);
const isDeleting = (categoryId: number) => deletingCategoryIds.value.includes(categoryId);

const setBusy = (type: 'save' | 'delete', categoryId: number, enabled: boolean) => {
  const source = type === 'save' ? savingCategoryIds : deletingCategoryIds;
  const current = new Set(source.value);
  if (enabled) current.add(categoryId);
  else current.delete(categoryId);
  source.value = [...current];
};

const loadBudgets = async () => {
  isLoading.value = true;
  try {
    const params: Record<string, string | number> = {
      month: month.value,
      period: periodType.value,
      default_threshold: defaultAlertThreshold.value,
    };
    if (periodType.value === 'semester' && selectedSemesterId.value) {
      params.semester_id = selectedSemesterId.value;
    }

    const { data } = await axiosInstance.get('/budgets', { params });
    items.value = data.items || [];
    summary.value = data.summary || { total_budget: 0, total_spent: 0, warning_count: 0, total_overspent: 0 };
    if (data?.range?.semester_id) {
      selectedSemesterId.value = data.range.semester_id;
    }
  } catch (error) {
    console.error(error);
    toast.show('Unable to load budgets right now.', 'danger');
  } finally {
    hasLoaded.value = true;
    isLoading.value = false;
  }
};

const saveBudget = async (item: BudgetItem) => {
  if (!item.can_edit) return;
  setBusy('save', item.category_id, true);
  try {
    const amount = Number(item.amount ?? 0);
    if (!Number.isFinite(amount) || amount <= 0) {
      toast.show('Budget amount must be greater than 0.', 'danger');
      return;
    }
    const threshold = Number(item.alert_threshold || 80);
    await axiosInstance.post('/budgets', {
      month: month.value,
      transaction_category_id: item.category_id,
      amount,
      alert_threshold: Math.min(Math.max(threshold, 1), 100),
    });
    await loadBudgets();
    toast.show(`Budget saved for ${item.category}.`, 'success');
  } catch (error) {
    console.error(error);
    toast.show(`Unable to save budget for ${item.category}.`, 'danger');
  } finally {
    setBusy('save', item.category_id, false);
  }
};

const clearBudget = async (item: BudgetItem) => {
  if (!item.can_edit) return;
  if (!item.budget_id) return;
  setBusy('delete', item.category_id, true);
  try {
    await axiosInstance.delete(`/budgets/${item.budget_id}`);
    await loadBudgets();
    toast.show(`Budget removed for ${item.category}.`, 'danger');
  } catch (error) {
    console.error(error);
    toast.show(`Unable to remove budget for ${item.category}.`, 'danger');
  } finally {
    setBusy('delete', item.category_id, false);
  }
};

const warningItems = computed(() => items.value.filter((item) => item.alert_level === 'warning' || item.alert_level === 'over'));
const focusedCategoryId = computed(() => {
  const raw = route.query.category_id;
  const parsed = Number(Array.isArray(raw) ? raw[0] : raw);
  return Number.isFinite(parsed) && parsed > 0 ? parsed : null;
});

const copyPreviousMonth = async () => {
  if (periodType.value === 'semester') return;
  isCopying.value = true;
  try {
    const { data } = await axiosInstance.post('/budgets/copy-previous-month', { month: month.value });
    await loadBudgets();
    const copiedCount = Number(data?.copied_count ?? 0);
    toast.show(copiedCount > 0 ? `Copied ${copiedCount} budget(s) from previous month.` : 'No previous budgets to copy.', copiedCount > 0 ? 'success' : 'danger');
  } catch (error) {
    console.error(error);
    toast.show('Unable to copy previous month budgets.', 'danger');
  } finally {
    isCopying.value = false;
  }
};
const loadStudentSemesters = async () => {
  if (!isStudentProfile.value) {
    studentSemesters.value = [];
    selectedSemesterId.value = null;
    return;
  }

  try {
    const { data } = await axiosInstance.get('/student-semesters');
    studentSemesters.value = Array.isArray(data?.items) ? data.items : [];
    if (!selectedSemesterId.value && studentSemesters.value.length > 0) {
      selectedSemesterId.value = studentSemesters.value[0].id;
    }
  } catch (error) {
    console.error(error);
  }
};

const clearFocus = () => {
  const nextQuery = { ...route.query };
  delete nextQuery.category_id;
  router.replace({ query: nextQuery });
};

const shiftPeriod = (offset: number) => {
  if (periodType.value === 'semester') {
    if (!sortedSemesters.value.length) return;
    const currentIndex = selectedSemesterIndex.value;
    if (currentIndex < 0) return;
    const nextIndex = currentIndex + offset;
    if (nextIndex < 0 || nextIndex >= sortedSemesters.value.length) return;
    selectedSemesterId.value = sortedSemesters.value[nextIndex].id;
    return;
  }

  const [year, monthPart] = month.value.split('-').map(Number);
  if (!year || !monthPart) return;
  const nextDate = new Date(year, monthPart - 1 + offset, 1);
  month.value = `${nextDate.getFullYear()}-${String(nextDate.getMonth() + 1).padStart(2, '0')}`;
  loadBudgets();
};

const persistDefaultThreshold = () => {
  const safe = Math.min(100, Math.max(1, Number(defaultAlertThreshold.value) || 80));
  defaultAlertThreshold.value = safe;
  localStorage.setItem('budget_default_threshold', String(safe));
  items.value = items.value.map((item) => (item.budget_id ? item : { ...item, alert_threshold: safe }));
};
const showFullScreenLoading = computed(() => isLoading.value && !hasLoaded.value);
const showTableSkeleton = computed(() => isLoading.value && hasLoaded.value);

onMounted(() => {
  periodType.value = isStudentProfile.value
    ? ((userStore.user?.preferred_period as 'monthly' | 'semester') || periodType.value)
    : 'monthly';
  loadStudentSemesters().finally(() => loadBudgets());
});

watch(
  () => route.query.month,
  (nextMonth) => {
    if (typeof nextMonth === 'string' && isValidMonth(nextMonth) && nextMonth !== month.value) {
      month.value = nextMonth;
      loadBudgets();
    }
  },
);

watch(
  () => month.value,
  (nextMonth) => {
    const nextQuery: Record<string, any> = { ...route.query, month: nextMonth, period: periodType.value };
    if (periodType.value === 'semester' && selectedSemesterId.value) {
      nextQuery.semester_id = String(selectedSemesterId.value);
    } else {
      delete nextQuery.semester_id;
    }
    router.replace({ query: nextQuery });
  },
);
watch(
  () => periodType.value,
  () => {
    loadBudgets();
  },
);
watch(
  () => selectedSemesterId.value,
  () => {
    if (periodType.value === 'semester') {
      loadBudgets();
    }
  },
);
watch(
  () => userStore.user?.profile_type,
  (nextType) => {
    if (nextType !== 'student' && periodType.value !== 'monthly') {
      periodType.value = 'monthly';
      selectedSemesterId.value = null;
    }
  },
);

</script>

<template>
  <div class="h-full w-full bg-slate-100 p-6">
    <div class="space-y-6">
      <section class="rounded-[32px] bg-slate-950 px-6 py-8 text-white shadow-2xl shadow-slate-900/10">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
          <div>
            <p class="text-sm uppercase tracking-[0.28em] text-slate-400">Budget & Alerts</p>
            <h1 class="mt-2 text-3xl font-semibold sm:text-4xl">Control monthly spending</h1>
            <p class="mt-2 text-sm text-slate-300">Set monthly category budgets and get warning alerts before overspending.</p>
          </div>
          <div class="flex flex-wrap items-center gap-3">
            <div v-if="isStudentProfile" class="inline-flex items-center gap-1 rounded-xl border border-white/20 bg-white/10 p-1 shadow-sm">
              <button
                type="button"
                class="h-10 rounded-lg px-3 text-sm font-medium transition"
                :class="periodType === 'monthly' ? 'bg-white/20 text-white' : 'text-white/90 hover:bg-white/15'"
                @click="periodType = 'monthly'; selectedSemesterId = null"
              >
                Monthly
              </button>
              <button
                v-if="isStudentProfile"
                type="button"
                class="h-10 rounded-lg px-3 text-sm font-medium transition"
                :class="periodType === 'semester' ? 'bg-white/20 text-white' : 'text-white/90 hover:bg-white/15'"
                @click="periodType = 'semester'"
              >
                Semester
              </button>
            </div>
            <div class="inline-flex items-center rounded-xl border border-white/20 bg-white/10 p-1 shadow-sm">
              <button type="button" @click="shiftPeriod(-1)" :disabled="!canShiftPrev" class="h-10 rounded-md px-3 text-sm font-medium text-white/90 hover:bg-white/15 disabled:opacity-50 disabled:cursor-not-allowed">
                Prev
              </button>
              <input v-if="periodType === 'monthly'" v-model="month" type="month" class="mx-1 h-10 rounded-md border border-white/20 bg-slate-900/50 px-3 text-sm text-white focus:ring-2 focus:ring-cyan-500/40" />
              <div v-else class="mx-1 flex h-10 min-w-[280px] items-center rounded-md border border-white/20 bg-slate-900/50 px-3 text-sm text-white">
                {{ selectedSemesterLabel }}
              </div>
              <button type="button" @click="shiftPeriod(1)" :disabled="!canShiftNext" class="h-10 rounded-md px-3 text-sm font-medium text-white/90 hover:bg-white/15 disabled:opacity-50 disabled:cursor-not-allowed">
                Next
              </button>
            </div>
            <div class="inline-flex items-center rounded-xl border border-white/20 bg-white/10 p-1 shadow-sm">
              <div class="mx-1 flex h-10 items-center gap-2 rounded-md border border-white/20 bg-slate-900/50 px-3">
                <span class="text-xs text-slate-300">Default Alert %</span>
                <input v-model.number="defaultAlertThreshold" type="number" min="1" max="100" class="h-7 w-14 rounded-md border border-white/20 bg-slate-900/70 px-2 py-1 text-sm text-white" @change="persistDefaultThreshold" />
              </div>
              <button type="button" @click="copyPreviousMonth" :disabled="isLoading || isCopying || periodType === 'semester'" class="rounded-md px-4 py-2 text-sm font-medium text-white/90 hover:bg-white/15 disabled:opacity-60">
                {{ isCopying ? 'Copying...' : 'Copy Prev Month' }}
              </button>
              <button type="button" @click="loadBudgets" :disabled="isLoading" class="rounded-lg border border-white/15 bg-white/15 px-4 py-2 text-sm font-medium text-white transition hover:bg-white/25 disabled:opacity-60">
                {{ isLoading ? 'Loading...' : 'Load' }}
              </button>
            </div>
          </div>
        </div>
      </section>

      <section v-if="focusedCategoryId" class="rounded-2xl border border-sky-200 bg-sky-50 p-3 text-sm text-sky-800 shadow-sm">
        Focused on category from alert.
        <button type="button" class="ml-2 font-medium underline" @click="clearFocus">Show all</button>
      </section>

      <section class="grid gap-6 lg:grid-cols-4">
        <article class="rounded-[28px] bg-white p-6 shadow-sm ring-1 ring-slate-200/70">
          <p class="text-sm text-slate-500">Total Budget</p>
          <p class="mt-3 text-3xl font-semibold text-slate-900">{{ formatMoney(summary.total_budget) }}</p>
        </article>
        <article class="rounded-[28px] bg-white p-6 shadow-sm ring-1 ring-slate-200/70">
          <p class="text-sm text-slate-500">Total Spent</p>
          <p class="mt-3 text-3xl font-semibold text-slate-900">{{ formatMoney(summary.total_spent) }}</p>
        </article>
        <article class="rounded-[28px] bg-white p-6 shadow-sm ring-1 ring-slate-200/70">
          <p class="text-sm text-slate-500">Alerts Triggered</p>
          <p class="mt-3 text-3xl font-semibold text-amber-600">{{ summary.warning_count }}</p>
        </article>
        <article class="rounded-[28px] bg-white p-6 shadow-sm ring-1 ring-slate-200/70">
          <p class="text-sm text-slate-500">Overspent Total</p>
          <p class="mt-3 text-3xl font-semibold text-red-600">{{ formatMoney(summary.total_overspent) }}</p>
        </article>
      </section>

      <section v-if="warningItems.length" class="rounded-2xl border border-amber-300 bg-amber-50 p-4 text-amber-900 shadow-sm">
        <p class="text-sm font-medium">Active Alerts</p>
        <div class="mt-2 flex flex-wrap gap-2">
          <span v-for="item in warningItems" :key="item.category_id" class="rounded-full bg-white px-3 py-1 text-xs ring-1 ring-amber-300">
            {{ item.category }} · {{ Number(item.usage_pct).toFixed(1) }}%
          </span>
        </div>
      </section>

      <section class="rounded-[28px] bg-white p-6 shadow-sm ring-1 ring-slate-200/70">
        <div class="mb-4">
          <h2 class="text-xl font-semibold text-slate-900">Category Budgets</h2>
          <p class="text-sm text-slate-500">Save a budget per category. Alert triggers when usage reaches threshold.</p>
        </div>

        <div v-if="!isLoading && items.length === 0" class="py-8 text-center text-slate-500">No categories yet. Create categories in Manage Options first.</div>

        <div v-else class="max-h-[560px] overflow-auto">
          <table class="min-w-full border-collapse text-left">
            <thead class="sticky top-0 z-10 border-b border-slate-200 bg-white">
              <tr class="text-sm text-slate-600">
                <th class="px-4 py-3">Category</th>
                <th class="px-4 py-3">Budget</th>
                <th class="px-4 py-3">Alert %</th>
                <th class="px-4 py-3">Spent</th>
                <th class="px-4 py-3">Remaining</th>
                <th class="px-4 py-3">Usage</th>
                <th class="px-4 py-3">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="showTableSkeleton" v-for="n in 5" :key="`skeleton-${n}`" class="border-b border-slate-100 text-sm">
                <td class="px-4 py-4"><div class="h-4 w-28 animate-pulse rounded bg-slate-100"></div></td>
                <td class="px-4 py-4"><div class="h-8 w-24 animate-pulse rounded bg-slate-100"></div></td>
                <td class="px-4 py-4"><div class="h-8 w-20 animate-pulse rounded bg-slate-100"></div></td>
                <td class="px-4 py-4"><div class="h-4 w-20 animate-pulse rounded bg-slate-100"></div></td>
                <td class="px-4 py-4"><div class="h-4 w-20 animate-pulse rounded bg-slate-100"></div></td>
                <td class="px-4 py-4"><div class="h-4 w-24 animate-pulse rounded bg-slate-100"></div></td>
                <td class="px-4 py-4"><div class="h-8 w-40 animate-pulse rounded bg-slate-100"></div></td>
              </tr>
              <tr v-else v-for="item in items" :key="item.category_id" class="border-b border-slate-100 text-sm transition-colors hover:bg-slate-50" :class="focusedCategoryId === item.category_id ? 'bg-blue-50' : ''">
                <td class="px-4 py-4 font-medium text-slate-900">{{ item.category }}</td>
                <td class="px-4 py-4">
                  <input v-model.number="item.amount" type="number" min="0" step="0.01" placeholder="0.00" class="w-32 rounded-md border border-gray-300 px-2 py-1.5 focus:ring-2 focus:ring-blue-500" />
                </td>
                <td class="px-4 py-4">
                  <input v-model.number="item.alert_threshold" type="number" min="1" max="100" step="1" class="w-20 rounded-md border border-gray-300 px-2 py-1.5 focus:ring-2 focus:ring-blue-500" />
                </td>
                <td class="px-4 py-4">{{ formatMoney(item.spent) }}</td>
                <td class="px-4 py-4" :class="item.remaining != null && item.remaining < 0 ? 'text-red-600' : 'text-slate-700'">
                  {{ item.remaining == null ? '—' : formatMoney(item.remaining) }}
                </td>
                <td class="px-4 py-4">
                  <div class="w-36">
                    <div class="mb-1 flex items-center justify-between text-xs">
                      <span :class="item.alert_level === 'over' ? 'text-red-600' : item.alert_level === 'warning' ? 'text-amber-600' : 'text-slate-500'">
                        {{ Number(item.usage_pct).toFixed(1) }}%
                      </span>
                    </div>
                    <div class="h-2 rounded-full bg-slate-200">
                      <div class="h-2 rounded-full transition-all"
                        :class="item.alert_level === 'over' ? 'bg-red-600' : item.alert_level === 'warning' ? 'bg-amber-500' : 'bg-emerald-500'"
                        :style="{ width: `${Math.min(Math.max(item.usage_pct, 0), 100)}%` }"></div>
                    </div>
                  </div>
                </td>
                <td class="px-4 py-4">
                  <div class="flex gap-2">
                    <RouterLink :to="{ path: '/transactions', query: { category: item.category, month, period: periodType, semester_id: periodType === 'semester' ? selectedSemesterId : undefined } }" class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                      View Txns
                    </RouterLink>
                    <button type="button" @click="saveBudget(item)" :disabled="!item.can_edit || isSaving(item.category_id) || isDeleting(item.category_id)" class="rounded-md bg-slate-900 px-3 py-1.5 text-xs font-medium text-white hover:bg-slate-800 disabled:opacity-60">
                      {{ isSaving(item.category_id) ? 'Saving...' : 'Save' }}
                    </button>
                    <button type="button" @click="clearBudget(item)" :disabled="!item.can_edit || !item.budget_id || isDeleting(item.category_id) || isSaving(item.category_id)" class="rounded-md border border-red-700 bg-red-50 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-100 disabled:opacity-50">
                      {{ isDeleting(item.category_id) ? 'Removing...' : 'Clear' }}
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <Teleport to="body">
        <Transition name="loading-fade">
          <div v-if="showFullScreenLoading" class="fixed inset-0 z-[120] flex items-center justify-center bg-black/55 px-4">
            <div class="flex w-full max-w-xs flex-col items-center rounded-2xl bg-white px-6 py-7 text-center shadow-2xl">
              <div class="relative mb-4 h-12 w-12">
                <span class="absolute inset-0 rounded-full border-4 border-slate-200"></span>
                <span class="absolute inset-0 animate-spin rounded-full border-4 border-transparent border-t-cyan-500 border-r-blue-600"></span>
              </div>
              <p class="text-lg font-semibold text-slate-900">Loading ...</p>
            </div>
          </div>
        </Transition>
      </Teleport>

    </div>
  </div>
</template>
