<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import axiosInstance from '../lib/axios';
import { useUserStore } from '../stores/userStore';

type SemesterModalMode = 'add' | 'edit' | 'view';

interface StudentSemester {
  id: number;
  name: string;
  start_date: string;
  end_date: string;
}

const userStore = useUserStore();
const isStudentProfile = computed(() => userStore.user?.profile_type === 'student');
const semesters = ref<StudentSemester[]>([]);
const isSemestersLoading = ref(false);
const isSemesterSaving = ref(false);
const deletingSemesterId = ref<number | null>(null);
const semesterError = ref('');
const modalMode = ref<SemesterModalMode>('add');
const showModal = ref(false);
const showConfirmDelete = ref(false);
const selectedSemester = ref<StudentSemester | null>(null);
const semesterForm = reactive({
  name: '',
  start_date: '',
  end_date: '',
});
const showLoadingOverlay = computed(
  () => isSemestersLoading.value || isSemesterSaving.value || deletingSemesterId.value !== null,
);

const loadSemesters = async () => {
  if (!isStudentProfile.value) {
    semesters.value = [];
    return;
  }

  isSemestersLoading.value = true;
  try {
    const { data } = await axiosInstance.get('/student-semesters');
    semesters.value = Array.isArray(data?.items) ? data.items : [];
  } catch (error) {
    console.error(error);
  } finally {
    isSemestersLoading.value = false;
  }
};

const resetSemesterForm = () => {
  semesterForm.name = '';
  semesterForm.start_date = '';
  semesterForm.end_date = '';
  semesterError.value = '';
};

const closeModal = () => {
  showModal.value = false;
  showConfirmDelete.value = false;
  semesterError.value = '';
};

const openAddModal = () => {
  modalMode.value = 'add';
  selectedSemester.value = null;
  resetSemesterForm();
  showModal.value = true;
};

const openViewModal = (item: StudentSemester) => {
  modalMode.value = 'view';
  selectedSemester.value = item;
  showModal.value = true;
};

const openEditModal = (item: StudentSemester) => {
  modalMode.value = 'edit';
  selectedSemester.value = item;
  semesterForm.name = item.name;
  semesterForm.start_date = item.start_date;
  semesterForm.end_date = item.end_date;
  semesterError.value = '';
  showModal.value = true;
};

const openDeleteConfirm = (item: StudentSemester) => {
  selectedSemester.value = item;
  showConfirmDelete.value = true;
};

const saveSemester = async () => {
  semesterError.value = '';
  if (!semesterForm.name.trim() || !semesterForm.start_date || !semesterForm.end_date) {
    semesterError.value = 'Please fill semester name, start date, and end date.';
    return;
  }

  isSemesterSaving.value = true;
  try {
    const payload = {
      name: semesterForm.name.trim(),
      start_date: semesterForm.start_date,
      end_date: semesterForm.end_date,
    };

    if (modalMode.value === 'edit' && selectedSemester.value?.id) {
      await axiosInstance.patch(`/student-semesters/${selectedSemester.value.id}`, payload);
    } else {
      await axiosInstance.post('/student-semesters', payload);
    }

    await loadSemesters();
    closeModal();
    resetSemesterForm();
  } catch (error: any) {
    console.error(error);
    semesterError.value = error?.response?.data?.message || 'Unable to save semester.';
  } finally {
    isSemesterSaving.value = false;
  }
};

const removeSemester = async () => {
  if (!selectedSemester.value?.id) return;
  deletingSemesterId.value = selectedSemester.value.id;
  try {
    await axiosInstance.delete(`/student-semesters/${selectedSemester.value.id}`);
    await loadSemesters();
    closeModal();
    selectedSemester.value = null;
  } catch (error) {
    console.error(error);
  } finally {
    deletingSemesterId.value = null;
  }
};

onMounted(() => {
  loadSemesters();
});
</script>

<template>
  <div class="min-h-screen bg-slate-100 p-6">
    <div class="space-y-6">
      <section class="rounded-[32px] bg-slate-950 px-6 py-8 text-white shadow-2xl shadow-slate-900/10">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <p class="text-sm uppercase tracking-[0.3em] text-slate-400">Settings</p>
            <h1 class="mt-2 text-3xl font-semibold sm:text-4xl">Manage Semester</h1>
            <p class="mt-2 text-sm text-slate-300">Create and maintain your semester date ranges manually.</p>
          </div>
          <button
            v-if="isStudentProfile"
            type="button"
            @click="openAddModal"
            class="rounded-xl border border-white/15 bg-white/10 px-5 py-3 text-sm font-medium text-white transition hover:bg-white/20"
          >
            Add Semester
          </button>
        </div>
      </section>

      <section class="rounded-[28px] bg-white p-6 shadow-sm ring-1 ring-slate-200/70">
        <div v-if="!isStudentProfile" class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
          Semester management is available for Student profile only.
        </div>
        <template v-else>
          <div v-if="!isSemestersLoading && !semesters.length" class="text-sm text-slate-500">
            No semester record yet. Click Add Semester to create one.
          </div>
          <div v-else class="overflow-x-auto">
            <table class="min-w-full border-collapse text-left">
              <thead class="border-b border-slate-200">
                <tr class="text-sm text-slate-600">
                  <th class="px-4 py-3 font-medium">Name</th>
                  <th class="px-4 py-3 font-medium">Start Date</th>
                  <th class="px-4 py-3 font-medium">End Date</th>
                  <th class="w-44 px-4 py-3 font-medium">Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in semesters" :key="item.id" class="border-b border-slate-100 text-sm">
                  <td class="px-4 py-3 font-medium text-slate-900">{{ item.name }}</td>
                  <td class="px-4 py-3 text-slate-700">{{ item.start_date }}</td>
                  <td class="px-4 py-3 text-slate-700">{{ item.end_date }}</td>
                  <td class="w-44 px-4 py-3">
                    <div class="flex gap-2">
                      <button type="button" @click="openViewModal(item)" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                        View
                      </button>
                      <button type="button" @click="openEditModal(item)" class="rounded-lg border border-blue-300 px-3 py-1.5 text-xs font-medium text-blue-700 hover:bg-blue-50">
                        Edit
                      </button>
                      <button type="button" @click="openDeleteConfirm(item)" class="rounded-lg border border-red-300 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50">
                        Delete
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </template>
      </section>
    </div>

    <Teleport to="body">
      <Transition name="modal-popup">
        <div v-if="showModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 px-4 py-8" @click.self="closeModal">
          <div class="modal-panel-animate relative z-[101] w-full max-w-2xl overflow-hidden rounded-2xl bg-white shadow-xl">
            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
              <div>
                <h2 class="text-xl font-semibold text-slate-900">{{ modalMode === 'add' ? 'Add Semester' : modalMode === 'edit' ? 'Edit Semester' : 'Semester Details' }}</h2>
                <p class="text-sm text-slate-500">{{ modalMode === 'view' ? 'Review semester details.' : 'Fill details for the semester period.' }}</p>
              </div>
              <button type="button" @click="closeModal" class="text-slate-500 hover:text-slate-900">x</button>
            </div>

            <div v-if="modalMode === 'view'" class="space-y-4 px-6 py-6">
              <div class="grid gap-4 md:grid-cols-2">
                <div>
                  <p class="text-sm font-medium text-slate-700">Name</p>
                  <p class="mt-2 text-slate-900">{{ selectedSemester?.name }}</p>
                </div>
                <div>
                  <p class="text-sm font-medium text-slate-700">Start Date</p>
                  <p class="mt-2 text-slate-900">{{ selectedSemester?.start_date }}</p>
                </div>
                <div class="md:col-span-2">
                  <p class="text-sm font-medium text-slate-700">End Date</p>
                  <p class="mt-2 text-slate-900">{{ selectedSemester?.end_date }}</p>
                </div>
              </div>
              <div class="flex justify-end">
                <button type="button" @click="closeModal" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Close</button>
              </div>
            </div>

            <form v-else @submit.prevent="saveSemester" class="space-y-4 px-6 py-6">
              <div>
                <label class="mb-2 block text-sm font-medium text-slate-700">Semester Name</label>
                <input v-model="semesterForm.name" type="text" class="w-full rounded-lg border border-slate-300 p-3 text-sm focus:ring-2 focus:ring-blue-500" placeholder="Semester name" />
              </div>
              <div class="grid gap-4 md:grid-cols-2">
                <div>
                  <label class="mb-2 block text-sm font-medium text-slate-700">Start Date</label>
                  <input v-model="semesterForm.start_date" type="date" class="w-full rounded-lg border border-slate-300 p-3 text-sm focus:ring-2 focus:ring-blue-500" />
                </div>
                <div>
                  <label class="mb-2 block text-sm font-medium text-slate-700">End Date</label>
                  <input v-model="semesterForm.end_date" type="date" class="w-full rounded-lg border border-slate-300 p-3 text-sm focus:ring-2 focus:ring-blue-500" />
                </div>
              </div>
              <p v-if="semesterError" class="text-sm text-red-600">{{ semesterError }}</p>
              <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <button type="button" @click="closeModal" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                  Cancel
                </button>
                <button type="submit" :disabled="isSemesterSaving" class="rounded-xl bg-slate-900 px-5 py-3 text-sm font-medium text-white transition hover:bg-slate-800 disabled:opacity-60">
                  {{ isSemesterSaving ? 'Saving...' : (modalMode === 'edit' ? 'Save Changes' : 'Save Semester') }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </Transition>
    </Teleport>

    <Teleport to="body">
      <Transition name="modal-popup">
        <div v-if="showConfirmDelete" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 px-4 py-8" @click.self="closeModal">
          <div class="modal-panel-animate relative z-[101] w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-xl">
            <div class="p-6">
              <h3 class="text-xl font-semibold text-slate-900">Delete this semester?</h3>
              <p class="mt-2 text-sm text-slate-500">This action cannot be undone.</p>
              <div class="mt-6 flex justify-end gap-3">
                <button type="button" @click="closeModal" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                  Cancel
                </button>
                <button type="button" @click="removeSemester" :disabled="deletingSemesterId !== null" class="rounded-lg bg-red-700 px-4 py-2 text-sm font-medium text-white hover:bg-red-800 disabled:opacity-60">
                  {{ deletingSemesterId !== null ? 'Deleting...' : 'Delete' }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <Teleport to="body">
      <Transition name="loading-fade">
        <div v-if="showLoadingOverlay" class="fixed inset-0 z-[120] flex items-center justify-center bg-black/55 px-4">
          <div class="flex w-full max-w-xs flex-col items-center rounded-2xl bg-white px-6 py-7 text-center shadow-2xl">
            <div class="relative mb-4 h-12 w-12">
              <span class="absolute inset-0 rounded-full border-4 border-slate-200"></span>
              <span class="absolute inset-0 animate-spin rounded-full border-4 border-transparent border-t-cyan-500 border-r-blue-600"></span>
            </div>
            <p class="text-lg font-semibold text-slate-900">Loading ...</p>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>
