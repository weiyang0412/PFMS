<script setup lang="ts">
import { AxiosError } from 'axios';
import axiosInstance from '../../lib/axios';
import { computed, reactive, ref } from 'vue';
import { useRoute, useRouter, RouterLink } from 'vue-router';

const route = useRoute();
const router = useRouter();

const form = reactive({
    token: String(route.query.token || ''),
    email: String(route.query.email || ''),
    password: '',
    password_confirmation: '',
});

const errors = reactive({
    email: [] as string[],
    password: [] as string[],
});

const status = ref('');
const isLoading = ref(false);

const hasToken = computed(() => Boolean(form.token));

const resetPassword = async () => {
    isLoading.value = true;
    status.value = '';
    errors.email = [];
    errors.password = [];

    try {
        await axiosInstance.get('/sanctum/csrf-cookie');
        const response = await axiosInstance.post('/reset-password', form);
        status.value = response.data?.status || 'Password updated successfully.';
        setTimeout(() => {
            router.push('/login');
        }, 1000);
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
    <div class="fixed inset-0 z-50 overflow-hidden bg-slate-950">
        <div class="pointer-events-none absolute -top-32 -left-24 h-80 w-80 rounded-full bg-cyan-500/20 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-32 -right-20 h-96 w-96 rounded-full bg-blue-500/20 blur-3xl"></div>
        <div class="flex h-full items-center justify-center px-4">
            <form
                @submit.prevent="resetPassword"
                novalidate
                class="w-full max-w-md rounded-2xl border border-slate-700/70 bg-slate-900/80 p-7 shadow-[0_20px_60px_-20px_rgba(8,47,73,0.75)] backdrop-blur-md"
            >
                <div class="mb-6">
                    <p class="text-sm font-medium text-cyan-300">Set new password</p>
                    <h1 class="mt-1 text-2xl font-semibold tracking-tight text-white">Reset your password</h1>
                    <p class="mt-2 text-sm text-slate-300">Choose a new password for your account.</p>
                </div>

                <div v-if="!hasToken" class="mb-4 rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-200">
                    Missing reset token. Please request a new reset link.
                </div>

                <div class="mb-4">
                    <label for="email" class="mb-2 block text-sm font-medium text-slate-100">Email</label>
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        class="block w-full rounded-xl border border-slate-600/80 bg-slate-700/45 p-3 text-sm text-slate-100 placeholder:text-slate-400 focus:border-cyan-400 focus:outline-none focus:ring-2 focus:ring-cyan-500/40"
                        :class="errors.email?.length ? 'border-red-400/80 focus:border-red-400 focus:ring-red-500/30' : ''"
                        required
                    />
                    <template v-if="errors.email?.length">
                        <span v-for="error in errors.email" :key="error" class="mt-1 block text-xs text-red-300">
                            {{ error }}
                        </span>
                    </template>
                </div>

                <div class="mb-4">
                    <label for="password" class="mb-2 block text-sm font-medium text-slate-100">New password</label>
                    <input
                        id="password"
                        v-model="form.password"
                        type="password"
                        class="block w-full rounded-xl border border-slate-600/80 bg-slate-700/45 p-3 text-sm text-slate-100 placeholder:text-slate-400 focus:border-cyan-400 focus:outline-none focus:ring-2 focus:ring-cyan-500/40"
                        :class="errors.password?.length ? 'border-red-400/80 focus:border-red-400 focus:ring-red-500/30' : ''"
                        required
                    />
                </div>

                <div class="mb-5">
                    <label for="password_confirmation" class="mb-2 block text-sm font-medium text-slate-100">Confirm new password</label>
                    <input
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        type="password"
                        class="block w-full rounded-xl border border-slate-600/80 bg-slate-700/45 p-3 text-sm text-slate-100 placeholder:text-slate-400 focus:border-cyan-400 focus:outline-none focus:ring-2 focus:ring-cyan-500/40"
                        required
                    />
                    <template v-if="errors.password?.length">
                        <span v-for="error in errors.password" :key="error" class="mt-1 block text-xs text-red-300">
                            {{ error }}
                        </span>
                    </template>
                </div>

                <div v-if="status" class="mb-4 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">
                    {{ status }}
                </div>

                <button
                    type="submit"
                    class="inline-flex w-full items-center justify-center rounded-xl bg-gradient-to-r from-cyan-500 to-blue-600 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-blue-900/40 transition hover:brightness-110 active:translate-y-px disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="isLoading || !hasToken"
                >
                    {{ isLoading ? 'Updating password...' : 'Reset password' }}
                </button>

                <p class="mt-4 text-center text-sm text-slate-300">
                    <RouterLink to="/login" class="font-semibold text-cyan-300 transition hover:text-cyan-200">Back to login</RouterLink>
                </p>
            </form>
        </div>
    </div>
</template>
