<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '../../lib/axios'

const stats = ref<any>(null)
const loading = ref(true)

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
          <RouterLink :to="{ name: 'admin-users' }" class="link-card">
            <span class="link-icon">👥</span>
            <span>Kelola Pengguna</span>
          </RouterLink>
          <RouterLink :to="{ name: 'admin-areas' }" class="link-card">
            <span class="link-icon">🏢</span>
            <span>Kelola Area</span>
          </RouterLink>
          <RouterLink :to="{ name: 'monitoring' }" class="link-card">
            <span class="link-icon">📡</span>
            <span>Live Monitoring</span>
          </RouterLink>
          <RouterLink :to="{ name: 'reports' }" class="link-card">
            <span class="link-icon">📄</span>
            <span>Laporan</span>
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

@media (max-width: 1024px) { .stats-grid, .links-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 640px) { .stats-grid, .links-grid { grid-template-columns: 1fr; } }
</style>
