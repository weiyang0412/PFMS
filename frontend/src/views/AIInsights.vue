<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import axiosInstance from '../lib/axios';
import { useUserStore } from '../stores/userStore';
import { formatCurrencyMYR, formatYmdDate, malaysiaCurrentMonthYm } from '../lib/formatters.js';

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

interface DashboardPeriod {
  type?: 'monthly' | 'semester';
  label: string;
  comparison_label?: string;
  updated_at: string;
  month?: string;
  semester_id?: number | null;
}

interface AiForecastPoint {
  month: string;
  month_key: string;
  income: number;
  expense: number;
  net: number;
}

interface AiRecommendation {
  title: string;
  detail: string;
  priority: 'low' | 'medium' | 'high';
}

interface AiInsights {
  source?: 'ollama' | 'heuristic';
  model?: string;
  risk_level: 'low' | 'medium' | 'high';
  confidence: number;
  summary: string;
  signals: {
    income_trend: 'up' | 'down' | 'stable';
    expense_trend: 'up' | 'down' | 'stable';
    largest_expense_category: string | null;
    largest_expense_amount: number;
    largest_expense_share: number;
    transaction_count: number;
    savings_rate: number;
  };
  forecast: {
    next_month: AiForecastPoint;
    next_three_months: AiForecastPoint[];
  };
  recommendations: AiRecommendation[];
}

interface DashboardSummary {
  overview: DashboardOverview;
  period: DashboardPeriod;
  ai_insights: AiInsights;
}

interface StudentSemester {
  id: number;
  name: string;
  start_date: string;
  end_date: string;
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

const defaultPeriod: DashboardPeriod = {
  label: 'this month',
  updated_at: '',
};

const defaultAiInsights: AiInsights = {
  source: 'heuristic',
  risk_level: 'low',
  confidence: 0,
  summary: 'Add transactions to generate AI insights and forecasts.',
  signals: {
    income_trend: 'stable',
    expense_trend: 'stable',
    largest_expense_category: null,
    largest_expense_amount: 0,
    largest_expense_share: 0,
    transaction_count: 0,
    savings_rate: 0,
  },
  forecast: {
    next_month: {
      month: 'Next month',
      month_key: '',
      income: 0,
      expense: 0,
      net: 0,
    },
    next_three_months: [],
  },
  recommendations: [],
};

const userStore = useUserStore();
const isStudentProfile = computed(() => userStore.user?.profile_type === 'student');
const isLoading = ref(false);
const isRefreshing = ref(false);
const loadError = ref('');
const summary = ref<DashboardSummary | null>(null);
const periodType = ref<'monthly' | 'semester'>('monthly');
const selectedMonth = ref(malaysiaCurrentMonthYm());
const studentSemesters = ref<StudentSemester[]>([]);
const selectedSemesterId = ref<number | null>(null);
const warmupStorageKey = 'ai-insights:last-warmup-at';
const warmupCooldownMs = 10 * 60 * 1000;

const overview = computed(() => summary.value?.overview ?? defaultOverview);
const period = computed(() => summary.value?.period ?? defaultPeriod);
const aiInsights = computed(() => summary.value?.ai_insights ?? defaultAiInsights);
const nextForecast = computed(() => aiInsights.value.forecast?.next_month ?? defaultAiInsights.forecast.next_month);
const forecastPath = computed(() => aiInsights.value.forecast?.next_three_months ?? []);
const recommendations = computed(() => aiInsights.value.recommendations ?? []);

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

const semesterMatchesMonth = (semester: StudentSemester, monthYm: string) => {
  if (!monthYm || !/^\d{4}-\d{2}$/.test(monthYm)) return false;
  const [year, month] = monthYm.split('-').map(Number);
  if (!year || !month) return false;

  const monthStart = new Date(Date.UTC(year, month - 1, 1));
  const monthEnd = new Date(Date.UTC(year, month, 0));
  const start = new Date(`${semester.start_date}T00:00:00`);
  const end = new Date(`${semester.end_date}T23:59:59`);
  return start <= monthEnd && end >= monthStart;
};

const semesterForMonth = (monthYm: string) => studentSemesters.value.find((semester) => semesterMatchesMonth(semester, monthYm))?.id ?? null;

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
    if (periodType.value === 'semester') {
      selectedSemesterId.value = semesterForMonth(selectedMonth.value);
    }
  } catch (error) {
    console.error(error);
  }
};

const shouldWarmupModel = () => {
  const raw = localStorage.getItem(warmupStorageKey);
  const lastWarmup = Number(raw || 0);
  return !Number.isFinite(lastWarmup) || Date.now() - lastWarmup > warmupCooldownMs;
};

const markWarmup = () => {
  localStorage.setItem(warmupStorageKey, String(Date.now()));
};

const warmupModel = async () => {
  try {
    await axiosInstance.post('/dashboard/warmup');
  } catch (error) {
    console.error(error);
  }
};

const loadInsights = async (manualRefresh = false) => {
  if (manualRefresh) isRefreshing.value = true;
  else isLoading.value = true;

  loadError.value = '';
  try {
    const { data } = await axiosInstance.get<DashboardSummary>('/dashboard/summary', { params: buildPeriodParams() });
    summary.value = data;
    if (data?.period?.semester_id) {
      selectedSemesterId.value = data.period.semester_id;
    }
  } catch (error) {
    console.error(error);
    loadError.value = 'Unable to load AI insights right now.';
  } finally {
    isLoading.value = false;
    isRefreshing.value = false;
  }
};

const riskMeta = computed(() => {
  switch (aiInsights.value.risk_level) {
    case 'high':
      return {
        label: 'High risk',
        tone: 'border-rose-200 bg-rose-50 text-rose-700',
        badge: 'bg-rose-500/15 text-rose-700 ring-1 ring-rose-400/30',
      };
    case 'medium':
      return {
        label: 'Moderate risk',
        tone: 'border-amber-200 bg-amber-50 text-amber-700',
        badge: 'bg-amber-500/15 text-amber-700 ring-1 ring-amber-400/30',
      };
    default:
      return {
        label: 'Low risk',
        tone: 'border-emerald-200 bg-emerald-50 text-emerald-700',
        badge: 'bg-emerald-500/15 text-emerald-700 ring-1 ring-emerald-400/30',
      };
  }
});

const trendLabel = (value: 'up' | 'down' | 'stable') => {
  if (value === 'up') return 'Increasing';
  if (value === 'down') return 'Decreasing';
  return 'Stable';
};

const trendTone = (value: 'up' | 'down' | 'stable') => {
  if (value === 'up') return 'text-rose-700';
  if (value === 'down') return 'text-emerald-700';
  return 'text-slate-600';
};

const signalRows = computed(() => [
  {
    label: 'Income trend',
    value: trendLabel(aiInsights.value.signals.income_trend),
    detail: 'Compared with the previous period',
    tone: trendTone(aiInsights.value.signals.income_trend),
  },
  {
    label: 'Expense trend',
    value: trendLabel(aiInsights.value.signals.expense_trend),
    detail: 'Compared with the previous period',
    tone: trendTone(aiInsights.value.signals.expense_trend),
  },
  {
    label: 'Largest expense category',
    value: aiInsights.value.signals.largest_expense_category ?? 'Uncategorized',
    detail: `${aiInsights.value.signals.largest_expense_share.toFixed(1)}% of current expenses`,
    tone: 'text-slate-600',
  },
  {
    label: 'Transactions analysed',
    value: String(aiInsights.value.signals.transaction_count),
    detail: `${aiInsights.value.signals.savings_rate.toFixed(1)}% savings rate`,
    tone: 'text-slate-600',
  },
]);

const recommendationTone = (priority: AiRecommendation['priority']) => {
  if (priority === 'high') return 'border-rose-200 bg-rose-50 text-rose-700';
  if (priority === 'medium') return 'border-amber-200 bg-amber-50 text-amber-700';
  return 'border-emerald-200 bg-emerald-50 text-emerald-700';
};

const sourceLabel = computed(() => {
  if (aiInsights.value.source === 'ollama') {
    return aiInsights.value.model ? `AI Model • ${aiInsights.value.model}` : 'Local AI';
  }
  return 'Heuristic fallback';
});

onMounted(() => {
  periodType.value = isStudentProfile.value
    ? ((userStore.user?.preferred_period as 'monthly' | 'semester') || 'monthly')
    : 'monthly';
  if (shouldWarmupModel()) {
    void warmupModel().finally(() => {
      markWarmup();
    });
  }
  void Promise.all([loadStudentSemesters(), loadInsights()]);
});

watch([selectedMonth, periodType], () => {
  if (periodType.value === 'semester') {
    selectedSemesterId.value = semesterForMonth(selectedMonth.value);
  } else {
    selectedSemesterId.value = null;
  }
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

<style scoped>
:global(.bg-slate-900 .text-slate-500),
:global(.bg-slate-900 .text-slate-600),
:global(.bg-slate-900 .text-slate-700),
:global(.bg-slate-800 .text-slate-500),
:global(.bg-slate-800 .text-slate-600),
:global(.bg-slate-800 .text-slate-700),
:global(.bg-slate-950 .text-slate-500),
:global(.bg-slate-950 .text-slate-600),
:global(.bg-slate-950 .text-slate-700) {
  color: rgb(226 232 240) !important;
}

:global(.bg-slate-900 .text-slate-400),
:global(.bg-slate-800 .text-slate-400),
:global(.bg-slate-950 .text-slate-400) {
  color: rgb(203 213 225) !important;
}

:global(.bg-slate-900 .text-slate-300),
:global(.bg-slate-800 .text-slate-300),
:global(.bg-slate-950 .text-slate-300) {
  color: rgb(248 250 252) !important;
}
</style>

<template>
  <div class="h-full w-full overflow-hidden bg-slate-100 p-6">
    <div class="space-y-6">
      <section class="w-full rounded-[32px] bg-slate-950 px-6 py-8 text-white shadow-2xl shadow-slate-900/10">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <p class="text-sm uppercase tracking-[0.28em] text-slate-400">AI Insights &amp; Predictions</p>
            <h1 class="mt-2 text-3xl font-semibold sm:text-4xl">Personalized recommendations from your transaction patterns</h1>
            <p class="mt-2 text-sm text-slate-300">
              Local AI analysis highlights spending pressure, expected cashflow, and the next action you can take before the month closes.
            </p>
          </div>
            <div class="flex items-center gap-3">
              <div v-if="isStudentProfile" class="inline-flex h-10 items-center rounded-xl border border-white/15 bg-white/10 p-0.5 shadow-sm">
              <button
                type="button"
                class="mx-0.5 my-0.5 h-[calc(100%-4px)] rounded-md px-3 text-sm font-medium transition"
                :class="periodType === 'monthly' ? 'bg-white/20 text-white' : 'text-white hover:bg-white/15'"
                @click="periodType = 'monthly'; loadInsights(true)"
              >
                Monthly
              </button>
              <button
                type="button"
                class="mx-0.5 my-0.5 h-[calc(100%-4px)] rounded-md px-3 text-sm font-medium transition"
                :class="periodType === 'semester' ? 'bg-white/20 text-white' : 'text-white hover:bg-white/15'"
                @click="periodType = 'semester'; loadInsights(true)"
              >
                Semester
              </button>
            </div>
            <select
              v-if="isStudentProfile && periodType === 'semester'"
              v-model.number="selectedSemesterId"
              @change="loadInsights(true)"
              class="h-10 rounded-xl border border-white/20 bg-slate-900/60 px-3 text-sm text-white focus:ring-2 focus:ring-cyan-500/30"
            >
              <option v-for="semester in studentSemesters" :key="semester.id" :value="semester.id">
                {{ semester.name }} ({{ longDate(semester.start_date) }} to {{ longDate(semester.end_date) }})
              </option>
            </select>
            <button
              type="button"
              @click="loadInsights(true)"
              :disabled="isRefreshing"
              class="rounded-xl border border-white/15 bg-white/10 px-4 py-2 text-sm font-medium text-white hover:bg-white/20 disabled:opacity-60"
            >
              {{ isRefreshing ? 'Refreshing...' : 'Refresh' }}
            </button>
            <div class="rounded-2xl bg-white/5 px-4 py-3 text-sm text-slate-300">Updated {{ shortDate(period.updated_at) }}</div>
          </div>
        </div>

        <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
          <article :class="['rounded-[24px] border p-5', riskMeta.tone]">
            <p class="text-sm font-medium uppercase tracking-[0.25em]">Risk</p>
            <div class="mt-3 flex items-center justify-between gap-3">
              <div>
                <p class="text-2xl font-semibold">{{ riskMeta.label }}</p>
                <p class="mt-2 text-sm opacity-80">{{ aiInsights.confidence }}% confidence</p>
                <p class="mt-1 text-xs uppercase tracking-[0.2em] opacity-70">{{ sourceLabel }}</p>
              </div>
              <span :class="['rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em]', riskMeta.badge]">
                AI
              </span>
            </div>
          </article>
          <article class="rounded-[24px] border border-white/10 bg-white/5 p-5">
            <p class="text-sm text-slate-300">Next month income</p>
            <p class="mt-3 text-2xl font-semibold text-emerald-300">{{ money(nextForecast.income) }}</p>
            <p class="mt-2 text-xs text-slate-400">{{ nextForecast.month }}</p>
          </article>
          <article class="rounded-[24px] border border-white/10 bg-white/5 p-5">
            <p class="text-sm text-slate-300">Next month expense</p>
            <p class="mt-3 text-2xl font-semibold text-amber-300">{{ money(nextForecast.expense) }}</p>
            <p class="mt-2 text-xs text-slate-400">{{ nextForecast.month }}</p>
          </article>
          <article class="rounded-[24px] border border-white/10 bg-white/5 p-5">
            <p class="text-sm text-slate-300">Next month net</p>
            <p class="mt-3 text-2xl font-semibold" :class="nextForecast.net >= 0 ? 'text-cyan-300' : 'text-rose-300'">
              {{ money(nextForecast.net) }}
            </p>
            <p class="mt-2 text-xs text-slate-400">Projected cashflow</p>
          </article>
        </div>

        <div class="mt-6 rounded-[28px] border border-white/10 bg-white/5 p-5">
          <p class="text-xs uppercase tracking-[0.25em] text-cyan-200/70">AI Summary</p>
          <p class="mt-2 text-lg text-white">{{ aiInsights.summary }}</p>
        </div>
      </section>

      <section class="grid gap-6 xl:grid-cols-[0.92fr_1.08fr]">
        <article class="rounded-[28px] bg-white p-6 shadow-sm ring-1 ring-slate-200/70">
          <div class="flex items-start justify-between gap-4">
            <div>
              <p class="text-sm uppercase tracking-[0.25em] text-slate-400">Forecast Path</p>
              <h2 class="mt-1 text-2xl font-semibold text-slate-900">Three month projection</h2>
            </div>
            <span class="rounded-full bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700">
              {{ period.label }}
            </span>
          </div>
          <div v-if="forecastPath.length" class="mt-5 space-y-3">
            <div v-for="item in forecastPath" :key="item.month_key" class="rounded-2xl bg-slate-50 p-4">
              <div class="flex items-center justify-between text-sm">
                <span class="font-medium text-slate-900">{{ item.month }}</span>
                <span :class="item.net >= 0 ? 'text-emerald-700' : 'text-rose-700'">Net {{ money(item.net) }}</span>
              </div>
              <div class="mt-3 grid grid-cols-3 gap-3 text-xs">
                <div class="rounded-xl bg-white p-3">
                  <p class="text-slate-500">Income</p>
                  <p class="mt-1 font-semibold text-emerald-700">{{ money(item.income) }}</p>
                </div>
                <div class="rounded-xl bg-white p-3">
                  <p class="text-slate-500">Expense</p>
                  <p class="mt-1 font-semibold text-amber-700">{{ money(item.expense) }}</p>
                </div>
                <div class="rounded-xl bg-white p-3">
                  <p class="text-slate-500">Buffer</p>
                  <p class="mt-1 font-semibold" :class="item.net >= 0 ? 'text-emerald-700' : 'text-rose-700'">{{ money(item.net) }}</p>
                </div>
              </div>
            </div>
          </div>
          <p v-else class="mt-5 rounded-2xl bg-slate-50 p-6 text-sm text-slate-500">
            Add more transactions to unlock a more reliable forecast path.
          </p>
        </article>

        <article class="rounded-[28px] bg-white p-6 shadow-sm ring-1 ring-slate-200/70">
          <div>
            <p class="text-sm uppercase tracking-[0.25em] text-slate-400">Recommendations</p>
            <h2 class="mt-1 text-2xl font-semibold text-slate-900">What to do next</h2>
          </div>
          <div v-if="recommendations.length" class="mt-5 space-y-3">
            <div
              v-for="item in recommendations"
              :key="item.title"
              :class="['rounded-2xl border p-4', recommendationTone(item.priority)]"
            >
              <div class="flex items-center justify-between gap-3">
                <p class="font-semibold">{{ item.title }}</p>
                <span class="rounded-full bg-white/70 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.14em]">
                  {{ item.priority }}
                </span>
              </div>
              <p class="mt-2 text-sm leading-6 opacity-90">{{ item.detail }}</p>
            </div>
          </div>
          <p v-else class="mt-5 rounded-2xl bg-slate-50 p-6 text-sm text-slate-500">
            No recommendation yet. Add transactions to generate personalized suggestions.
          </p>
        </article>

        <article class="rounded-[28px] bg-slate-900 p-6 text-white shadow-sm xl:col-span-2">
          <div>
            <p class="text-sm uppercase tracking-[0.25em] text-slate-400">Signals</p>
            <h2 class="mt-1 text-2xl font-semibold">Behavior snapshot</h2>
          </div>
          <div class="mt-5 space-y-3">
            <div v-for="signal in signalRows" :key="signal.label" class="rounded-2xl bg-white/5 p-4">
              <p class="text-xs uppercase tracking-[0.2em] text-slate-400">{{ signal.label }}</p>
              <p class="mt-2 text-lg font-semibold" :class="signal.tone">{{ signal.value }}</p>
              <p class="mt-1 text-sm text-slate-300">{{ signal.detail }}</p>
            </div>
          </div>
          <div class="mt-5 rounded-2xl border border-white/10 bg-white/5 p-4 text-sm text-slate-300">
            The insight layer is built from your current spending patterns, so the more transactions you record, the sharper the forecast becomes.
          </div>
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
