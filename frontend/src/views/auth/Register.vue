<script setup lang="ts">
import { AxiosError } from 'axios';
import axiosInstance from '../../lib/axios';
import { reactive, ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { useUserStore } from '../../stores/userStore';

interface RegisterForm {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
}

const form = reactive<RegisterForm>({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const errors = reactive({
    name: [],
    email: [],
    password: [],
});

const isLoading = ref(false);
const showPassword = ref(false);
const showPasswordConfirmation = ref(false);
const router = useRouter();
const userStore = useUserStore();

const register = async (payload: RegisterForm) => {
    isLoading.value = true;
    await axiosInstance.get('/sanctum/csrf-cookie', {
        baseURL: 'http://localhost:8000',
    });
    errors.name = [];
    errors.email = [];
    errors.password = [];
    try {
        await axiosInstance.post('/register', payload);
        await userStore.fetchUser();
        router.push('/dashboard');
    } catch (e) {
        // console.log(e.response)
        if (e instanceof AxiosError && e.response?.status === 422) {
            const responseErrors = e.response.data.errors;
            errors.name = responseErrors.name || [];
            errors.email = responseErrors.email || [];
            errors.password = responseErrors.password || [];
        }
    } finally {
        isLoading.value = false;
    }
};

</script>

<template>
    <div class="fixed inset-0 z-50 overflow-hidden bg-slate-950">
        <div class="pointer-events-none absolute -top-32 -left-24 h-80 w-80 rounded-full bg-cyan-500/20 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-32 -right-20 h-96 w-96 rounded-full bg-blue-500/20 blur-3xl"></div>
        <div class="flex h-full items-center justify-center px-4">
            <form
                @submit.prevent="register(form)"
                class="w-full max-w-md rounded-2xl border border-slate-700/70 bg-slate-900/80 p-7 shadow-[0_20px_60px_-20px_rgba(8,47,73,0.75)] backdrop-blur-md"
            >
                <div class="mb-6">
                    <p class="text-sm font-medium text-cyan-300">Create your account</p>
                    <h1 class="mt-1 text-2xl font-semibold tracking-tight text-white">Join SmartBudget today</h1>
                    <p class="mt-2 text-sm text-slate-300">Set up your account and start managing money smarter.</p>
                </div>

                <div class="mb-4">
                    <label for="name" class="mb-2 block text-sm font-medium text-slate-100">Name</label>
                    <input
                        id="name"
                        v-model="form.name"
                        type="text"
                        class="block w-full rounded-xl border border-slate-600/80 bg-slate-700/45 p-3 text-sm text-slate-100 placeholder:text-slate-400 focus:border-cyan-400 focus:outline-none focus:ring-2 focus:ring-cyan-500/40"
                        :class="errors.name?.length ? 'border-red-400/80 focus:border-red-400 focus:ring-red-500/30' : ''"
                        placeholder="Your full name"
                        required
                    />
                    <template v-if="errors.name?.length">
                        <span v-for="error in errors.name" :key="error" class="mt-1 block text-xs text-red-300">
                            {{ error }}
                        </span>
                    </template>
                </div>

                <div class="mb-4">
                    <label for="email" class="mb-2 block text-sm font-medium text-slate-100">Email</label>
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        class="block w-full rounded-xl border border-slate-600/80 bg-slate-700/45 p-3 text-sm text-slate-100 placeholder:text-slate-400 focus:border-cyan-400 focus:outline-none focus:ring-2 focus:ring-cyan-500/40"
                        :class="errors.email?.length ? 'border-red-400/80 focus:border-red-400 focus:ring-red-500/30' : ''"
                        placeholder="name@flowbite.com"
                        required
                    />
                    <template v-if="errors.email?.length">
                        <span v-for="error in errors.email" :key="error" class="mt-1 block text-xs text-red-300">
                            {{ error }}
                        </span>
                    </template>
                </div>

                <div class="mb-4">
                    <label for="password" class="mb-2 block text-sm font-medium text-slate-100">Password</label>
                    <div class="relative">
                        <input
                            id="password"
                            v-model="form.password"
                            :type="showPassword ? 'text' : 'password'"
                            class="block w-full rounded-xl border border-slate-600/80 bg-slate-700/45 p-3 pr-12 text-sm text-slate-100 placeholder:text-slate-400 focus:border-cyan-400 focus:outline-none focus:ring-2 focus:ring-cyan-500/40"
                            :class="errors.password?.length ? 'border-red-400/80 focus:border-red-400 focus:ring-red-500/30' : ''"
                            required
                        />
                        <button
                            type="button"
                            class="absolute inset-y-0 right-2 my-auto inline-flex h-8 w-8 items-center justify-center rounded-full text-slate-300 transition hover:bg-slate-600/70 hover:text-white focus:outline-none focus:ring-2 focus:ring-cyan-500/40"
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
                        <span v-for="error in errors.password" :key="error" class="mt-1 block text-xs text-red-300">
                            {{ error }}
                        </span>
                    </template>
                </div>

                <div class="mb-5">
                    <label for="password_confirmation" class="mb-2 block text-sm font-medium text-slate-100">Confirm password</label>
                    <div class="relative">
                        <input
                            id="password_confirmation"
                            v-model="form.password_confirmation"
                            :type="showPasswordConfirmation ? 'text' : 'password'"
                            class="block w-full rounded-xl border border-slate-600/80 bg-slate-700/45 p-3 pr-12 text-sm text-slate-100 placeholder:text-slate-400 focus:border-cyan-400 focus:outline-none focus:ring-2 focus:ring-cyan-500/40"
                            required
                        />
                        <button
                            type="button"
                            class="absolute inset-y-0 right-2 my-auto inline-flex h-8 w-8 items-center justify-center rounded-full text-slate-300 transition hover:bg-slate-600/70 hover:text-white focus:outline-none focus:ring-2 focus:ring-cyan-500/40"
                            :aria-label="showPasswordConfirmation ? 'Hide confirm password' : 'Show confirm password'"
                            :title="showPasswordConfirmation ? 'Hide confirm password' : 'Show confirm password'"
                            @click="showPasswordConfirmation = !showPasswordConfirmation"
                        >
                            <svg v-if="showPasswordConfirmation" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
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
                </div>

                <button
                    type="submit"
                    class="inline-flex w-full items-center justify-center rounded-xl bg-gradient-to-r from-cyan-500 to-blue-600 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-blue-900/40 transition hover:brightness-110 active:translate-y-px disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="isLoading"
                >
                    {{ isLoading ? 'Creating account...' : 'Create account' }}
                </button>
                <p class="mt-4 text-center text-sm text-slate-300">
                    Already have an account?
                    <RouterLink to="/login" class="font-semibold text-cyan-300 transition hover:text-cyan-200">Sign in</RouterLink>
                </p>
            </form>
        </div>
        <div v-if="isLoading" class="fixed inset-0 z-[120] flex items-center justify-center bg-black/55 px-4">
            <div class="flex w-full max-w-xs flex-col items-center rounded-2xl bg-white px-6 py-7 text-center shadow-2xl">
                <span class="mb-4 h-12 w-12 animate-spin rounded-full border-4 border-slate-900 border-t-transparent"></span>
                <p class="text-lg font-semibold text-slate-900">Loading ...</p>
            </div>
        </div>
    </div>
</template>
