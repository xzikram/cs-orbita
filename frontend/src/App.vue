<script setup lang="ts">
import { useAuthStore } from './stores/auth'
import { onMounted, watch, ref } from 'vue'
import { useOnline } from './composables/useOnline'

const authStore = useAuthStore()
const { isOnline } = useOnline()
const showSyncBanner = ref(false)

onMounted(async () => {
  try {
    await authStore.fetchUser()
  } catch {
    // Not logged in
  }
})

watch(isOnline, (online) => {
  if (online) {
    showSyncBanner.value = true
    setTimeout(() => { showSyncBanner.value = false }, 3000)
  }
})
</script>

<template>
  <div id="cleantrack-app" class="min-h-screen bg-background text-foreground">
    <!-- Online/Offline indicator -->
    <Transition name="slide-down">
      <div
        v-if="!isOnline"
        class="fixed top-0 left-0 right-0 z-[9999] bg-warning/90 text-warning-foreground text-center py-2 text-sm font-medium backdrop-blur-sm"
      >
        ⚡ Mode Offline — Data akan disinkronkan saat online
      </div>
    </Transition>

    <Transition name="slide-down">
      <div
        v-if="showSyncBanner"
        class="fixed top-0 left-0 right-0 z-[9999] bg-success/90 text-white text-center py-2 text-sm font-medium backdrop-blur-sm"
      >
        ✓ Kembali online — Menyinkronkan data...
      </div>
    </Transition>

    <RouterView />
  </div>
</template>

<style>
.slide-down-enter-active,
.slide-down-leave-active {
  transition: transform 0.3s ease, opacity 0.3s ease;
}
.slide-down-enter-from,
.slide-down-leave-to {
  transform: translateY(-100%);
  opacity: 0;
}
</style>
