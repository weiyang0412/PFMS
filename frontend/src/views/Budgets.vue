<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import axiosInstance from '../lib/axios';

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
}

interface BudgetSummary {
  total_budget: number;
  total_spent: number;
  warning_count: number;
}

const month = ref(new Date().toISOString().slice(0, 7));
const items = ref<BudgetItem[]>([]);
const summary = ref<BudgetSummary>({ total_budget: 0, total_spent: 0, warning_count: 0 });
const isLoading = ref(false);
const savingCategoryIds = ref<number[]>([]);
const deletingCategoryIds = ref<number[]>([]);
const showToast = ref(false);
const toastMessage = ref('');
const toastTone = ref<'success' | 'danger'>('success');
let toastTimer: ReturnType<typeof setTimeout> | null = null;

const formatMoney = (value = 0) =>
  new Intl.NumberFormat('en-MY', {
    style: 'currency',
    currency: 'MYR',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(Number(value || 0));

const showToastMessage = (message: string, tone: 'success' | 'danger' = 'success') => {
  toastMessage.value = message;
  toastTone.value = tone;
  showToast.value = true;
  if (toastTimer) clearTimeout(toastTimer);
  toastTimer = setTimeout(() => { showToast.value = false; }, 2600);
};

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
    const { data } = await axiosInstance.get('/budgets', { params: { month: month.value } });
    items.value = data.items || [];
    summary.value = data.summary || { total_budget: 0, total_spent: 0, warning_count: 0 };
  } catch (error) {
    console.error(error);
    showToastMessage('Unable to load budgets right now.', 'danger');
  } finally {
    isLoading.value = false;
  }
};

const saveBudget = async (item: BudgetItem) => {
  setBusy('save', item.category_id, true);
  try {
    const amount = Number(item.amount ?? 0);
    if (!Number.isFinite(amount) || amount <= 0) {
      showToastMessage('Budget amount must be greater than 0.', 'danger');
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
    showToastMessage(`Budget saved for ${item.category}.`, 'success');
  } catch (error) {
    console.error(error);
    showToastMessage(`Unable to save budget for ${item.category}.`, 'danger');
  } finally {
    setBusy('save', item.category_id, false);
  }
};

const clearBudget = async (item: BudgetItem) => {
  if (!item.budget_id) return;
  setBusy('delete', item.category_id, true);
  try {
    await axiosInstance.delete(`/budgets/${item.budget_id}`);
    await loadBudgets();
    showToastMessage(`Budget removed for ${item.category}.`, 'danger');
  } catch (error) {
    console.error(error);
    showToastMessage(`Unable to remove budget for ${item.category}.`, 'danger');
  } finally {
    setBusy('delete', item.category_id, false);
  }
};

const warningItems = computed(() => items.value.filter((item) => item.alert_level === 'warning' || item.alert_level === 'over'));

onMounted(() => {
  loadBudgets();
});

onUnmounted(() => {
  if (toastTimer) clearTimeout(toastTimer);
});
</script>

<template>
  <div class="h-full w-full bg-slate-100 p-6">
    <div class="space-y-6">
      <section class="rounded-lg bg-white p-6 shadow">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
          <div>
            <h1 class="text-3xl font-semibold text-slate-900">Budget & Alerts</h1>
            <p class="mt-2 text-sm text-slate-500">Set monthly category budgets and get warning alerts before overspending.</p>
          </div>
          <div class="flex items-center gap-3">
            <input v-model="month" type="month" class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500" />
            <button type="button" @click="loadBudgets" :disabled="isLoading" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-60">
              {{ isLoading ? 'Loading...' : 'Load' }}
            </button>
          </div>
        </div>
      </section>

      <section class="grid gap-6 lg:grid-cols-3">
        <article class="rounded-lg bg-white p-6 shadow">
          <p class="text-sm text-slate-500">Total Budget</p>
          <p class="mt-3 text-3xl font-semibold text-slate-900">{{ formatMoney(summary.total_budget) }}</p>
        </article>
        <article class="rounded-lg bg-white p-6 shadow">
          <p class="text-sm text-slate-500">Total Spent</p>
          <p class="mt-3 text-3xl font-semibold text-slate-900">{{ formatMoney(summary.total_spent) }}</p>
        </article>
        <article class="rounded-lg bg-white p-6 shadow">
          <p class="text-sm text-slate-500">Alerts Triggered</p>
          <p class="mt-3 text-3xl font-semibold text-amber-600">{{ summary.warning_count }}</p>
        </article>
      </section>

      <section v-if="warningItems.length" class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-amber-900 shadow">
        <p class="text-sm font-medium">Active Alerts</p>
        <div class="mt-2 flex flex-wrap gap-2">
          <span v-for="item in warningItems" :key="item.category_id" class="rounded-full bg-white px-3 py-1 text-xs ring-1 ring-amber-300">
            {{ item.category }} · {{ Number(item.usage_pct).toFixed(1) }}%
          </span>
        </div>
      </section>

      <section class="rounded-lg bg-white p-6 shadow">
        <div class="mb-4">
          <h2 class="text-xl font-semibold text-slate-900">Category Budgets</h2>
          <p class="text-sm text-slate-500">Save a budget per category. Alert triggers when usage reaches threshold.</p>
        </div>

        <div v-if="!isLoading && items.length === 0" class="py-8 text-center text-slate-500">No categories yet. Create categories in Manage Options first.</div>

        <div v-else-if="!isLoading" class="overflow-x-auto">
          <table class="min-w-full border-collapse text-left">
            <thead class="border-b border-slate-200">
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
              <tr v-for="item in items" :key="item.category_id" class="border-b border-slate-100 text-sm">
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
                    <button type="button" @click="saveBudget(item)" :disabled="isSaving(item.category_id) || isDeleting(item.category_id)" class="rounded-md bg-slate-900 px-3 py-1.5 text-xs font-medium text-white hover:bg-slate-800 disabled:opacity-60">
                      {{ isSaving(item.category_id) ? 'Saving...' : 'Save' }}
                    </button>
                    <button type="button" @click="clearBudget(item)" :disabled="!item.budget_id || isDeleting(item.category_id) || isSaving(item.category_id)" class="rounded-md border border-red-700 bg-red-50 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-100 disabled:opacity-50">
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
        <div v-if="isLoading" class="fixed inset-0 z-[120] flex items-center justify-center bg-black/55 px-4">
          <div class="flex w-full max-w-xs flex-col items-center rounded-2xl bg-white px-6 py-7 text-center shadow-2xl">
            <span class="mb-4 h-12 w-12 animate-spin rounded-full border-4 border-slate-900 border-t-transparent"></span>
            <p class="text-lg font-semibold text-slate-900">Loading ...</p>
          </div>
        </div>
      </Teleport>

      <Teleport to="body">
        <transition name="toast-fade">
          <div v-if="showToast" class="pointer-events-none fixed bottom-6 right-6 z-[130] rounded-lg px-4 py-3 text-sm font-medium text-white shadow-xl" :class="toastTone === 'danger' ? 'bg-red-700' : 'bg-emerald-600'">
            {{ toastMessage }}
          </div>
        </transition>
      </Teleport>
    </div>
  </div>
</template>

<style scoped>
.toast-fade-enter-active,
.toast-fade-leave-active {
  transition: all 0.2s ease;
}

.toast-fade-enter-from,
.toast-fade-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}
</style>

