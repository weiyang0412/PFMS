<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { malaysiaCurrentMonthYm } from '../lib/formatters.js';

interface StudentSemester {
  id: number;
  name: string;
  start_date: string;
  end_date: string;
}

const props = withDefaults(
  defineProps<{
    modelValue: number | null;
    semesters: StudentSemester[];
    label?: string;
    placeholder?: string;
    currentLabel?: string;
    clearLabel?: string;
    referenceMonthYm?: string;
  }>(),
  {
    label: 'Semester',
    placeholder: 'Select semester',
    currentLabel: 'Current semester',
    clearLabel: 'Clear',
    referenceMonthYm: malaysiaCurrentMonthYm(),
  },
);

const emit = defineEmits<{
  (event: 'update:modelValue', value: number | null): void;
  (event: 'change', value: number | null): void;
}>();

const rootRef = ref<HTMLElement | null>(null);
const isOpen = ref(false);
const displayYear = ref(Number(props.referenceMonthYm.slice(0, 4)));

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

const semesterLabel = (semester: StudentSemester) => `${semester.name} (${longDate(semester.start_date)} to ${longDate(semester.end_date)})`;

const sortedSemesters = computed(() =>
  [...props.semesters].sort((a, b) => {
    const aTime = new Date(`${a.start_date}T00:00:00`).getTime();
    const bTime = new Date(`${b.start_date}T00:00:00`).getTime();
    return aTime - bTime;
  }),
);

const semesterYears = computed(() => {
  const years = [...new Set(sortedSemesters.value.map((semester) => new Date(`${semester.start_date}T00:00:00`).getUTCFullYear()))];
  return years.length ? years.sort((a, b) => a - b) : [displayYear.value];
});

const semestersForYear = computed(() =>
  sortedSemesters.value.filter((semester) => new Date(`${semester.start_date}T00:00:00`).getUTCFullYear() === displayYear.value),
);

const yearIndex = computed(() => semesterYears.value.findIndex((year) => year === displayYear.value));

const selectedSemester = computed(() => props.semesters.find((semester) => semester.id === props.modelValue) ?? null);
const selectedLabel = computed(() => (selectedSemester.value ? semesterLabel(selectedSemester.value) : props.placeholder));

const semesterForMonth = (monthYm: string) => {
  if (!monthYm || !/^\d{4}-\d{2}$/.test(monthYm)) return null;
  const [year, month] = monthYm.split('-').map(Number);
  const monthStart = new Date(Date.UTC(year, month - 1, 1));
  const monthEnd = new Date(Date.UTC(year, month, 0));

  return (
    props.semesters.find((semester) => {
      const start = new Date(`${semester.start_date}T00:00:00`);
      const end = new Date(`${semester.end_date}T23:59:59`);
      return start <= monthEnd && end >= monthStart;
    }) ?? null
  );
};

const updateValue = (value: number | null) => {
  emit('update:modelValue', value);
  emit('change', value);
};

const openPicker = () => {
  const selected = selectedSemester.value;
  displayYear.value = selected ? new Date(`${selected.start_date}T00:00:00`).getUTCFullYear() : Number(props.referenceMonthYm.slice(0, 4));
  isOpen.value = !isOpen.value;
};

const closePicker = () => {
  isOpen.value = false;
};

const shiftYear = (delta: number) => {
  const currentIndex = semesterYears.value.findIndex((year) => year === displayYear.value);
  if (currentIndex === -1) {
    displayYear.value += delta;
    return;
  }

  const nextIndex = Math.max(0, Math.min(semesterYears.value.length - 1, currentIndex + delta));
  displayYear.value = semesterYears.value[nextIndex];
};

const chooseSemester = (semester: StudentSemester) => {
  updateValue(semester.id);
  displayYear.value = new Date(`${semester.start_date}T00:00:00`).getUTCFullYear();
  closePicker();
};

const clearSelection = () => {
  updateValue(null);
  closePicker();
};

const setCurrentSemester = () => {
  const currentSemester = semesterForMonth(malaysiaCurrentMonthYm());
  if (currentSemester) {
    updateValue(currentSemester.id);
    displayYear.value = new Date(`${currentSemester.start_date}T00:00:00`).getUTCFullYear();
  }
  closePicker();
};

const handleOutsideClick = (event: PointerEvent) => {
  const target = event.target as Node;
  if (!rootRef.value?.contains(target)) {
    closePicker();
  }
};

onMounted(() => {
  document.addEventListener('pointerdown', handleOutsideClick);
});

onBeforeUnmount(() => {
  document.removeEventListener('pointerdown', handleOutsideClick);
});
</script>

<template>
  <div ref="rootRef" class="relative inline-block">
    <button
      type="button"
      class="flex min-w-[270px] items-center justify-between gap-3 rounded-2xl border border-white/10 bg-white/5 px-4 py-2 text-left transition hover:bg-white/10"
      @click.stop="openPicker"
    >
      <span class="text-sm text-slate-400">{{ label }}</span>
      <span class="text-sm font-medium text-white">{{ selectedLabel }}</span>
      <span class="text-slate-400">▾</span>
    </button>

    <div
      v-if="isOpen"
      class="absolute right-0 top-[calc(100%+0.5rem)] z-30 w-[360px] rounded-2xl border border-slate-200 bg-white p-4 text-slate-900 shadow-2xl"
    >
      <div class="flex items-center justify-between rounded-xl bg-slate-100 px-3 py-2 text-slate-800">
        <button
          type="button"
          class="rounded-lg px-2 py-1 text-sm font-semibold text-slate-800 hover:bg-white/80 disabled:opacity-40"
          :disabled="yearIndex === 0 && semesterYears.length > 0"
          @click="shiftYear(-1)"
        >
          Prev
        </button>
        <p class="text-sm font-semibold text-slate-800">{{ displayYear }}</p>
        <button
          type="button"
          class="rounded-lg px-2 py-1 text-sm font-semibold text-slate-800 hover:bg-white/80 disabled:opacity-40"
          :disabled="semesterYears.length > 0 && yearIndex === semesterYears.length - 1"
          @click="shiftYear(1)"
        >
          Next
        </button>
      </div>

      <div v-if="semestersForYear.length" class="mt-4 grid gap-2">
        <button
          v-for="semester in semestersForYear"
          :key="semester.id"
          type="button"
          class="rounded-xl px-3 py-3 text-left text-sm font-medium transition"
          :class="modelValue === semester.id ? 'bg-cyan-500 text-white ring-2 ring-cyan-600' : 'bg-slate-100 text-slate-800 hover:bg-slate-200'"
          @click="chooseSemester(semester)"
        >
          <span class="block font-semibold">{{ semester.name }}</span>
          <span class="mt-1 block text-xs opacity-80">{{ semesterLabel(semester) }}</span>
        </button>
      </div>
      <div v-else class="mt-4 rounded-xl bg-slate-50 px-4 py-5 text-sm text-slate-500">
        No semester found for this year.
      </div>

      <div class="mt-4 flex items-center justify-between text-sm">
        <button type="button" class="font-medium text-slate-800 hover:text-slate-950" @click="clearSelection">
          {{ clearLabel }}
        </button>
        <button type="button" class="font-medium text-slate-800 hover:text-slate-950" @click="setCurrentSemester">
          {{ currentLabel }}
        </button>
      </div>
    </div>
  </div>
</template>
