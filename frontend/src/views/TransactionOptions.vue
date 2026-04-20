<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import axiosInstance from '../lib/axios';
import { useToast } from '../composables/useToast.js';

interface ManagedOption {
  id: number;
  name: string;
  applies_to?: 'income' | 'expense' | 'both';
}

const typeOptions = ref<ManagedOption[]>([]);
const categoryOptions = ref<ManagedOption[]>([]);
const newTypeName = ref('');
const newCategoryName = ref('');
const newCategoryAppliesTo = ref<'income' | 'expense' | 'both'>('both');
const optionErrors = reactive({
  type: '',
  category: '',
});
const isLoading = ref(false);
const isTypeSubmitting = ref(false);
const isCategorySubmitting = ref(false);
const deletingTypeId = ref<number | null>(null);
const deletingCategoryId = ref<number | null>(null);
const toast = useToast();

const loadOptions = async () => {
  isLoading.value = true;
  try {
    const { data } = await axiosInstance.get('/transaction-options');
    typeOptions.value = data.types || [];
    categoryOptions.value = data.categories || [];
  } catch (error) {
    console.error(error);
  } finally {
    isLoading.value = false;
  }
};

const createType = async () => {
  const name = newTypeName.value.trim();
  optionErrors.type = '';

  if (!name) {
    optionErrors.type = 'Type name is required.';
    return;
  }

  isTypeSubmitting.value = true;

  try {
    await axiosInstance.post('/transaction-options/types', { name });
    newTypeName.value = '';
    await loadOptions();
    toast.show('Type added successfully.', 'success');
  } catch (error: any) {
    optionErrors.type = error?.response?.data?.message || error?.response?.data?.errors?.name?.[0] || 'Unable to save type.';
  } finally {
    isTypeSubmitting.value = false;
  }
};

const createCategory = async () => {
  const name = newCategoryName.value.trim();
  optionErrors.category = '';

  if (!name) {
    optionErrors.category = 'Category name is required.';
    return;
  }

  isCategorySubmitting.value = true;

  try {
    await axiosInstance.post('/transaction-options/categories', {
      name,
      applies_to: newCategoryAppliesTo.value,
    });
    newCategoryName.value = '';
    newCategoryAppliesTo.value = 'both';
    await loadOptions();
    toast.show('Category added successfully.', 'success');
  } catch (error: any) {
    optionErrors.category = error?.response?.data?.message || error?.response?.data?.errors?.name?.[0] || 'Unable to save category.';
  } finally {
    isCategorySubmitting.value = false;
  }
};

const removeType = async (option: ManagedOption) => {
  optionErrors.type = '';
  deletingTypeId.value = option.id;

  try {
    await axiosInstance.delete(`/transaction-options/types/${option.id}`);
    await loadOptions();
    toast.show('Type deleted successfully.', 'danger');
  } catch (error: any) {
    optionErrors.type = error?.response?.data?.message || 'Unable to delete type.';
  } finally {
    deletingTypeId.value = null;
  }
};

const removeCategory = async (option: ManagedOption) => {
  optionErrors.category = '';
  deletingCategoryId.value = option.id;

  try {
    await axiosInstance.delete(`/transaction-options/categories/${option.id}`);
    await loadOptions();
    toast.show('Category deleted successfully.', 'danger');
  } catch (error: any) {
    optionErrors.category = error?.response?.data?.message || 'Unable to delete category.';
  } finally {
    deletingCategoryId.value = null;
  }
};

onMounted(() => {
  loadOptions();
});

const categoryScopeLabel = (value: ManagedOption['applies_to']) => {
  if (value === 'income') return 'Income';
  if (value === 'expense') return 'Expense';
  return 'Both';
};

const categoryScopeClass = (value: ManagedOption['applies_to']) => {
  if (value === 'income') return 'bg-emerald-100 text-emerald-700';
  if (value === 'expense') return 'bg-rose-100 text-rose-700';
  return 'bg-slate-200 text-slate-700';
};
</script>

<template>
  <div class="h-full w-full overflow-hidden bg-slate-100 p-6">
    <div class="h-full w-full space-y-6">
      <section class="rounded-[32px] bg-slate-950 px-6 py-8 text-white shadow-2xl shadow-slate-900/10">
        <p class="text-sm uppercase tracking-[0.28em] text-slate-400">Manage Options</p>
        <h1 class="mt-2 text-3xl font-semibold sm:text-4xl">Configure transaction dictionaries</h1>
        <p class="mt-2 text-sm text-slate-300">Create the type and category lists that appear in your add transaction form.</p>
      </section>

      <div class="grid gap-6 lg:grid-cols-2">
        <section class="rounded-[28px] bg-white p-6 shadow-sm ring-1 ring-slate-200/70">
          <h2 class="text-xl font-semibold text-slate-900">Types</h2>
          <p class="mt-1 text-sm text-slate-500">Examples: Income, Expense, Transfer.</p>
          <div class="mt-4 flex gap-2">
            <input
              v-model="newTypeName"
              type="text"
              class="flex-1 rounded-xl border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-900/10"
              placeholder="Add a new type"
            />
            <button
              type="button"
              @click="createType"
              :disabled="isTypeSubmitting"
              class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800 disabled:opacity-60"
            >
              Add
            </button>
          </div>
          <p v-if="optionErrors.type" class="mt-2 text-xs text-red-600">{{ optionErrors.type }}</p>
          <div v-if="typeOptions.length" class="mt-6 flex flex-wrap gap-2">
            <div
              v-for="option in typeOptions"
              :key="option.id"
              class="inline-flex items-center gap-2 rounded-full bg-slate-50 px-3 py-2 text-sm text-slate-700 ring-1 ring-slate-200 transition hover:bg-slate-100"
            >
              <span>{{ option.name }}</span>
              <button
                type="button"
                @click="removeType(option)"
                :disabled="deletingTypeId === option.id"
                class="text-slate-400 hover:text-red-600 disabled:opacity-60"
              >
                x
              </button>
            </div>
          </div>
          <p v-else-if="!isLoading" class="mt-6 text-sm text-slate-500">No types yet. Add one above.</p>
        </section>

        <section class="rounded-[28px] bg-white p-6 shadow-sm ring-1 ring-slate-200/70">
          <h2 class="text-xl font-semibold text-slate-900">Categories</h2>
          <p class="mt-1 text-sm text-slate-500">Examples: Food, Transport, Salary.</p>
          <div class="mt-4 flex flex-col gap-2 sm:flex-row">
            <input
              v-model="newCategoryName"
              type="text"
              class="flex-1 rounded-xl border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-900/10"
              placeholder="Add a new category"
            />
            <select
              v-model="newCategoryAppliesTo"
              class="rounded-xl border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-900/10"
            >
              <option value="both">Both</option>
              <option value="expense">Expense only</option>
              <option value="income">Income only</option>
            </select>
            <button
              type="button"
              @click="createCategory"
              :disabled="isCategorySubmitting"
              class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800 disabled:opacity-60"
            >
              Add
            </button>
          </div>
          <p v-if="optionErrors.category" class="mt-2 text-xs text-red-600">{{ optionErrors.category }}</p>
          <div v-if="categoryOptions.length" class="mt-6 flex flex-wrap gap-2">
            <div
              v-for="option in categoryOptions"
              :key="option.id"
              class="inline-flex items-center gap-2 rounded-full bg-slate-50 px-3 py-2 text-sm text-slate-700 ring-1 ring-slate-200 transition hover:bg-slate-100"
            >
              <span>{{ option.name }}</span>
              <span
                class="rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide"
                :class="categoryScopeClass(option.applies_to)"
              >
                {{ categoryScopeLabel(option.applies_to) }}
              </span>
              <button
                type="button"
                @click="removeCategory(option)"
                :disabled="deletingCategoryId === option.id"
                class="text-slate-400 hover:text-red-600 disabled:opacity-60"
              >
                x
              </button>
            </div>
          </div>
          <p v-else-if="!isLoading" class="mt-6 text-sm text-slate-500">No categories yet. Add one above.</p>
        </section>
      </div>

      <Teleport to="body">
        <div
          v-if="isLoading"
          class="fixed inset-0 z-[120] flex items-center justify-center bg-black/55 px-4"
        >
          <div class="flex w-full max-w-xs flex-col items-center rounded-2xl bg-white px-6 py-7 text-center shadow-2xl">
            <span class="mb-4 h-12 w-12 animate-spin rounded-full border-4 border-slate-900 border-t-transparent"></span>
            <p class="text-lg font-semibold text-slate-900">Loading ...</p>
            <!-- <p class="mt-1 text-sm text-slate-500">Fetching your data.</p> -->
          </div>
        </div>
      </Teleport>

    </div>
  </div>
</template>

