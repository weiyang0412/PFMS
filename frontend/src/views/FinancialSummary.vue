<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import axiosInstance from '../lib/axios';
import { formatCurrencyMYR, formatYmdDate } from '../lib/formatters.js';

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

interface DashboardInsights {
  largest_expense_category: string | null;
  largest_expense_amount: number;
  average_expense: number;
  transactions_this_month: number;
}

interface DashboardRecentItem {
  id: number;
  description: string;
  category: string;
  type: 'income' | 'expense';
  amount: number;
  transaction_date: string;
}

interface DashboardCategoryItem {
  category: string;
  amount: number;
  percentage: number;
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

interface DashboardPeriod {
  label: string;
  updated_at: string;
}

interface DashboardSummary {
  overview: DashboardOverview;
  insights: DashboardInsights;
  recent_transactions: DashboardRecentItem[];
  category_breakdown: DashboardCategoryItem[];
  accounts: DashboardAccount[];
  monthly_trend: DashboardTrendItem[];
  period: DashboardPeriod;
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

const isLoading = ref(false);
const isRefreshing = ref(false);
const loadError = ref('');
const summary = ref<DashboardSummary | null>(null);

const overview = computed(() => summary.value?.overview ?? defaultOverview);
const insights = computed(() => summary.value?.insights ?? defaultInsights);
const recent = computed(() => summary.value?.recent_transactions ?? []);
const categories = computed(() => summary.value?.category_breakdown ?? []);
const accounts = computed(() => summary.value?.accounts ?? []);
const monthlyTrend = computed(() => summary.value?.monthly_trend ?? []);
const period = computed(() => summary.value?.period ?? { label: 'this month', updated_at: '' });

const money = (value = 0) => formatCurrencyMYR(value);
const shortDate = (value = '') => formatYmdDate(value, { locale: 'en-MY', fallback: '-' });
const change = (value = 0) => `${Number(value) >= 0 ? '+' : ''}${Number(value).toFixed(1)}%`;

const topCategories = computed(() => [...categories.value].sort((a, b) => Number(b.amount) - Number(a.amount)).slice(0, 5));
const topAccounts = computed(() => [...accounts.value].sort((a, b) => Number(b.balance || 0) - Number(a.balance || 0)).slice(0, 5));

const currentTrend = computed(() => monthlyTrend.value[monthlyTrend.value.length - 1] ?? null);
const previousTrend = computed(() => monthlyTrend.value[monthlyTrend.value.length - 2] ?? null);

const calcPct = (current = 0, previous = 0) => {
  if (previous === 0) return current === 0 ? 0 : 100;
  return ((current - previous) / Math.abs(previous)) * 100;
};

const netChangePct = computed(() => calcPct(Number(currentTrend.value?.net || 0), Number(previousTrend.value?.net || 0)));

const accountContribution = computed(() => {
  const total = Number(overview.value.total_balance || 0);
  if (total <= 0) return topAccounts.value.map((account) => ({ ...account, contribution: 0 }));
  return topAccounts.value.map((account) => ({
    ...account,
    contribution: (Number(account.balance || 0) / total) * 100,
  }));
});

const loadSummary = async (manualRefresh = false) => {
  if (manualRefresh) isRefreshing.value = true;
  else isLoading.value = true;
  loadError.value = '';
  try {
    const { data } = await axiosInstance.get<DashboardSummary>('/dashboard/summary');
    summary.value = data;
  } catch (error) {
    console.error(error);
    loadError.value = 'Unable to load financial summary right now.';
  } finally {
    isLoading.value = false;
    isRefreshing.value = false;
  }
};

onMounted(() => {
  loadSummary();
});
</script>

<template>
  <div class="min-h-screen bg-slate-100 p-6">
    <div class="space-y-6">
      <section class="rounded-[32px] bg-slate-950 px-6 py-8 text-white shadow-2xl shadow-slate-900/10">
        <p class="text-sm uppercase tracking-[0.28em] text-slate-400">Financial Summary</p>
        <div class="mt-2 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <h1 class="text-3xl font-semibold sm:text-4xl">Your Financial Health Snapshot</h1>
            <p class="mt-2 text-sm text-slate-300">Summary data for {{ period.label }}.</p>
          </div>
          <div class="flex items-center gap-3">
            <button
              type="button"
              @click="loadSummary(true)"
              :disabled="isRefreshing"
              class="rounded-xl border border-white/15 bg-white/10 px-4 py-2 text-sm font-medium text-white hover:bg-white/20 disabled:opacity-60"
            >
              {{ isRefreshing ? 'Refreshing...' : 'Refresh' }}
            </button>
            <div class="rounded-2xl bg-white/5 px-4 py-3 text-sm text-slate-300">Updated {{ shortDate(period.updated_at) }}</div>
          </div>
        </div>
      </section>

      <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
        <article class="rounded-[28px] bg-white p-5 shadow-sm ring-1 ring-slate-200/70">
          <p class="text-sm text-slate-500">Total Balance</p>
          <p class="mt-2 text-2xl font-semibold text-slate-900">{{ money(overview.total_balance) }}</p>
          <p class="mt-2 text-xs text-slate-500">{{ overview.account_count }} accounts</p>
        </article>
        <article class="rounded-[28px] bg-white p-5 shadow-sm ring-1 ring-slate-200/70">
          <p class="text-sm text-slate-500">Monthly Income</p>
          <p class="mt-2 text-2xl font-semibold text-emerald-700">{{ money(overview.monthly_income) }}</p>
          <p class="mt-2 text-xs text-slate-500">{{ change(overview.income_change_pct) }} vs last month</p>
        </article>
        <article class="rounded-[28px] bg-white p-5 shadow-sm ring-1 ring-slate-200/70">
          <p class="text-sm text-slate-500">Monthly Expense</p>
          <p class="mt-2 text-2xl font-semibold text-rose-700">{{ money(overview.monthly_expense) }}</p>
          <p class="mt-2 text-xs text-slate-500">{{ change(overview.expense_change_pct) }} vs last month</p>
        </article>
        <article class="rounded-[28px] bg-white p-5 shadow-sm ring-1 ring-slate-200/70">
          <p class="text-sm text-slate-500">Net Change (MoM)</p>
          <p class="mt-2 text-2xl font-semibold" :class="netChangePct >= 0 ? 'text-emerald-700' : 'text-rose-700'">{{ netChangePct.toFixed(1) }}%</p>
          <p class="mt-2 text-xs text-slate-500">Based on monthly net trend</p>
        </article>
        <article class="rounded-[28px] bg-white p-5 shadow-sm ring-1 ring-slate-200/70">
          <p class="text-sm text-slate-500">Savings Rate</p>
          <p class="mt-2 text-2xl font-semibold text-slate-900">{{ Number(overview.savings_rate).toFixed(1) }}%</p>
          <p class="mt-2 text-xs text-slate-500">Net {{ money(overview.net_cashflow) }}</p>
        </article>
      </section>

      <section class="grid gap-6 xl:grid-cols-[1.05fr_0.95fr_1fr]">
        <article class="rounded-[28px] bg-slate-900 p-6 text-white shadow-sm">
          <h2 class="text-2xl font-semibold">Key Insights</h2>
          <div class="mt-5 space-y-4">
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
            <div class="rounded-2xl bg-white/5 p-4">
              <p class="text-sm text-slate-300">Transactions this month</p>
              <p class="mt-2 text-lg font-semibold">{{ insights.transactions_this_month }}</p>
            </div>
          </div>
        </article>

        <article class="rounded-[28px] bg-white p-6 shadow-sm ring-1 ring-slate-200/70">
          <h2 class="text-2xl font-semibold text-slate-900">Top 5 Spending Categories</h2>
          <div v-if="topCategories.length" class="mt-5 space-y-4">
            <div v-for="item in topCategories" :key="item.category">
              <div class="flex items-center justify-between text-sm">
                <span class="font-medium text-slate-700">{{ item.category }}</span>
                <span class="text-slate-500">{{ money(item.amount) }}</span>
              </div>
              <div class="mt-2 h-2.5 rounded-full bg-slate-100">
                <div class="h-2.5 rounded-full bg-gradient-to-r from-sky-500 via-cyan-500 to-emerald-500" :style="{ width: `${Math.max(Number(item.percentage), 6)}%` }"></div>
              </div>
              <p class="mt-1 text-xs text-slate-500">{{ Number(item.percentage).toFixed(1) }}% of monthly expense</p>
            </div>
          </div>
          <p v-else class="mt-5 rounded-2xl bg-slate-50 p-6 text-sm text-slate-500">No category breakdown yet.</p>
        </article>

        <article class="rounded-[28px] bg-white p-6 shadow-sm ring-1 ring-slate-200/70">
          <h2 class="text-2xl font-semibold text-slate-900">Account Contribution</h2>
          <div v-if="accountContribution.length" class="mt-5 space-y-4">
            <div v-for="account in accountContribution" :key="account.id">
              <div class="flex items-center justify-between text-sm">
                <span class="font-medium text-slate-700">{{ account.name }}</span>
                <span class="text-slate-500">{{ account.contribution.toFixed(1) }}%</span>
              </div>
              <div class="mt-2 h-2.5 rounded-full bg-slate-100">
                <div class="h-2.5 rounded-full bg-gradient-to-r from-emerald-500 to-teal-500" :style="{ width: `${Math.max(account.contribution, 6)}%` }"></div>
              </div>
              <p class="mt-1 text-xs text-slate-500">{{ money(account.balance) }}</p>
            </div>
          </div>
          <p v-else class="mt-5 rounded-2xl bg-slate-50 p-6 text-sm text-slate-500">No accounts yet.</p>
        </article>
      </section>

      <section>
        <article class="rounded-[28px] bg-white p-6 shadow-sm ring-1 ring-slate-200/70">
          <h2 class="text-2xl font-semibold text-slate-900">Recent Transactions</h2>
          <div v-if="recent.length" class="mt-5 overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
              <thead>
                <tr class="text-left text-sm text-slate-500">
                  <th class="pb-3 font-medium">Description</th>
                  <th class="pb-3 font-medium">Date</th>
                  <th class="pb-3 text-right font-medium">Amount</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-for="item in recent" :key="item.id">
                  <td class="py-4 font-medium text-slate-900">{{ item.description }}</td>
                  <td class="py-4 text-slate-500">{{ shortDate(item.transaction_date) }}</td>
                  <td class="py-4 text-right font-semibold" :class="item.type === 'income' ? 'text-emerald-600' : 'text-red-600'">
                    {{ item.type === 'income' ? '+' : '-' }}{{ money(item.amount) }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <p v-else class="mt-5 rounded-2xl bg-slate-50 p-6 text-sm text-slate-500">No recent transactions yet.</p>
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



