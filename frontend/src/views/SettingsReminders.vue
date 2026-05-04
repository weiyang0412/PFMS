<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import axiosInstance from '../lib/axios';
import { useToast } from '../composables/useToast';

const toast = useToast();
const isLoading = ref(false);
const isSaving = ref(false);
const isSendingTest = ref(false);
const formError = ref('');

const form = reactive({
  reminders_enabled: true,
  reminder_days_before: 3,
  reports_enabled: true,
  report_frequency: 'weekly',
  report_weekday: 1,
  report_month_day: 1,
});

const loadPreferences = async () => {
  isLoading.value = true;
  formError.value = '';
  try {
    const { data } = await axiosInstance.get('/notification-preferences');
    form.reminders_enabled = Boolean(data?.reminders_enabled);
    form.reminder_days_before = Number(data?.reminder_days_before || 3);
    form.reports_enabled = Boolean(data?.reports_enabled);
    form.report_frequency = String(data?.report_frequency || 'weekly');
    form.report_weekday = Number(data?.report_weekday || 1);
    form.report_month_day = Number(data?.report_month_day || 1);
  } catch (error: any) {
    console.error(error);
    formError.value = error?.response?.data?.message || 'Unable to load reminder settings.';
  } finally {
    isLoading.value = false;
  }
};

const savePreferences = async () => {
  isSaving.value = true;
  formError.value = '';
  try {
    await axiosInstance.patch('/notification-preferences', { ...form });
    toast.show('Reminder and report settings saved.', 'success');
  } catch (error: any) {
    console.error(error);
    formError.value = error?.response?.data?.message || 'Unable to save reminder settings.';
    toast.show(formError.value, 'danger');
  } finally {
    isSaving.value = false;
  }
};

const sendTestReport = async () => {
  isSendingTest.value = true;
  try {
    await axiosInstance.post('/notification-preferences/send-test-report');
    toast.show('Test report email sent to your account email.', 'success');
  } catch (error: any) {
    console.error(error);
    toast.show(error?.response?.data?.message || 'Unable to send test report email.', 'danger');
  } finally {
    isSendingTest.value = false;
  }
};

onMounted(() => {
  loadPreferences();
});
</script>

<template>
  <div class="min-h-screen bg-slate-100 p-6">
    <div class="space-y-6">
      <section class="rounded-[32px] bg-slate-950 px-6 py-8 text-white shadow-2xl shadow-slate-900/10">
        <p class="text-sm uppercase tracking-[0.3em] text-slate-400">Settings</p>
        <h1 class="mt-2 text-3xl font-semibold sm:text-4xl">Reminders & Email Reports</h1>
        <p class="mt-2 text-sm text-slate-300">Control reminder notifications and automatic report delivery.</p>
      </section>

      <section class="rounded-[28px] bg-white p-6 shadow-sm ring-1 ring-slate-200/70">
        <div v-if="formError" class="mb-4 rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700">{{ formError }}</div>
        <div class="grid gap-6 md:grid-cols-2">
          <article class="rounded-2xl border border-slate-200 p-4">
            <h2 class="text-lg font-semibold text-slate-900">Reminders</h2>
            <label class="mt-4 flex items-center justify-between">
              <span class="text-sm text-slate-700">Enable reminders</span>
              <input v-model="form.reminders_enabled" type="checkbox" class="h-4 w-4" />
            </label>
            <div class="mt-4">
              <label class="mb-2 block text-sm text-slate-700">Days before due date</label>
              <input v-model.number="form.reminder_days_before" type="number" min="1" max="30" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
            </div>
          </article>

          <article class="rounded-2xl border border-slate-200 p-4">
            <h2 class="text-lg font-semibold text-slate-900">Email Reports</h2>
            <label class="mt-4 flex items-center justify-between">
              <span class="text-sm text-slate-700">Enable reports</span>
              <input v-model="form.reports_enabled" type="checkbox" class="h-4 w-4" />
            </label>
            <div class="mt-4">
              <label class="mb-2 block text-sm text-slate-700">Frequency</label>
              <select v-model="form.report_frequency" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                <option value="weekly">Weekly</option>
                <option value="monthly">Monthly</option>
              </select>
            </div>
            <div v-if="form.report_frequency === 'weekly'" class="mt-4">
              <label class="mb-2 block text-sm text-slate-700">Weekday (1=Mon, 7=Sun)</label>
              <input v-model.number="form.report_weekday" type="number" min="1" max="7" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
            </div>
            <div v-else class="mt-4">
              <label class="mb-2 block text-sm text-slate-700">Day of month (1-28)</label>
              <input v-model.number="form.report_month_day" type="number" min="1" max="28" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
            </div>
          </article>
        </div>

        <div class="mt-6 flex flex-wrap gap-3">
          <button type="button" @click="savePreferences" :disabled="isSaving || isLoading" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-60">
            {{ isSaving ? 'Saving...' : 'Save Settings' }}
          </button>
          <button type="button" @click="sendTestReport" :disabled="isSendingTest || isLoading" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-60">
            {{ isSendingTest ? 'Sending...' : 'Send Test Report' }}
          </button>
        </div>
      </section>
    </div>
  </div>
</template>

