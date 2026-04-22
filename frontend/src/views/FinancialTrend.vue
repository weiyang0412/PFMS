<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import axiosInstance from '../lib/axios';
import { useUserStore } from '../stores/userStore';
import { formatCurrencyMYR, formatYmdDate } from '../lib/formatters.js';

interface DashboardTrendItem {
  month: string;
  income: number;
  expense: number;
  net: number;
}

interface DashboardCategoryItem {
  category: string;
  amount: number;
  percentage: number;
}

interface DashboardPeriod {
  type?: 'monthly' | 'semester';
  label: string;
  comparison_label?: string;
  updated_at: string;
  month?: string;
  semester_id?: number | null;
}

interface DashboardSummary {
  monthly_trend: DashboardTrendItem[];
  category_breakdown: DashboardCategoryItem[];
  period: DashboardPeriod;
}
interface StudentSemester {
  id: number;
  name: string;
  start_date: string;
  end_date: string;
}

type TrendRange = '3' | '6' | 'all';
type ChartMode = 'bar' | 'line';

const isLoading = ref(false);
const isRefreshing = ref(false);
const loadError = ref('');
const trend = ref<DashboardTrendItem[]>([]);
const categories = ref<DashboardCategoryItem[]>([]);
const period = ref<DashboardPeriod>({ label: 'this month', updated_at: '' });
const trendRange = ref<TrendRange>('6');
const chartMode = ref<ChartMode>('bar');
const userStore = useUserStore();
const isStudentProfile = computed(() => userStore.user?.profile_type === 'student');
const periodType = ref<'monthly' | 'semester'>('monthly');
const selectedMonth = ref(new Date().toISOString().slice(0, 7));
const studentSemesters = ref<StudentSemester[]>([]);
const selectedSemesterId = ref<number | null>(null);

const money = (value = 0) => formatCurrencyMYR(value);
const shortDate = (value = '') => formatYmdDate(value, { locale: 'en-MY', fallback: '-' });
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

const displayedTrend = computed<DashboardTrendItem[]>(() => {
  if (trendRange.value === 'all') return trend.value;
  const count = Number(trendRange.value);
  return trend.value.slice(-count);
});

const displayMax = computed(() => {
  const values = displayedTrend.value.flatMap((item) => [Number(item.income) || 0, Number(item.expense) || 0]);
  return Math.max(1, ...values);
});

const totalIncome = computed(() => displayedTrend.value.reduce((sum, item) => sum + Number(item.income || 0), 0));
const totalExpense = computed(() => displayedTrend.value.reduce((sum, item) => sum + Number(item.expense || 0), 0));
const totalNet = computed(() => displayedTrend.value.reduce((sum, item) => sum + Number(item.net || 0), 0));

const latestPoint = computed(() => displayedTrend.value[displayedTrend.value.length - 1] ?? null);
const previousPoint = computed(() => displayedTrend.value[displayedTrend.value.length - 2] ?? null);

const changePct = (current = 0, previous = 0) => {
  if (previous === 0) return current === 0 ? 0 : 100;
  return ((current - previous) / Math.abs(previous)) * 100;
};

const incomeMoM = computed(() => changePct(Number(latestPoint.value?.income || 0), Number(previousPoint.value?.income || 0)));
const expenseMoM = computed(() => changePct(Number(latestPoint.value?.expense || 0), Number(previousPoint.value?.expense || 0)));

const topCategories = computed(() => [...categories.value].sort((a, b) => Number(b.amount) - Number(a.amount)).slice(0, 5));

const linePoints = (key: 'income' | 'expense') => {
  const rows = displayedTrend.value;
  if (!rows.length) return '';

  const width = 620;
  const height = 240;
  const padX = 24;
  const padY = 20;
  const chartW = width - padX * 2;
  const chartH = height - padY * 2;
  const denominator = Math.max(1, rows.length - 1);

  return rows
    .map((row, index) => {
      const x = padX + (index / denominator) * chartW;
      const y = height - padY - (Number(row[key] || 0) / displayMax.value) * chartH;
      return `${x},${y}`;
    })
    .join(' ');
};
const buildPeriodParams = () => {
  const params: Record<string, string | number> = {
    period: periodType.value,
    month: selectedMonth.value,
  };
  if (periodType.value === 'semester' && selectedSemesterId.value) {
    params.semester_id = selectedSemesterId.value;
  }
  return params;
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

const loadTrend = async (manualRefresh = false) => {
  if (manualRefresh) isRefreshing.value = true;
  else isLoading.value = true;

  loadError.value = '';
  try {
    const { data } = await axiosInstance.get<DashboardSummary>('/dashboard/summary', { params: buildPeriodParams() });
    trend.value = data.monthly_trend || [];
    categories.value = data.category_breakdown || [];
    period.value = data.period || { label: 'this month', updated_at: '' };
    if (data?.period?.semester_id) {
      selectedSemesterId.value = data.period.semester_id;
    }
  } catch (error) {
    console.error(error);
    loadError.value = 'Unable to load financial trend right now.';
  } finally {
    isLoading.value = false;
    isRefreshing.value = false;
  }
};

onMounted(() => {
  periodType.value = isStudentProfile.value
    ? ((userStore.user?.preferred_period as 'monthly' | 'semester') || 'monthly')
    : 'monthly';
  loadStudentSemesters().finally(() => loadTrend());
});
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
  <div class="min-h-screen bg-slate-100 p-6">
    <div class="space-y-6">
      <section class="rounded-[32px] bg-slate-950 px-6 py-8 text-white shadow-2xl shadow-slate-900/10">
        <p class="text-sm uppercase tracking-[0.28em] text-slate-400">Financial Trend</p>
        <div class="mt-2 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <h1 class="text-3xl font-semibold sm:text-4xl">Income vs Expense Trend</h1>
            <p class="mt-2 text-sm text-slate-300">Performance trend for {{ period.label }}.</p>
          </div>
          <div class="flex items-center gap-3">
            <div v-if="isStudentProfile" class="inline-flex h-10 items-center rounded-xl border border-white/20 bg-white/10 p-0.5 shadow-sm">
              <button
                type="button"
                class="mx-0.5 my-0.5 h-[calc(100%-4px)] rounded-md px-3 text-sm font-medium transition"
                :class="periodType === 'monthly' ? 'bg-white/20 text-white' : 'text-white/90 hover:bg-white/15'"
                @click="periodType = 'monthly'; loadTrend(true)"
              >
                Monthly
              </button>
              <button
                type="button"
                class="mx-0.5 my-0.5 h-[calc(100%-4px)] rounded-md px-3 text-sm font-medium transition"
                :class="periodType === 'semester' ? 'bg-white/20 text-white' : 'text-white/90 hover:bg-white/15'"
                @click="periodType = 'semester'; loadTrend(true)"
              >
                Semester
              </button>
            </div>
            <select
              v-if="isStudentProfile && periodType === 'semester'"
              v-model.number="selectedSemesterId"
              @change="loadTrend(true)"
              class="h-10 rounded-xl border border-white/20 bg-slate-900/60 px-3 text-sm text-white focus:ring-2 focus:ring-cyan-500/30"
            >
              <option v-for="semester in studentSemesters" :key="semester.id" :value="semester.id">
                {{ semester.name }} ({{ longDate(semester.start_date) }} to {{ longDate(semester.end_date) }})
              </option>
            </select>
            <button
              type="button"
              @click="loadTrend(true)"
              :disabled="isRefreshing"
              class="rounded-xl border border-white/15 bg-white/10 px-4 py-2 text-sm font-medium text-white hover:bg-white/20 disabled:opacity-60"
            >
              {{ isRefreshing ? 'Refreshing...' : 'Refresh' }}
            </button>
            <div class="rounded-2xl bg-white/5 px-4 py-3 text-sm text-slate-300">Updated {{ shortDate(period.updated_at) }}</div>
          </div>
        </div>
      </section>

      <section class="grid gap-6 xl:grid-cols-[1.35fr_0.95fr]">
        <article class="rounded-[28px] bg-white p-6 shadow-sm ring-1 ring-slate-200/70">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="text-2xl font-semibold text-slate-900">Trend Visualization</h2>
            <div class="flex items-center gap-2">
              <div class="rounded-xl bg-slate-100 p-1">
                <button type="button" @click="trendRange = '3'" :class="trendRange === '3' ? 'bg-white text-slate-900 shadow' : 'text-slate-600'" class="rounded-lg px-3 py-1.5 text-xs font-medium">3M</button>
                <button type="button" @click="trendRange = '6'" :class="trendRange === '6' ? 'bg-white text-slate-900 shadow' : 'text-slate-600'" class="rounded-lg px-3 py-1.5 text-xs font-medium">6M</button>
                <button type="button" @click="trendRange = 'all'" :class="trendRange === 'all' ? 'bg-white text-slate-900 shadow' : 'text-slate-600'" class="rounded-lg px-3 py-1.5 text-xs font-medium">All</button>
              </div>
              <div class="rounded-xl bg-slate-100 p-1">
                <button type="button" @click="chartMode = 'bar'" :class="chartMode === 'bar' ? 'bg-white text-slate-900 shadow' : 'text-slate-600'" class="rounded-lg px-3 py-1.5 text-xs font-medium">Bar</button>
                <button type="button" @click="chartMode = 'line'" :class="chartMode === 'line' ? 'bg-white text-slate-900 shadow' : 'text-slate-600'" class="rounded-lg px-3 py-1.5 text-xs font-medium">Line</button>
              </div>
            </div>
          </div>

          <div class="mt-4 grid gap-3 sm:grid-cols-3">
            <div class="rounded-xl border border-slate-200 p-3">
              <p class="text-xs text-slate-500">Total Income</p>
              <p class="mt-1 text-lg font-semibold text-emerald-700">{{ money(totalIncome) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 p-3">
              <p class="text-xs text-slate-500">Total Expense</p>
              <p class="mt-1 text-lg font-semibold text-rose-700">{{ money(totalExpense) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 p-3">
              <p class="text-xs text-slate-500">Net Cashflow</p>
              <p class="mt-1 text-lg font-semibold" :class="totalNet >= 0 ? 'text-emerald-700' : 'text-rose-700'">{{ money(totalNet) }}</p>
            </div>
          </div>

          <div v-if="displayedTrend.length" class="mt-6 rounded-[24px] bg-slate-50 p-5">
            <div v-if="chartMode === 'bar'" class="grid items-end gap-3" :style="{ gridTemplateColumns: `repeat(${displayedTrend.length}, minmax(0, 1fr))` }">
              <div v-for="item in displayedTrend" :key="item.month" class="flex flex-col items-center gap-3">
                <div class="flex h-56 items-end gap-2">
                  <div class="w-4 rounded-t-full bg-sky-500" :style="{ height: `${Math.max((item.income / displayMax) * 180, item.income > 0 ? 12 : 0)}px` }"></div>
                  <div class="w-4 rounded-t-full bg-amber-500" :style="{ height: `${Math.max((item.expense / displayMax) * 180, item.expense > 0 ? 12 : 0)}px` }"></div>
                </div>
                <div class="text-center">
                  <p class="text-sm font-medium text-slate-700">{{ item.month }}</p>
                  <p class="text-xs text-slate-500">{{ money(item.net) }}</p>
                </div>
              </div>
            </div>

            <div v-else class="overflow-x-auto">
              <svg viewBox="0 0 620 240" class="h-64 w-full min-w-[560px]">
                <polyline :points="linePoints('income')" fill="none" stroke="#0ea5e9" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                <polyline :points="linePoints('expense')" fill="none" stroke="#f59e0b" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
              </svg>
              <div class="mt-2 flex items-center gap-4 text-xs">
                <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-sky-500"></span>Income</span>
                <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-amber-500"></span>Expense</span>
              </div>
            </div>
          </div>
          <p v-else class="mt-6 rounded-2xl bg-slate-50 p-6 text-sm text-slate-500">No trend data yet.</p>
        </article>

        <article class="rounded-[28px] bg-white p-6 shadow-sm ring-1 ring-slate-200/70">
          <h2 class="text-2xl font-semibold text-slate-900">Trend Insights</h2>
          <div class="mt-4 grid gap-3 sm:grid-cols-2">
            <div class="rounded-xl border border-slate-200 p-3">
              <p class="text-xs text-slate-500">Income MoM</p>
              <p class="mt-1 text-lg font-semibold" :class="incomeMoM >= 0 ? 'text-emerald-700' : 'text-rose-700'">{{ incomeMoM.toFixed(1) }}%</p>
            </div>
            <div class="rounded-xl border border-slate-200 p-3">
              <p class="text-xs text-slate-500">Expense MoM</p>
              <p class="mt-1 text-lg font-semibold" :class="expenseMoM <= 0 ? 'text-emerald-700' : 'text-rose-700'">{{ expenseMoM.toFixed(1) }}%</p>
            </div>
          </div>

          <h3 class="mt-6 text-lg font-semibold text-slate-900">Top Spending Categories</h3>
          <div v-if="topCategories.length" class="mt-3 space-y-3">
            <div v-for="item in topCategories" :key="item.category">
              <div class="flex items-center justify-between text-sm">
                <span class="font-medium text-slate-700">{{ item.category }}</span>
                <span class="text-slate-500">{{ money(item.amount) }}</span>
              </div>
              <div class="mt-1 h-2 rounded-full bg-slate-100">
                <div class="h-2 rounded-full bg-gradient-to-r from-sky-500 via-cyan-500 to-emerald-500" :style="{ width: `${Math.max(Number(item.percentage), 6)}%` }"></div>
              </div>
            </div>
          </div>
          <p v-else class="mt-3 rounded-2xl bg-slate-50 p-4 text-sm text-slate-500">No category breakdown yet.</p>
        </article>
      </section>

      <div v-if="loadError" class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">{{ loadError }}</div>
    </div>

    <Teleport to="body">
      <Transition name="loading-fade">
        <div v-if="isLoading" class="fixed inset-0 z-[120] flex items-center justify-center bg-black/55 px-4">
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
</template>

