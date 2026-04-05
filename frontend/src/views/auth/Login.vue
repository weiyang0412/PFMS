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
    <div class="absolute top-0 left-0 right-0 bottom-0 flex items-center justify-center bg-gray-900">
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
                <input type="password" id="password" v-model="form.password"
                    class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                    required />
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