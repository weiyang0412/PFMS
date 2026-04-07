
<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import axiosInstance from '../lib/axios';

type Mode = 'add' | 'edit' | 'view';
interface TransactionForm { description: string; amount: number | null; transaction_type_id: number | null; transaction_category_id: number | null; transaction_date: string; }
interface ManagedOption { id: number; name: string; }

const today = () => new Date().toISOString().slice(0, 10);
const form = reactive<TransactionForm>({ description: '', amount: null, transaction_type_id: null, transaction_category_id: null, transaction_date: today() });
const errors = reactive<Record<string, string[]>>({ description: [], amount: [], type: [], category: [], transaction_date: [] });
const transactions = ref<Array<Record<string, any>>>([]);
const typeOptions = ref<ManagedOption[]>([]);
const categoryOptions = ref<ManagedOption[]>([]);
const isLoading = ref(false);
const isSubmitting = ref(false);
const isDeleting = ref(false);
const isOptionsLoading = ref(false);
const showModal = ref(false);
const showConfirmDelete = ref(false);
const mode = ref<Mode>('add');
const selectedTransaction = ref<Record<string, any> | null>(null);
const currentPage = ref(1);
const perPage = ref(10);
const totalPages = ref(1);
const totalTransactions = ref(0);

const clearFormErrors = () => Object.keys(errors).forEach((key) => { errors[key] = []; });
const defaultTypeId = () => typeOptions.value[0]?.id ?? null;
const resetForm = () => { form.description = ''; form.amount = null; form.transaction_type_id = defaultTypeId(); form.transaction_category_id = null; form.transaction_date = today(); clearFormErrors(); };

const loadTransactions = async (page = 1) => {
  isLoading.value = true;
  try {
    const { data } = await axiosInstance.get('/transactions', { params: { page, per_page: perPage.value } });
    transactions.value = data.data;
    totalPages.value = data.last_page;
    totalTransactions.value = data.total;
    currentPage.value = data.current_page;
  } catch (error) { console.error(error); } finally { isLoading.value = false; }
};

const loadOptions = async () => {
  isOptionsLoading.value = true;
  try {
    const { data } = await axiosInstance.get('/transaction-options');
    typeOptions.value = data.types || [];
    categoryOptions.value = data.categories || [];
    if (!form.transaction_type_id || !typeOptions.value.some((option) => option.id === form.transaction_type_id)) form.transaction_type_id = defaultTypeId();
    if (form.transaction_category_id && !categoryOptions.value.some((option) => option.id === form.transaction_category_id)) form.transaction_category_id = null;
  } catch (error) { console.error(error); } finally { isOptionsLoading.value = false; }
};

const openAddModal = async () => { mode.value = 'add'; selectedTransaction.value = null; await loadOptions(); resetForm(); showModal.value = true; };
const openViewModal = (transaction: Record<string, any>) => { mode.value = 'view'; selectedTransaction.value = transaction; showModal.value = true; };
const openEditModal = async (transaction: Record<string, any>) => {
  mode.value = 'edit';
  selectedTransaction.value = transaction;
  await loadOptions();
  form.description = transaction.description || '';
  form.amount = transaction.amount != null ? Number(transaction.amount) : null;
  form.transaction_type_id = transaction.transaction_type_id ?? defaultTypeId();
  form.transaction_category_id = transaction.transaction_category_id ?? null;
  form.transaction_date = transaction.transaction_date || today();
  clearFormErrors();
  showModal.value = true;
};
const openConfirmDelete = (transaction: Record<string, any>) => { selectedTransaction.value = transaction; showConfirmDelete.value = true; };
const closeModal = () => { showModal.value = false; showConfirmDelete.value = false; };

const saveTransaction = async () => {
  if (mode.value === 'view') return closeModal();
  isSubmitting.value = true;
  clearFormErrors();
  try {
    const payload = { description: form.description, amount: form.amount, transaction_type_id: form.transaction_type_id, transaction_category_id: form.transaction_category_id, transaction_date: form.transaction_date };
    if (mode.value === 'edit' && selectedTransaction.value?.id) await axiosInstance.patch(`/transactions/${selectedTransaction.value.id}`, payload);
    else await axiosInstance.post('/transactions', payload);
    await loadTransactions();
    resetForm();
    showModal.value = false;
  } catch (error: any) {
    if (error?.response?.status === 422) Object.assign(errors, { ...errors, ...error.response.data.errors });
    else console.error(error);
  } finally { isSubmitting.value = false; }
};
const deleteTransaction = async () => {
  if (!selectedTransaction.value?.id) return;
  isDeleting.value = true;
  try {
    await axiosInstance.delete(`/transactions/${selectedTransaction.value.id}`);
    await loadTransactions(currentPage.value);
    showConfirmDelete.value = false;
    selectedTransaction.value = null;
  } catch (error) { console.error(error); } finally { isDeleting.value = false; }
};

const goToPage = (page: number) => { if (page >= 1 && page <= totalPages.value) loadTransactions(page); };
const nextPage = () => { if (currentPage.value < totalPages.value) goToPage(currentPage.value + 1); };
const prevPage = () => { if (currentPage.value > 1) goToPage(currentPage.value - 1); };

onMounted(async () => {
  await Promise.all([loadTransactions(), loadOptions()]);
  if (!form.transaction_type_id) form.transaction_type_id = defaultTypeId();
});
</script>

<template>
  <div class="h-full w-full overflow-hidden bg-slate-100 p-6">
    <div class="h-full w-full space-y-6">
      <section class="w-full rounded-lg bg-white p-6 shadow">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h1 class="text-3xl font-semibold text-slate-900">Transactions</h1>
            <p class="text-sm text-slate-500">Track your income and expenses on a full page.</p>
          </div>
          <button type="button" @click="openAddModal" class="rounded-lg bg-blue-700 px-5 py-3 text-sm font-medium text-white hover:bg-blue-800">Add Transaction</button>
        </div>
      </section>

      <Teleport to="body">
        <div v-if="showModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 px-4 py-8" @click.self="closeModal">
          <div class="relative z-[101] max-h-[90vh] w-full max-w-3xl overflow-hidden rounded-2xl bg-white shadow-xl">
            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
              <div>
                <h2 class="text-xl font-semibold text-slate-900">{{ mode === 'view' ? 'Transaction Details' : mode === 'edit' ? 'Edit Transaction' : 'Add Transaction' }}</h2>
                <p class="text-sm text-slate-500">{{ mode === 'view' ? 'Review the transaction details.' : mode === 'edit' ? 'Update your transaction.' : 'Create a new transaction entry.' }}</p>
              </div>
              <button type="button" @click="closeModal" class="text-slate-500 hover:text-slate-900">x</button>
            </div>
            <div v-if="mode === 'view'" class="space-y-4 px-6 py-6">
              <div class="grid gap-4 md:grid-cols-2">
                <div><p class="text-sm font-medium text-slate-700">Description</p><p class="mt-2 text-slate-900">{{ selectedTransaction?.description }}</p></div>
                <div><p class="text-sm font-medium text-slate-700">Amount</p><p class="mt-2 text-slate-900">{{ selectedTransaction?.type === 'income' ? '+' : '-' }}{{ Number(selectedTransaction?.amount || 0).toFixed(2) }}</p></div>
                <div><p class="text-sm font-medium text-slate-700">Type</p><p class="mt-2 text-slate-900">{{ selectedTransaction?.type }}</p></div>
                <div><p class="text-sm font-medium text-slate-700">Category</p><p class="mt-2 text-slate-900">{{ selectedTransaction?.category || '—' }}</p></div>
                <div class="md:col-span-2"><p class="text-sm font-medium text-slate-700">Date</p><p class="mt-2 text-slate-900">{{ selectedTransaction?.transaction_date }}</p></div>
              </div>
              <div class="flex justify-end"><button type="button" @click="closeModal" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Close</button></div>
            </div>

            <form v-else @submit.prevent="saveTransaction" class="max-h-[calc(90vh-88px)] overflow-y-auto px-6 py-6 space-y-6">
              <div class="grid gap-4 md:grid-cols-2">
                <div><label class="mb-2 block text-sm font-medium text-slate-700">Description</label><input v-model="form.description" type="text" class="w-full rounded-lg border border-gray-300 p-3 focus:ring-2 focus:ring-blue-500" placeholder="Rent, Salary, Groceries..." /><p v-for="error in errors.description" :key="error" class="text-xs text-red-600">{{ error }}</p></div>
                <div><label class="mb-2 block text-sm font-medium text-slate-700">Amount</label><input v-model.number="form.amount" type="number" min="0" step="0.01" class="w-full rounded-lg border border-gray-300 p-3 focus:ring-2 focus:ring-blue-500" placeholder="0.00" /><p v-for="error in errors.amount" :key="error" class="text-xs text-red-600">{{ error }}</p></div>
                <div><label class="mb-2 block text-sm font-medium text-slate-700">Type</label><select v-model="form.transaction_type_id" class="w-full rounded-lg border border-gray-300 p-3 focus:ring-2 focus:ring-blue-500" :disabled="isOptionsLoading || typeOptions.length === 0"><option :value="null" disabled>Select a type</option><option v-for="option in typeOptions" :key="option.id" :value="option.id">{{ option.name }}</option></select><p v-for="error in errors.transaction_type_id" :key="error" class="text-xs text-red-600">{{ error }}</p></div>
                <div><label class="mb-2 block text-sm font-medium text-slate-700">Category</label><select v-model="form.transaction_category_id" class="w-full rounded-lg border border-gray-300 p-3 focus:ring-2 focus:ring-blue-500" :disabled="isOptionsLoading"><option :value="null">No category</option><option v-for="option in categoryOptions" :key="option.id" :value="option.id">{{ option.name }}</option></select><p v-for="error in errors.transaction_category_id" :key="error" class="text-xs text-red-600">{{ error }}</p></div>
                <div><label class="mb-2 block text-sm font-medium text-slate-700">Date</label><input v-model="form.transaction_date" type="date" class="w-full rounded-lg border border-gray-300 p-3 focus:ring-2 focus:ring-blue-500" /><p v-for="error in errors.transaction_date" :key="error" class="text-xs text-red-600">{{ error }}</p></div>
              </div>

              <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end"><button type="button" @click="closeModal" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</button><button type="submit" :disabled="isSubmitting || isOptionsLoading || typeOptions.length === 0" class="rounded-lg bg-blue-700 px-5 py-3 text-sm font-medium text-white hover:bg-blue-800 disabled:opacity-60">{{ mode === 'edit' ? 'Save Changes' : 'Save Transaction' }}</button></div>
            </form>
          </div>
        </div>
      </Teleport>

      <section class="flex-1 overflow-hidden rounded-lg bg-white p-6 shadow">
        <div class="mb-4"><h2 class="text-xl font-semibold text-slate-900">Recent Transactions</h2><p class="text-sm text-slate-500">Track your latest finance records.</p></div>
        <div v-if="!isLoading" class="flex h-full flex-col">
          <div v-if="transactions.length === 0" class="py-10 text-center text-slate-500">No transactions yet. Add one to start tracking.</div>
          <div v-else class="flex flex-1 flex-col">
            <div class="flex-1 overflow-hidden"><div class="h-full overflow-x-auto"><table class="min-w-full border-collapse text-left"><thead class="sticky top-0 z-10 border-b border-slate-200 bg-white"><tr class="text-sm text-slate-600"><th class="px-4 py-3">Date</th><th class="px-4 py-3">Description</th><th class="px-4 py-3">Category</th><th class="px-4 py-3">Type</th><th class="px-4 py-3">Amount</th><th class="px-4 py-3">Actions</th></tr></thead><tbody><tr v-for="transaction in transactions" :key="transaction.id" class="border-b border-slate-200 text-sm"><td class="px-4 py-3">{{ transaction.transaction_date }}</td><td class="px-4 py-3">{{ transaction.description }}</td><td class="px-4 py-3">{{ transaction.category || '—' }}</td><td class="px-4 py-3">{{ transaction.type }}</td><td class="px-4 py-3 font-semibold" :class="transaction.type === 'income' ? 'text-emerald-600' : 'text-red-600'">{{ transaction.type === 'income' ? '+' : '-' }}{{ Number(transaction.amount).toFixed(2) }}</td><td class="px-4 py-3"><div class="flex flex-wrap gap-2"><button type="button" @click="openViewModal(transaction)" class="rounded-md border border-slate-300 bg-white px-3 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50">View</button><button type="button" @click="openEditModal(transaction)" class="rounded-md border border-blue-700 bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700 hover:bg-blue-100">Edit</button><button type="button" @click="openConfirmDelete(transaction)" class="rounded-md border border-red-700 bg-red-50 px-3 py-1 text-xs font-medium text-red-700 hover:bg-red-100">Delete</button></div></td></tr></tbody></table></div></div>
            <div v-if="totalPages > 1" class="mt-4 flex items-center justify-between"><div class="text-sm text-slate-500">Showing {{ (currentPage - 1) * perPage + 1 }} to {{ Math.min(currentPage * perPage, totalTransactions) }} of {{ totalTransactions }} transactions</div><div class="flex items-center gap-2"><button @click="prevPage" :disabled="currentPage === 1" class="rounded-md border border-slate-300 bg-white px-3 py-1 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50">Previous</button><span class="text-sm text-slate-700">Page {{ currentPage }} of {{ totalPages }}</span><button @click="nextPage" :disabled="currentPage === totalPages" class="rounded-md border border-slate-300 bg-white px-3 py-1 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50">Next</button></div></div>
          </div>
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
            <!-- <p class="mt-1 text-sm text-slate-500">Fetching your latest transaction records.</p> -->
          </div>
        </div>
      </Teleport>

      <Teleport to="body">
        <div v-if="showConfirmDelete" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 px-4 py-8" @click.self="showConfirmDelete = false">
          <div class="relative z-[101] w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-xl"><div class="p-6"><h3 class="text-xl font-semibold text-slate-900">Delete this transaction?</h3><p class="mt-2 text-sm text-slate-500">This action cannot be undone. Confirm to remove this transaction permanently.</p><div class="mt-6 flex justify-end gap-3"><button type="button" @click="showConfirmDelete = false" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</button><button type="button" @click="deleteTransaction" :disabled="isDeleting" class="rounded-lg bg-red-700 px-4 py-2 text-sm font-medium text-white hover:bg-red-800 disabled:opacity-60"><span v-if="isDeleting" class="mr-2 inline-block h-3 w-3 animate-spin rounded-full border-2 border-white border-t-transparent"></span>Delete</button></div></div></div>
        </div>
      </Teleport>
    </div>

    <Teleport to="body">
      <div v-if="isSubmitting" class="fixed inset-0 z-[110] flex items-center justify-center bg-black/50"><div class="relative z-[111] flex flex-col items-center rounded-lg bg-white p-6 shadow-xl"><span class="mb-4 h-12 w-12 animate-spin rounded-full border-4 border-blue-500 border-t-transparent"></span><p class="text-lg font-medium text-slate-900">Saving transaction...</p></div></div>
    </Teleport>
  </div>
</template>
