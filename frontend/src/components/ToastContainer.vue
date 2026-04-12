<script setup lang="ts">
import { computed } from 'vue';
import { useToast } from '../composables/useToast.js';

const { visible, message, tone } = useToast();
const toastClass = computed(() => (tone.value === 'danger' ? 'bg-red-700' : 'bg-emerald-600'));
</script>

<template>
  <Teleport to="body">
    <transition name="toast-fade">
      <div v-if="visible" class="pointer-events-none fixed bottom-6 right-6 z-[130] rounded-lg px-4 py-3 text-sm font-medium text-white shadow-xl" :class="toastClass">
        {{ message }}
      </div>
    </transition>
  </Teleport>
</template>

<style scoped>
.toast-fade-enter-active,
.toast-fade-leave-active {
  transition: all 0.2s ease;
}

.toast-fade-enter-from,
.toast-fade-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}
</style>
