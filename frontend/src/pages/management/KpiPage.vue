<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '../../lib/axios'

const stats = ref<any>(null)
const loading = ref(true)

const period = ref('month')
const startDate = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0])
const endDate = ref(new Date().toISOString().split('T')[0])

async function loadData() {
  loading.value = true
  try {
    const { data } = await api.get('/api/v1/dashboard/kpi', {
      params: {
        start_date: startDate.value,
        end_date: endDate.value
      }
    })
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

function updatePeriod(p: string) {
  period.value = p
  const today = new Date()
  
  if (p === 'week') {
    const lastWeek = new Date(today.getTime() - 7 * 24 * 60 * 60 * 1000)
    startDate.value = lastWeek.toISOString().split('T')[0]
  } else if (p === 'month') {
    const startOfMonth = new Date(today.getFullYear(), today.getMonth(), 1)
    startDate.value = startOfMonth.toISOString().split('T')[0]
  } else if (p === 'year') {
    const startOfYear = new Date(today.getFullYear(), 0, 1)
    startDate.value = startOfYear.toISOString().split('T')[0]
  }
  
  endDate.value = today.toISOString().split('T')[0]
  loadData()
}
</script>

<template>
  <div class="kpi-page animate-fade-in">
    <div class="flex justify-between items-center mb-6">
      <div>
        <h1 class="text-2xl font-bold">KPI Manajemen</h1>
        <p class="text-muted-foreground">Laporan performa cleaning service rumah sakit</p>
      </div>
      <div class="flex gap-2">
        <button class="btn" :class="period === 'week' ? 'btn-primary' : 'btn-secondary'" @click="updatePeriod('week')">7 Hari</button>
        <button class="btn" :class="period === 'month' ? 'btn-primary' : 'btn-secondary'" @click="updatePeriod('month')">Bulan Ini</button>
        <button class="btn" :class="period === 'year' ? 'btn-primary' : 'btn-secondary'" @click="updatePeriod('year')">Tahun Ini</button>
      </div>
    </div>

    <div v-if="loading" class="flex justify-center py-12">
      <div class="spinner-large"></div>
    </div>

    <template v-else-if="stats">
      <!-- Top Level Metrics -->
      <div class="stats-grid mb-6">
        <div class="card-stat animate-slide-up stagger-1">
          <div class="stat-header">
            <span class="stat-title">Tingkat Penyelesaian</span>
            <span class="stat-icon text-success">🎯</span>
          </div>
          <div class="stat-value text-success">{{ stats.completion_rate }}%</div>
          <p class="stat-desc">{{ stats.completed_activities }} dari {{ stats.total_activities }} aktivitas selesai</p>
        </div>

        <div class="card-stat animate-slide-up stagger-2">
          <div class="stat-header">
            <span class="stat-title">Kepatuhan SLA (Tepat Waktu)</span>
            <span class="stat-icon text-primary">⏱️</span>
          </div>
          <div class="stat-value text-primary">{{ stats.sla_compliance }}%</div>
          <p class="stat-desc">{{ stats.total_activities - stats.late_activities }} aktivitas tepat waktu</p>
        </div>

        <div class="card-stat animate-slide-up stagger-3">
          <div class="stat-header">
            <span class="stat-title">Rata-rata Skor Audit</span>
            <span class="stat-icon text-accent">⭐</span>
          </div>
          <div class="stat-value text-accent">{{ stats.avg_audit_score }}</div>
          <p class="stat-desc">Dari skor maksimum 100</p>
        </div>

        <div class="card-stat animate-slide-up stagger-4">
          <div class="stat-header">
            <span class="stat-title">Penyelesaian Komplain</span>
            <span class="stat-icon text-warning">🔧</span>
          </div>
          <div class="stat-value text-warning">{{ stats.complaint_resolution_rate }}%</div>
          <p class="stat-desc">{{ stats.resolved_complaints }} dari {{ stats.total_complaints }} komplain diselesaikan</p>
        </div>
      </div>

      <!-- Trend Chart (Using simple CSS bars for mockup) -->
      <div class="card mb-6 animate-slide-up stagger-5">
        <h2 class="font-bold mb-4">Tren Harian (Aktivitas Selesai vs Total)</h2>
        <div class="chart-container">
          <div class="bar-chart">
            <div v-for="(day, idx) in stats.daily_trend" :key="idx" class="bar-group" :title="`${day.day}: ${day.completed} / ${day.total}`">
              <div class="bar bar-bg" :style="{ height: '100%' }">
                <div class="bar bar-fill bg-primary" :style="{ height: `${day.total > 0 ? (day.completed / day.total) * 100 : 0}%` }"></div>
              </div>
              <span class="bar-label">{{ new Date(day.day).getDate() }}</span>
            </div>
            <div v-if="stats.daily_trend.length === 0" class="w-full text-center py-8 text-muted-foreground">
              Tidak ada data pada periode ini
            </div>
          </div>
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

.chart-container {
  height: 250px;
  width: 100%;
  display: flex;
  align-items: flex-end;
  padding-top: 1rem;
}

.bar-chart {
  display: flex;
  height: 100%;
  width: 100%;
  align-items: flex-end;
  justify-content: space-between;
  gap: 4px;
}

.bar-group {
  flex: 1;
  height: calc(100% - 20px);
  display: flex;
  flex-direction: column;
  justify-content: flex-end;
  align-items: center;
}

.bar-bg {
  width: 100%;
  max-width: 40px;
  background-color: hsl(var(--muted));
  border-radius: 4px 4px 0 0;
  position: relative;
  overflow: hidden;
}

.bar-fill {
  width: 100%;
  position: absolute;
  bottom: 0;
  left: 0;
  border-radius: 4px 4px 0 0;
  transition: height 1s ease-out;
}

.bar-label {
  font-size: 0.75rem;
  color: hsl(var(--muted-foreground));
  margin-top: 4px;
  text-align: center;
}

.bg-primary { background-color: hsl(var(--primary)); }

/* Utils */
.flex { display: flex; }
.justify-between { justify-content: space-between; }
.justify-center { justify-content: center; }
.items-center { align-items: center; }
.gap-2 { gap: 0.5rem; }
.mb-4 { margin-bottom: 1rem; }
.mb-6 { margin-bottom: 1.5rem; }
.py-12 { padding-top: 3rem; padding-bottom: 3rem; }
.text-2xl { font-size: 1.5rem; line-height: 2rem; }
.font-bold { font-weight: 700; }
.text-success { color: hsl(var(--success)); }
.text-primary { color: hsl(var(--primary)); }
.text-accent { color: hsl(var(--accent)); }
.text-warning { color: hsl(var(--warning)); }
.text-muted-foreground { color: hsl(var(--muted-foreground)); }

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

@media (max-width: 1024px) {
  .stats-grid { grid-template-columns: repeat(2, 1fr); }
}
</style>
