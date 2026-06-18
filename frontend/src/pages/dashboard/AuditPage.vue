<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import api from '../../lib/axios'

// Helper: get YYYY-MM-DD using local timezone (NOT UTC)
function getLocalDateString(date = new Date()): string {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

const data = ref({
  date: getLocalDateString(),
  shifts: [] as any[],
  areas: [] as any[],
  summary: { total_areas: 0, total_objects: 0, total_checked: 0, total_cells: 0, completion_rate: 0 }
})
const loading = ref(true)
const selectedDate = ref(getLocalDateString())
const expandedAreaId = ref<number | null>(null)

const dynamicColspan = computed(() => {
  return data.value.shifts.length + 1
})

const areasWithStatus = computed(() => {
  return data.value.areas.map((area: any) => {
    let statusColor = 'none'
    if (area.completion_rate >= 100) statusColor = 'complete'
    else if (area.completion_rate > 0) statusColor = 'partial'
    else if (area.total_cells > 0) statusColor = 'empty'
    return { ...area, statusColor }
  })
})

async function loadData() {
  loading.value = true
  try {
    const response = await api.get('/api/v1/dashboard/audit-grid', {
      params: { date: selectedDate.value }
    })
    data.value = response.data.data
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

function toggleArea(areaId: number) {
  expandedAreaId.value = expandedAreaId.value === areaId ? null : areaId
}

function getProgressColor(rate: number): string {
  if (rate >= 100) return 'hsl(var(--success))'
  if (rate >= 50) return 'hsl(var(--warning))'
  if (rate > 0) return 'hsl(var(--destructive))'
  return 'hsl(var(--muted-foreground))'
}

onMounted(() => {
  loadData()
})
</script>

<template>
  <div class="audit-grid-page animate-fade-in">
    <!-- Header -->
    <div class="page-header">
      <div>
        <h1 class="page-title">
          <span class="title-icon">📋</span>
          Audit Grid
        </h1>
        <p class="page-subtitle">Monitoring detail kebersihan per objek dan shift</p>
      </div>
      <div class="header-actions">
        <input
          type="date"
          v-model="selectedDate"
          @change="loadData"
          class="input date-input"
        />
        <button class="btn btn-secondary" @click="loadData">
          <span>🔄</span> Refresh
        </button>
      </div>
    </div>

    <!-- Summary Cards -->
    <div class="summary-grid">
      <div class="summary-card animate-slide-up stagger-1">
        <div class="summary-icon summary-icon-areas">🏢</div>
        <div class="summary-info">
          <span class="summary-value">{{ data.summary.total_areas }}</span>
          <span class="summary-label">Total Area</span>
        </div>
      </div>
      <div class="summary-card animate-slide-up stagger-2">
        <div class="summary-icon summary-icon-objects">📦</div>
        <div class="summary-info">
          <span class="summary-value">{{ data.summary.total_objects }}</span>
          <span class="summary-label">Total Objek</span>
        </div>
      </div>
      <div class="summary-card animate-slide-up stagger-3">
        <div class="summary-icon summary-icon-checked">✅</div>
        <div class="summary-info">
          <span class="summary-value">{{ data.summary.total_checked }}</span>
          <span class="summary-label">Terceklis</span>
        </div>
      </div>
      <div class="summary-card animate-slide-up stagger-4">
        <div class="summary-icon summary-icon-rate">📊</div>
        <div class="summary-info">
          <span class="summary-value">{{ data.summary.completion_rate }}%</span>
          <span class="summary-label">Completion Rate</span>
        </div>
        <div class="summary-ring">
          <svg viewBox="0 0 36 36">
            <circle cx="18" cy="18" r="15.5" fill="none" stroke="hsl(var(--border))" stroke-width="3"/>
            <circle
              cx="18" cy="18" r="15.5" fill="none"
              :stroke="getProgressColor(data.summary.completion_rate)"
              stroke-width="3"
              stroke-linecap="round"
              :stroke-dasharray="`${(data.summary.completion_rate / 100) * 97.4} 97.4`"
              transform="rotate(-90 18 18)"
            />
          </svg>
        </div>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading-state">
      <div class="spinner-large"></div>
      <p>Memuat audit grid...</p>
    </div>

    <!-- Audit Grid Table -->
    <div v-else class="card table-card animate-slide-up">
      <div v-if="areasWithStatus.length === 0" class="empty-state">
        <div class="empty-icon">📋</div>
        <h3>Belum ada data audit</h3>
        <p>Tidak ada aktivitas pembersihan untuk tanggal {{ selectedDate }}.</p>
      </div>

      <div v-else class="table-responsive">
        <table class="audit-table grid-table">
          <thead>
            <tr>
              <th class="sticky-col col-area">Area / Objek</th>
              <th
                v-for="shift in data.shifts"
                :key="shift.id"
                class="text-center col-shift"
              >
                <div class="shift-name">{{ shift.name }}</div>
                <div class="shift-time">{{ shift.start_time }} - {{ shift.end_time }}</div>
              </th>
            </tr>
          </thead>
          <tbody>
            <template v-for="area in areasWithStatus" :key="area.area_id">
              <!-- Area Header Row -->
              <tr class="area-header-row" :class="'area-status-' + area.statusColor" @click="toggleArea(area.area_id)">
                <td :colspan="dynamicColspan" class="area-header-cell">
                  <div class="area-header-content">
                    <div class="area-header-left">
                      <span class="area-expand-icon">{{ expandedAreaId === area.area_id ? '▼' : '▶' }}</span>
                      <span class="area-header-name">{{ area.area_name }}</span>
                      <span class="area-header-code">({{ area.area_code }})</span>
                    </div>
                    <div class="area-header-right">
                      <div class="area-progress-bar">
                        <div
                          class="area-progress-fill"
                          :style="{ width: area.completion_rate + '%', backgroundColor: getProgressColor(area.completion_rate) }"
                        ></div>
                      </div>
                      <span class="area-progress-text" :style="{ color: getProgressColor(area.completion_rate) }">
                        {{ area.completion_rate }}%
                      </span>
                      <span class="area-count">
                        {{ area.total_checked }}/{{ area.total_cells }}
                      </span>
                    </div>
                  </div>
                </td>
              </tr>
              <!-- Object Rows (collapsible) -->
              <template v-if="expandedAreaId === area.area_id || expandedAreaId === null">
                <tr v-for="(obj, idx) in area.objects" :key="`${area.area_id}-${idx}`" class="object-row">
                  <td class="sticky-col object-name-cell">
                    <span class="object-indent">{{ obj.object_name }}</span>
                  </td>
                  <td v-for="shift in data.shifts" :key="shift.id" class="text-center check-cell">
                    <span v-if="obj.shifts[shift.id] === true" class="check-icon">✓</span>
                    <span v-else-if="obj.shifts[shift.id] === false" class="cross-icon">✗</span>
                    <span v-else class="null-icon">-</span>
                  </td>
                </tr>
              </template>
            </template>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<style scoped>
.audit-grid-page {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

/* Header */
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 1rem;
}

.page-title {
  font-size: 1.5rem;
  font-weight: 700;
  color: hsl(var(--foreground));
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin: 0;
}

.title-icon { font-size: 1.75rem; }

.page-subtitle {
  font-size: 0.875rem;
  color: hsl(var(--muted-foreground));
  margin-top: 0.25rem;
}

.header-actions {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.date-input {
  width: auto;
}

/* Summary Cards */
.summary-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 1rem;
}

@media (max-width: 768px) {
  .summary-grid { grid-template-columns: repeat(2, 1fr); }
}

.summary-card {
  background: hsl(var(--card));
  border: 1px solid hsl(var(--border));
  border-radius: var(--radius);
  padding: 1rem 1.25rem;
  display: flex;
  align-items: center;
  gap: 0.875rem;
  transition: all 0.3s ease;
  position: relative;
  overflow: hidden;
}

.summary-card::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 3px;
  opacity: 0;
  transition: opacity 0.3s;
}

.summary-card:hover::before { opacity: 1; }
.summary-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 16px hsl(var(--primary) / 0.08);
}

.summary-card:nth-child(1)::before { background: hsl(var(--primary)); }
.summary-card:nth-child(2)::before { background: hsl(var(--accent)); }
.summary-card:nth-child(3)::before { background: hsl(var(--success)); }
.summary-card:nth-child(4)::before { background: linear-gradient(90deg, hsl(var(--primary)), hsl(var(--success))); }

.summary-icon {
  font-size: 1.5rem;
  width: 2.75rem;
  height: 2.75rem;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 0.625rem;
  flex-shrink: 0;
}

.summary-icon-areas { background: hsl(var(--primary) / 0.12); }
.summary-icon-objects { background: hsl(var(--accent) / 0.12); }
.summary-icon-checked { background: hsl(var(--success) / 0.12); }
.summary-icon-rate { background: hsl(var(--primary) / 0.12); }

.summary-info {
  display: flex;
  flex-direction: column;
  flex: 1;
}

.summary-value {
  font-size: 1.5rem;
  font-weight: 800;
  line-height: 1;
  color: hsl(var(--foreground));
}

.summary-label {
  font-size: 0.6875rem;
  color: hsl(var(--muted-foreground));
  font-weight: 500;
  margin-top: 0.125rem;
}

.summary-ring {
  width: 2.5rem;
  height: 2.5rem;
  flex-shrink: 0;
}

.summary-ring svg {
  width: 100%;
  height: 100%;
}

.summary-ring circle:last-child {
  transition: stroke-dasharray 0.8s ease;
}

/* Table */
.table-card {
  padding: 0;
  overflow: hidden;
}

.table-responsive {
  overflow: auto;
  max-height: 65vh;
}

.grid-table {
  border-collapse: collapse;
}

.col-area {
  width: 14rem;
  min-width: 14rem;
}

.col-shift {
  min-width: 7rem;
  border-left: 1px solid hsl(var(--border));
}

.sticky-col {
  position: sticky;
  left: 0;
  z-index: 5;
  background: hsl(var(--card));
}

thead .sticky-col {
  background: hsl(var(--muted));
  z-index: 6;
}

.shift-name {
  font-weight: 600;
  font-size: 0.8125rem;
}

.shift-time {
  font-size: 0.625rem;
  color: hsl(var(--muted-foreground));
  font-weight: 400;
  margin-top: 0.125rem;
}

.text-center { text-align: center; }

/* Area Header Row */
.area-header-row {
  cursor: pointer;
  transition: background 0.2s;
}

.area-header-row:hover {
  background: hsl(var(--muted) / 0.5) !important;
}

.area-header-cell {
  padding: 0.75rem 1rem !important;
  border-top: 2px solid hsl(var(--border));
  background: hsl(var(--muted) / 0.3);
}

/* Color-coded area status */
.area-status-complete .area-header-cell {
  border-left: 4px solid hsl(var(--success));
}

.area-status-partial .area-header-cell {
  border-left: 4px solid hsl(var(--warning));
}

.area-status-empty .area-header-cell {
  border-left: 4px solid hsl(var(--destructive));
}

.area-status-none .area-header-cell {
  border-left: 4px solid hsl(var(--muted-foreground) / 0.3);
}

.area-header-content {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
}

.area-header-left {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.area-expand-icon {
  font-size: 0.625rem;
  color: hsl(var(--muted-foreground));
  width: 1rem;
  text-align: center;
  transition: transform 0.2s;
}

.area-header-name {
  font-weight: 700;
  font-size: 0.875rem;
  color: hsl(var(--foreground));
}

.area-header-code {
  font-size: 0.6875rem;
  color: hsl(var(--muted-foreground));
}

.area-header-right {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.area-progress-bar {
  width: 120px;
  height: 6px;
  background: hsl(var(--muted));
  border-radius: 3px;
  overflow: hidden;
}

.area-progress-fill {
  height: 100%;
  border-radius: 3px;
  transition: width 0.5s ease;
}

.area-progress-text {
  font-size: 0.75rem;
  font-weight: 700;
  min-width: 2.5rem;
  text-align: right;
}

.area-count {
  font-size: 0.6875rem;
  color: hsl(var(--muted-foreground));
  background: hsl(var(--muted));
  padding: 0.125rem 0.5rem;
  border-radius: 0.25rem;
  white-space: nowrap;
}

/* Object Rows */
.object-row {
  animation: fadeRowIn 0.2s ease-out;
}

@keyframes fadeRowIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

.object-name-cell {
  padding-left: 2.25rem !important;
  font-size: 0.8125rem;
  background: hsl(var(--card));
}

.object-indent {
  color: hsl(var(--foreground) / 0.85);
}

.check-cell {
  border-left: 1px solid hsl(var(--border));
  background: hsl(var(--card));
}

.check-icon {
  color: hsl(var(--success));
  font-weight: 800;
  font-size: 1.125rem;
}

.cross-icon {
  color: hsl(var(--destructive));
  font-weight: 800;
  font-size: 1.125rem;
}

.null-icon {
  color: hsl(var(--muted-foreground) / 0.4);
  font-size: 0.8125rem;
}

/* Loading & Empty */
.loading-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 4rem 2rem;
  gap: 1rem;
  color: hsl(var(--muted-foreground));
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 4rem 2rem;
  text-align: center;
}

.empty-icon { font-size: 3rem; margin-bottom: 1rem; }
.empty-state h3 { font-size: 1.125rem; font-weight: 600; color: hsl(var(--foreground)); margin: 0 0 0.5rem; }
.empty-state p { font-size: 0.875rem; color: hsl(var(--muted-foreground)); margin: 0; }

.spinner-large {
  width: 3rem; height: 3rem;
  border: 4px solid rgba(255,255,255,0.1);
  border-top-color: hsl(var(--primary));
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin { to { transform: rotate(360deg); } }
</style>
