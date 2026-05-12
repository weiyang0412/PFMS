<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import axiosInstance from '../lib/axios';
import { useToast } from '../composables/useToast';

const toast = useToast();
const isLoading = ref(false);
const isSaving = ref(false);
const isSendingTest = ref(false);
const isSendingAlertTest = ref(false);
const formError = ref('');

const form = reactive({
  budget_alerts_enabled: true,
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
    form.budget_alerts_enabled = data?.budget_alerts_enabled !== false;
    form.reports_enabled = Boolean(data?.reports_enabled);
    form.report_frequency = String(data?.report_frequency || 'weekly');
    form.report_weekday = Number(data?.report_weekday || 1);
    form.report_month_day = Number(data?.report_month_day || 1);
  } catch (error: any) {
    console.error(error);
    formError.value = error?.response?.data?.message || 'Unable to load notification settings.';
  } finally {
    isLoading.value = false;
  }
};

const savePreferences = async () => {
  isSaving.value = true;
  formError.value = '';
  try {
    await axiosInstance.patch('/notification-preferences', { ...form });
    toast.show('Notification settings saved.', 'success');
  } catch (error: any) {
    console.error(error);
    formError.value = error?.response?.data?.message || 'Unable to save notification settings.';
    toast.show(formError.value, 'danger');
  } finally {
    isSaving.value = false;
  }
};

const sendTestReport = async () => {
  isSendingTest.value = true;
  try {
    await axiosInstance.post('/notification-preferences/send-test-report');
    toast.show('Test report sent to your account email.', 'success');
  } catch (error: any) {
    console.error(error);
    toast.show(error?.response?.data?.message || 'Unable to send test report.', 'danger');
  } finally {
    isSendingTest.value = false;
  }
};

const sendTestBudgetAlert = async () => {
  isSendingAlertTest.value = true;
  try {
    await axiosInstance.post('/notification-preferences/send-test-budget-alert');
    toast.show('Test budget alert sent to your account email.', 'success');
  } catch (error: any) {
    console.error(error);
    toast.show(error?.response?.data?.message || 'Unable to send test budget alert.', 'danger');
  } finally {
    isSendingAlertTest.value = false;
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
        <p class="text-sm uppercase tracking-[0.3em] text-slate-400">Notifications</p>
        <h1 class="mt-2 text-3xl font-semibold sm:text-4xl">Notifications & Reports</h1>
        <p class="mt-2 text-sm text-slate-300">Choose when you want budget warnings and email reports to arrive.</p>
      </section>

      <section class="rounded-[28px] bg-white p-6 shadow-sm ring-1 ring-slate-200/70">
        <div v-if="formError" class="mb-4 rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700">{{ formError }}</div>
        <div class="space-y-6">
          <article class="rounded-2xl border border-slate-200 p-4">
            <h2 class="text-lg font-semibold text-slate-900">Budget Alerts</h2>
            <label class="mt-4 flex items-center justify-between">
              <span class="text-sm text-slate-700">Receive budget alert emails</span>
              <input v-model="form.budget_alerts_enabled" type="checkbox" class="h-4 w-4" />
            </label>
          </article>

          <article class="rounded-2xl border border-slate-200 p-4">
            <h2 class="text-lg font-semibold text-slate-900">Report Delivery</h2>
            <label class="mt-4 flex items-center justify-between">
              <span class="text-sm text-slate-700">Receive email reports</span>
              <input v-model="form.reports_enabled" type="checkbox" class="h-4 w-4" />
            </label>
            <div class="mt-4">
              <label class="mb-2 block text-sm text-slate-700">Report frequency</label>
              <select v-model="form.report_frequency" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                <option value="weekly">Weekly</option>
                <option value="monthly">Monthly</option>
                <option value="manual">Manual</option>
              </select>
            </div>
            <div v-if="form.report_frequency === 'weekly'" class="mt-4">
              <label class="mb-2 block text-sm text-slate-700">Send on this weekday (1 = Mon, 7 = Sun)</label>
              <input v-model.number="form.report_weekday" type="number" min="1" max="7" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
            </div>
            <div v-else-if="form.report_frequency === 'monthly'" class="mt-4">
              <label class="mb-2 block text-sm text-slate-700">Send on this day of month (1-28)</label>
              <input v-model.number="form.report_month_day" type="number" min="1" max="28" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
            </div>
            <p v-else class="mt-4 rounded-xl bg-slate-50 px-3 py-2 text-sm text-slate-600">
              Manual reports are only sent when you click the button below.
            </p>
          </article>
        </div>

        <div class="mt-6 flex flex-wrap gap-3">
          <button type="button" @click="savePreferences" :disabled="isSaving || isLoading" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-60">
            Save Changes
          </button>
          <button type="button" @click="sendTestReport" :disabled="isSendingTest || isLoading" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-60">
            {{ isSendingTest ? 'Sending...' : 'Send Test Report' }}
          </button>
          <button type="button" @click="sendTestBudgetAlert" :disabled="isSendingAlertTest || isLoading" class="rounded-xl border border-rose-300 bg-rose-50 px-4 py-2 text-sm font-medium text-rose-700 hover:bg-rose-100 disabled:opacity-60">
            {{ isSendingAlertTest ? 'Sending...' : 'Send Test Alert' }}
          </button>
        </div>
      </section>
    </div>

    <Teleport to="body">
      <Transition name="loading-fade">
        <div v-if="isLoading || isSaving" class="fixed inset-0 z-[120] flex items-center justify-center bg-black/55 px-4">
          <div class="flex w-full max-w-xs flex-col items-center rounded-2xl bg-white px-6 py-7 text-center shadow-2xl">
            <div class="relative mb-4 h-12 w-12">
              <span class="absolute inset-0 rounded-full border-4 border-slate-200"></span>
              <span class="absolute inset-0 animate-spin rounded-full border-4 border-transparent border-t-cyan-500 border-r-blue-600"></span>
            </div>
            <p class="text-lg font-semibold text-slate-900">
              {{ isLoading ? 'Loading...' : 'Saving...' }}
            </p>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>
