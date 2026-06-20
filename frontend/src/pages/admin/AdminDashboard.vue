<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import api from '../../lib/axios'

const stats = ref<any>(null)
const loading = ref(true)
const isMobile = ref(false)

function checkWidth() {
  isMobile.value = window.innerWidth <= 768
}

async function loadData() {
  try {
    const { data } = await api.get('/api/v1/dashboard/supervisor')
    stats.value = data.data
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadData()
  checkWidth()
  window.addEventListener('resize', checkWidth)
})

onUnmounted(() => {
  window.removeEventListener('resize', checkWidth)
})

const quickLinks = computed(() => {
  if (isMobile.value) {
    return [
      { name: 'supervisor-dashboard', label: 'Overview', icon: '📊' },
      { name: 'monitoring', label: 'Live Monitoring', icon: '📡' },
      // { name: 'approvals', label: 'Approval Laporan', icon: '✅' },
      { name: 'kpi-dashboard', label: 'KPI Dashboard', icon: '📈' },
    ]
  }
  return [
    { name: 'admin-users', label: 'Kelola Pengguna', icon: '👥' },
    { name: 'admin-areas', label: 'Kelola Area', icon: '🏢' },
    { name: 'monitoring', label: 'Live Monitoring', icon: '📡' },
    { name: 'reports', label: 'Laporan', icon: '📄' },
  ]
})
</script>

<template>
  <div class="admin-dashboard animate-fade-in">
    <div class="flex justify-between items-center mb-6">
      <div>
        <h1 class="text-2xl font-bold">Admin Dashboard</h1>
        <p class="text-muted-foreground">Ringkasan sistem dan data operasional.</p>
      </div>
      <button class="btn btn-secondary" @click="loadData">🔄 Refresh</button>
    </div>

    <div v-if="loading" class="flex justify-center py-12">
      <div class="spinner-large"></div>
    </div>

    <template v-else-if="stats">
      <div class="stats-grid mb-6">
        <div class="card-stat animate-slide-up stagger-1">
          <div class="stat-header">
            <span class="stat-title">Total Area</span>
            <span class="stat-icon">🏢</span>
          </div>
          <div class="stat-value text-primary">{{ stats.total_areas }}</div>
          <p class="stat-desc">Area terdaftar di sistem</p>
        </div>

        <div class="card-stat animate-slide-up stagger-2">
          <div class="stat-header">
            <span class="stat-title">Aktivitas Hari Ini</span>
            <span class="stat-icon">📊</span>
          </div>
          <div class="stat-value text-accent">{{ stats.total_activities }}</div>
          <p class="stat-desc">Laporan pembersihan masuk</p>
        </div>

        <div class="card-stat animate-slide-up stagger-3">
          <div class="stat-header">
            <span class="stat-title">Completion Rate</span>
            <span class="stat-icon">🎯</span>
          </div>
          <div class="stat-value text-success">{{ stats.completion_rate }}%</div>
          <div class="progress-bg mt-3">
            <div class="progress-fill" :style="{ width: `${stats.completion_rate}%` }"></div>
          </div>
        </div>

        <div class="card-stat animate-slide-up stagger-4">
          <div class="stat-header">
            <span class="stat-title">Terlambat</span>
            <span class="stat-icon">⚠️</span>
          </div>
          <div class="stat-value text-destructive">{{ stats.areas_late }}</div>
          <p class="stat-desc">Melewati batas SLA</p>
        </div>
      </div>

      <div class="quick-links">
        <h2 class="font-bold mb-4">Menu Cepat</h2>
        <div class="links-grid">
          <RouterLink 
            v-for="link in quickLinks" 
            :key="link.name" 
            :to="{ name: link.name }" 
            class="link-card"
          >
            <span class="link-icon">{{ link.icon }}</span>
            <span>{{ link.label }}</span>
          </RouterLink>
        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
.stats-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 1.5rem;
}

.stat-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem; }
.stat-title { font-size: 0.875rem; font-weight: 500; color: hsl(var(--muted-foreground)); text-transform: uppercase; letter-spacing: 0.05em; }
.stat-icon { font-size: 1.25rem; }
.stat-value { font-size: 2.5rem; font-weight: 700; line-height: 1.2; margin-bottom: 0.25rem; }
.stat-desc { font-size: 0.875rem; color: hsl(var(--muted-foreground)); }

.progress-bg { height: 0.5rem; background: hsl(var(--muted)); border-radius: 9999px; overflow: hidden; }
.progress-fill { height: 100%; background: linear-gradient(90deg, hsl(var(--primary)), hsl(var(--accent))); transition: width 1s ease; }

.links-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 1rem;
}

.link-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.75rem;
  padding: 1.5rem;
  background: hsl(var(--card));
  border: 1px solid hsl(var(--border));
  border-radius: 0.75rem;
  text-decoration: none;
  color: hsl(var(--foreground));
  font-weight: 500;
  transition: all 0.2s;
}

.link-card:hover {
  background: hsl(var(--primary) / 0.1);
  border-color: hsl(var(--primary) / 0.3);
  transform: translateY(-2px);
}

.link-icon { font-size: 2rem; }

/* Utils */
.flex { display: flex; }
.justify-between { justify-content: space-between; }
.justify-center { justify-content: center; }
.items-center { align-items: center; }
.mb-4 { margin-bottom: 1rem; }
.mb-6 { margin-bottom: 1.5rem; }
.mt-3 { margin-top: 0.75rem; }
.py-12 { padding-top: 3rem; padding-bottom: 3rem; }
.text-2xl { font-size: 1.5rem; font-weight: 700; }
.font-bold { font-weight: 700; }
.text-primary { color: hsl(var(--primary)); }
.text-accent { color: hsl(var(--accent)); }
.text-success { color: hsl(var(--success)); }
.text-destructive { color: hsl(var(--destructive)); }
.text-muted-foreground { color: hsl(var(--muted-foreground)); }

.spinner-large { width: 3rem; height: 3rem; border: 4px solid rgba(255,255,255,0.1); border-top-color: hsl(var(--primary)); border-radius: 50%; animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

@media (max-width: 1024px) {
  .stats-grid, .links-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 768px) {
  .admin-dashboard {
    padding: 0;
  }
  
  .admin-dashboard .mb-6 {
    margin-bottom: 0.5rem !important;
  }
  
  .text-2xl {
    font-size: 1.125rem !important;
  }
  
  .text-muted-foreground {
    font-size: 0.7rem !important;
  }
  
  .btn-secondary {
    padding: 0.25rem 0.5rem;
    font-size: 0.7rem;
  }

  .stats-grid {
    grid-template-columns: repeat(2, 1fr) !important;
    gap: 0.5rem !important;
    margin-bottom: 0.5rem !important;
  }
  
  .card-stat {
    padding: 0.5rem 0.75rem !important;
    border-radius: 0.5rem !important;
  }
  
  .stat-title {
    font-size: 0.625rem !important;
  }
  
  .stat-icon {
    font-size: 0.875rem !important;
  }
  
  .stat-value {
    font-size: 1.25rem !important;
    margin-bottom: 0px !important;
  }
  
  .stat-desc {
    display: none !important;
  }

  .progress-bg {
    height: 0.375rem !important;
    margin-top: 0.375rem !important;
  }
  
  .quick-links {
    margin-top: 0.5rem;
  }
  
  .quick-links h2 {
    font-size: 0.875rem;
    margin-bottom: 0.375rem !important;
  }

  .links-grid {
    grid-template-columns: repeat(2, 1fr) !important;
    gap: 0.375rem !important;
  }
  
  .link-card {
    flex-direction: row !important;
    align-items: center !important;
    justify-content: flex-start !important;
    gap: 0.375rem !important;
    padding: 0.5rem 0.625rem !important;
    border-radius: 0.5rem !important;
  }
  
  .link-card span {
    font-size: 0.75rem !important;
  }
  
  .link-icon {
    font-size: 1.125rem !important;
  }
}

@media (max-width: 640px) {
  /* Override to maintain the compact 2-column mobile layout */
  .stats-grid, .links-grid {
    grid-template-columns: repeat(2, 1fr) !important;
  }
}
</style>
