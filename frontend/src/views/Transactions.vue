<script setup lang="ts">
import { reactive, ref, onMounted } from 'vue';
import axiosInstance from '../lib/axios';

interface TransactionForm {
    description: string;
    amount: number | null;
    type: 'income' | 'expense';
    category: string;
    transaction_date: string;
}

const form = reactive<TransactionForm>({
    description: '',
    amount: null,
    type: 'expense',
    category: '',
    transaction_date: new Date().toISOString().slice(0, 10),
});

const errors = reactive<Record<string, string[]>>({
    description: [],
    amount: [],
    type: [],
    category: [],
    transaction_date: [],
});

const transactions = ref<Array<Record<string, any>>>([]);
const isLoading = ref(false);
const isSubmitting = ref(false);
const isDeleting = ref(false);
const showModal = ref(false);
const showConfirmDelete = ref(false);
const mode = ref<'add' | 'edit' | 'view'>('add');
const selectedTransaction = ref<Record<string, any> | null>(null);
const currentPage = ref(1);
const perPage = ref(10);
const totalPages = ref(1);
const totalTransactions = ref(0);

const resetForm = () => {
    form.description = '';
    form.amount = null;
    form.type = 'expense';
    form.category = '';
    form.transaction_date = new Date().toISOString().slice(0, 10);
    Object.keys(errors).forEach((key) => {
        errors[key] = [];
    });
};

const loadTransactions = async (page = 1) => {
    isLoading.value = true;
    try {
        const response = await axiosInstance.get('/transactions', {
            params: {
                page: page,
                per_page: perPage.value,
            },
        });
        transactions.value = response.data.data;
        totalPages.value = response.data.last_page;
        totalTransactions.value = response.data.total;
        currentPage.value = response.data.current_page;
    } catch (error) {
        console.error(error);
    } finally {
        isLoading.value = false;
    }
};

const openAddModal = () => {
    mode.value = 'add';
    selectedTransaction.value = null;
    resetForm();
    showModal.value = true;
};

const openViewModal = (transaction: Record<string, any>) => {
    mode.value = 'view';
    selectedTransaction.value = transaction;
    showModal.value = true;
};

const openEditModal = (transaction: Record<string, any>) => {
    mode.value = 'edit';
    selectedTransaction.value = transaction;
    form.description = transaction.description || '';
    form.amount = transaction.amount != null ? Number(transaction.amount) : null;
    form.type = transaction.type || 'expense';
    form.category = transaction.category || '';
    form.transaction_date = transaction.transaction_date || new Date().toISOString().slice(0, 10);
    Object.keys(errors).forEach((key) => {
        errors[key] = [];
    });
    showModal.value = true;
};

const openConfirmDelete = (transaction: Record<string, any>) => {
    selectedTransaction.value = transaction;
    showConfirmDelete.value = true;
};

const closeModal = () => {
    showModal.value = false;
    showConfirmDelete.value = false;
};

const saveTransaction = async () => {
    if (mode.value === 'view') {
        closeModal();
        return;
    }

    isSubmitting.value = true;
    Object.keys(errors).forEach((key) => {
        errors[key] = [];
    });

    try {
        if (mode.value === 'edit' && selectedTransaction.value?.id) {
            await axiosInstance.patch(`/transactions/${selectedTransaction.value.id}`, {
                description: form.description,
                amount: form.amount,
                type: form.type,
                category: form.category,
                transaction_date: form.transaction_date,
            });
        } else {
            await axiosInstance.post('/transactions', {
                description: form.description,
                amount: form.amount,
                type: form.type,
                category: form.category,
                transaction_date: form.transaction_date,
            });
        }

        await loadTransactions();
        resetForm();
        showModal.value = false;
    } catch (error: any) {
        if (error?.response?.status === 422) {
            const responseErrors = error.response.data.errors;
            Object.keys(responseErrors).forEach((key) => {
                errors[key] = responseErrors[key] || [];
            });
        } else {
            console.error(error);
        }
    } finally {
        isSubmitting.value = false;
    }
};

const deleteTransaction = async () => {
    if (!selectedTransaction.value?.id) {
        return;
    }

    isDeleting.value = true;

    try {
        await axiosInstance.delete(`/transactions/${selectedTransaction.value.id}`);
        await loadTransactions(currentPage.value);
        showConfirmDelete.value = false;
        selectedTransaction.value = null;
    } catch (error) {
        console.error(error);
    } finally {
        isDeleting.value = false;
    }
};

const goToPage = (page: number) => {
    if (page >= 1 && page <= totalPages.value) {
        loadTransactions(page);
    }
};

const nextPage = () => {
    if (currentPage.value < totalPages.value) {
        goToPage(currentPage.value + 1);
    }
};

const prevPage = () => {
    if (currentPage.value > 1) {
        goToPage(currentPage.value - 1);
    }
};

onMounted(() => loadTransactions());
</script>

<template>
    <div class="h-full w-full bg-slate-100 p-6 overflow-hidden">
        <div class="h-full space-y-6 w-full">
            <section class="rounded-lg bg-white p-6 shadow w-full flex-shrink-0">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 class="text-3xl font-semibold text-slate-900">Transactions</h1>
                        <p class="text-sm text-slate-500">Track your income and expenses on a full page.</p>
                    </div>
                    <button type="button" @click="openAddModal"
                        class="inline-flex items-center justify-center rounded-lg bg-blue-700 px-5 py-3 text-sm font-medium text-white transition hover:bg-blue-800 focus:outline-none focus:ring-0">
                        Add Transaction
                    </button>
                </div>
            </section>

            <Teleport to="body">
                <div v-if="showModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 px-4 py-8"
                    @click.self="closeModal">
                    <div class="relative z-[101] w-full max-w-2xl overflow-hidden rounded-2xl bg-white shadow-xl">
                    <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                        <div>
                            <h2 class="text-xl font-semibold text-slate-900">
                                {{ mode === 'view' ? 'Transaction Details' : mode === 'edit' ? 'Edit Transaction' : 'Add Transaction' }}
                            </h2>
                            <p class="text-sm text-slate-500">
                                {{ mode === 'view' ? 'Review the transaction details.' : mode === 'edit' ? 'Update your transaction.' : 'Create a new income or expense entry.' }}
                            </p>
                        </div>
                        <button type="button" @click="closeModal" class="text-slate-500 hover:text-slate-900">
                            ✕
                        </button>
                    </div>

                    <div v-if="mode === 'view'">
                        <div class="space-y-4 px-6 py-6">
                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <p class="text-sm font-medium text-slate-700">Description</p>
                                    <p class="mt-2 text-slate-900">{{ selectedTransaction?.description }}</p>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-slate-700">Amount</p>
                                    <p class="mt-2 text-slate-900">
                                        {{ selectedTransaction?.type === 'income' ? '+' : '-' }}{{
                                            Number(selectedTransaction?.amount || 0).toFixed(2) }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-slate-700">Type</p>
                                    <p class="mt-2 capitalize text-slate-900">{{ selectedTransaction?.type }}</p>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-slate-700">Category</p>
                                    <p class="mt-2 text-slate-900">{{ selectedTransaction?.category || '—' }}</p>
                                </div>
                                <div class="md:col-span-2">
                                    <p class="text-sm font-medium text-slate-700">Date</p>
                                    <p class="mt-2 text-slate-900">{{ selectedTransaction?.transaction_date }}</p>
                                </div>
                            </div>
                            <div class="flex justify-end">
                                <button type="button" @click="closeModal"
                                    class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                                    Close
                                </button>
                            </div>
                        </div>
                    </div>

                    <form v-else @submit.prevent="saveTransaction" class="space-y-4 px-6 py-6">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="block mb-2 text-sm font-medium text-slate-700">Description</label>
                                <input v-model="form.description" type="text"
                                    class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                    placeholder="Rent, Salary, Groceries..." />
                                <template v-if="errors.description?.length">
                                    <p v-for="error in errors.description" :key="error" class="text-xs text-red-600">{{
                                        error }}</p>
                                </template>
                            </div>

                            <div>
                                <label class="block mb-2 text-sm font-medium text-slate-700">Amount</label>
                                <input v-model.number="form.amount" type="number" min="0" step="0.01"
                                    class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                    placeholder="0.00" />
                                <template v-if="errors.amount?.length">
                                    <p v-for="error in errors.amount" :key="error" class="text-xs text-red-600">{{ error
                                        }}</p>
                                </template>
                            </div>

                            <div>
                                <label class="block mb-2 text-sm font-medium text-slate-700">Type</label>
                                <select v-model="form.type"
                                    class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                    <option value="expense">Expense</option>
                                    <option value="income">Income</option>
                                </select>
                                <template v-if="errors.type?.length">
                                    <p v-for="error in errors.type" :key="error" class="text-xs text-red-600">{{ error
                                        }}</p>
                                </template>
                            </div>

                            <div>
                                <label class="block mb-2 text-sm font-medium text-slate-700">Category</label>
                                <input v-model="form.category" type="text"
                                    class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                    placeholder="Food, Transport, Salary..." />
                                <template v-if="errors.category?.length">
                                    <p v-for="error in errors.category" :key="error" class="text-xs text-red-600">{{
                                        error }}</p>
                                </template>
                            </div>

                            <div>
                                <label class="block mb-2 text-sm font-medium text-slate-700">Date</label>
                                <input v-model="form.transaction_date" type="date"
                                    class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" />
                                <template v-if="errors.transaction_date?.length">
                                    <p v-for="error in errors.transaction_date" :key="error"
                                        class="text-xs text-red-600">{{ error }}</p>
                                </template>
                            </div>
                        </div>

                        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                            <button type="button" @click="closeModal"
                                class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                                Cancel
                            </button>
                            <button type="submit"
                                class="inline-flex items-center justify-center rounded-lg bg-blue-700 px-5 py-3 text-sm font-medium text-white hover:bg-blue-800">
                                {{ mode === 'edit' ? 'Save Changes' : 'Save Transaction' }}
                            </button>
                        </div>
                    </form>
                    </div>
                </div>
            </Teleport>

            <section class="bg-white rounded-lg shadow p-6 flex-1 overflow-hidden">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="text-xl font-semibold text-slate-900">Recent Transactions</h2>
                        <p class="text-sm text-slate-500">Track your latest finance records.</p>
                    </div>
                </div>

                <div v-if="isLoading" class="py-10 text-center text-slate-500">Loading transactions...</div>

                <div v-else class="h-full flex flex-col">
                    <div v-if="transactions.length === 0" class="py-10 text-center text-slate-500">
                        No transactions yet. Add one to start tracking.
                    </div>

                    <div v-else class="flex-1 flex flex-col">
                        <div class="flex-1 overflow-hidden">
                            <div class="h-full overflow-x-auto">
                                <table class="min-w-full text-left border-collapse">
                                    <thead class="sticky top-0 z-10 bg-white border-b border-slate-200">
                                        <tr class="text-sm text-slate-600">
                                            <th class="px-4 py-3">Date</th>
                                            <th class="px-4 py-3">Description</th>
                                            <th class="px-4 py-3">Category</th>
                                            <th class="px-4 py-3">Type</th>
                                            <th class="px-4 py-3">Amount</th>
                                            <th class="px-4 py-3">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="transaction in transactions" :key="transaction.id"
                                            class="border-b border-slate-200 text-sm">
                                            <td class="px-4 py-3">{{ transaction.transaction_date }}</td>
                                            <td class="px-4 py-3">{{ transaction.description }}</td>
                                            <td class="px-4 py-3">{{ transaction.category || '—' }}</td>
                                            <td class="px-4 py-3 capitalize">{{ transaction.type }}</td>
                                            <td class="px-4 py-3 font-semibold"
                                                :class="transaction.type === 'income' ? 'text-emerald-600' : 'text-red-600'">
                                                {{ transaction.type === 'income' ? '+' : '-' }}{{
                                                Number(transaction.amount).toFixed(2) }}
                                            </td>
                                            <td class="px-4 py-3">
                                                <div class="flex flex-wrap gap-2">
                                                    <button type="button" @click="openViewModal(transaction)"
                                                        class="rounded-md border border-slate-300 bg-white px-3 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50">
                                                        View
                                                    </button>
                                                    <button type="button" @click="openEditModal(transaction)"
                                                        class="rounded-md border border-blue-700 bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700 hover:bg-blue-100">
                                                        Edit
                                                    </button>
                                                    <button type="button" @click="openConfirmDelete(transaction)"
                                                        class="rounded-md border border-red-700 bg-red-50 px-3 py-1 text-xs font-medium text-red-700 hover:bg-red-100">
                                                        Delete
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Pagination -->
                        <div v-if="totalPages > 1" class="mt-4 flex items-center justify-between flex-shrink-0">
                            <div class="text-sm text-slate-500">
                                Showing {{ (currentPage - 1) * perPage + 1 }} to {{ Math.min(currentPage * perPage,
                                totalTransactions) }} of {{ totalTransactions }} transactions
                            </div>
                            <div class="flex items-center gap-2">
                                <button @click="prevPage" :disabled="currentPage === 1"
                                    class="rounded-md border border-slate-300 bg-white px-3 py-1 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50">
                                    Previous
                                </button>
                                <span class="text-sm text-slate-700">
                                    Page {{ currentPage }} of {{ totalPages }}
                                </span>
                                <button @click="nextPage" :disabled="currentPage === totalPages"
                                    class="rounded-md border border-slate-300 bg-white px-3 py-1 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50">
                                    Next
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <Teleport to="body">
                <div v-if="showConfirmDelete"
                    class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 px-4 py-8"
                    @click.self="showConfirmDelete = false">
                    <div class="relative z-[101] w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-xl">
                        <div class="p-6">
                            <h3 class="text-xl font-semibold text-slate-900">Delete this transaction?</h3>
                            <p class="mt-2 text-sm text-slate-500">This action cannot be undone. Confirm to remove this
                                transaction
                                permanently.</p>
                            <div class="mt-6 flex justify-end gap-3">
                                <button type="button" @click="showConfirmDelete = false"
                                    class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                                    Cancel
                                </button>
                                <button type="button" @click="deleteTransaction" :disabled="isDeleting"
                                    class="inline-flex items-center justify-center rounded-lg bg-red-700 px-4 py-2 text-sm font-medium text-white hover:bg-red-800 disabled:cursor-not-allowed disabled:opacity-60">
                                    <span v-if="isDeleting"
                                        class="mr-2 h-3 w-3 rounded-full border-2 border-white border-t-transparent animate-spin"></span>
                                    Delete
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </Teleport>

        </div>

        <Teleport to="body">
            <div v-if="isSubmitting" class="fixed inset-0 z-[110] flex items-center justify-center bg-black/50">
                <div class="relative z-[111] flex flex-col items-center p-6 bg-white rounded-lg shadow-xl">
                    <span
                        class="w-12 h-12 mb-4 border-4 border-blue-500 border-t-transparent rounded-full animate-spin"></span>
                    <p class="text-lg font-medium text-slate-900">Saving transaction...</p>
                </div>
            </div>
        </Teleport>
    </div>
</template>
