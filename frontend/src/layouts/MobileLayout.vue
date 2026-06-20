<script setup lang="ts">
import { useAuthStore } from '../stores/auth'
import { useRoute } from 'vue-router'
import { computed } from 'vue'

const authStore = useAuthStore()
const route = useRoute()

const currentRoute = computed(() => route.name)

const navItems = [
  { name: 'mobile-dashboard', label: 'Home', icon: '🏠' },
  { name: 'mobile-tasks', label: 'Tugas', icon: '📋' },
  { name: 'mobile-scan', label: 'Scan', icon: '📷' },
  { name: 'mobile-history', label: 'Riwayat', icon: '📜' },
  { name: 'mobile-profile', label: 'Profil', icon: '👤' },
]

function handleRefresh() {
  window.location.reload()
}
</script>

<template>
  <div class="mobile-app">
    <!-- Mobile Header -->
    <header class="mobile-header">
      <div class="mobile-header-content">
        <img src="/Logo%20RS%20JEC%20ORBITA.png" alt="Logo RS JEC Orbita" class="mobile-logo-img" />
        <div class="mobile-header-actions">
          <span class="mobile-user">{{ authStore.user?.name }}</span>
          <button class="mobile-refresh-btn" @click="handleRefresh" title="Refresh">🔄</button>
        </div>
      </div>
    </header>

    <!-- Page Content -->
    <main class="mobile-content">
      <RouterView v-slot="{ Component }">
        <Transition name="page" mode="out-in">
          <component :is="Component" />
        </Transition>
      </RouterView>
    </main>

    <!-- Bottom Navigation -->
    <nav class="mobile-bottom-nav">
      <RouterLink
        v-for="item in navItems"
        :key="item.name"
        :to="{ name: item.name }"
        :class="{ active: currentRoute === item.name }"
      >
        <span class="nav-icon">{{ item.icon }}</span>
        <span>{{ item.label }}</span>
      </RouterLink>
    </nav>
  </div>
</template>

<style scoped>
.mobile-app {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  background: hsl(var(--background));
  color: hsl(var(--foreground));
  max-width: 480px;
  margin: 0 auto;
}

.mobile-header {
  position: sticky;
  top: 0;
  z-index: 40;
  background: hsl(var(--card));
  border-bottom: 1px solid hsl(var(--border));
  padding: 0.875rem 1rem;
}

.mobile-header-content {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.mobile-logo-img {
  height: 24px;
  max-width: 180px;
  width: auto;
  object-fit: contain;
}

.mobile-user {
  font-size: 0.75rem;
  color: hsl(var(--muted-foreground));
}

.mobile-content {
  flex: 1;
  padding: 1rem;
  padding-bottom: 5rem;
  overflow-y: auto;
}

.mobile-bottom-nav {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  max-width: 480px;
  margin: 0 auto;
  z-index: 50;
  background: hsl(var(--card) / 0.9);
  border-top: 1px solid hsl(var(--border));
  display: flex;
  justify-content: space-around;
  padding: 0.5rem 0.25rem;
  padding-bottom: calc(0.5rem + env(safe-area-inset-bottom));
  backdrop-filter: blur(10px);
}

.mobile-bottom-nav a {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.25rem;
  text-decoration: none;
  color: hsl(var(--muted-foreground));
  font-size: 0.65rem;
  font-weight: 500;
  transition: all 0.2s ease;
  flex: 1;
  padding: 0.25rem;
  border-radius: 0.5rem;
}

.mobile-bottom-nav a .nav-icon {
  font-size: 1.25rem;
  margin-bottom: 0.125rem;
  transition: transform 0.2s ease;
}

.mobile-bottom-nav a.active {
  color: hsl(var(--primary));
}

.mobile-bottom-nav a.active .nav-icon {
  transform: translateY(-2px);
}

.mobile-bottom-nav a:hover {
  background: hsl(var(--muted));
}

.mobile-header-actions {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.mobile-refresh-btn {
  background: transparent;
  border: none;
  font-size: 1.1rem;
  cursor: pointer;
  padding: 0.25rem;
  border-radius: 0.375rem;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background-color 0.2s ease, transform 0.2s ease;
}

.mobile-refresh-btn:hover {
  background: hsl(var(--muted));
}

.mobile-refresh-btn:active {
  transform: scale(0.9) rotate(30deg);
}
</style>
