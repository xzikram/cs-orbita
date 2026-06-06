<script setup lang="ts">
import { useAuthStore } from '../stores/auth'
import { useRouter, useRoute } from 'vue-router'
import { ref, computed } from 'vue'

const authStore = useAuthStore()
const router = useRouter()
const route = useRoute()
const sidebarCollapsed = ref(false)
const mobileOpen = ref(false)

const currentRoute = computed(() => route.name)

const menuItems = computed(() => {
  const items = []

  // Supervisor menus
  if (['supervisor', 'administrator'].includes(authStore.user?.role || '')) {
    items.push(
      { name: 'supervisor-dashboard', label: 'Overview', icon: '📊', group: 'Monitoring' },
      { name: 'monitoring', label: 'Live Monitoring', icon: '📡', group: 'Monitoring' },
      { name: 'heatmap', label: 'Heatmap Area', icon: '🗺️', group: 'Monitoring' },
      { name: 'audit', label: 'Audit Grid', icon: '📋', group: 'Monitoring' },
      { name: 'approvals', label: 'Approval Laporan', icon: '✅', group: 'Monitoring' },
    )
  }

  // Admin menus
  if (authStore.user?.role === 'administrator') {
    items.push(
      { name: 'admin-users', label: 'Pengguna', icon: '👥', group: 'Admin' },
      { name: 'admin-areas', label: 'Kelola Area', icon: '🏢', group: 'Admin' },
    )
  }

  // Management menus
  if (['manajemen', 'administrator'].includes(authStore.user?.role || '')) {
    items.push(
      { name: 'kpi-dashboard', label: 'KPI Dashboard', icon: '📈', group: 'Manajemen' },
    )
  }

  // Kepala Ruangan menus
  if (authStore.user?.role === 'kepala_ruangan') {
    items.push(
      { name: 'my-areas', label: 'Area Saya', icon: '🏠', group: 'Area' },
      { name: 'approvals', label: 'Approval Laporan', icon: '✅', group: 'Area' },
    )
  }

  // Common menus
  items.push(
    { name: 'complaints', label: 'Komplain', icon: '⚠️', group: 'Umum' },
    { name: 'reports', label: 'Laporan', icon: '📄', group: 'Umum' },
  )

  return items
})

const groupedMenu = computed(() => {
  const groups: Record<string, typeof menuItems.value> = {}
  menuItems.value.forEach(item => {
    if (!groups[item.group]) groups[item.group] = []
    groups[item.group].push(item)
  })
  return groups
})

async function handleLogout() {
  await authStore.logout()
  router.push({ name: 'login' })
}
</script>

<template>
  <div class="dashboard-layout">
    <!-- Mobile Hamburger -->
    <button class="hamburger-btn" @click="mobileOpen = !mobileOpen">
      {{ mobileOpen ? '✕' : '☰' }}
    </button>

    <!-- Backdrop -->
    <div v-if="mobileOpen" class="sidebar-backdrop" @click="mobileOpen = false"></div>

    <!-- Sidebar -->
    <aside class="sidebar" :class="{ collapsed: sidebarCollapsed, 'mobile-open': mobileOpen }">
      <div class="sidebar-header">
        <div class="sidebar-logo" v-if="!sidebarCollapsed">
          <span class="logo-text">CLEANTRACK</span>
          <span class="logo-badge">RS</span>
        </div>
        <button class="sidebar-toggle" @click="sidebarCollapsed = !sidebarCollapsed">
          {{ sidebarCollapsed ? '→' : '←' }}
        </button>
      </div>

      <nav class="sidebar-nav">
        <div v-for="(items, group) in groupedMenu" :key="group" class="nav-group">
          <span class="nav-group-title" v-if="!sidebarCollapsed">{{ group }}</span>
          <RouterLink
            v-for="item in items"
            :key="item.name"
            :to="{ name: item.name }"
            class="nav-item"
            :class="{ active: currentRoute === item.name }"
            :title="item.label"
            @click="mobileOpen = false"
          >
            <span class="nav-icon">{{ item.icon }}</span>
            <span class="nav-label" v-if="!sidebarCollapsed">{{ item.label }}</span>
          </RouterLink>
        </div>
      </nav>

      <div class="sidebar-footer">
        <div class="user-info" v-if="!sidebarCollapsed">
          <div class="user-avatar">{{ authStore.user?.name?.[0] || '?' }}</div>
          <div class="user-details">
            <span class="user-name">{{ authStore.user?.name }}</span>
            <span class="user-role">{{ authStore.user?.role_label }}</span>
          </div>
        </div>
        <button class="btn btn-ghost logout-btn" @click="handleLogout">
          <span>🚪</span>
          <span v-if="!sidebarCollapsed">Keluar</span>
        </button>
      </div>
    </aside>

    <!-- Main Content -->
    <main class="dashboard-main">
      <RouterView v-slot="{ Component }">
        <Transition name="page" mode="out-in">
          <component :is="Component" />
        </Transition>
      </RouterView>
    </main>
  </div>
</template>

<style scoped>
.dashboard-layout {
  display: flex;
  min-height: 100vh;
  background: hsl(var(--background));
  color: hsl(var(--foreground));
}

.sidebar {
  width: 260px;
  background: hsl(var(--card));
  border-right: 1px solid hsl(var(--border));
  display: flex;
  flex-direction: column;
  position: fixed;
  top: 0;
  left: 0;
  bottom: 0;
  z-index: 40;
  transition: width 0.3s ease;
}

.sidebar.collapsed {
  width: 64px;
}

.sidebar-header {
  padding: 1.25rem 1rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-bottom: 1px solid hsl(var(--border));
}

.sidebar-logo {
  display: flex;
  align-items: center;
  gap: 0.375rem;
}

.logo-text {
  font-size: 1rem;
  font-weight: 800;
  letter-spacing: -0.02em;
  background: linear-gradient(135deg, hsl(210, 100%, 56%), hsl(262, 83%, 58%));
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.logo-badge {
  font-size: 0.625rem;
  font-weight: 700;
  padding: 0.125rem 0.375rem;
  background: hsl(262, 83%, 58%);
  color: white;
  border-radius: 0.25rem;
}

.sidebar-toggle {
  width: 2rem;
  height: 2rem;
  display: flex;
  align-items: center;
  justify-content: center;
  background: hsl(var(--muted));
  border: 1px solid hsl(var(--border));
  border-radius: 0.375rem;
  color: hsl(var(--muted-foreground));
  cursor: pointer;
  font-size: 0.75rem;
  transition: all 0.2s;
}

.sidebar-toggle:hover {
  background: hsl(var(--muted) / 0.8);
}

.sidebar-nav {
  flex: 1;
  overflow-y: auto;
  padding: 0.75rem 0.5rem;
}

.nav-group {
  margin-bottom: 1.25rem;
}

.nav-group-title {
  display: block;
  font-size: 0.625rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: hsl(var(--muted-foreground));
  padding: 0 0.75rem;
  margin-bottom: 0.5rem;
}

.nav-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.625rem 0.75rem;
  border-radius: 0.5rem;
  color: hsl(var(--muted-foreground));
  text-decoration: none;
  font-size: 0.875rem;
  font-weight: 450;
  transition: all 0.2s;
  margin-bottom: 0.125rem;
}

.nav-item:hover {
  background: hsl(var(--muted));
  color: hsl(var(--foreground));
}

.nav-item.active {
  background: hsl(var(--primary) / 0.1);
  color: hsl(var(--primary));
  font-weight: 500;
}

.nav-icon {
  font-size: 1.125rem;
  flex-shrink: 0;
}

.sidebar-footer {
  padding: 0.75rem;
  border-top: 1px solid hsl(var(--border));
}

.user-info {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.5rem;
  margin-bottom: 0.5rem;
}

.user-avatar {
  width: 2rem;
  height: 2rem;
  border-radius: 50%;
  background: linear-gradient(135deg, hsl(210, 100%, 56%), hsl(262, 83%, 58%));
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 0.75rem;
  color: white;
  flex-shrink: 0;
}

.user-details {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.user-name {
  font-size: 0.8125rem;
  font-weight: 500;
  color: hsl(var(--foreground));
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.user-role {
  font-size: 0.6875rem;
  color: hsl(var(--muted-foreground));
}

.logout-btn {
  width: 100%;
  justify-content: center;
  gap: 0.5rem;
  font-size: 0.8125rem;
  color: hsl(var(--muted-foreground));
}

.logout-btn:hover {
  color: hsl(var(--destructive));
}

.dashboard-main {
  flex: 1;
  margin-left: 260px;
  padding: 1.5rem 2rem;
  min-height: 100vh;
  transition: margin-left 0.3s ease;
}

.sidebar.collapsed + .dashboard-main,
.sidebar.collapsed ~ .dashboard-main {
  margin-left: 64px;
}

@media (max-width: 1024px) {
  .sidebar {
    transform: translateX(-100%);
    width: 260px;
  }

  .sidebar.mobile-open {
    transform: translateX(0);
  }

  .dashboard-main {
    margin-left: 0;
  }

  .hamburger-btn {
    display: flex;
  }
}

.hamburger-btn {
  display: none;
  position: fixed;
  top: 1rem;
  left: 1rem;
  z-index: 50;
  width: 2.5rem;
  height: 2.5rem;
  align-items: center;
  justify-content: center;
  background: hsl(var(--card));
  border: 1px solid hsl(var(--border));
  border-radius: 0.5rem;
  color: hsl(var(--foreground));
  font-size: 1.25rem;
  cursor: pointer;
  box-shadow: 0 2px 8px rgba(0,0,0,0.3);
}

.sidebar-backdrop {
  display: none;
}

@media (max-width: 1024px) {
  .sidebar-backdrop {
    display: block;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.5);
    z-index: 39;
    backdrop-filter: blur(2px);
  }
}
</style>
