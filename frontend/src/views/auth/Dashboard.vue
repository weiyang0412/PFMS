<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import axiosInstance from '../../lib/axios';
import { useUserStore } from '../../stores/userStore';

interface DashboardOverview {
  total_balance: number;
  account_count: number;
  monthly_income: number;
  monthly_expense: number;
  net_cashflow: number;
  savings_rate: number;
  income_change_pct: number;
  expense_change_pct: number;
}

interface DashboardAccount {
  id: number;
  name: string;
  balance: number;
}

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

interface DashboardRecentItem {
  id: number;
  description: string;
  category: string;
  type: 'income' | 'expense';
  amount: number;
  transaction_date: string;
}

interface DashboardInsights {
  largest_expense_category: string | null;
  largest_expense_amount: number;
  average_expense: number;
  transactions_this_month: number;
}

interface DashboardPeriod {
  label: string;
  updated_at: string;
}

interface DashboardSummary {
  overview: DashboardOverview;
  accounts: DashboardAccount[];
  monthly_trend: DashboardTrendItem[];
  category_breakdown: DashboardCategoryItem[];
  recent_transactions: DashboardRecentItem[];
  insights: DashboardInsights;
  period: DashboardPeriod;
}

interface BudgetAlertSummary {
  total_budget: number;
  total_spent: number;
  warning_count: number;
  total_overspent: number;
}

const defaultOverview: DashboardOverview = {
  total_balance: 0,
  account_count: 0,
  monthly_income: 0,
  monthly_expense: 0,
  net_cashflow: 0,
  savings_rate: 0,
  income_change_pct: 0,
  expense_change_pct: 0,
};

const defaultInsights: DashboardInsights = {
  largest_expense_category: null,
  largest_expense_amount: 0,
  average_expense: 0,
  transactions_this_month: 0,
};

const defaultPeriod: DashboardPeriod = {
  label: 'this month',
  updated_at: '',
};

const userStore = useUserStore();
const isInitialLoading = ref(false);
const isRefreshing = ref(false);
const isBudgetAlertLoading = ref(false);
const loadError = ref('');
const summary = ref<DashboardSummary | null>(null);
const budgetAlertSummary = ref<BudgetAlertSummary>({ total_budget: 0, total_spent: 0, warning_count: 0, total_overspent: 0 });

const overview = computed<DashboardOverview>(() => summary.value?.overview ?? defaultOverview);
const accounts = computed<DashboardAccount[]>(() => summary.value?.accounts ?? []);
const trend = computed<DashboardTrendItem[]>(() => summary.value?.monthly_trend ?? []);
const categories = computed<DashboardCategoryItem[]>(() => summary.value?.category_breakdown ?? []);
const recent = computed<DashboardRecentItem[]>(() => summary.value?.recent_transactions ?? []);
const insights = computed<DashboardInsights>(() => summary.value?.insights ?? defaultInsights);
const period = computed<DashboardPeriod>(() => summary.value?.period ?? defaultPeriod);
const maxTrend = computed(() => {
  const values = trend.value.flatMap((item) => [Number(item.income) || 0, Number(item.expense) || 0]);
  return Math.max(1, ...values);
});

const money = (value = 0) =>
  new Intl.NumberFormat('en-MY', {
    style: 'currency',
    currency: 'MYR',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(Number(value || 0));

const shortDate = (value = '') => {
  if (!value || typeof value !== 'string') return '-';
  const [year, month, day] = value.split('-').map(Number);
  if (!year || !month || !day) return '-';
  const parsed = new Date(year, month - 1, day);
  if (Number.isNaN(parsed.getTime())) return '-';
  return new Intl.DateTimeFormat('en-MY', { day: '2-digit', month: 'short', year: 'numeric' }).format(parsed);
};

const change = (value = 0) => `${Number(value) >= 0 ? '+' : ''}${Number(value).toFixed(1)}%`;
const currentMonth = () => {
  const now = new Date();
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
};

const loadDashboard = async (manualRefresh = false) => {
  if (manualRefresh) isRefreshing.value = true;
  else if (!summary.value) isInitialLoading.value = true;
  loadError.value = '';
  try {
    const { data } = await axiosInstance.get<DashboardSummary>('/dashboard/summary');
    summary.value = data;
  } catch (error) {
    console.error(error);
    loadError.value = 'Unable to load dashboard data right now.';
  } finally {
    isInitialLoading.value = false;
    isRefreshing.value = false;
  }
};

const loadBudgetAlerts = async () => {
  isBudgetAlertLoading.value = true;
  try {
    const { data } = await axiosInstance.get('/budgets', { params: { month: currentMonth(), only_budgeted: 1 } });
    budgetAlertSummary.value = data.summary || { total_budget: 0, total_spent: 0, warning_count: 0 };
  } catch (error) {
    console.error(error);
  } finally {
    isBudgetAlertLoading.value = false;
  }
};

const refreshAll = async () => {
  await Promise.all([loadDashboard(true), loadBudgetAlerts()]);
};

onMounted(() => {
  loadDashboard();
  loadBudgetAlerts();
});
</script>

<template>
  <div class="min-h-screen bg-slate-100 p-6">
    <div class="space-y-6">
      <section class="rounded-[32px] bg-slate-950 px-6 py-8 text-white shadow-2xl shadow-slate-900/10">
        <p class="text-sm uppercase tracking-[0.3em] text-slate-400">Financial Dashboard</p>
        <div class="mt-3 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <h1 class="text-3xl font-semibold sm:text-4xl">Welcome back, {{ userStore.user?.name ?? 'there' }}.</h1>
            <p class="mt-2 text-sm text-slate-300">Live data from your accounts and transactions for {{ period.label ??
              'this month' }}.</p>
          </div>
          <div class="flex items-center gap-3">
            <button
              type="button"
              @click="refreshAll"
              :disabled="isRefreshing"
              class="rounded-xl border border-white/15 bg-white/10 px-4 py-2 text-sm font-medium text-white hover:bg-white/20 disabled:cursor-not-allowed disabled:opacity-60"
            >
              {{ isRefreshing ? 'Refreshing...' : 'Refresh' }}
            </button>
            <div class="rounded-2xl bg-white/5 px-4 py-3 text-sm text-slate-300">
              Updated {{ shortDate(period.updated_at) }}
            </div>
          </div>
        </div>
        <div v-if="loadError" class="mt-6 rounded-2xl bg-rose-500/10 p-4 text-sm text-rose-100">{{ loadError }}</div>
        <RouterLink
          v-if="!isBudgetAlertLoading && budgetAlertSummary.warning_count > 0"
          to="/budgets"
          class="mt-4 block rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 hover:bg-amber-100"
        >
          <p class="font-medium">{{ budgetAlertSummary.warning_count }} budget alert(s) need attention.</p>
          <p class="mt-1 text-amber-800">Overspent total: {{ money(budgetAlertSummary.total_overspent) }}. Click to review warnings and overspending categories.</p>
        </RouterLink>
        <div class="mt-8 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
          <article class="rounded-3xl border border-white/10 bg-white/5 p-4">
            <p class="text-sm text-slate-300">Total Balance</p>
            <p class="mt-3 text-2xl font-semibold">{{ money(overview.total_balance) }}</p>
            <p class="mt-2 text-xs text-slate-400">{{ overview.account_count ?? 0 }} accounts</p>
          </article>
          <article class="rounded-3xl border border-white/10 bg-white/5 p-4">
            <p class="text-sm text-slate-300">Monthly Income</p>
            <p class="mt-3 text-2xl font-semibold">{{ money(overview.monthly_income) }}</p>
            <p class="mt-2 text-xs text-emerald-300">{{ change(overview.income_change_pct) }} vs last month</p>
          </article>
          <article class="rounded-3xl border border-white/10 bg-white/5 p-4">
            <p class="text-sm text-slate-300">Monthly Expense</p>
            <p class="mt-3 text-2xl font-semibold">{{ money(overview.monthly_expense) }}</p>
            <p class="mt-2 text-xs text-amber-300">{{ change(overview.expense_change_pct) }} vs last month</p>
          </article>
          <article class="rounded-3xl border border-white/10 bg-white/5 p-4">
            <p class="text-sm text-slate-300">Savings Rate</p>
            <p class="mt-3 text-2xl font-semibold">{{ Number(overview.savings_rate ?? 0).toFixed(1) }}%</p>
            <p class="mt-2 text-xs text-slate-400">Net cashflow {{ money(overview.net_cashflow) }}</p>
          </article>
        </div>
      </section>

      <section class="grid gap-6 xl:grid-cols-[1.3fr_0.9fr]">
        <article class="rounded-[28px] bg-white p-6 shadow-sm ring-1 ring-slate-200/70">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm uppercase tracking-[0.25em] text-slate-400">Trend</p>
              <h2 class="mt-1 text-2xl font-semibold text-slate-900">Income vs expense</h2>
            </div>
          </div>
          <div v-if="trend.length" class="mt-6 grid grid-cols-6 items-end gap-3 rounded-[24px] bg-slate-50 p-5">
            <div v-for="item in trend" :key="item.month" class="flex flex-col items-center gap-3">
              <div class="flex h-56 items-end gap-2">
                <div class="w-4 rounded-t-full bg-sky-500"
                  :style="{ height: `${Math.max((item.income / maxTrend) * 180, item.income > 0 ? 12 : 0)}px` }"></div>
                <div class="w-4 rounded-t-full bg-amber-500"
                  :style="{ height: `${Math.max((item.expense / maxTrend) * 180, item.expense > 0 ? 12 : 0)}px` }">
                </div>
              </div>
              <div class="text-center">
                <p class="text-sm font-medium text-slate-700">{{ item.month }}</p>
                <p class="text-xs text-slate-500">{{ money(item.net) }}</p>
              </div>
            </div>
          </div>
          <div v-else class="mt-6 rounded-[24px] bg-slate-50 p-6 text-sm text-slate-500">
            <p>Add some transactions to see the trend chart.</p>
            <RouterLink to="/transactions" class="mt-3 inline-flex rounded-lg bg-slate-900 px-3 py-2 text-xs font-medium text-white hover:bg-slate-800">
              Go to Transactions
            </RouterLink>
          </div>
        </article>

        <article class="rounded-[28px] bg-white p-6 shadow-sm ring-1 ring-slate-200/70">
          <p class="text-sm uppercase tracking-[0.25em] text-slate-400">Accounts</p>
          <h2 class="mt-1 text-2xl font-semibold text-slate-900">Balance by account</h2>
          <div v-if="accounts.length" class="mt-6 space-y-4">
            <div v-for="account in accounts" :key="account.id" class="rounded-2xl bg-slate-50 p-4">
              <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-medium text-slate-900">{{ account.name }}</p>
                <span class="text-sm font-semibold text-slate-700">{{ money(account.balance) }}</span>
              </div>
              <div class="mt-3 h-2 rounded-full bg-slate-200">
                <div class="h-2 rounded-full bg-gradient-to-r from-emerald-500 to-teal-500"
                  :style="{ width: `${Math.max((account.balance / Math.max(Number(overview.total_balance || 0), 1)) * 100, 8)}%` }">
                </div>
              </div>
            </div>
          </div>
          <div v-else class="mt-6 rounded-2xl bg-slate-50 p-6 text-sm text-slate-500">
            <p>No accounts yet. Add one to track your real balance.</p>
            <RouterLink to="/accounts" class="mt-3 inline-flex rounded-lg bg-slate-900 px-3 py-2 text-xs font-medium text-white hover:bg-slate-800">
              Go to Accounts
            </RouterLink>
          </div>
        </article>
      </section>

      <section class="grid gap-6 lg:grid-cols-2 xl:grid-cols-[1fr_1fr_1.1fr]">
        <article class="rounded-[28px] bg-white p-6 shadow-sm ring-1 ring-slate-200/70">
          <p class="text-sm uppercase tracking-[0.25em] text-slate-400">Spending</p>
          <h2 class="mt-1 text-xl font-semibold text-slate-900">Expense by category</h2>
          <div v-if="categories.length" class="mt-6 space-y-5">
            <div v-for="item in categories" :key="item.category">
              <div class="flex items-center justify-between text-sm">
                <span class="font-medium text-slate-700">{{ item.category }}</span>
                <span class="text-slate-500">{{ money(item.amount) }} · {{ Number(item.percentage).toFixed(1) }}%</span>
              </div>
              <div class="mt-2 h-2.5 rounded-full bg-slate-100">
                <div class="h-2.5 rounded-full bg-gradient-to-r from-sky-500 via-cyan-500 to-emerald-500"
                  :style="{ width: `${Math.max(Number(item.percentage), 6)}%` }"></div>
              </div>
            </div>
          </div>
          <div v-else class="mt-6 rounded-2xl bg-slate-50 p-6 text-sm text-slate-500">
            <p>No expense categories yet for this month.</p>
            <RouterLink to="/transactions" class="mt-3 inline-flex rounded-lg bg-slate-900 px-3 py-2 text-xs font-medium text-white hover:bg-slate-800">
              Add Transactions
            </RouterLink>
          </div>
        </article>

        <article class="rounded-[28px] bg-white p-6 shadow-sm ring-1 ring-slate-200/70">
          <p class="text-sm uppercase tracking-[0.25em] text-slate-400">Snapshot</p>
          <h2 class="mt-1 text-xl font-semibold text-slate-900">This month</h2>
          <div class="mt-6 space-y-4">
            <div class="rounded-2xl border border-slate-200 p-4">
              <p class="text-sm text-slate-500">Income change</p>
              <p class="mt-2 text-lg font-semibold text-slate-900">{{ change(overview.income_change_pct) }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 p-4">
              <p class="text-sm text-slate-500">Expense change</p>
              <p class="mt-2 text-lg font-semibold text-slate-900">{{ change(overview.expense_change_pct) }}</p>
            </div>
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
              <p class="text-sm text-emerald-700">Transactions this month</p>
              <p class="mt-2 text-lg font-semibold text-emerald-900">{{ insights.transactions_this_month ?? 0 }}</p>
            </div>
          </div>
        </article>

        <article class="rounded-[28px] bg-slate-900 p-6 text-white shadow-sm">
          <p class="text-sm uppercase tracking-[0.25em] text-slate-400">Insights</p>
          <h2 class="mt-1 text-xl font-semibold">Financial health summary</h2>
          <div class="mt-6 space-y-4">
            <div class="rounded-2xl bg-white/5 p-4">
              <p class="text-sm text-slate-300">Largest expense category</p>
              <p class="mt-2 text-lg font-semibold">{{ insights.largest_expense_category ?? 'No expense data yet' }}</p>
            </div>
            <div class="rounded-2xl bg-white/5 p-4">
              <p class="text-sm text-slate-300">Largest category amount</p>
              <p class="mt-2 text-lg font-semibold">{{ money(insights.largest_expense_amount) }}</p>
            </div>
            <div class="rounded-2xl bg-white/5 p-4">
              <p class="text-sm text-slate-300">Average expense</p>
              <p class="mt-2 text-lg font-semibold">{{ money(insights.average_expense) }}</p>
            </div>
          </div>
        </article>
      </section>

      <section class="rounded-[28px] bg-white p-6 shadow-sm ring-1 ring-slate-200/70">
        <div class="flex items-center justify-between gap-3">
          <div>
            <p class="text-sm uppercase tracking-[0.25em] text-slate-400">Activity</p>
            <h2 class="mt-1 text-2xl font-semibold text-slate-900">Recent transactions</h2>
          </div>
          <span class="rounded-full bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700">{{ recent.length }}
            latest entries</span>
        </div>
        <div v-if="recent.length" class="mt-6 overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-200">
            <thead>
              <tr class="text-left text-sm text-slate-500">
                <th class="pb-3 font-medium">Description</th>
                <th class="pb-3 font-medium">Category</th>
                <th class="pb-3 font-medium">Date</th>
                <th class="pb-3 text-right font-medium">Amount</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="item in recent" :key="item.id">
                <td class="py-4 font-medium text-slate-900">{{ item.description }}</td>
                <td class="py-4 text-slate-500">{{ item.category }}</td>
                <td class="py-4 text-slate-500">{{ shortDate(item.transaction_date) }}</td>
                <td class="py-4 text-right font-semibold"
                  :class="item.type === 'income' ? 'text-emerald-600' : 'text-red-600'">
                  {{ item.type === 'income' ? '+' : '-' }}{{ money(item.amount) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-else class="mt-6 rounded-2xl bg-slate-50 p-6 text-sm text-slate-500">
          <p>No transactions yet. Add some to populate the dashboard.</p>
          <RouterLink to="/transactions" class="mt-3 inline-flex rounded-lg bg-slate-900 px-3 py-2 text-xs font-medium text-white hover:bg-slate-800">
            Go to Transactions
          </RouterLink>
        </div>
      </section>
    </div>

    <Teleport to="body">
      <div v-if="isInitialLoading" class="fixed inset-0 z-[120] flex items-center justify-center bg-black/55 px-4">
        <div class="flex w-full max-w-xs flex-col items-center rounded-2xl bg-white px-6 py-7 text-center shadow-2xl">
          <span class="mb-4 h-12 w-12 animate-spin rounded-full border-4 border-slate-900 border-t-transparent"></span>
          <p class="text-lg font-semibold text-slate-900">Loading ...</p>
          <!-- <p class="mt-1 text-sm text-slate-500">Fetching your latest account and transaction data.</p> -->
        </div>
      </div>
    </Teleport>
  </div>
</template>
