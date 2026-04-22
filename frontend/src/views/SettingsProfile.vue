<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import { useUserStore } from '../stores/userStore';

const userStore = useUserStore();
const profileForm = reactive({
  name: userStore.user?.name || '',
  email: userStore.user?.email || '',
  profile_type: (userStore.user?.profile_type || 'general') as 'student' | 'general',
  preferred_period: (userStore.user?.preferred_period || 'monthly') as 'monthly' | 'semester',
});
const isSavingProfile = ref(false);
const profileError = ref('');
const showLoadingOverlay = computed(() => isSavingProfile.value);

const saveProfile = async () => {
  profileError.value = '';
  isSavingProfile.value = true;

  try {
    if (profileForm.profile_type === 'general') {
      profileForm.preferred_period = 'monthly';
    }

    await userStore.updatePreferences({
      name: profileForm.name.trim(),
      profile_type: profileForm.profile_type,
      preferred_period: profileForm.preferred_period,
    });
  } catch (error: any) {
    console.error(error);
    profileError.value = error?.response?.data?.message || 'Unable to update profile.';
  } finally {
    isSavingProfile.value = false;
  }
};

watch(
  () => userStore.user,
  (nextUser) => {
    profileForm.name = nextUser?.name || '';
    profileForm.email = nextUser?.email || '';
    profileForm.profile_type = (nextUser?.profile_type || 'general') as 'student' | 'general';
    profileForm.preferred_period = (nextUser?.preferred_period || 'monthly') as 'monthly' | 'semester';
  },
  { immediate: true, deep: true },
);

watch(
  () => profileForm.profile_type,
  (type) => {
    if (type === 'general') {
      profileForm.preferred_period = 'monthly';
    }
  },
);
</script>

<template>
  <div class="min-h-screen bg-slate-100 p-6">
    <div class="space-y-6">
      <section class="rounded-[32px] bg-slate-950 px-6 py-8 text-white shadow-2xl shadow-slate-900/10">
        <p class="text-sm uppercase tracking-[0.3em] text-slate-400">Settings</p>
        <h1 class="mt-2 text-3xl font-semibold sm:text-4xl">Profile</h1>
        <p class="mt-2 text-sm text-slate-300">Manage your profile type and preferred period.</p>
      </section>

      <section class="rounded-[28px] bg-white p-6 shadow-sm ring-1 ring-slate-200/70">
        <div class="max-w-xl space-y-4">
          <div>
            <label class="mb-2 block text-sm font-medium text-slate-700">Name</label>
            <input
              v-model="profileForm.name"
              type="text"
              class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-900/10"
              placeholder="Your name"
            />
          </div>
          <div>
            <label class="mb-2 block text-sm font-medium text-slate-700">Email</label>
            <input
              v-model="profileForm.email"
              type="email"
              disabled
              class="w-full rounded-xl border border-slate-300 bg-slate-100 px-3 py-2 text-sm text-slate-500"
            />
          </div>
          <div>
            <label class="mb-2 block text-sm font-medium text-slate-700">Profile Type</label>
            <select v-model="profileForm.profile_type" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-900/10">
              <option value="general">General</option>
              <option value="student">Student</option>
            </select>
          </div>
          <div>
            <label class="mb-2 block text-sm font-medium text-slate-700">Preferred Period</label>
            <select
              v-model="profileForm.preferred_period"
              :disabled="profileForm.profile_type === 'general'"
              class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-900/10 disabled:bg-slate-100 disabled:text-slate-500"
            >
              <option value="monthly">Monthly</option>
              <option value="semester">Semester</option>
            </select>
          </div>
          <p v-if="profileError" class="text-sm text-red-600">{{ profileError }}</p>
          <button
            type="button"
            @click="saveProfile"
            :disabled="isSavingProfile"
            class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-60"
          >
            {{ isSavingProfile ? 'Saving...' : 'Save Profile' }}
          </button>
        </div>
      </section>
    </div>

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
