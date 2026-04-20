<script setup lang="ts">
import { AxiosError } from 'axios';
import axiosInstance from '../../lib/axios';
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useUserStore } from '../../stores/userStore';

interface LoginForm {
    email: string;
    password: string;
}

const form = reactive<LoginForm>({
    email: '',
    password: '',
});

const errors = reactive({
    email: [],
    password: [],
});

const isLoading = ref(false);
const showPassword = ref(false);
const router = useRouter();

const userStore = useUserStore();

const login = async (payload: LoginForm) => {
    isLoading.value = true;
    await axiosInstance.get('/sanctum/csrf-cookie', {
        baseURL: 'http://localhost:8000',
    });
    errors.email = [];
    errors.password = [];
    try {
        await axiosInstance.post('/login', payload);
        await userStore.fetchUser();
        router.push({ name: 'dashboard' });
    } catch (e) {
        if (e instanceof AxiosError && e.response?.status === 422) {
            const responseErrors = e.response.data.errors;
            errors.email = responseErrors.email || [];
            errors.password = responseErrors.password || [];
        }
    } finally {
        isLoading.value = false;
    }
};

</script>


<template>
    <div class="fixed inset-0 flex items-center justify-center overflow-hidden bg-gray-900">
        <form @submit.prevent="login(form)" class="max-w-sm w-full p-4 bg-white rounded-lg shadow-md dark:bg-gray-800">
            <div class="mb-5">
                <label for="email" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Email</label>
                <input type="email" id="email" v-model="form.email"
                    class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                    placeholder="name@flowbite.com" required />
                <template v-if="errors.email?.length">
                    <span v-for="error in errors.email" :key="error" class="text-xs text-red-500 italic">
                        {{ error }}
                    </span>
                </template>
            </div>
            <div class="mb-5">
                <label for="password"
                    class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Password</label>
                <div class="relative">
                    <input
                        :type="showPassword ? 'text' : 'password'"
                        id="password"
                        v-model="form.password"
                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 pr-10 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                        required
                    />
                    <button
                        type="button"
                        class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                        :aria-label="showPassword ? 'Hide password' : 'Show password'"
                        :title="showPassword ? 'Hide password' : 'Show password'"
                        @click="showPassword = !showPassword"
                    >
                        <svg v-if="showPassword" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3l18 18" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.585 10.587a2 2 0 002.828 2.828" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.68 16.673A9.717 9.717 0 0112 18c-5 0-9.27-3.11-11-7a11.052 11.052 0 012.818-3.964m3.147-2.148A9.723 9.723 0 0112 4c5 0 9.27 3.11 11 7a11.05 11.05 0 01-1.67 2.672" />
                        </svg>
                        <svg v-else class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z" />
                            <circle cx="12" cy="12" r="3" stroke-width="2" />
                        </svg>
                    </button>
                </div>
                <template v-if="errors.password?.length">
                    <span v-for="error in errors.password" :key="error" class="text-xs text-red-500 italic">
                        {{ error }}
                    </span>
                </template>
            </div>
            <button
                type="submit"
                class="inline-flex items-center justify-center text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm w-full sm:w-auto px-5 py-2.5 text-center dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800"
            >
                Login
            </button>
        </form>
        <div v-if="isLoading" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
            <div class="flex flex-col items-center p-6 bg-white rounded-lg shadow-xl">
                <span class="w-12 h-12 mb-4 border-4 border-blue-500 border-t-transparent rounded-full animate-spin"></span>
                <p class="text-lg font-medium text-gray-900">Logging in...</p>
            </div>
        </div>
    </div>
</template>
