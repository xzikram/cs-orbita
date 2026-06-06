<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import api from '../../lib/axios'

const stats = ref({
  total_areas: 0,
  total_activities: 0,
  areas_completed: 0,
  areas_pending: 0,
  areas_late: 0,
  completion_rate: 0,
  recent_activities: [] as any[]
})
const loading = ref(true)
let interval: number

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
  interval = window.setInterval(loadData, 60000) // refresh every minute
})

onUnmounted(() => {
  clearInterval(interval)
})
</script>

<template>
  <div class="overview-page animate-fade-in">
    <div class="flex justify-between items-center mb-6">
      <div>
        <h1 class="text-2xl font-bold">Command Center</h1>
        <p class="text-muted-foreground">Ringkasan operasional hari ini</p>
      </div>
      <button class="btn btn-secondary" @click="loadData">
        <span>🔄</span> Refresh
      </button>
    </div>

    <div v-if="loading" class="flex justify-center py-12">
      <div class="spinner-large"></div>
    </div>

    <template v-else>
      <div class="stats-grid mb-6">
        <div class="card-stat animate-slide-up stagger-1">
          <div class="stat-header">
            <span class="stat-title">Progress Area</span>
            <span class="stat-icon text-primary">📊</span>
          </div>
          <div class="stat-value">{{ stats.completion_rate }}%</div>
          <p class="stat-desc">{{ stats.areas_completed }} dari {{ stats.total_areas }} area selesai</p>
          <div class="progress-bg mt-3">
            <div class="progress-fill" :style="{ width: `${stats.completion_rate}%` }"></div>
          </div>
        </div>

        <div class="card-stat animate-slide-up stagger-2">
          <div class="stat-header">
            <span class="stat-title">Area Pending</span>
            <span class="stat-icon text-warning">⏳</span>
          </div>
          <div class="stat-value">{{ stats.areas_pending }}</div>
          <p class="stat-desc text-warning">Belum dibersihkan</p>
        </div>

        <div class="card-stat animate-slide-up stagger-3">
          <div class="stat-header">
            <span class="stat-title">Terlambat</span>
            <span class="stat-icon text-destructive">⚠️</span>
          </div>
          <div class="stat-value">{{ stats.areas_late }}</div>
          <p class="stat-desc text-destructive">Melewati batas waktu SLA</p>
        </div>
      </div>

      <div class="card p-0 overflow-hidden animate-slide-up stagger-4">
        <div class="p-4 border-b border-border flex justify-between items-center">
          <h2 class="font-bold">Aktivitas Terbaru</h2>
          <RouterLink :to="{ name: 'monitoring' }" class="text-sm text-primary hover:underline">Lihat Semua</RouterLink>
        </div>
        <div class="table-responsive">
          <table class="audit-table">
            <thead>
              <tr>
                <th>Waktu</th>
                <th>Area</th>
                <th>Petugas</th>
                <th>Shift</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="act in stats.recent_activities" :key="act.id">
                <td>{{ act.time }}</td>
                <td class="font-medium">{{ act.area }}</td>
                <td>{{ act.user }}</td>
                <td>{{ act.shift }}</td>
                <td>
                  <span class="badge" :class="act.status === 'completed' ? 'badge-success' : 'badge-warning'">
                    {{ act.status === 'completed' ? 'Selesai' : 'Pending' }}
                  </span>
                  <span v-if="act.is_late" class="badge badge-destructive ml-2">Telat</span>
                </td>
              </tr>
              <tr v-if="stats.recent_activities.length === 0">
                <td colspan="5" class="text-center py-4 text-muted-foreground">Belum ada aktivitas hari ini</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
.stats-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1.5rem;
}

.stat-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.5rem;
}

.stat-title {
  font-size: 0.875rem;
  font-weight: 500;
  color: hsl(var(--muted-foreground));
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.stat-icon {
  font-size: 1.25rem;
}

.stat-value {
  font-size: 2.5rem;
  font-weight: 700;
  line-height: 1.2;
  margin-bottom: 0.25rem;
}

.stat-desc {
  font-size: 0.875rem;
  color: hsl(var(--muted-foreground));
}

.progress-bg {
  height: 0.5rem;
  background: hsl(var(--muted));
  border-radius: 9999px;
  overflow: hidden;
}

.progress-fill {
  height: 100%;
  background: linear-gradient(90deg, hsl(var(--primary)), hsl(var(--accent)));
  transition: width 1s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.table-responsive {
  overflow-x: auto;
}

/* Utils */
.flex { display: flex; }
.justify-between { justify-content: space-between; }
.justify-center { justify-content: center; }
.items-center { align-items: center; }
.mb-6 { margin-bottom: 1.5rem; }
.mt-3 { margin-top: 0.75rem; }
.ml-2 { margin-left: 0.5rem; }
.py-12 { padding-top: 3rem; padding-bottom: 3rem; }
.py-4 { padding-top: 1rem; padding-bottom: 1rem; }
.p-0 { padding: 0; }
.p-4 { padding: 1rem; }
.text-2xl { font-size: 1.5rem; line-height: 2rem; }
.text-sm { font-size: 0.875rem; line-height: 1.25rem; }
.text-center { text-align: center; }
.font-bold { font-weight: 700; }
.font-medium { font-weight: 500; }
.text-primary { color: hsl(var(--primary)); }
.text-warning { color: hsl(var(--warning)); }
.text-destructive { color: hsl(var(--destructive)); }
.text-muted-foreground { color: hsl(var(--muted-foreground)); }
.border-b { border-bottom-width: 1px; }
.border-border { border-color: hsl(var(--border)); }
.overflow-hidden { overflow: hidden; }
.hover\:underline:hover { text-decoration: underline; }

.spinner-large {
  width: 3rem;
  height: 3rem;
  border: 4px solid rgba(255,255,255,0.1);
  border-top-color: hsl(var(--primary));
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

@media (max-width: 768px) {
  .stats-grid { grid-template-columns: 1fr; }
}
</style>
