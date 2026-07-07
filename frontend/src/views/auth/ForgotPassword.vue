<script setup lang="ts">
import { AxiosError } from 'axios';
import axiosInstance from '../../lib/axios';
import { reactive, ref } from 'vue';
import { RouterLink } from 'vue-router';

const form = reactive({
    email: '',
});

const errors = reactive({
    email: [] as string[],
});

const status = ref('');
const isLoading = ref(false);

const sendResetLink = async () => {
    isLoading.value = true;
    status.value = '';
    errors.email = [];

    try {
        await axiosInstance.get('/sanctum/csrf-cookie');
        const response = await axiosInstance.post('/forgot-password', form);
        status.value = response.data?.status || 'Password reset link sent.';
    } catch (e) {
        if (e instanceof AxiosError && e.response?.status === 422) {
            const responseErrors = e.response.data.errors;
            errors.email = responseErrors.email || [];
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
                @submit.prevent="sendResetLink"
                novalidate
                class="w-full max-w-md rounded-2xl border border-slate-700/70 bg-slate-900/80 p-7 shadow-[0_20px_60px_-20px_rgba(8,47,73,0.75)] backdrop-blur-md"
            >
                <div class="mb-6">
                    <p class="text-sm font-medium text-cyan-300">Reset access</p>
                    <h1 class="mt-1 text-2xl font-semibold tracking-tight text-white">Forgot your password?</h1>
                    <p class="mt-2 text-sm text-slate-300">Enter your email and we will send a reset link.</p>
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

                <div v-if="status" class="mb-4 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">
                    {{ status }}
                </div>

                <button
                    type="submit"
                    class="inline-flex w-full items-center justify-center rounded-xl bg-gradient-to-r from-cyan-500 to-blue-600 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-blue-900/40 transition hover:brightness-110 active:translate-y-px disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="isLoading"
                >
                    {{ isLoading ? 'Sending reset link...' : 'Send reset link' }}
                </button>

                <p class="mt-4 text-center text-sm text-slate-300">
                    Remember your password?
                    <RouterLink to="/login" class="font-semibold text-cyan-300 transition hover:text-cyan-200">Back to login</RouterLink>
                </p>
            </form>
        </div>
    </div>
</template>
