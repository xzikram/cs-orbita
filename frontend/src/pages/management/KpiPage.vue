<script setup lang="ts">
import { ref, onMounted, nextTick } from 'vue'
import api from '../../lib/axios'

const stats = ref<any>(null)
const loading = ref(true)
const period = ref('month')
const animated = ref(false)

function formatLocalDate(date: Date): string {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

const today = new Date()
const startOfMonth = new Date(today.getFullYear(), today.getMonth(), 1)
const startDate = ref(formatLocalDate(startOfMonth))
const endDate = ref(formatLocalDate(today))

async function loadData() {
  loading.value = true
  animated.value = false
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
    // Delay animation trigger so progress bars animate from 0
    await nextTick()
    setTimeout(() => {
      animated.value = true
    }, 100)
  }
}

onMounted(() => {
  loadData()
})

function updatePeriod(p: string) {
  period.value = p
  const now = new Date()
  
  if (p === 'week') {
    const lastWeek = new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000)
    startDate.value = formatLocalDate(lastWeek)
  } else if (p === 'month') {
    const som = new Date(now.getFullYear(), now.getMonth(), 1)
    startDate.value = formatLocalDate(som)
  } else if (p === 'year') {
    const soy = new Date(now.getFullYear(), 0, 1)
    startDate.value = formatLocalDate(soy)
  }
  
  endDate.value = formatLocalDate(now)
  loadData()
}

function getFormattedDate(dStr: string) {
  try {
    const d = new Date(dStr)
    return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' })
  } catch {
    return dStr
  }
}

function formatPeriodLabel(dStr: string) {
  try {
    const d = new Date(dStr)
    return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })
  } catch {
    return dStr
  }
}

function getMaxDailyTrend(trend: any[]) {
  if (!trend || trend.length === 0) return 10
  return Math.max(...trend.map(t => Math.max(t.total, t.completed)), 10)
}

// Tooltip for bar chart
const hoveredBar = ref<{ show: boolean; x: number; y: number; data: any }>({
  show: false, x: 0, y: 0, data: null
})

function showBarTooltip(event: MouseEvent, day: any) {
  const rect = (event.currentTarget as HTMLElement).getBoundingClientRect()
  hoveredBar.value = {
    show: true,
    x: rect.left + rect.width / 2,
    y: rect.top - 8,
    data: day
  }
}

function hideBarTooltip() {
  hoveredBar.value.show = false
}
</script>

<template>
  <div class="kpi-page animate-fade-in">
    <!-- Header -->
    <div class="kpi-header">
      <div>
        <h1 class="page-title">KPI & Performa</h1>
        <p class="page-subtitle">Analisis pencapaian kebersihan dan kepatuhan operasional.</p>
      </div>
      <div class="header-actions">
        <!-- Active Period Badge -->
        <div class="period-badge" v-if="stats && !loading">
          <span class="period-badge-icon">📅</span>
          <span class="period-badge-text">
            {{ formatPeriodLabel(startDate) }} — {{ formatPeriodLabel(endDate) }}
          </span>
        </div>
        <button class="btn btn-secondary hide-on-mobile" @click="loadData" :disabled="loading">
          {{ loading ? 'Memuat...' : '🔄 Refresh' }}
        </button>
      </div>
    </div>

    <!-- Date Range & Preset Filter Card -->
    <div class="card filter-card mb-6 animate-slide-up">
      <div class="filter-wrapper">
        <div class="date-pickers">
          <div class="form-group">
            <label class="label">Dari Tanggal</label>
            <input type="date" v-model="startDate" class="input input-date" @change="period = 'custom'; loadData()" />
          </div>
          <div class="form-group">
            <label class="label">Sampai Tanggal</label>
            <input type="date" v-model="endDate" class="input input-date" @change="period = 'custom'; loadData()" />
          </div>
        </div>
        <div class="preset-buttons">
          <label class="label hidden-mobile">&nbsp;</label>
          <div class="preset-group">
            <button class="btn preset-btn" :class="period === 'week' ? 'btn-primary' : 'btn-secondary'" @click="updatePeriod('week')">7 Hari</button>
            <button class="btn preset-btn" :class="period === 'month' ? 'btn-primary' : 'btn-secondary'" @click="updatePeriod('month')">Bulan Ini</button>
            <button class="btn preset-btn" :class="period === 'year' ? 'btn-primary' : 'btn-secondary'" @click="updatePeriod('year')">Tahun Ini</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Loading Spinner -->
    <div v-if="loading" class="loading-container">
      <div class="spinner-large"></div>
      <p class="loading-text">Memuat data KPI...</p>
    </div>

    <!-- Main Content -->
    <template v-else-if="stats">
      <!-- Top Stats Grid -->
      <div class="stats-grid mb-6">
        <div class="card-stat animate-slide-up gradient-1">
          <div class="stat-header">
            <span class="stat-title">Penyelesaian</span>
            <span class="stat-icon">🎯</span>
          </div>
          <div class="stat-value color-success">{{ stats.completion_rate }}%</div>
          <div class="progress-bg mt-2">
            <div class="progress-fill fill-success" :style="{ width: animated ? `${stats.completion_rate}%` : '0%' }"></div>
          </div>
          <p class="stat-desc mt-2"><b>{{ stats.completed_activities }}</b> dari {{ stats.total_activities }} aktivitas selesai</p>
        </div>

        <div class="card-stat animate-slide-up gradient-3">
          <div class="stat-header">
            <span class="stat-title">Skor Audit</span>
            <span class="stat-icon">⭐</span>
          </div>
          <div class="stat-value color-accent">{{ stats.avg_audit_score }}</div>
          <div class="progress-bg mt-2">
            <div class="progress-fill fill-accent" :style="{ width: animated ? `${stats.avg_audit_score}%` : '0%' }"></div>
          </div>
          <p class="stat-desc mt-2">Nilai rata-rata dari skor maksimal 100</p>
        </div>

        <div class="card-stat animate-slide-up gradient-4">
          <div class="stat-header">
            <span class="stat-title">Resolusi Komplain</span>
            <span class="stat-icon">🔧</span>
          </div>
          <div class="stat-value color-warning">{{ stats.complaint_resolution_rate }}%</div>
          <div class="progress-bg mt-2">
            <div class="progress-fill fill-warning" :style="{ width: animated ? `${stats.complaint_resolution_rate}%` : '0%' }"></div>
          </div>
          <p class="stat-desc mt-2"><b>{{ stats.resolved_complaints }}</b> dari {{ stats.total_complaints }} komplain diselesaikan</p>
        </div>
      </div>

      <!-- Detail Visualizations Grid -->
      <div class="details-grid mb-6 animate-slide-up">
        <!-- Daily Trend Card -->
        <div class="card trend-card">
          <div class="card-header-flex">
            <h2 class="section-title">Tren Penyelesaian vs Total Rencana</h2>
            <div class="chart-legend">
              <span class="legend-item"><span class="legend-color total"></span> Total</span>
              <span class="legend-item"><span class="legend-color completed"></span> Selesai</span>
            </div>
          </div>
          
          <div class="chart-container mt-4">
            <div class="bar-chart-premium" v-if="stats.daily_trend && stats.daily_trend.length > 0">
              <div
                v-for="(day, idx) in stats.daily_trend"
                :key="idx"
                class="bar-group-premium"
                @mouseenter="showBarTooltip($event, day)"
                @mouseleave="hideBarTooltip"
              >
                <div class="stacked-bars-wrapper">
                  <!-- Total Bar (Left) -->
                  <div class="bar-bar total-bar" :style="{ height: animated ? `${(day.total / getMaxDailyTrend(stats.daily_trend)) * 100}%` : '0%' }">
                    <span class="bar-val-hint">{{ day.total }}</span>
                  </div>
                  <!-- Completed Bar (Right) -->
                  <div class="bar-bar completed-bar" :style="{ height: animated ? `${(day.completed / getMaxDailyTrend(stats.daily_trend)) * 100}%` : '0%' }">
                    <span class="bar-val-hint">{{ day.completed }}</span>
                  </div>
                </div>
                <span class="bar-label">{{ getFormattedDate(day.day) }}</span>
              </div>
            </div>

            <!-- Empty Daily Trend State -->
            <div v-else class="empty-chart-standalone">
              <span class="empty-icon">📅</span>
              <p class="empty-text">Tidak ada data tren untuk periode ini</p>
              <p class="empty-hint">Coba pilih rentang tanggal yang berbeda</p>
            </div>
          </div>
        </div>

        <!-- Leaderboards Section -->
        <div class="card leaderboards-card">
          <h2 class="section-title mb-4">🏆 Pemantauan Operasional</h2>
          
          <!-- Top Staff Leaderboard -->
          <div class="leaderboard-section mb-6">
            <h3 class="leaderboard-title">🌟 Kinerja Staf Terbaik</h3>
            <div class="leaderboard-list">
              <div v-for="(staff, idx) in stats.top_staff" :key="idx" class="leaderboard-item">
                <div class="item-rank" :class="`rank-${Number(idx) + 1}`">{{ Number(idx) + 1 }}</div>
                <div class="item-details">
                  <div class="item-name">{{ staff.name }}</div>
                  <div class="item-id">NIP: {{ staff.employee_id }}</div>
                </div>
                <div class="item-score color-success font-bold">{{ staff.completed }} Selesai</div>
              </div>
              <div v-if="!stats.top_staff || stats.top_staff.length === 0" class="empty-leaderboard">
                <span class="empty-lb-icon">👤</span>
                <p>Belum ada data aktivitas staf.</p>
              </div>
            </div>
          </div>

          <!-- Bottom Areas Leaderboard -->
          <div class="leaderboard-section">
            <h3 class="leaderboard-title">⚠️ Area Perlu Perhatian (Rasio Terendah)</h3>
            <div class="leaderboard-list">
              <div v-for="(area, idx) in stats.bottom_areas" :key="idx" class="leaderboard-item">
                <div class="item-rank rank-warn">{{ Number(idx) + 1 }}</div>
                <div class="item-details">
                  <div class="item-name">{{ area.name }}</div>
                  <div class="item-id">Kode: {{ area.code }}</div>
                </div>
                <div class="item-score-bar-wrapper">
                  <span class="item-score color-destructive font-bold">{{ area.rate }}%</span>
                  <div class="mini-progress">
                    <div class="mini-progress-fill bg-destructive" :style="{ width: `${area.rate}%` }"></div>
                  </div>
                </div>
              </div>
              <div v-if="!stats.bottom_areas || stats.bottom_areas.length === 0" class="empty-leaderboard">
                <span class="empty-lb-icon">✨</span>
                <p>Semua area dalam performa sempurna.</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- Bar Chart Tooltip -->
    <Teleport to="body">
      <Transition name="tooltip-fade">
        <div
          v-if="hoveredBar.show && hoveredBar.data"
          class="chart-tooltip"
          :style="{ left: `${hoveredBar.x}px`, top: `${hoveredBar.y}px` }"
        >
          <div class="tooltip-date">{{ getFormattedDate(hoveredBar.data.day) }}</div>
          <div class="tooltip-row">
            <span class="tooltip-dot total"></span>
            <span>Total: <b>{{ hoveredBar.data.total }}</b></span>
          </div>
          <div class="tooltip-row">
            <span class="tooltip-dot completed"></span>
            <span>Selesai: <b>{{ hoveredBar.data.completed }}</b></span>
          </div>
          <div class="tooltip-row" v-if="hoveredBar.data.late > 0">
            <span class="tooltip-dot late"></span>
            <span>Terlambat: <b>{{ hoveredBar.data.late }}</b></span>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<style scoped>
/* ===== Page Header ===== */
.kpi-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1.5rem;
  gap: 1rem;
}

.page-title {
  font-size: 1.5rem;
  font-weight: 700;
  line-height: 2rem;
  color: hsl(var(--foreground));
  margin: 0;
}

.page-subtitle {
  color: hsl(var(--muted-foreground));
  font-size: 0.875rem;
  margin-top: 0.25rem;
}

.header-actions {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  flex-shrink: 0;
}

/* Period Badge */
.period-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  padding: 0.375rem 0.75rem;
  background: hsl(var(--primary) / 0.08);
  border: 1px solid hsl(var(--primary) / 0.2);
  border-radius: 9999px;
  font-size: 0.75rem;
  color: hsl(var(--primary));
  font-weight: 500;
  white-space: nowrap;
}

.period-badge-icon {
  font-size: 0.8rem;
}

/* ===== Filter Card ===== */
.filter-card {
  background: hsl(var(--card) / 0.95);
  border: 1px solid hsl(var(--border));
  backdrop-filter: blur(10px);
}

.filter-wrapper {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  gap: 1.5rem;
}

.date-pickers {
  display: flex;
  gap: 1rem;
  flex: 1;
}

.preset-buttons {
  display: flex;
  flex-direction: column;
}

.preset-group {
  display: flex;
  gap: 0.5rem;
}

.input-date {
  max-width: 200px;
}

/* ===== Loading ===== */
.loading-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 4rem 0;
  gap: 1rem;
}

.loading-text {
  font-size: 0.875rem;
  color: hsl(var(--muted-foreground));
  animation: pulse 1.5s ease-in-out infinite;
}

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.5; }
}

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

/* ===== Stats Card Custom Designs ===== */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1.5rem;
}

.card-stat {
  padding: 1.5rem;
  border-radius: 1rem;
  border: 1px solid transparent;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
  position: relative;
  overflow: hidden;
}

.card-stat:hover {
  transform: translateY(-4px);
  box-shadow: 0 12px 30px rgba(0, 0, 0, 0.2);
}

.gradient-1 { background: linear-gradient(135deg, hsl(142, 70%, 15%), hsl(142, 60%, 5%)); border-color: hsl(142, 70%, 25% / 0.4); }
.gradient-2 { background: linear-gradient(135deg, hsl(210, 80%, 15%), hsl(210, 70%, 5%)); border-color: hsl(210, 80%, 25% / 0.4); }
.gradient-3 { background: linear-gradient(135deg, hsl(262, 70%, 15%), hsl(262, 60%, 5%)); border-color: hsl(262, 70%, 25% / 0.4); }
.gradient-4 { background: linear-gradient(135deg, hsl(38, 70%, 15%), hsl(38, 60%, 5%)); border-color: hsl(38, 70%, 25% / 0.4); }

.stat-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.75rem;
}

.stat-title {
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: hsl(var(--muted-foreground));
}

.stat-icon {
  font-size: 1.5rem;
}

.stat-value {
  font-size: 2.25rem;
  font-weight: 800;
  line-height: 1;
  font-family: var(--font-mono);
}

.progress-bg {
  height: 0.375rem;
  background: rgba(255, 255, 255, 0.1);
  border-radius: 9999px;
  overflow: hidden;
}

.progress-fill {
  height: 100%;
  border-radius: 9999px;
  transition: width 1.2s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.fill-success { background: hsl(var(--success)); }
.fill-primary { background: hsl(var(--primary)); }
.fill-accent { background: hsl(var(--accent)); }
.fill-warning { background: hsl(var(--warning)); }

.stat-desc {
  font-size: 0.8rem;
  color: hsl(var(--muted-foreground));
}

/* ===== Color Utilities ===== */
.color-success { color: hsl(var(--success)); }
.color-primary { color: hsl(var(--primary)); }
.color-accent { color: hsl(var(--accent)); }
.color-warning { color: hsl(var(--warning)); }
.color-destructive { color: hsl(var(--destructive)); }
.font-bold { font-weight: 700; }

/* ===== Section Title ===== */
.section-title {
  font-size: 1rem;
  font-weight: 700;
  color: hsl(var(--foreground));
  margin: 0;
}

/* ===== Details Visualizations Grid ===== */
.details-grid {
  display: grid;
  grid-template-columns: 3fr 2fr;
  gap: 1.5rem;
  align-items: stretch;
}

.trend-card {
  display: flex;
  flex-direction: column;
}

.card-header-flex {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.chart-legend {
  display: flex;
  gap: 1rem;
  font-size: 0.75rem;
  color: hsl(var(--muted-foreground));
}

.legend-item {
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.legend-color {
  width: 0.75rem;
  height: 0.75rem;
  border-radius: 0.25rem;
  display: inline-block;
}
.legend-color.total { background: hsl(var(--muted)); }
.legend-color.completed { background: hsl(var(--primary)); }

.chart-container {
  height: 320px;
  width: 100%;
  display: flex;
  flex-direction: column;
  justify-content: flex-end;
  position: relative;
}

.bar-chart-premium {
  display: flex;
  height: 100%;
  width: 100%;
  align-items: flex-end;
  justify-content: space-between;
  gap: 0.5rem;
  position: relative;
  border-bottom: 1px solid hsl(var(--border));
  padding-bottom: 0.5rem;
}

.bar-group-premium {
  flex: 1;
  height: calc(100% - 20px);
  display: flex;
  flex-direction: column;
  justify-content: flex-end;
  align-items: center;
  position: relative;
  cursor: pointer;
}

.bar-group-premium:hover .bar-bar {
  filter: brightness(1.2);
}

.stacked-bars-wrapper {
  display: flex;
  align-items: flex-end;
  gap: 2px;
  width: 100%;
  max-width: 40px;
  height: 100%;
}

.bar-bar {
  flex: 1;
  border-radius: 4px 4px 0 0;
  transition: height 0.8s cubic-bezier(0.34, 1.56, 0.64, 1), filter 0.2s ease;
  position: relative;
  display: flex;
  justify-content: center;
  min-height: 2px;
}

.total-bar {
  background: hsl(var(--muted));
}

.completed-bar {
  background: linear-gradient(180deg, hsl(var(--primary)), hsl(var(--primary) / 0.5));
}

.bar-val-hint {
  position: absolute;
  top: -1.25rem;
  font-size: 0.65rem;
  font-weight: 600;
  opacity: 0;
  transform: translateY(4px);
  transition: all 0.2s ease;
  pointer-events: none;
  color: hsl(var(--foreground));
}

.bar-group-premium:hover .bar-val-hint {
  opacity: 1;
  transform: translateY(0);
}

.bar-label {
  font-size: 0.65rem;
  color: hsl(var(--muted-foreground));
  margin-top: 0.5rem;
  white-space: nowrap;
}

/* ===== Empty State — Standalone ===== */
.empty-chart-standalone {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 100%;
  color: hsl(var(--muted-foreground));
  gap: 0.5rem;
}

.empty-icon {
  font-size: 3rem;
}

.empty-text {
  font-size: 0.9375rem;
  font-weight: 500;
}

.empty-hint {
  font-size: 0.8125rem;
  opacity: 0.7;
}

/* ===== Chart Tooltip ===== */
.chart-tooltip {
  position: fixed;
  z-index: 9999;
  transform: translate(-50%, -100%);
  background: hsl(var(--card));
  border: 1px solid hsl(var(--border));
  border-radius: 0.5rem;
  padding: 0.625rem 0.875rem;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
  pointer-events: none;
  min-width: 140px;
}

.tooltip-date {
  font-size: 0.75rem;
  font-weight: 700;
  margin-bottom: 0.375rem;
  color: hsl(var(--foreground));
  border-bottom: 1px solid hsl(var(--border));
  padding-bottom: 0.25rem;
}

.tooltip-row {
  display: flex;
  align-items: center;
  gap: 0.375rem;
  font-size: 0.75rem;
  color: hsl(var(--muted-foreground));
  line-height: 1.6;
}

.tooltip-dot {
  width: 8px;
  height: 8px;
  border-radius: 2px;
  flex-shrink: 0;
}

.tooltip-dot.total { background: hsl(var(--muted-foreground)); }
.tooltip-dot.completed { background: hsl(var(--primary)); }
.tooltip-dot.late { background: hsl(var(--destructive)); }

.tooltip-fade-enter-active,
.tooltip-fade-leave-active {
  transition: opacity 0.15s ease;
}
.tooltip-fade-enter-from,
.tooltip-fade-leave-to {
  opacity: 0;
}

/* ===== Leaderboards styling ===== */
.leaderboards-card {
  display: flex;
  flex-direction: column;
}

.leaderboard-title {
  font-size: 0.875rem;
  font-weight: 700;
  color: hsl(var(--foreground));
  margin-bottom: 0.75rem;
  border-bottom: 1px solid hsl(var(--border));
  padding-bottom: 0.25rem;
}

.leaderboard-list {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.leaderboard-item {
  display: flex;
  align-items: center;
  padding: 0.625rem;
  background: hsl(var(--muted) / 0.4);
  border: 1px solid hsl(var(--border) / 0.5);
  border-radius: 0.5rem;
  gap: 0.75rem;
  transition: background 0.2s ease;
}

.leaderboard-item:hover {
  background: hsl(var(--muted) / 0.8);
}

.item-rank {
  width: 1.5rem;
  height: 1.5rem;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 800;
  font-size: 0.75rem;
  background: hsl(var(--card));
  border: 1px solid hsl(var(--border));
  flex-shrink: 0;
}

.rank-1 { background: #ffd700; color: #000; border-color: #ffd700; }
.rank-2 { background: #c0c0c0; color: #000; border-color: #c0c0c0; }
.rank-3 { background: #cd7f32; color: #000; border-color: #cd7f32; }

.rank-warn {
  color: hsl(var(--destructive));
  border-color: hsl(var(--destructive) / 0.5);
  background: hsl(var(--destructive) / 0.1);
}

.item-details {
  flex: 1;
  min-width: 0;
}

.item-name {
  font-size: 0.875rem;
  font-weight: 600;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.item-id {
  font-size: 0.7rem;
  color: hsl(var(--muted-foreground));
}

.item-score-bar-wrapper {
  text-align: right;
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 0.25rem;
}

.item-score {
  font-size: 0.8125rem;
}

.mini-progress {
  width: 60px;
  height: 4px;
  background: hsl(var(--muted));
  border-radius: 9999px;
  overflow: hidden;
}

.mini-progress-fill {
  height: 100%;
  transition: width 0.8s ease;
}

.bg-destructive {
  background: hsl(var(--destructive));
}

.empty-leaderboard {
  text-align: center;
  padding: 1.5rem;
  color: hsl(var(--muted-foreground));
  font-size: 0.8125rem;
  border: 1px dashed hsl(var(--border));
  border-radius: 0.5rem;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.375rem;
}

.empty-lb-icon {
  font-size: 1.5rem;
  opacity: 0.5;
}

/* ===== Spacing Utilities ===== */
.mb-4 { margin-bottom: 1rem; }
.mb-6 { margin-bottom: 1.5rem; }
.mt-2 { margin-top: 0.5rem; }
.mt-4 { margin-top: 1rem; }

/* ===== Responsiveness ===== */
@media (max-width: 1200px) {
  .stats-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 1024px) {
  .details-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 768px) {
  .kpi-header {
    flex-direction: column;
    align-items: stretch;
  }

  .header-actions {
    flex-direction: column;
    align-items: stretch;
  }

  .period-badge {
    justify-content: center;
  }

  .stats-grid {
    grid-template-columns: 1fr;
  }

  .filter-wrapper {
    flex-direction: column;
    align-items: stretch;
  }

  .date-pickers {
    flex-direction: column;
  }

  .preset-group {
    width: 100%;
  }

  .preset-group .btn {
    flex: 1;
  }

  .input-date {
    max-width: 100%;
  }

  .hidden-mobile {
    display: none;
  }
}
</style>
