<script setup lang="ts">
import { computed, ref, onMounted, onBeforeUnmount, watch } from 'vue';
import { RouterLink, RouterView, useRouter, useRoute } from 'vue-router';
import { useUserStore } from './stores/userStore';
import appLogo from './assets/logo.png';

// const user = ref<{ name: string; email: string } | null>(null);
// const isAuthenticated = ref(false);

const userStore = useUserStore();
const isSidebarOpen = ref(false);
const isOpen = ref(false);
const dropdownRef = ref<HTMLElement | null>(null);
const logoutLoading = ref(false);

const hideSidebarRoutes = [''];

const router = useRouter();
const route = useRoute();

watch(
  () => userStore.user,
  (newUser) => {
    if (newUser) {
      isSidebarOpen.value = true;
    } else {
      isSidebarOpen.value = false;
    }
  }
);

const logout = async () => {
  logoutLoading.value = true;
  try {
    await userStore.logout();
    router.push('/login');
  } finally {
    logoutLoading.value = false;
  }
};

const toggleSidebar = () => {
  isSidebarOpen.value = !isSidebarOpen.value;
};

const shouldShowSidebar = computed(() => {
  return !hideSidebarRoutes.includes(String(route.name ?? ''));
});

const sidebarItemClasses = computed(() => {
  return [
    'flex items-center p-2 text-gray-900 rounded-lg dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 group',
    isSidebarOpen.value ? 'justify-start' : 'justify-center',
  ].join(' ');
});

const sidebarLabelClasses = computed(() => {
  return isSidebarOpen.value ? 'ms-3' : 'hidden';
});

const sidebarLogoTextClass = computed(() => {
  return isSidebarOpen.value ? 'text-lg font-semibold text-slate-900 dark:text-white' : 'hidden';
});

const toggleDropdown = () => {
  isOpen.value = !isOpen.value;
};

const handleClickOutside = (event: MouseEvent) => {
  if (dropdownRef.value && !dropdownRef.value.contains(event.target as Node)) {
    isOpen.value = false;
  }
};

onMounted(() => {
  userStore.fetchUser();
  document.addEventListener('click', handleClickOutside);
});

onBeforeUnmount(() => {
  document.removeEventListener('click', handleClickOutside);
});
</script>

<template>
  <div>
    <div
      v-if="isSidebarOpen && shouldShowSidebar"
      class="fixed inset-0 z-30 bg-black/40 lg:hidden"
      @click="toggleSidebar"
    ></div>

    <!-- Sidebar -->
    <aside
      v-if="shouldShowSidebar"
      :class="[
        'fixed top-0 left-0 z-40 h-screen pt-4 transition-all duration-300 bg-white dark:bg-gray-800 border-r border-gray-200 dark:border-gray-700 flex flex-col',
        isSidebarOpen ? 'w-64' : 'w-16',
      ]"
    >
      <div class="border-b border-gray-200 dark:border-gray-700 pb-4" :class="isSidebarOpen ? 'px-6' : 'px-4'">
        <div :class="['flex items-center gap-3', isSidebarOpen ? 'justify-between' : 'justify-center']">
          <div class="flex items-center gap-3">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-white p-1 shadow-sm">
              <img :src="appLogo" class="h-10 w-10 object-contain" alt="Logo" />
            </div>
            <div class="flex flex-col" :class="isSidebarOpen ? '' : 'hidden'">
              <span class="text-lg font-semibold text-slate-900 dark:text-white">SMARTBUDGET</span>
              <span class="text-sm text-gray-500 dark:text-gray-400">Hi, {{ userStore.user?.name ?? 'Guest' }}</span>
            </div>
          </div>
        </div>
      </div>
      <div class="px-3 pb-4 mt-4 flex-1 overflow-y-auto">
        <ul v-if="userStore.user" class="space-y-2 font-medium">
          <!-- Dashboard -->
          <li>
            <RouterLink to="/dashboard" :class="sidebarItemClasses">
              <svg class="w-6 h-6 text-gray-500 group-hover:text-gray-900 dark:text-gray-400 dark:group-hover:text-white" fill="currentColor" viewBox="0 0 22 21">
                <path stroke="currentColor" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5Zm16 14a1 1 0 0 1-1 1h-4a1 1 0 0 1-1-1v-2a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2ZM4 13a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v6a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-6Zm16-2a1 1 0 0 1-1 1h-4a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v6Z"/>
              </svg>
              <span :class="sidebarLabelClasses">Dashboard</span>
            </RouterLink>
          </li>
          <!-- Accounts -->
          <li>
            <RouterLink to="/accounts" :class="sidebarItemClasses">
              <svg class="w-6 h-6 text-gray-500 group-hover:text-gray-900 dark:text-gray-400 dark:group-hover:text-white" fill="currentColor" viewBox="0 0 22 21">
                <path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M3 21h18M4 18h16M6 10v8m4-8v8m4-8v8m4-8v8M4 9.5v-.955a1 1 0 0 1 .458-.84l7-4.52a1 1 0 0 1 1.084 0l7 4.52a1 1 0 0 1 .458.84V9.5a.5.5 0 0 1-.5.5h-15a.5.5 0 0 1-.5-.5Z"/>
              </svg>
              <span :class="sidebarLabelClasses">Accounts</span>
            </RouterLink>
          </li>
          <!-- Transactions -->
          <li>
            <RouterLink to="/transactions" :class="sidebarItemClasses">
              <svg class="w-6 h-6 text-gray-500 group-hover:text-gray-900 dark:text-gray-400 dark:group-hover:text-white" fill="currentColor" viewBox="0 0 22 21">
                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16h13M4 16l4-4m-4 4 4 4M20 8H7m13 0-4 4m4-4-4-4"/>
              </svg>
              <span :class="sidebarLabelClasses">Transactions</span>
            </RouterLink>
          </li>
          <li>
            <RouterLink to="/transaction-options" :class="sidebarItemClasses">
              <svg class="w-6 h-6 text-gray-500 group-hover:text-gray-900 dark:text-gray-400 dark:group-hover:text-white" fill="none" viewBox="0 0 24 24">
                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H21M3 6h3m4 12h11M3 18h3m8-6h7M3 12h7m-1-8v4m8 8v4"/>
              </svg>
              <span :class="sidebarLabelClasses">Manage Options</span>
            </RouterLink>
          </li>
          <!-- Financial Analytics -->
          <li>
            <RouterLink to="/dashboard" :class="sidebarItemClasses">
              <svg class="w-6 h-6 text-gray-500 transition duration-75 dark:text-gray-400 group-hover:text-gray-900 dark:group-hover:text-white" fill="currentColor" viewBox="0 0 22 21">
                <path d="M16.975 11H10V4.025a1 1 0 0 0-1.066-.998 8.5 8.5 0 1 0 9.039 9.039.999.999 0 0 0-1-1.066h.002Z"/>
                <path d="M12.5 0c-.157 0-.311.01-.565.027A1 1 0 0 0 11 1.02V10h8.975a1 1 0 0 0 1-.935c.013-.188.028-.374.028-.565A8.51 8.51 0 0 0 12.5 0Z"/>
              </svg>
              <span :class="sidebarLabelClasses">Financial Analytics</span>
            </RouterLink>
          </li>
        </ul>
      </div>

      <!-- Bottom Side -->
      <div class="px-3 pb-4 mt-auto">
        <ul class="space-y-2 font-medium">
          <!-- Log In / Log Out -->
          <li v-if="userStore.user">
            <div
              @click="logoutLoading ? null : logout()"
              :class="['flex items-center p-2 rounded-lg text-gray-900 dark:text-white group', logoutLoading ? 'cursor-not-allowed opacity-60' : 'cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700', isSidebarOpen ? '' : 'justify-center']"
            >
              <svg class="w-6 h-6 text-gray-500 group-hover:text-gray-900 dark:text-gray-400 dark:group-hover:text-white"
                  fill="none" viewBox="0 0 24 24">
                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M20 12H8m12 0-4 4m4-4-4-4M9 4H7a3 3 0 0 0-3 3v10a3 3 0 0 0 3 3h2"/>
              </svg>
              <span :class="sidebarLabelClasses">Log Out</span>
            </div>
          </li>

          <li v-if="!userStore.user">
            <RouterLink
              to="/login"
              :class="sidebarItemClasses"
            >
              <svg class="w-6 h-6 text-gray-500 group-hover:text-gray-900 dark:text-gray-400 dark:group-hover:text-white" fill="none" viewBox="0 0 24 24">
                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 12h12m0 0-4-4m4 4-4 4M15 4h2a3 3 0 0 1 3 3v10a3 3 0 0 1-3 3h-2"/>
              </svg>
              <span :class="sidebarLabelClasses">Sign In</span>
            </RouterLink>
          </li>

          <li v-if="!userStore.user">
            <RouterLink
              to="/register"
              :class="sidebarItemClasses"
            >
              <svg class="w-6 h-6 text-gray-500 group-hover:text-gray-900 dark:text-gray-400 dark:group-hover:text-white" fill="none" viewBox="0 0 24 24">
                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0ZM12 14a7 7 0 0 0-7 7h14a7 7 0 0 0-7-7Zm8-3v6m3-3h-6"/>
              </svg>
              <span :class="sidebarLabelClasses">Sign Up</span>
            </RouterLink>
          </li>
        </ul>
        <div class="border-t border-gray-200 dark:border-gray-700 my-4"></div>
        <div class="mt-4 flex justify-end pr-2">
          <button
            type="button"
            @click="toggleSidebar"
            class="inline-flex h-10 w-10 items-center justify-center text-gray-600 hover:text-gray-900 focus:outline-none dark:text-gray-200 dark:hover:text-white"
            aria-label="Toggle sidebar"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                :d="isSidebarOpen ? 'M15 19l-7-7 7-7' : 'M9 5l7 7-7 7'"
              />
            </svg>
          </button>
        </div>
      </div>
    </aside>

    <!-- Main content -->
    <main 
      class="bg-slate-100 h-screen transition-all duration-300"
      :class="isSidebarOpen && shouldShowSidebar ? 'ml-64' : shouldShowSidebar ? 'ml-16' : 'ml-0'"
    >
      <div class="mb-6">
          <RouterView />
        </div>
    </main>

    <div v-if="logoutLoading" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
      <div class="flex flex-col items-center p-6 bg-white rounded-lg shadow-xl">
        <span class="w-12 h-12 mb-4 border-4 border-blue-500 border-t-transparent rounded-full animate-spin"></span>
        <p class="text-lg font-medium text-gray-900">Logging out...</p>
      </div>
    </div>
  </div>
</template>
