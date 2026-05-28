<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import axiosInstance from '../lib/axios';
import { useUserStore } from '../stores/userStore';
import { formatCurrencyMYR, malaysiaCurrentMonthYm } from '../lib/formatters.js';

interface StudentSemester {
  id: number;
  name: string;
  start_date: string;
  end_date: string;
}

interface GamificationPeriod {
  type?: 'monthly' | 'semester';
  label: string;
  comparison_label?: string;
  updated_at: string;
  month?: string;
  semester_id?: number | null;
}

interface GamificationProfile {
  points: number;
  level: number;
  level_progress: number;
  next_level_points: number;
  points_to_next_level: number;
  active_days: number;
  streak_days: number;
  transactions_count: number;
  budget_count: number;
  cashflow: number;
  savings_rate: number;
}

interface GamificationPointsBreakdown {
  code: string;
  label: string;
  points: number;
  detail: string;
}

interface GamificationBadge {
  code: string;
  title: string;
  description: string;
  target: number;
  kind: string;
  earned: boolean;
  earned_at: string | null;
  progress: number;
}

interface GamificationChallenge {
  code: string;
  title: string;
  description: string;
  target: number;
  progress: number;
  reward_points: number;
  reward_label: string;
  completed: boolean;
  status: 'pending' | 'active' | 'completed';
  subtext?: string | null;
}

interface GamificationReward {
  code: string;
  title: string;
  description: string;
  threshold: number | null;
  unlocked: boolean;
}

interface GamificationSummary {
  period: GamificationPeriod;
  profile: GamificationProfile;
  points_breakdown: GamificationPointsBreakdown[];
  badges: GamificationBadge[];
  challenges: GamificationChallenge[];
  rewards: GamificationReward[];
}

const defaultSummary: GamificationSummary = {
  period: {
    label: 'this month',
    updated_at: '',
  },
  profile: {
    points: 0,
    level: 1,
    level_progress: 0,
    next_level_points: 250,
    points_to_next_level: 250,
    active_days: 0,
    streak_days: 0,
    transactions_count: 0,
    budget_count: 0,
    cashflow: 0,
    savings_rate: 0,
  },
  points_breakdown: [],
  badges: [],
  challenges: [],
  rewards: [],
};

const userStore = useUserStore();
const isStudentProfile = computed(() => userStore.user?.profile_type === 'student');
const isLoading = ref(false);
const isRefreshing = ref(false);
const loadError = ref('');
const summary = ref<GamificationSummary | null>(null);
const periodType = ref<'monthly' | 'semester'>('monthly');
const selectedMonth = ref(malaysiaCurrentMonthYm());
const studentSemesters = ref<StudentSemester[]>([]);
const selectedSemesterId = ref<number | null>(null);

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

const money = (value = 0) => formatCurrencyMYR(value);
const safeSummary = computed(() => summary.value ?? defaultSummary);
const profile = computed(() => safeSummary.value.profile);
const badges = computed(() => safeSummary.value.badges ?? []);
const challenges = computed(() => safeSummary.value.challenges ?? []);
const rewards = computed(() => safeSummary.value.rewards ?? []);
const pointsBreakdown = computed(() => safeSummary.value.points_breakdown ?? []);
const earnedBadges = computed(() => badges.value.filter((badge) => badge.earned));
const completedChallenges = computed(() => challenges.value.filter((challenge) => challenge.completed));
const nextReward = computed(() => rewards.value.find((reward) => !reward.unlocked) ?? null);

const sortedSemesters = computed(() =>
  [...studentSemesters.value].sort((a, b) => {
    const aTime = new Date(`${a.start_date}T00:00:00`).getTime();
    const bTime = new Date(`${b.start_date}T00:00:00`).getTime();
    return aTime - bTime;
  }),
);

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

const semesterForMonth = (monthYm: string) =>
  studentSemesters.value.find((semester) => semesterMatchesMonth(semester, monthYm))?.id ?? null;

const buildParams = () => {
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

const loadSummary = async (manualRefresh = false) => {
  if (manualRefresh) isRefreshing.value = true;
  else isLoading.value = true;

  loadError.value = '';
  try {
    const { data } = await axiosInstance.get<GamificationSummary>('/gamification/summary', { params: buildParams() });
    summary.value = data;
    if (data?.period?.semester_id) {
      selectedSemesterId.value = data.period.semester_id;
    }
  } catch (error) {
    console.error(error);
    loadError.value = 'Unable to load gamification data right now.';
  } finally {
    isLoading.value = false;
    isRefreshing.value = false;
  }
};

const selectPeriodType = (value: 'monthly' | 'semester') => {
  periodType.value = value;
  if (value === 'semester') {
    selectedSemesterId.value = semesterForMonth(selectedMonth.value);
  } else {
    selectedSemesterId.value = null;
  }
  void loadSummary(true);
};

const handleMonthChange = (event: Event) => {
  const value = (event.target as HTMLInputElement).value;
  selectedMonth.value = value || malaysiaCurrentMonthYm();
  if (periodType.value === 'semester') {
    selectedSemesterId.value = semesterForMonth(selectedMonth.value);
  }
  void loadSummary(true);
};

const selectSemester = (semesterId: number) => {
  selectedSemesterId.value = semesterId;
  periodType.value = 'semester';
  void loadSummary(true);
};

const formatProgress = (value = 0) => `${Math.max(0, Math.min(100, Number(value))).toFixed(1)}%`;

const badgeTone = (earned: boolean) => {
  return earned
    ? 'border-emerald-200 bg-emerald-50 text-emerald-900 shadow-[0_12px_30px_-20px_rgba(16,185,129,0.25)]'
    : 'border-slate-200 bg-slate-50 text-slate-800';
};

const challengeTone = (status: GamificationChallenge['status']) => {
  if (status === 'completed') return 'border-emerald-200 bg-emerald-50 text-emerald-900';
  if (status === 'active') return 'border-amber-200 bg-amber-50 text-amber-900';
  return 'border-slate-200 bg-slate-50 text-slate-800';
};

const rewardTone = (unlocked: boolean) => {
  return unlocked
    ? 'border-cyan-200 bg-cyan-50 text-cyan-900'
    : 'border-slate-200 bg-slate-50 text-slate-800';
};

onMounted(() => {
  periodType.value = isStudentProfile.value ? ((userStore.user?.preferred_period as 'monthly' | 'semester') || 'monthly') : 'monthly';
  void loadStudentSemesters().finally(() => {
    if (periodType.value === 'semester') {
      selectedSemesterId.value = semesterForMonth(selectedMonth.value);
    }
    void loadSummary();
  });
});
</script>

<template>
  <div class="min-h-screen bg-slate-100 p-6">
    <div class="space-y-6">
      <section class="rounded-[32px] bg-slate-950 px-6 py-8 text-white shadow-2xl shadow-slate-900/10">
        <p class="text-sm uppercase tracking-[0.28em] text-slate-400">Gamification Module</p>
        <div class="mt-2 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
          <div class="max-w-3xl">
            <h1 class="text-3xl font-semibold sm:text-4xl">Gamified Engagement</h1>
            <p class="mt-2 text-sm text-slate-300">
              Turn financial habits into points, badges, challenges, and rewards that match the rest of PFMS.
            </p>
          </div>
          <div class="flex flex-wrap gap-3">
            <div class="rounded-2xl border border-white/10 bg-white/10 px-4 py-3">
              <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Level</p>
              <p class="mt-1 text-2xl font-semibold">{{ profile.level }}</p>
            </div>
            <div class="rounded-2xl border border-white/10 bg-white/10 px-4 py-3">
              <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Points</p>
              <p class="mt-1 text-2xl font-semibold">{{ profile.points }}</p>
            </div>
            <div class="rounded-2xl border border-white/10 bg-white/10 px-4 py-3">
              <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Streak</p>
              <p class="mt-1 text-2xl font-semibold">{{ profile.streak_days }}d</p>
            </div>
          </div>
        </div>
      </section>

      <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-[24px] border border-slate-200 bg-white px-5 py-4 shadow-sm">
          <p class="text-sm text-slate-500">Points</p>
          <p class="mt-2 text-3xl font-semibold text-slate-950">{{ profile.points }}</p>
          <p class="mt-1 text-xs text-slate-500">Current gamification score</p>
        </div>
        <div class="rounded-[24px] border border-slate-200 bg-white px-5 py-4 shadow-sm">
          <p class="text-sm text-slate-500">Cashflow</p>
          <p class="mt-2 text-3xl font-semibold" :class="profile.cashflow >= 0 ? 'text-emerald-600' : 'text-rose-600'">
            {{ money(profile.cashflow) }}
          </p>
          <p class="mt-1 text-xs text-slate-500">Selected period net position</p>
        </div>
        <div class="rounded-[24px] border border-slate-200 bg-white px-5 py-4 shadow-sm">
          <p class="text-sm text-slate-500">Savings rate</p>
          <p class="mt-2 text-3xl font-semibold text-slate-950">{{ profile.savings_rate.toFixed(1) }}%</p>
          <p class="mt-1 text-xs text-slate-500">Driving rewards and badges</p>
        </div>
        <div class="rounded-[24px] border border-slate-200 bg-white px-5 py-4 shadow-sm">
          <p class="text-sm text-slate-500">Transactions</p>
          <p class="mt-2 text-3xl font-semibold text-slate-950">{{ profile.transactions_count }}</p>
          <p class="mt-1 text-xs text-slate-500">Logged in the selected period</p>
        </div>
      </section>

      <section class="rounded-[28px] bg-slate-950 px-6 py-6 text-white shadow-2xl shadow-slate-900/10">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <p class="text-sm uppercase tracking-[0.28em] text-slate-400">Controls</p>
            <h2 class="mt-2 text-2xl font-semibold">Choose your period</h2>
            <p class="mt-1 text-sm text-slate-300">Switch between monthly and semester views for the module.</p>
          </div>
          <div class="flex flex-wrap items-center gap-3">
            <div v-if="isStudentProfile" class="inline-flex rounded-2xl border border-white/10 bg-white/5 p-1">
              <button
                type="button"
                class="rounded-xl px-4 py-2 text-sm font-medium transition"
                :class="periodType === 'monthly' ? 'bg-white text-slate-950 shadow-sm' : 'text-white/80 hover:bg-white/10'"
                @click="selectPeriodType('monthly')"
              >
                Monthly
              </button>
              <button
                type="button"
                class="rounded-xl px-4 py-2 text-sm font-medium transition"
                :class="periodType === 'semester' ? 'bg-white text-slate-950 shadow-sm' : 'text-white/80 hover:bg-white/10'"
                @click="selectPeriodType('semester')"
              >
                Semester
              </button>
            </div>
            <label class="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 px-4 py-2">
              <span class="text-sm text-slate-400">Month</span>
              <input
                :value="selectedMonth"
                type="month"
                class="border-0 bg-transparent p-0 text-sm font-medium text-white focus:ring-0"
                @change="handleMonthChange"
              />
            </label>
            <button
              type="button"
              class="rounded-2xl bg-cyan-500 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-cyan-400 disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="isRefreshing"
              @click="loadSummary(true)"
            >
              {{ isRefreshing ? 'Refreshing...' : 'Refresh' }}
            </button>
          </div>
        </div>

        <div v-if="isStudentProfile && periodType === 'semester' && sortedSemesters.length" class="mt-5 flex flex-wrap gap-2">
          <button
            v-for="semester in sortedSemesters"
            :key="semester.id"
            type="button"
            class="rounded-full border px-4 py-2 text-sm transition"
            :class="semester.id === selectedSemesterId ? 'border-cyan-300/30 bg-cyan-400/15 text-white' : 'border-white/10 bg-white/5 text-slate-300 hover:bg-white/10'"
            @click="selectSemester(semester.id)"
          >
            {{ semester.name }}
          </button>
        </div>
      </section>

      <section v-if="loadError" class="rounded-[24px] border border-rose-200 bg-rose-50 px-5 py-4 text-rose-700">
        {{ loadError }}
      </section>

      <section class="grid gap-4 xl:grid-cols-[1.2fr_0.8fr]">
        <div class="rounded-[28px] bg-white p-5 shadow-sm">
          <div class="flex items-center justify-between gap-4">
            <div>
              <p class="text-xs font-semibold uppercase tracking-[0.28em] text-cyan-700">Points Breakdown</p>
              <h2 class="mt-2 text-2xl font-semibold text-slate-950">How your score is built</h2>
            </div>
            <p class="text-sm text-slate-500">{{ pointsBreakdown.length }} bonus sources</p>
          </div>

          <div v-if="isLoading" class="mt-6 space-y-3">
            <div class="h-20 animate-pulse rounded-2xl bg-slate-100"></div>
            <div class="h-20 animate-pulse rounded-2xl bg-slate-100"></div>
            <div class="h-20 animate-pulse rounded-2xl bg-slate-100"></div>
          </div>
          <div v-else class="mt-6 space-y-3">
            <div
              v-for="item in pointsBreakdown"
              :key="item.code"
              class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4"
            >
              <div class="flex items-start justify-between gap-4">
                <div>
                  <p class="font-semibold text-slate-950">{{ item.label }}</p>
                  <p class="mt-1 text-sm text-slate-500">{{ item.detail }}</p>
                </div>
                <div class="rounded-full bg-slate-950 px-3 py-1 text-sm font-semibold text-white">
                  +{{ item.points }}
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="rounded-[28px] bg-white p-5 shadow-sm">
          <p class="text-xs font-semibold uppercase tracking-[0.28em] text-cyan-700">Reward Radar</p>
          <h2 class="mt-2 text-2xl font-semibold text-slate-950">Next milestone</h2>

          <div class="mt-5 rounded-[24px] bg-slate-950 px-5 py-5 text-white">
            <p class="text-sm text-slate-400">Upcoming reward</p>
            <h3 class="mt-2 text-xl font-semibold">{{ nextReward?.title ?? 'All rewards unlocked' }}</h3>
            <p class="mt-2 text-sm leading-6 text-slate-300">
              {{ nextReward?.description ?? 'You have unlocked every visible milestone for this period.' }}
            </p>
            <div v-if="nextReward" class="mt-4 inline-flex rounded-full border border-cyan-300/30 bg-cyan-500/10 px-3 py-1 text-sm text-cyan-100">
              Unlock at {{ nextReward.threshold }} points
            </div>
          </div>

          <div class="mt-5 grid gap-3 sm:grid-cols-2">
            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
              <p class="text-sm text-slate-500">Badges earned</p>
              <p class="mt-1 text-2xl font-semibold text-slate-950">{{ earnedBadges.length }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
              <p class="text-sm text-slate-500">Challenges cleared</p>
              <p class="mt-1 text-2xl font-semibold text-slate-950">{{ completedChallenges.length }}</p>
            </div>
          </div>
        </div>
      </section>

      <section class="rounded-[28px] bg-white p-5 shadow-sm">
        <div class="flex items-end justify-between gap-4">
          <div>
            <p class="text-xs font-semibold uppercase tracking-[0.28em] text-cyan-700">Badges</p>
            <h2 class="mt-2 text-2xl font-semibold text-slate-950">Your unlocked collection</h2>
          </div>
          <p class="text-sm text-slate-500">{{ earnedBadges.length }} earned, {{ badges.length - earnedBadges.length }} remaining</p>
        </div>

        <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
          <article
            v-for="badge in badges"
            :key="badge.code"
            class="rounded-[24px] border p-5 transition hover:-translate-y-0.5"
            :class="badgeTone(badge.earned)"
          >
            <div class="flex items-start justify-between gap-4">
              <div>
                <div class="inline-flex rounded-full border border-current/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.22em]">
                  {{ badge.kind.replace('_', ' ') }}
                </div>
                <h3 class="mt-3 text-xl font-semibold">{{ badge.title }}</h3>
                <p class="mt-2 text-sm leading-6 opacity-90">{{ badge.description }}</p>
              </div>
              <div class="rounded-2xl bg-white/10 px-3 py-2 text-right backdrop-blur">
                <p class="text-xs uppercase tracking-[0.2em] opacity-70">Target</p>
                <p class="text-lg font-semibold">{{ badge.target }}</p>
              </div>
            </div>

            <div class="mt-5 h-2 overflow-hidden rounded-full bg-white/15">
              <div
                class="h-full rounded-full"
                :class="badge.earned ? 'bg-emerald-300' : 'bg-white/60'"
                :style="{ width: formatProgress(badge.progress) }"
              ></div>
            </div>

            <div class="mt-4 flex items-center justify-between text-sm">
              <span>{{ formatProgress(badge.progress) }}</span>
              <span>{{ badge.earned ? `Earned ${longDate(badge.earned_at || '')}` : 'In progress' }}</span>
            </div>
          </article>
        </div>
      </section>

      <section class="grid gap-4 xl:grid-cols-[1fr_0.95fr]">
        <div class="rounded-[28px] bg-white p-5 shadow-sm">
          <div class="flex items-end justify-between gap-4">
            <div>
              <p class="text-xs font-semibold uppercase tracking-[0.28em] text-cyan-700">Challenges</p>
              <h2 class="mt-2 text-2xl font-semibold text-slate-950">Active quests</h2>
            </div>
            <p class="text-sm text-slate-500">{{ completedChallenges.length }} completed</p>
          </div>

          <div class="mt-6 space-y-4">
            <article
              v-for="challenge in challenges"
              :key="challenge.code"
              class="rounded-[24px] border p-5"
              :class="challengeTone(challenge.status)"
            >
              <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div>
                  <div class="inline-flex rounded-full border border-current/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.22em]">
                    {{ challenge.status }}
                  </div>
                  <h3 class="mt-3 text-xl font-semibold">{{ challenge.title }}</h3>
                  <p class="mt-2 text-sm leading-6 opacity-90">{{ challenge.description }}</p>
                  <p v-if="challenge.subtext" class="mt-2 text-sm opacity-80">{{ challenge.subtext }}</p>
                </div>
                <div class="rounded-2xl bg-white/10 px-4 py-3 text-right backdrop-blur">
                  <p class="text-xs uppercase tracking-[0.2em] opacity-70">Reward</p>
                  <p class="text-lg font-semibold">{{ challenge.reward_label }}</p>
                </div>
              </div>

              <div class="mt-5 flex items-center justify-between text-sm">
                <span>Progress</span>
                <span>{{ formatProgress(challenge.progress) }}</span>
              </div>
              <div class="mt-2 h-2 overflow-hidden rounded-full bg-white/15">
                <div
                  class="h-full rounded-full"
                  :class="challenge.completed ? 'bg-emerald-300' : 'bg-amber-300'"
                  :style="{ width: formatProgress(challenge.progress) }"
                ></div>
              </div>
            </article>
          </div>
        </div>

        <div class="space-y-4">
          <section class="rounded-[28px] bg-white p-5 shadow-sm">
            <div class="flex items-end justify-between gap-4">
              <div>
                <p class="text-xs font-semibold uppercase tracking-[0.28em] text-cyan-700">Rewards</p>
                <h2 class="mt-2 text-2xl font-semibold text-slate-950">Milestone track</h2>
              </div>
              <p class="text-sm text-slate-500">Level {{ profile.level }} track</p>
            </div>

            <div class="mt-6 space-y-3">
              <article
                v-for="reward in rewards"
                :key="reward.code"
                class="rounded-[22px] border p-4"
                :class="rewardTone(reward.unlocked)"
              >
                <div class="flex items-start justify-between gap-4">
                  <div>
                    <h3 class="font-semibold">{{ reward.title }}</h3>
                    <p class="mt-1 text-sm leading-6 opacity-90">{{ reward.description }}</p>
                  </div>
                  <div class="rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em]">
                    {{ reward.unlocked ? 'Unlocked' : 'Locked' }}
                  </div>
                </div>
                <p v-if="reward.threshold !== null" class="mt-3 text-sm opacity-80">
                  Threshold: {{ reward.threshold }} points
                </p>
              </article>
            </div>
          </section>

          <section class="rounded-[28px] bg-slate-950 p-5 text-white shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-[0.28em] text-cyan-200">Quick Links</p>
            <h2 class="mt-2 text-2xl font-semibold">Keep the momentum going</h2>
            <p class="mt-2 text-sm leading-6 text-slate-300">
              Add more transactions, tighten budgets, and return to this page to see the rewards fill up.
            </p>

            <div class="mt-5 flex flex-wrap gap-3">
              <router-link to="/transactions" class="rounded-2xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-cyan-50">
                Open Transactions
              </router-link>
              <router-link to="/budgets" class="rounded-2xl border border-white/15 bg-white/5 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-white/10">
                Open Budgets
              </router-link>
            </div>
          </section>
        </div>
      </section>
    </div>
  </div>
</template>
