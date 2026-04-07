<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import axiosInstance from '../lib/axios';

interface ManagedOption {
  id: number;
  name: string;
}

const typeOptions = ref<ManagedOption[]>([]);
const categoryOptions = ref<ManagedOption[]>([]);
const newTypeName = ref('');
const newCategoryName = ref('');
const optionErrors = reactive({
  type: '',
  category: '',
});
const isLoading = ref(false);
const isTypeSubmitting = ref(false);
const isCategorySubmitting = ref(false);
const deletingTypeId = ref<number | null>(null);
const deletingCategoryId = ref<number | null>(null);

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
    await axiosInstance.post('/transaction-options/categories', { name });
    newCategoryName.value = '';
    await loadOptions();
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
  } catch (error: any) {
    optionErrors.category = error?.response?.data?.message || 'Unable to delete category.';
  } finally {
    deletingCategoryId.value = null;
  }
};

onMounted(() => {
  loadOptions();
});
</script>

<template>
  <div class="h-full w-full bg-slate-100 p-6">
    <div class="space-y-6">
      <section class="rounded-lg bg-white p-6 shadow">
        <h1 class="text-3xl font-semibold text-slate-900">Manage Transaction Options</h1>
        <p class="mt-2 text-sm text-slate-500">Create the type and category lists that appear in your add transaction form.</p>
      </section>

      <div v-if="!isLoading" class="grid gap-6 lg:grid-cols-2">
        <section class="rounded-lg bg-white p-6 shadow">
          <h2 class="text-xl font-semibold text-slate-900">Types</h2>
          <p class="mt-1 text-sm text-slate-500">Examples: Income, Expense, Transfer.</p>
          <div class="mt-4 flex gap-2">
            <input
              v-model="newTypeName"
              type="text"
              class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500"
              placeholder="Add a new type"
            />
            <button
              type="button"
              @click="createType"
              :disabled="isTypeSubmitting"
              class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-60"
            >
              Add
            </button>
          </div>
          <p v-if="optionErrors.type" class="mt-2 text-xs text-red-600">{{ optionErrors.type }}</p>
          <div class="mt-6 flex flex-wrap gap-2">
            <div
              v-for="option in typeOptions"
              :key="option.id"
              class="inline-flex items-center gap-2 rounded-full bg-slate-50 px-3 py-2 text-sm text-slate-700 ring-1 ring-slate-200"
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
        </section>

        <section class="rounded-lg bg-white p-6 shadow">
          <h2 class="text-xl font-semibold text-slate-900">Categories</h2>
          <p class="mt-1 text-sm text-slate-500">Examples: Food, Transport, Salary.</p>
          <div class="mt-4 flex gap-2">
            <input
              v-model="newCategoryName"
              type="text"
              class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500"
              placeholder="Add a new category"
            />
            <button
              type="button"
              @click="createCategory"
              :disabled="isCategorySubmitting"
              class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-60"
            >
              Add
            </button>
          </div>
          <p v-if="optionErrors.category" class="mt-2 text-xs text-red-600">{{ optionErrors.category }}</p>
          <div v-if="categoryOptions.length" class="mt-6 flex flex-wrap gap-2">
            <div
              v-for="option in categoryOptions"
              :key="option.id"
              class="inline-flex items-center gap-2 rounded-full bg-slate-50 px-3 py-2 text-sm text-slate-700 ring-1 ring-slate-200"
            >
              <span>{{ option.name }}</span>
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
          <p v-else class="mt-6 text-sm text-slate-500">No categories yet. Add one above.</p>
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
