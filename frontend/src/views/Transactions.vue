<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import axiosInstance from '../lib/axios';
import { useToast } from '../composables/useToast.js';
import { formatCurrencyMYR, formatYmdDate } from '../lib/formatters.js';

type Mode = 'add' | 'edit' | 'view';
interface TransactionForm { description: string; amount: number | null; transaction_type_id: number | null; transaction_category_id: number | null; transaction_date: string; }
interface ManagedOption {
  id: number;
  name: string;
  applies_to?: 'income' | 'expense' | 'both';
}
interface BudgetItem {
  category_id: number;
  category: string;
  budget_id: number | null;
  amount: number | null;
  alert_threshold: number;
  spent: number;
  remaining: number | null;
  usage_pct: number;
  alert_level: 'none' | 'safe' | 'warning' | 'over';
}
interface BudgetSummary {
  total_budget: number;
  total_spent: number;
  warning_count: number;
  total_overspent: number;
}

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
const isBudgetLoading = ref(false);
const budgetSummary = ref<BudgetSummary>({ total_budget: 0, total_spent: 0, warning_count: 0, total_overspent: 0 });
const budgetItems = ref<BudgetItem[]>([]);
const typeFilter = ref('all');
const categoryFilter = ref('all');
const descriptionSearch = ref('');
const monthFilter = ref(new Date().toISOString().slice(0, 7));
const toast = useToast();
const route = useRoute();

const clearFormErrors = () => Object.keys(errors).forEach((key) => { errors[key] = []; });
const defaultTypeId = () => typeOptions.value[0]?.id ?? null;
const resetForm = () => { form.description = ''; form.amount = null; form.transaction_type_id = defaultTypeId(); form.transaction_category_id = null; form.transaction_date = today(); clearFormErrors(); };
const parseTransactionDate = (value: unknown) => {
  if (typeof value !== 'string') return null;
  const [year, month, day] = value.split('-').map(Number);
  if (!year || !month || !day) return null;
  const parsed = new Date(year, month - 1, day);
  return Number.isNaN(parsed.getTime()) ? null : parsed;
};
const formatDisplayDate = (value: unknown) => {
  return formatYmdDate(value, { locale: 'en-GB', fallback: '—' });
};
const formatSignedAmount = (amount: unknown, type: unknown) => {
  const numericAmount = Number(amount ?? 0);
  const safeAmount = Number.isFinite(numericAmount) ? Math.abs(numericAmount) : 0;
  const prefix = type === 'income' ? '+' : '-';
  return `${prefix}${formatCurrencyMYR(safeAmount)}`;
};
const formatMoney = (value: unknown) => {
  return formatCurrencyMYR(value);
};
const parseDateTime = (value: unknown) => {
  if (!value) return null;
  const parsed = new Date(String(value));
  return Number.isNaN(parsed.getTime()) ? null : parsed;
};
const sortedTransactions = computed(() =>
  [...transactions.value].sort((a, b) => {
    const dateA = parseTransactionDate(a?.transaction_date)?.getTime() ?? 0;
    const dateB = parseTransactionDate(b?.transaction_date)?.getTime() ?? 0;
    if (dateB !== dateA) return dateB - dateA;

    const createdAtA = parseDateTime(a?.created_at)?.getTime() ?? 0;
    const createdAtB = parseDateTime(b?.created_at)?.getTime() ?? 0;
    if (createdAtB !== createdAtA) return createdAtB - createdAtA;

    return Number(b?.id ?? 0) - Number(a?.id ?? 0);
  }),
);
const hasActiveFilters = computed(() =>
  typeFilter.value !== 'all' || categoryFilter.value !== 'all' || descriptionSearch.value.trim().length > 0,
);
const filteredTransactions = computed(() => {
  const keyword = descriptionSearch.value.trim().toLowerCase();

  return sortedTransactions.value.filter((transaction) => {
    const matchesType = typeFilter.value === 'all' || String(transaction?.type || '') === typeFilter.value;
    const matchesCategory = categoryFilter.value === 'all'
      || (categoryFilter.value === '__none__'
        ? !transaction?.category
        : String(transaction?.category || '') === categoryFilter.value);
    const matchesDescription = !keyword || String(transaction?.description || '').toLowerCase().includes(keyword);
    return matchesType && matchesCategory && matchesDescription;
  });
});
const budgetUsagePct = computed(() => {
  const totalBudget = Number(budgetSummary.value.total_budget || 0);
  const totalSpent = Number(budgetSummary.value.total_spent || 0);
  if (totalBudget <= 0) return 0;
  return Math.max(0, (totalSpent / totalBudget) * 100);
});
const budgetStatus = computed(() => {
  const totalBudget = Number(budgetSummary.value.total_budget || 0);
  if (totalBudget <= 0) return 'none';
  if (budgetUsagePct.value >= 100) return 'over';
  if (budgetSummary.value.warning_count > 0 || budgetUsagePct.value >= 80) return 'warning';
  return 'safe';
});
const budgetStatusText = computed(() => {
  if (budgetStatus.value === 'over') return 'Over Budget';
  if (budgetStatus.value === 'warning') return 'Warning';
  if (budgetStatus.value === 'safe') return 'On Track';
  return 'No Budget';
});
const budgetStatusClass = computed(() => {
  if (budgetStatus.value === 'over') return 'bg-red-100 text-red-700 ring-red-200';
  if (budgetStatus.value === 'warning') return 'bg-amber-100 text-amber-700 ring-amber-200';
  if (budgetStatus.value === 'safe') return 'bg-emerald-100 text-emerald-700 ring-emerald-200';
  return 'bg-slate-100 text-slate-600 ring-slate-200';
});
const budgetStatusDotClass = computed(() => {
  if (budgetStatus.value === 'over') return 'bg-red-500';
  if (budgetStatus.value === 'warning') return 'bg-amber-500';
  if (budgetStatus.value === 'safe') return 'bg-emerald-500';
  return 'bg-slate-400';
});
const selectedTypeName = computed(() => {
  const selectedType = typeOptions.value.find((option) => option.id === form.transaction_type_id);
  return String(selectedType?.name || '').toLowerCase();
});
const filteredCategoryOptionsForForm = computed(() => {
  const typeName = selectedTypeName.value;
  if (!typeName) return categoryOptions.value;

  if (typeName !== 'income' && typeName !== 'expense') return categoryOptions.value;

  return categoryOptions.value.filter((option) => {
    const appliesTo = option.applies_to || 'both';
    return appliesTo === 'both' || appliesTo === typeName;
  });
});
const topBudgetAlerts = computed(() =>
  budgetItems.value
    .filter((item) => item.alert_level === 'warning' || item.alert_level === 'over')
    .sort((a, b) => Number(b.usage_pct) - Number(a.usage_pct))
    .slice(0, 3),
);

const loadTransactions = async (page = 1) => {
  isLoading.value = true;
  try {
    const params: Record<string, string | number> = {
      page,
      per_page: perPage.value,
    };
    if (monthFilter.value) {
      params.month = monthFilter.value;
    }

    const { data } = await axiosInstance.get('/transactions', { params });
    transactions.value = data.data;
    totalPages.value = data.last_page;
    totalTransactions.value = data.total;
    currentPage.value = data.current_page;
  } catch (error) { console.error(error); } finally { isLoading.value = false; }
};
const currentMonth = () => {
  const now = new Date();
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
};
const loadBudgetSnapshot = async () => {
  isBudgetLoading.value = true;
  try {
    const { data } = await axiosInstance.get('/budgets', { params: { month: currentMonth() } });
    budgetSummary.value = data.summary || { total_budget: 0, total_spent: 0, warning_count: 0, total_overspent: 0 };
    budgetItems.value = data.items || [];
  } catch (error) { console.error(error); } finally { isBudgetLoading.value = false; }
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
    const isEditing = mode.value === 'edit' && !!selectedTransaction.value?.id;
    const payload = { description: form.description, amount: form.amount, transaction_type_id: form.transaction_type_id, transaction_category_id: form.transaction_category_id, transaction_date: form.transaction_date };
    if (isEditing) await axiosInstance.patch(`/transactions/${selectedTransaction.value.id}`, payload);
    else await axiosInstance.post('/transactions', payload);
    showModal.value = false;
    await loadTransactions();
    await loadBudgetSnapshot();
    resetForm();
    toast.show(isEditing ? 'Transaction updated successfully.' : 'Transaction added successfully.', 'success');
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
    showConfirmDelete.value = false;
    await loadTransactions(currentPage.value);
    await loadBudgetSnapshot();
    selectedTransaction.value = null;
    toast.show('Transaction deleted successfully.', 'danger');
  } catch (error) { console.error(error); } finally { isDeleting.value = false; }
};

const goToPage = (page: number) => { if (page >= 1 && page <= totalPages.value) loadTransactions(page); };
const nextPage = () => { if (currentPage.value < totalPages.value) goToPage(currentPage.value + 1); };
const prevPage = () => { if (currentPage.value > 1) goToPage(currentPage.value - 1); };
const handleMonthFilterChange = () => {
  loadTransactions(1);
};

onMounted(async () => {
  if (typeof route.query.month === 'string' && /^\d{4}-\d{2}$/.test(route.query.month)) {
    monthFilter.value = route.query.month;
  }
  if (typeof route.query.category === 'string' && route.query.category.trim()) {
    categoryFilter.value = route.query.category.trim();
  }
  await Promise.all([loadTransactions(), loadOptions(), loadBudgetSnapshot()]);
  if (!form.transaction_type_id) form.transaction_type_id = defaultTypeId();
});

watch(
  () => form.transaction_type_id,
  () => {
    if (
      form.transaction_category_id
      && !filteredCategoryOptionsForForm.value.some((option) => option.id === form.transaction_category_id)
    ) {
      form.transaction_category_id = null;
    }
  },
);

</script>

<template>
  <div class="h-full w-full overflow-hidden bg-slate-100 p-6">
    <div class="h-full w-full space-y-6">
      <section class="w-full rounded-[32px] bg-slate-950 px-6 py-8 text-white shadow-2xl shadow-slate-900/10">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <p class="text-sm uppercase tracking-[0.28em] text-slate-400">Transactions</p>
            <h1 class="mt-2 text-3xl font-semibold sm:text-4xl">Track every money movement</h1>
            <p class="mt-2 text-sm text-slate-300">Track your income and expenses on a full page.</p>
          </div>
          <button type="button" @click="openAddModal"
            class="rounded-xl border border-white/15 bg-white/10 px-5 py-3 text-sm font-medium text-white transition hover:bg-white/20">Add
            Transaction</button>
        </div>
      </section>

      <Teleport to="body">
        <div v-if="showModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 px-4 py-8"
          @click.self="closeModal">
          <div class="relative z-[101] max-h-[90vh] w-full max-w-3xl overflow-hidden rounded-2xl bg-white shadow-xl">
            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
              <div>
                <h2 class="text-xl font-semibold text-slate-900">{{ mode === 'view' ? 'Transaction Details' : mode ===
                  'edit' ? 'Edit Transaction' : 'Add Transaction' }}</h2>
                <p class="text-sm text-slate-500">{{ mode === 'view' ? 'Review the transaction details.' : mode ===
                  'edit' ? 'Update your transaction.' : 'Create a new transaction entry.' }}</p>
              </div>
              <button type="button" @click="closeModal" class="text-slate-500 hover:text-slate-900">x</button>
            </div>
            <div v-if="mode === 'view'" class="space-y-4 px-6 py-6">
              <div class="grid gap-4 md:grid-cols-2">
                <div>
                  <p class="text-sm font-medium text-slate-700">Description</p>
                  <p class="mt-2 text-slate-900">{{ selectedTransaction?.description }}</p>
                </div>
                <div>
                  <p class="text-sm font-medium text-slate-700">Amount</p>
                  <p class="mt-2 text-slate-900">{{ formatSignedAmount(selectedTransaction?.amount, selectedTransaction?.type) }}</p>
                </div>
                <div>
                  <p class="text-sm font-medium text-slate-700">Type</p>
                  <p class="mt-2 text-slate-900">{{ selectedTransaction?.type }}</p>
                </div>
                <div>
                  <p class="text-sm font-medium text-slate-700">Category</p>
                  <p class="mt-2 text-slate-900">{{ selectedTransaction?.category || '—' }}</p>
                </div>
                <div class="md:col-span-2">
                  <p class="text-sm font-medium text-slate-700">Date</p>
                  <p class="mt-2 text-slate-900">{{ formatDisplayDate(selectedTransaction?.transaction_date) }}</p>
                </div>
              </div>
              <div class="flex justify-end"><button type="button" @click="closeModal"
                  class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Close</button>
              </div>
            </div>

            <form v-else @submit.prevent="saveTransaction"
              class="max-h-[calc(90vh-88px)] overflow-y-auto px-6 py-6 space-y-6">
              <div class="grid gap-4 md:grid-cols-2">
                <div><label class="mb-2 block text-sm font-medium text-slate-700">Type</label><select
                    v-model="form.transaction_type_id"
                    class="w-full rounded-lg border border-gray-300 p-3 focus:ring-2 focus:ring-blue-500"
                    :disabled="isOptionsLoading || typeOptions.length === 0">
                    <option :value="null" disabled>Select a type</option>
                    <option v-for="option in typeOptions" :key="option.id" :value="option.id">{{ option.name }}</option>
                  </select>
                  <p v-for="error in errors.transaction_type_id" :key="error" class="text-xs text-red-600">{{ error }}
                  </p>
                </div>
                <div><label class="mb-2 block text-sm font-medium text-slate-700">Category</label><select
                    v-model="form.transaction_category_id"
                    class="w-full rounded-lg border border-gray-300 p-3 focus:ring-2 focus:ring-blue-500"
                    :disabled="isOptionsLoading">
                    <option :value="null">No category</option>
                    <option v-for="option in filteredCategoryOptionsForForm" :key="option.id" :value="option.id">{{ option.name }}
                    </option>
                  </select>
                  <p v-for="error in errors.transaction_category_id" :key="error" class="text-xs text-red-600">{{ error
                    }}</p>
                </div>
                <div><label class="mb-2 block text-sm font-medium text-slate-700">Date</label><input
                    v-model="form.transaction_date" type="date"
                    class="w-full rounded-lg border border-gray-300 p-3 focus:ring-2 focus:ring-blue-500" />
                  <p v-for="error in errors.transaction_date" :key="error" class="text-xs text-red-600">{{ error }}</p>
                </div>
                <div><label class="mb-2 block text-sm font-medium text-slate-700">Amount</label><input
                    v-model.number="form.amount" type="number" min="0" step="0.01"
                    class="w-full rounded-lg border border-gray-300 p-3 focus:ring-2 focus:ring-blue-500"
                    placeholder="0.00" />
                  <p v-for="error in errors.amount" :key="error" class="text-xs text-red-600">{{ error }}</p>
                </div>
                <div class="md:col-span-2"><label class="mb-2 block text-sm font-medium text-slate-700">Description</label><input
                    v-model="form.description" type="text"
                    class="w-full rounded-lg border border-gray-300 p-3 focus:ring-2 focus:ring-blue-500"
                    placeholder="Rent, Salary, Groceries..." />
                  <p v-for="error in errors.description" :key="error" class="text-xs text-red-600">{{ error }}</p>
                </div>
              </div>

              <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end"><button type="button"
                  @click="closeModal"
                  class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</button><button
                  type="submit" :disabled="isSubmitting || isOptionsLoading || typeOptions.length === 0"
                  class="rounded-xl bg-slate-900 px-5 py-3 text-sm font-medium text-white transition hover:bg-slate-800 disabled:opacity-60">{{
                    mode === 'edit' ? 'Save Changes' : 'Save Transaction' }}</button></div>
            </form>
          </div>
        </div>
      </Teleport>

      <div class="grid gap-6 lg:grid-cols-3">
        <section class="overflow-hidden rounded-[28px] bg-white p-6 shadow-sm ring-1 ring-slate-200/70 lg:col-span-2">
          <div class="mb-4">
            <h2 class="text-xl font-semibold text-slate-900">Recent Transactions</h2>
            <p class="text-sm text-slate-500">Track your latest finance records.</p>
            <div class="mt-4 grid gap-3 md:grid-cols-4">
              <select v-model="typeFilter" class="rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-700 focus:ring-2 focus:ring-slate-900/10">
                <option value="all">All Types</option>
                <option v-for="option in typeOptions" :key="option.id" :value="option.name">{{ option.name }}</option>
              </select>
              <select v-model="categoryFilter" class="rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-700 focus:ring-2 focus:ring-slate-900/10">
                <option value="all">All Categories</option>
                <option value="__none__">No category</option>
                <option v-for="option in categoryOptions" :key="option.id" :value="option.name">{{ option.name }}</option>
              </select>
              <input v-model="monthFilter" type="month" @change="handleMonthFilterChange" class="rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-700 focus:ring-2 focus:ring-slate-900/10" />
              <input v-model.trim="descriptionSearch" type="text" class="rounded-xl border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-900/10" placeholder="Search description..." />
            </div>
          </div>
          <div v-if="!isLoading" class="flex h-full flex-col">
            <div v-if="transactions.length === 0" class="py-10 text-center text-slate-500">No transactions yet. Add one to
              start tracking.</div>
            <div v-else-if="filteredTransactions.length === 0" class="py-10 text-center text-slate-500">No matching transactions found.</div>
            <div v-else class="flex flex-1 flex-col">
              <div class="flex-1 overflow-hidden">
                <div class="h-full overflow-x-auto">
                  <table class="min-w-full border-collapse text-left">
                    <thead class="sticky top-0 z-10 border-b border-slate-200 bg-white">
                      <tr class="text-sm text-slate-600">
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3">Category</th>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3">Description</th>
                        <th class="px-4 py-3">Amount</th>
                        <th class="w-40 px-2 py-3">Actions</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="transaction in filteredTransactions" :key="transaction.id"
                        class="border-b border-slate-200 text-sm">
                        <td class="px-4 py-3">{{ formatDisplayDate(transaction.transaction_date) }}</td>
                        <td class="px-4 py-3">{{ transaction.category || '—' }}</td>
                        <td class="px-4 py-3">{{ transaction.type }}</td>
                        <td class="px-4 py-3">{{ transaction.description }}</td>
                        <td class="px-4 py-3 font-semibold"
                          :class="transaction.type === 'income' ? 'text-emerald-600' : 'text-red-600'">{{ formatSignedAmount(transaction.amount, transaction.type) }}</td>
                        <td class="w-40 px-2 py-3">
                          <div class="flex flex-nowrap gap-1"><button type="button" @click="openViewModal(transaction)"
                              class="rounded-md border border-slate-300 bg-white px-3 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50">View</button><button
                              type="button" @click="openEditModal(transaction)"
                              class="rounded-md border border-blue-700 bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700 hover:bg-blue-100">Edit</button><button
                              type="button" @click="openConfirmDelete(transaction)"
                              class="rounded-md border border-red-700 bg-red-50 px-3 py-1 text-xs font-medium text-red-700 hover:bg-red-100">Delete</button>
                          </div>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
              <div v-if="totalPages > 1 && !hasActiveFilters" class="mt-4 flex items-center justify-between">
                <div class="text-sm text-slate-500">Showing {{ (currentPage - 1) * perPage + 1 }} to {{
                  Math.min(currentPage * perPage, totalTransactions) }} of {{ totalTransactions }} transactions</div>
                <div class="flex items-center gap-2"><button @click="prevPage" :disabled="currentPage === 1"
                    class="rounded-md border border-slate-300 bg-white px-3 py-1 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50">Previous</button><span
                    class="text-sm text-slate-700">Page {{ currentPage }} of {{ totalPages }}</span><button
                    @click="nextPage" :disabled="currentPage === totalPages"
                    class="rounded-md border border-slate-300 bg-white px-3 py-1 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50">Next</button>
                </div>
              </div>
            </div>
          </div>
        </section>

        <section class="rounded-[28px] bg-white p-6 shadow-sm ring-1 ring-slate-200/70 lg:col-span-1">
          <div class="flex items-start justify-between gap-3">
            <div>
              <h2 class="text-xl font-semibold text-slate-900">Budget Snapshot</h2>
              <p class="mt-1 text-sm text-slate-500">Track this month's budget and alerts.</p>
            </div>
            <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-medium ring-1" :class="budgetStatusClass">
              <span class="h-2 w-2 rounded-full" :class="budgetStatusDotClass"></span>
              {{ budgetStatusText }}
            </span>
          </div>

          <div v-if="!isBudgetLoading" class="mt-5 space-y-5">
            <div class="grid grid-cols-2 gap-3">
              <div class="rounded-xl bg-slate-50 p-3">
                <p class="text-xs uppercase tracking-wide text-slate-500">Total Budget</p>
                <p class="mt-1 text-base font-semibold text-slate-900">{{ formatMoney(budgetSummary.total_budget) }}</p>
              </div>
              <div class="rounded-xl bg-slate-50 p-3">
                <p class="text-xs uppercase tracking-wide text-slate-500">Total Spent</p>
                <p class="mt-1 text-base font-semibold text-slate-900">{{ formatMoney(budgetSummary.total_spent) }}</p>
              </div>
            </div>

            <div>
              <div class="mb-1 flex items-center justify-between text-xs">
                <span class="text-slate-500">Usage</span>
                <span class="font-medium text-slate-700">{{ Number(budgetUsagePct).toFixed(1) }}%</span>
              </div>
              <div class="h-2.5 rounded-full bg-slate-200">
                <div class="h-2.5 rounded-full transition-all"
                  :class="budgetStatus === 'over' ? 'bg-red-600' : budgetStatus === 'warning' ? 'bg-amber-500' : budgetStatus === 'safe' ? 'bg-emerald-500' : 'bg-slate-400'"
                  :style="{ width: `${Math.min(Math.max(budgetUsagePct, 0), 100)}%` }"></div>
              </div>
            </div>

            <div v-if="topBudgetAlerts.length">
              <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Active Alerts</p>
              <div class="space-y-2">
                <RouterLink
                  v-for="item in topBudgetAlerts"
                  :key="item.category_id"
                  :to="{ path: '/budgets', query: { month: currentMonth(), category_id: item.category_id } }"
                  class="block rounded-xl border px-3 py-2 hover:opacity-95"
                  :class="item.alert_level === 'over' ? 'border-red-200 bg-red-50 border-l-4 border-l-red-500' : 'border-amber-200 bg-amber-50 border-l-4 border-l-amber-500'">
                  <div class="flex items-center justify-between text-sm">
                    <span class="font-medium text-slate-900">{{ item.category }}</span>
                    <span :class="item.alert_level === 'over' ? 'text-red-700' : 'text-amber-700'">{{ Number(item.usage_pct).toFixed(1) }}%</span>
                  </div>
                  <p class="mt-1 text-xs text-slate-600">
                    {{ item.remaining != null && item.remaining < 0 ? `Over by ${formatMoney(Math.abs(item.remaining))}` : `Remaining ${formatMoney(item.remaining ?? 0)}` }}
                  </p>
                </RouterLink>
              </div>
            </div>
            <div v-else class="rounded-xl bg-emerald-50 p-3 text-sm text-emerald-700">No active budget alerts. Nice work.</div>

            <RouterLink :to="{ path: '/budgets', query: { month: currentMonth() } }" class="inline-flex w-full items-center justify-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
              Manage Budgets
            </RouterLink>
          </div>

          <div v-else class="mt-5 space-y-3">
            <div class="h-16 animate-pulse rounded-xl bg-slate-100"></div>
            <div class="h-16 animate-pulse rounded-xl bg-slate-100"></div>
            <div class="h-10 animate-pulse rounded-xl bg-slate-100"></div>
          </div>
        </section>
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
              <!-- <p class="mt-1 text-sm text-slate-500">Fetching your latest transaction records.</p> -->
            </div>
          </div>
        </Transition>
      </Teleport>

      <Teleport to="body">
        <div v-if="showConfirmDelete"
          class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 px-4 py-8"
          @click.self="showConfirmDelete = false">
          <div class="relative z-[101] w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-xl">
            <div class="p-6">
              <h3 class="text-xl font-semibold text-slate-900">Delete this transaction?</h3>
              <p class="mt-2 text-sm text-slate-500">This action cannot be undone. Confirm to remove this transaction
                permanently.</p>
              <div class="mt-6 flex justify-end gap-3"><button type="button" @click="showConfirmDelete = false"
                  class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</button><button
                  type="button" @click="deleteTransaction" :disabled="isDeleting"
                  class="rounded-lg bg-red-700 px-4 py-2 text-sm font-medium text-white hover:bg-red-800 disabled:opacity-60"><span
                    v-if="isDeleting"
                    class="mr-2 inline-block h-3 w-3 animate-spin rounded-full border-2 border-white border-t-transparent"></span>Delete</button>
              </div>
            </div>
          </div>
        </div>
      </Teleport>
    </div>

  </div>
</template>




