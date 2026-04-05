<script setup lang="ts">
import { ref } from 'vue';
import axiosInstance from '../../lib/axios';
import { useRouter } from 'vue-router';

const user = ref<{ name: string; email: string } | null>(null);
const isAuthenticated = ref(false);

const getUser = async () => {
    try {
        const response = await axiosInstance.get('/user');
        user.value = response.data;
        isAuthenticated.value = true;
    } catch (error) {
        console.error(error);
        user.value = null;
        isAuthenticated.value = false;
    }
};

const router = useRouter();

const logout = async () => {
    try {
        await axiosInstance.post('/logout');
        user.value = null;
        isAuthenticated.value = false;
        router.push({ name: 'home'});
    } catch (error) {
        console.error(error);
    }
};


getUser();
</script>

<template>
  <div class="px-6 pt-6">
    <h1 class="text-3xl text-slate-900">Dashboard</h1>
    <div class="flex items-center justify-between mt-4">
      <div>
        <p class="text-lg text-slate-900">Welcome back, {{ user?.name }}</p>
        <p class="text-sm text-slate-900">{{ user?.email }}</p>
      </div>
    </div>
  </div>
</template>

