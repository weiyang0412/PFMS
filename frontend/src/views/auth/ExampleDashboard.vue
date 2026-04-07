<script setup lang="ts">
import { computed } from 'vue';
import { useUserStore } from '../../stores/userStore';

type MetricCard = {
  label: string;
  value: string;
  change: string;
  trend: 'up' | 'down';
  tone: string;
};

type BudgetItem = {
  name: string;
  spent: number;
  limit: number;
  color: string;
};

type TransactionItem = {
  merchant: string;
  category: string;
  date: string;
  amount: number;
  type: 'income' | 'expense';
};

const userStore = useUserStore();

const metrics: MetricCard[] = [
  { label: 'Net Worth', value: 'RM 128,450', change: '+8.4%', trend: 'up', tone: 'from-emerald-500 to-teal-500' },
  { label: 'Monthly Income', value: 'RM 18,200', change: '+2.1%', trend: 'up', tone: 'from-sky-500 to-cyan-500' },
  { label: 'Monthly Expense', value: 'RM 11,640', change: '-4.3%', trend: 'down', tone: 'from-amber-500 to-orange-500' },
  { label: 'Savings Rate', value: '36%', change: '+5.7%', trend: 'up', tone: 'from-fuchsia-500 to-pink-500' },
];

const budgetItems: BudgetItem[] = [
  { name: 'Housing', spent: 3200, limit: 3800, color: 'bg-sky-500' },
  { name: 'Food & Dining', spent: 1240, limit: 1500, color: 'bg-emerald-500' },
  { name: 'Transport', spent: 720, limit: 900, color: 'bg-amber-500' },
  { name: 'Subscriptions', spent: 310, limit: 300, color: 'bg-rose-500' },
];

const recentTransactions: TransactionItem[] = [
  { merchant: 'Salary Credit', category: 'Income', date: '07 Apr 2026', amount: 9200, type: 'income' },
  { merchant: 'Setia City Mall', category: 'Shopping', date: '06 Apr 2026', amount: -438, type: 'expense' },
  { merchant: 'Shell Damansara', category: 'Fuel', date: '06 Apr 2026', amount: -96, type: 'expense' },
  { merchant: 'Grab Subscription', category: 'Transport', date: '05 Apr 2026', amount: -19, type: 'expense' },
  { merchant: 'Maybank Savings', category: 'Transfer', date: '04 Apr 2026', amount: -1500, type: 'expense' },
];

const cashflowSeries = [52, 68, 61, 84, 78, 96, 88, 110];

const chartPoints = computed(() => {
  return cashflowSeries
    .map((value, index) => {
      const x = index * 78;
      const y = 180 - (value / 110) * 140;
      return `${x},${y.toFixed(2)}`;
    })
    .join(' ');
});

const savingsProgress = computed(() => 72);
const budgetHealth = computed(() => Math.round((6560 / 7600) * 100));

const formatCurrency = (value: number) =>
  new Intl.NumberFormat('en-MY', {
    style: 'currency',
    currency: 'MYR',
    maximumFractionDigits: 0,
  }).format(Math.abs(value));
</script>

<template>
  <div class="min-h-screen bg-slate-100 px-4 py-6 sm:px-6 lg:px-8">
    <div class="mx-auto flex max-w-7xl flex-col gap-6">
      <section class="overflow-hidden rounded-[32px] bg-slate-950 text-white shadow-2xl shadow-slate-900/10">
        <div class="grid gap-8 px-6 py-8 lg:grid-cols-[1.4fr_0.9fr] lg:px-8">
          <div class="relative">
            <div class="absolute -left-10 top-0 h-40 w-40 rounded-full bg-cyan-400/20 blur-3xl"></div>
            <div class="absolute right-0 top-10 h-32 w-32 rounded-full bg-emerald-400/10 blur-3xl"></div>
            <div class="relative">
              <p class="text-sm uppercase tracking-[0.3em] text-slate-400">Financial Dashboard</p>
              <h1 class="mt-3 max-w-2xl text-3xl font-semibold leading-tight sm:text-4xl">
                Welcome back, {{ userStore.user?.name ?? 'there' }}. Your money is moving in the right direction.
              </h1>
              <p class="mt-4 max-w-2xl text-sm text-slate-300 sm:text-base">
                Here is a live-style overview of your income, spending rhythm, savings progress, and the categories
                that need attention this week.
              </p>

              <div class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article
                  v-for="metric in metrics"
                  :key="metric.label"
                  class="rounded-3xl border border-white/10 bg-white/5 p-4 backdrop-blur"
                >
                  <div class="flex items-start justify-between gap-3">
                    <div>
                      <p class="text-sm text-slate-300">{{ metric.label }}</p>
                      <p class="mt-3 text-2xl font-semibold text-white">{{ metric.value }}</p>
                    </div>
                    <span
                      :class="[
                        'inline-flex rounded-full px-2.5 py-1 text-xs font-medium',
                        metric.trend === 'up'
                          ? 'bg-emerald-400/15 text-emerald-200'
                          : 'bg-amber-400/15 text-amber-200',
                      ]"
                    >
                      {{ metric.change }}
                    </span>
                  </div>
                  <div class="mt-4 h-1.5 rounded-full bg-white/10">
                    <div class="h-1.5 rounded-full bg-gradient-to-r" :class="metric.tone" style="width: 72%"></div>
                  </div>
                </article>
              </div>
            </div>
          </div>

          <div class="rounded-[28px] border border-white/10 bg-white/5 p-5 backdrop-blur">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm text-slate-300">This month</p>
                <p class="mt-1 text-2xl font-semibold">Cashflow Pulse</p>
              </div>
              <span class="rounded-full bg-emerald-400/15 px-3 py-1 text-xs font-medium text-emerald-200">
                Positive balance
              </span>
            </div>

            <div class="mt-6 rounded-3xl bg-slate-900/70 p-4">
              <div class="flex items-end justify-between">
                <div>
                  <p class="text-sm text-slate-400">Available balance</p>
                  <p class="mt-2 text-4xl font-semibold text-white">RM 24,980</p>
                </div>
                <div class="text-right text-sm text-slate-400">
                  <p>Updated</p>
                  <p class="text-white">07 Apr 2026</p>
                </div>
              </div>

              <div class="mt-6 grid grid-cols-2 gap-3">
                <div class="rounded-2xl bg-white/5 p-4">
                  <p class="text-sm text-slate-400">Income</p>
                  <p class="mt-2 text-xl font-semibold text-emerald-300">RM 18,200</p>
                </div>
                <div class="rounded-2xl bg-white/5 p-4">
                  <p class="text-sm text-slate-400">Expense</p>
                  <p class="mt-2 text-xl font-semibold text-amber-300">RM 11,640</p>
                </div>
              </div>
            </div>

            <div class="mt-5 grid gap-3 sm:grid-cols-3">
              <div class="rounded-2xl bg-white/5 p-4">
                <p class="text-sm text-slate-400">Emergency fund</p>
                <p class="mt-2 text-lg font-semibold text-white">8.6 months</p>
              </div>
              <div class="rounded-2xl bg-white/5 p-4">
                <p class="text-sm text-slate-400">Debt ratio</p>
                <p class="mt-2 text-lg font-semibold text-white">18%</p>
              </div>
              <div class="rounded-2xl bg-white/5 p-4">
                <p class="text-sm text-slate-400">Investments</p>
                <p class="mt-2 text-lg font-semibold text-white">RM 48,300</p>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section class="grid gap-6 xl:grid-cols-[1.35fr_0.95fr]">
        <article class="rounded-[28px] bg-white p-6 shadow-sm ring-1 ring-slate-200/70">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <p class="text-sm font-medium uppercase tracking-[0.25em] text-slate-400">Overview</p>
              <h2 class="mt-1 text-2xl font-semibold text-slate-900">Income vs expense trend</h2>
            </div>
            <div class="flex items-center gap-4 text-sm text-slate-500">
              <span class="inline-flex items-center gap-2">
                <span class="h-2.5 w-2.5 rounded-full bg-sky-500"></span>
                Income
              </span>
              <span class="inline-flex items-center gap-2">
                <span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                Savings
              </span>
            </div>
          </div>

          <div class="mt-6 rounded-[24px] bg-slate-50 p-4">
            <svg viewBox="0 0 546 190" class="h-64 w-full">
              <defs>
                <linearGradient id="cashflowLine" x1="0%" x2="100%" y1="0%" y2="0%">
                  <stop offset="0%" stop-color="#0ea5e9" />
                  <stop offset="100%" stop-color="#10b981" />
                </linearGradient>
              </defs>
              <g>
                <line
                  v-for="guide in [20, 55, 90, 125, 160]"
                  :key="guide"
                  x1="0"
                  :y1="guide"
                  x2="546"
                  :y2="guide"
                  stroke="#cbd5e1"
                  stroke-dasharray="5 7"
                  stroke-width="1"
                />
              </g>
              <polyline
                :points="chartPoints"
                fill="none"
                stroke="url(#cashflowLine)"
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="5"
              />
              <circle
                v-for="(value, index) in cashflowSeries"
                :key="`${value}-${index}`"
                :cx="index * 78"
                :cy="180 - (value / 110) * 140"
                r="6"
                fill="#fff"
                stroke="#0f172a"
                stroke-width="3"
              />
            </svg>

            <div class="mt-4 grid grid-cols-4 gap-2 text-sm text-slate-500 sm:grid-cols-8">
              <span v-for="month in ['Sep', 'Oct', 'Nov', 'Dec', 'Jan', 'Feb', 'Mar', 'Apr']" :key="month">
                {{ month }}
              </span>
            </div>
          </div>
        </article>

        <article class="rounded-[28px] bg-white p-6 shadow-sm ring-1 ring-slate-200/70">
          <p class="text-sm font-medium uppercase tracking-[0.25em] text-slate-400">Goal Tracking</p>
          <h2 class="mt-1 text-2xl font-semibold text-slate-900">Savings goal progress</h2>

          <div class="mt-8 flex flex-col items-center justify-center">
            <div
              class="relative flex h-52 w-52 items-center justify-center rounded-full"
              :style="{
                background: `conic-gradient(#0f766e 0% ${savingsProgress}%, #dbeafe ${savingsProgress}% 100%)`,
              }"
            >
              <div class="flex h-36 w-36 flex-col items-center justify-center rounded-full bg-white shadow-inner">
                <span class="text-4xl font-semibold text-slate-900">{{ savingsProgress }}%</span>
                <span class="mt-1 text-sm text-slate-500">of RM 30,000</span>
              </div>
            </div>
            <p class="mt-5 max-w-sm text-center text-sm text-slate-500">
              You are ahead of target. If the current pace continues, the goal can be reached around July 2026.
            </p>
          </div>

          <div class="mt-8 space-y-4">
            <div class="rounded-2xl bg-emerald-50 p-4">
              <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-emerald-900">Retirement reserve</p>
                <span class="text-sm font-semibold text-emerald-700">On track</span>
              </div>
              <div class="mt-3 h-2 rounded-full bg-emerald-100">
                <div class="h-2 rounded-full bg-emerald-500" style="width: 64%"></div>
              </div>
            </div>
            <div class="rounded-2xl bg-sky-50 p-4">
              <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-sky-900">Vacation fund</p>
                <span class="text-sm font-semibold text-sky-700">RM 6,800 / 10,000</span>
              </div>
              <div class="mt-3 h-2 rounded-full bg-sky-100">
                <div class="h-2 rounded-full bg-sky-500" style="width: 68%"></div>
              </div>
            </div>
          </div>
        </article>
      </section>

      <section class="grid gap-6 lg:grid-cols-2 xl:grid-cols-[1fr_1fr_1.1fr]">
        <article class="rounded-[28px] bg-white p-6 shadow-sm ring-1 ring-slate-200/70">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium uppercase tracking-[0.25em] text-slate-400">Budget</p>
              <h2 class="mt-1 text-xl font-semibold text-slate-900">Category control</h2>
            </div>
            <span class="rounded-full bg-slate-100 px-3 py-1 text-sm font-medium text-slate-600">
              {{ budgetHealth }}% used
            </span>
          </div>

          <div class="mt-6 space-y-5">
            <div v-for="item in budgetItems" :key="item.name">
              <div class="flex items-center justify-between text-sm">
                <span class="font-medium text-slate-700">{{ item.name }}</span>
                <span class="text-slate-500">
                  {{ formatCurrency(item.spent) }} / {{ formatCurrency(item.limit) }}
                </span>
              </div>
              <div class="mt-2 h-2.5 rounded-full bg-slate-100">
                <div
                  class="h-2.5 rounded-full"
                  :class="item.color"
                  :style="{ width: `${Math.min((item.spent / item.limit) * 100, 100)}%` }"
                ></div>
              </div>
            </div>
          </div>
        </article>

        <article class="rounded-[28px] bg-white p-6 shadow-sm ring-1 ring-slate-200/70">
          <p class="text-sm font-medium uppercase tracking-[0.25em] text-slate-400">Upcoming</p>
          <h2 class="mt-1 text-xl font-semibold text-slate-900">Bills and reminders</h2>

          <div class="mt-6 space-y-4">
            <div class="rounded-2xl border border-slate-200 p-4">
              <div class="flex items-center justify-between">
                <div>
                  <p class="font-medium text-slate-900">Mortgage payment</p>
                  <p class="text-sm text-slate-500">Due 10 Apr 2026</p>
                </div>
                <span class="text-sm font-semibold text-slate-900">RM 2,400</span>
              </div>
            </div>
            <div class="rounded-2xl border border-slate-200 p-4">
              <div class="flex items-center justify-between">
                <div>
                  <p class="font-medium text-slate-900">Credit card settlement</p>
                  <p class="text-sm text-slate-500">Due 12 Apr 2026</p>
                </div>
                <span class="text-sm font-semibold text-slate-900">RM 1,120</span>
              </div>
            </div>
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
              <div class="flex items-center justify-between">
                <div>
                  <p class="font-medium text-amber-900">Subscription alert</p>
                  <p class="text-sm text-amber-700">Streaming costs exceeded your limit</p>
                </div>
                <span class="text-sm font-semibold text-amber-900">Action needed</span>
              </div>
            </div>
          </div>
        </article>

        <article class="rounded-[28px] bg-slate-900 p-6 text-white shadow-sm">
          <p class="text-sm font-medium uppercase tracking-[0.25em] text-slate-400">Insights</p>
          <h2 class="mt-1 text-xl font-semibold">Financial health summary</h2>

          <div class="mt-6 space-y-4">
            <div class="rounded-2xl bg-white/5 p-4">
              <p class="text-sm text-slate-300">Most improved area</p>
              <p class="mt-2 text-lg font-semibold">Dining spend dropped by 12%</p>
            </div>
            <div class="rounded-2xl bg-white/5 p-4">
              <p class="text-sm text-slate-300">Biggest drag</p>
              <p class="mt-2 text-lg font-semibold">Subscriptions are 3% over budget</p>
            </div>
            <div class="rounded-2xl bg-white/5 p-4">
              <p class="text-sm text-slate-300">Best next move</p>
              <p class="mt-2 text-lg font-semibold">Move RM 1,000 into investments this week</p>
            </div>
          </div>
        </article>
      </section>

      <section class="rounded-[28px] bg-white p-6 shadow-sm ring-1 ring-slate-200/70">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <p class="text-sm font-medium uppercase tracking-[0.25em] text-slate-400">Activity</p>
            <h2 class="mt-1 text-2xl font-semibold text-slate-900">Recent transactions</h2>
          </div>
          <button
            type="button"
            class="inline-flex items-center justify-center rounded-full bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800"
          >
            Export report
          </button>
        </div>

        <div class="mt-6 overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-200">
            <thead>
              <tr class="text-left text-sm text-slate-500">
                <th class="pb-3 font-medium">Merchant</th>
                <th class="pb-3 font-medium">Category</th>
                <th class="pb-3 font-medium">Date</th>
                <th class="pb-3 text-right font-medium">Amount</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="item in recentTransactions" :key="`${item.merchant}-${item.date}`">
                <td class="py-4">
                  <p class="font-medium text-slate-900">{{ item.merchant }}</p>
                </td>
                <td class="py-4 text-slate-500">{{ item.category }}</td>
                <td class="py-4 text-slate-500">{{ item.date }}</td>
                <td class="py-4 text-right font-semibold" :class="item.type === 'income' ? 'text-emerald-600' : 'text-slate-900'">
                  {{ item.type === 'income' ? '+' : '-' }}{{ formatCurrency(item.amount) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </div>
</template>

