<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import axiosInstance from '../lib/axios';
import { useToast } from '../composables/useToast.js';
import { formatCurrencyMYR } from '../lib/formatters.js';

type Mode = 'add' | 'edit';

interface AccountItem {
  id: number;
  name: string;
  balance: number | string;
}

interface AccountForm {
  name: string;
  balance: number | null;
}

const accounts = ref<AccountItem[]>([]);
const isLoading = ref(false);
const isSubmitting = ref(false);
const isDeleting = ref(false);
const showModal = ref(false);
const showConfirmDelete = ref(false);
const mode = ref<Mode>('add');
const selectedAccount = ref<AccountItem | null>(null);
const toast = useToast();

const form = reactive<AccountForm>({
  name: '',
  balance: null,
});

const errors = reactive<Record<string, string[]>>({
  name: [],
  balance: [],
});

const clearErrors = () => {
  errors.name = [];
  errors.balance = [];
};

const resetForm = () => {
  form.name = '';
  form.balance = null;
  clearErrors();
};

const totalBalance = computed(() =>
  accounts.value.reduce((sum, account) => sum + Number(account.balance || 0), 0)
);

const formatCurrency = (value: number) => formatCurrencyMYR(value);

const loadAccounts = async () => {
  isLoading.value = true;
  try {
    const { data } = await axiosInstance.get('/accounts');
    accounts.value = data.accounts || [];
  } catch (error) {
    console.error(error);
  } finally {
    isLoading.value = false;
  }
};

const openAddModal = () => {
  mode.value = 'add';
  selectedAccount.value = null;
  resetForm();
  showModal.value = true;
};

const openEditModal = (account: AccountItem) => {
  mode.value = 'edit';
  selectedAccount.value = account;
  form.name = account.name;
  form.balance = Number(account.balance);
  clearErrors();
  showModal.value = true;
};

const openConfirmDelete = (account: AccountItem) => {
  selectedAccount.value = account;
  showConfirmDelete.value = true;
};

const closeModal = () => {
  showModal.value = false;
  showConfirmDelete.value = false;
  clearErrors();
};

const saveAccount = async () => {
  isSubmitting.value = true;
  clearErrors();

  try {
    const payload = {
      name: form.name,
      balance: form.balance,
    };

    if (mode.value === 'edit' && selectedAccount.value?.id) {
      await axiosInstance.patch(`/accounts/${selectedAccount.value.id}`, payload);
    } else {
      await axiosInstance.post('/accounts', payload);
    }

    await loadAccounts();
    showModal.value = false;
    resetForm();
    toast.show(mode.value === 'edit' ? 'Account updated successfully.' : 'Account added successfully.', 'success');
  } catch (error: any) {
    if (error?.response?.status === 422) {
      Object.assign(errors, { ...errors, ...error.response.data.errors });
    } else {
      console.error(error);
    }
  } finally {
    isSubmitting.value = false;
  }
};

const deleteAccount = async () => {
  if (!selectedAccount.value?.id) return;

  isDeleting.value = true;
  try {
    await axiosInstance.delete(`/accounts/${selectedAccount.value.id}`);
    await loadAccounts();
    showConfirmDelete.value = false;
    selectedAccount.value = null;
    toast.show('Account deleted successfully.', 'danger');
  } catch (error) {
    console.error(error);
  } finally {
    isDeleting.value = false;
  }
};

onMounted(() => {
  loadAccounts();
});
</script>

<template>
  <div class="h-full w-full bg-slate-100 p-6">
    <div class="space-y-6">
      <section class="rounded-[32px] bg-slate-950 px-6 py-8 text-white shadow-2xl shadow-slate-900/10">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
          <div>
            <p class="text-sm uppercase tracking-[0.28em] text-slate-400">Accounts</p>
            <h1 class="mt-2 text-3xl font-semibold sm:text-4xl">Manage your balances</h1>
            <p class="mt-2 text-sm text-slate-300">
              Set how much money you currently have in each account.
            </p>
          </div>
          <button
            type="button"
            @click="openAddModal"
            class="rounded-xl border border-white/15 bg-white/10 px-5 py-3 text-sm font-medium text-white transition hover:bg-white/20"
          >
            Add Account
          </button>
        </div>
      </section>

      <section class="grid gap-6 lg:grid-cols-[1.2fr_0.8fr]">
        <div class="rounded-[28px] bg-white p-6 shadow-sm ring-1 ring-slate-200/70">
          <p class="text-sm font-medium uppercase tracking-[0.2em] text-slate-400">Total Balance</p>
          <p class="mt-3 text-4xl font-semibold text-slate-900">{{ formatCurrency(totalBalance) }}</p>
          <p class="mt-2 text-sm text-slate-500">
            This is the total across all accounts you have added.
          </p>
        </div>

        <div class="rounded-[28px] bg-slate-900 p-6 text-white shadow-sm">
          <p class="text-sm font-medium uppercase tracking-[0.2em] text-slate-400">Accounts Summary</p>
          <div class="mt-4 space-y-3">
            <div class="rounded-2xl bg-white/5 p-4">
              <p class="text-sm text-slate-300">Number of accounts</p>
              <p class="mt-2 text-2xl font-semibold">{{ accounts.length }}</p>
            </div>
            <div class="rounded-2xl bg-white/5 p-4">
              <p class="text-sm text-slate-300">Highest balance</p>
              <p class="mt-2 text-2xl font-semibold">
                {{ formatCurrency(Math.max(...accounts.map((account) => Number(account.balance || 0)), 0)) }}
              </p>
            </div>
          </div>
        </div>
      </section>

      <section class="rounded-[28px] bg-white p-6 shadow-sm ring-1 ring-slate-200/70">
        <div class="mb-4">
          <h2 class="text-xl font-semibold text-slate-900">Your Accounts</h2>
          <p class="text-sm text-slate-500">Manage account names and current balances here.</p>
        </div>

        <div v-if="!isLoading && accounts.length === 0" class="py-10 text-center text-slate-500">
          No accounts yet. Add one to start tracking your balances.
        </div>

        <div v-else-if="!isLoading" class="overflow-x-auto">
          <table class="min-w-full border-collapse text-left">
            <thead class="border-b border-slate-200">
              <tr class="text-sm text-slate-600">
                <th class="px-4 py-3">Account Name</th>
                <th class="px-4 py-3">Balance</th>
                <th class="px-4 py-3">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="account in accounts"
                :key="account.id"
                class="border-b border-slate-100 text-sm"
              >
                <td class="px-4 py-4 font-medium text-slate-900">{{ account.name }}</td>
                <td class="px-4 py-4 text-slate-700">{{ formatCurrency(Number(account.balance)) }}</td>
                <td class="px-4 py-4">
                  <div class="flex flex-wrap gap-2">
                    <button
                      type="button"
                      @click="openEditModal(account)"
                      class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-1.5 text-xs font-medium text-blue-700 transition hover:bg-blue-100"
                    >
                      Edit
                    </button>
                    <button
                      type="button"
                      @click="openConfirmDelete(account)"
                      class="rounded-lg border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-medium text-red-700 transition hover:bg-red-100"
                    >
                      Delete
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <Teleport to="body">
        <div
          v-if="isLoading"
          class="fixed inset-0 z-[120] flex items-center justify-center bg-black/55 px-4"
        >
          <div class="flex w-full max-w-xs flex-col items-center rounded-2xl bg-white px-6 py-7 text-center shadow-2xl">
            <span class="mb-4 h-12 w-12 animate-spin rounded-full border-4 border-slate-900 border-t-transparent"></span>
            <p class="text-lg font-semibold text-slate-900">Loading ...</p>
            <!-- <p class="mt-1 text-sm text-slate-500">Fetching your latest balances and account list.</p> -->
          </div>
        </div>
      </Teleport>

      <Teleport to="body">
        <div
          v-if="showModal"
          class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 px-4 py-8"
          @click.self="closeModal"
        >
          <div class="relative z-[101] w-full max-w-xl overflow-hidden rounded-2xl bg-white shadow-xl">
            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
              <div>
                <h2 class="text-xl font-semibold text-slate-900">
                  {{ mode === 'edit' ? 'Edit Account' : 'Add Account' }}
                </h2>
                <p class="text-sm text-slate-500">
                  {{ mode === 'edit' ? 'Update the current account balance.' : 'Create a new account and set its balance.' }}
                </p>
              </div>
              <button type="button" @click="closeModal" class="text-slate-500 hover:text-slate-900">
                x
              </button>
            </div>

            <form @submit.prevent="saveAccount" class="space-y-6 px-6 py-6">
              <div class="grid gap-4">
                <div>
                  <label class="mb-2 block text-sm font-medium text-slate-700">Account Name</label>
                  <input
                    v-model="form.name"
                    type="text"
                    class="w-full rounded-lg border border-gray-300 p-3 focus:ring-2 focus:ring-blue-500"
                    placeholder="Cash, Maybank Savings, Touch 'n Go..."
                  />
                  <p v-for="error in errors.name" :key="error" class="text-xs text-red-600">{{ error }}</p>
                </div>

                <div>
                  <label class="mb-2 block text-sm font-medium text-slate-700">Current Balance</label>
                  <input
                    v-model.number="form.balance"
                    type="number"
                    min="0"
                    step="0.01"
                    class="w-full rounded-lg border border-gray-300 p-3 focus:ring-2 focus:ring-blue-500"
                    placeholder="0.00"
                  />
                  <p v-for="error in errors.balance" :key="error" class="text-xs text-red-600">{{ error }}</p>
                </div>
              </div>

              <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <button
                  type="button"
                  @click="closeModal"
                  class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  :disabled="isSubmitting"
                  class="rounded-lg bg-slate-900 px-5 py-3 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-60"
                >
                  {{ mode === 'edit' ? 'Save Changes' : 'Save Account' }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </Teleport>

      <Teleport to="body">
        <div
          v-if="showConfirmDelete"
          class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 px-4 py-8"
          @click.self="showConfirmDelete = false"
        >
          <div class="relative z-[101] w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-xl">
            <div class="p-6">
              <h3 class="text-xl font-semibold text-slate-900">Delete this account?</h3>
              <p class="mt-2 text-sm text-slate-500">
                This account will be removed from your balance list.
              </p>
              <div class="mt-6 flex justify-end gap-3">
                <button
                  type="button"
                  @click="showConfirmDelete = false"
                  class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                >
                  Cancel
                </button>
                <button
                  type="button"
                  @click="deleteAccount"
                  :disabled="isDeleting"
                  class="rounded-lg bg-red-700 px-4 py-2 text-sm font-medium text-white hover:bg-red-800 disabled:opacity-60"
                >
                  <span
                    v-if="isDeleting"
                    class="mr-2 inline-block h-3 w-3 animate-spin rounded-full border-2 border-white border-t-transparent"
                  ></span>
                  Delete
                </button>
              </div>
            </div>
          </div>
        </div>
      </Teleport>

    </div>
  </div>
</template>

