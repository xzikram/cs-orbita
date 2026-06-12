<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import api from '../../lib/axios'

const data = ref({
  shifts: [] as any[],
  areas: [] as any[],
  summary: { total_cells: 0, clean: 0, pending: 0, late: 0, none: 0, total_areas: 0 }
})
const loading = ref(true)
const filterBuilding = ref('')
const filterFloor = ref('')
const hoveredCell = ref<{ area: any; shift: any; status: string; x: number; y: number } | null>(null)

const buildings = computed(() => {
  const set = new Set(data.value.areas.map((a: any) => a.building))
  return Array.from(set).filter(Boolean).sort()
})

const floors = computed(() => {
  const list = data.value.areas
    .filter((a: any) => !filterBuilding.value || a.building === filterBuilding.value)
    .map((a: any) => a.floor)
  return Array.from(new Set(list)).filter(Boolean).sort()
})

const filteredAreas = computed(() => {
  return data.value.areas.filter((a: any) => {
    if (filterBuilding.value && a.building !== filterBuilding.value) return false
    if (filterFloor.value && a.floor !== filterFloor.value) return false
    return true
  })
})

const summaryCards = computed(() => {
  const s = data.value.summary
  const total = s.total_cells || 1
  return [
    { icon: '🏥', label: 'Total Area', value: s.total_areas, color: 'primary' },
    { icon: '✅', label: 'Bersih', value: s.clean, pct: Math.round((s.clean / total) * 100), color: 'success' },
    { icon: '⏳', label: 'Pending', value: s.pending, pct: Math.round((s.pending / total) * 100), color: 'warning' },
  ]
})

async function loadData() {
  loading.value = true
  try {
    const response = await api.get('/api/v1/dashboard/heatmap')
    data.value = response.data.data
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

function showTooltip(event: MouseEvent, area: any, shift: any) {
  const status = area.statuses[shift.id]
  hoveredCell.value = {
    area,
    shift,
    status,
    x: event.clientX,
    y: event.clientY,
  }
}

function hideTooltip() {
  hoveredCell.value = null
}

function getStatusLabel(status: string) {
  switch (status) {
    case 'clean': return '✅ Sudah Bersih'
    case 'pending': return '⏳ Sedang Proses'
    default: return '⬜ Belum Dikerjakan'
  }
}

onMounted(() => {
  loadData()
})
</script>

<template>
  <div class="heatmap-page animate-fade-in">
    <!-- Header -->
    <div class="page-header">
      <div>
        <h1 class="page-title">
          <span class="title-icon">🗺️</span>
          Heatmap Area
        </h1>
        <p class="page-subtitle">Peta status kebersihan seluruh rumah sakit hari ini</p>
      </div>
      <button class="btn btn-secondary" @click="loadData">
        <span>🔄</span> Refresh
      </button>
    </div>

    <!-- Summary Cards -->
    <div class="summary-grid">
      <div
        v-for="(card, idx) in summaryCards"
        :key="idx"
        class="summary-card animate-slide-up"
        :class="'summary-' + card.color"
        :style="{ animationDelay: (idx * 0.05) + 's' }"
      >
        <div class="summary-icon">{{ card.icon }}</div>
        <div class="summary-info">
          <span class="summary-value">{{ card.value }}</span>
          <span class="summary-label">{{ card.label }}</span>
        </div>
        <span v-if="card.pct !== undefined" class="summary-pct">{{ card.pct }}%</span>
      </div>
    </div>

    <!-- Filters + Legend -->
    <div class="heatmap-controls animate-slide-up">
      <div class="filter-group">
        <select v-model="filterBuilding" class="input filter-select" @change="filterFloor = ''">
          <option value="">Semua Gedung</option>
          <option v-for="b in buildings" :key="b" :value="b">{{ b }}</option>
        </select>
        <select v-model="filterFloor" class="input filter-select">
          <option value="">Semua Lantai</option>
          <option v-for="f in floors" :key="f" :value="f">{{ f }}</option>
        </select>
      </div>
      <div class="legend">
        <div class="legend-item"><div class="legend-box heatmap-clean"></div> Bersih</div>
        <div class="legend-item"><div class="legend-box heatmap-pending"></div> Pending</div>
        <div class="legend-item"><div class="legend-box heatmap-none"></div> Belum</div>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading-state">
      <div class="spinner-large"></div>
      <p>Memuat data heatmap...</p>
    </div>

    <!-- Heatmap Table -->
    <div v-else class="card table-card animate-slide-up">
      <div v-if="filteredAreas.length === 0" class="empty-state">
        <div class="empty-icon">🏥</div>
        <h3>Tidak ada area ditemukan</h3>
        <p>Coba ubah filter gedung atau lantai.</p>
      </div>
      <div v-else class="table-responsive">
        <table class="audit-table heatmap-table">
          <thead>
            <tr>
              <th class="sticky-col">Area</th>
              <th>Gedung / Lantai</th>
              <th>Kategori</th>
              <th
                v-for="shift in data.shifts"
                :key="shift.id"
                class="text-center shift-header"
              >
                <div class="shift-name">{{ shift.name }}</div>
                <div class="shift-time">{{ shift.start_time }} - {{ shift.end_time }}</div>
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="area in filteredAreas" :key="area.id">
              <td class="sticky-col td-area">
                <span class="area-name">{{ area.name }}</span>
                <span class="area-code">{{ area.code }}</span>
              </td>
              <td class="td-location">
                <span class="building-name">{{ area.building }}</span>
                <span class="floor-name">{{ area.floor }}</span>
              </td>
              <td>
                <span class="badge badge-secondary">{{ area.category }}</span>
              </td>
              <td
                v-for="shift in data.shifts"
                :key="shift.id"
                class="text-center heatmap-td"
              >
                <div
                  class="heatmap-cell"
                  :class="`heatmap-${area.statuses[shift.id]}`"
                  @mouseenter="showTooltip($event, area, shift)"
                  @mouseleave="hideTooltip"
                >
                  <span v-if="area.statuses[shift.id] === 'clean'">✓</span>
                  <span v-else-if="area.statuses[shift.id] === 'pending'">⏳</span>
                  <span v-else-if="area.statuses[shift.id] === 'late'">!</span>
                  <span v-else>-</span>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Tooltip -->
    <Teleport to="body">
      <div
        v-if="hoveredCell"
        class="heatmap-tooltip"
        :style="{ top: (hoveredCell.y - 80) + 'px', left: (hoveredCell.x + 12) + 'px' }"
      >
        <div class="tooltip-title">{{ hoveredCell.area.name }}</div>
        <div class="tooltip-shift">{{ hoveredCell.shift.name }}</div>
        <div class="tooltip-status">{{ getStatusLabel(hoveredCell.status) }}</div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
.heatmap-page {
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

/* Summary Cards */
.summary-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
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

.summary-primary::before { background: hsl(var(--primary)); }
.summary-success::before { background: hsl(var(--success)); }
.summary-warning::before { background: hsl(var(--warning)); }
.summary-destructive::before { background: hsl(var(--destructive)); }

.summary-icon {
  font-size: 1.5rem;
  width: 2.75rem;
  height: 2.75rem;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 0.625rem;
  background: hsl(var(--muted));
  flex-shrink: 0;
}

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

.summary-pct {
  font-size: 0.75rem;
  font-weight: 700;
  color: hsl(var(--muted-foreground));
  background: hsl(var(--muted));
  padding: 0.25rem 0.5rem;
  border-radius: 0.375rem;
}

/* Controls */
.heatmap-controls {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 1rem;
}

.filter-group {
  display: flex;
  gap: 0.5rem;
}

.filter-select {
  width: auto;
  min-width: 150px;
}

.legend {
  display: flex;
  gap: 1rem;
  flex-wrap: wrap;
}

.legend-item {
  display: flex;
  align-items: center;
  gap: 0.375rem;
  font-size: 0.8125rem;
  color: hsl(var(--muted-foreground));
}

.legend-box {
  width: 1rem;
  height: 1rem;
  border-radius: 0.25rem;
}

/* Table */
.table-card {
  padding: 0;
  overflow: hidden;
}

.table-responsive {
  overflow: auto;
  max-height: 70vh;
}

.heatmap-table th,
.heatmap-table td {
  padding: 0.625rem 0.875rem;
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

.shift-header {
  min-width: 100px;
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

.td-area {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
  min-width: 160px;
}

.area-name {
  font-weight: 600;
  font-size: 0.8125rem;
  color: hsl(var(--foreground));
}

.area-code {
  font-size: 0.6875rem;
  color: hsl(var(--muted-foreground));
}

.td-location {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
}

.building-name {
  font-size: 0.8125rem;
  font-weight: 500;
}

.floor-name {
  font-size: 0.6875rem;
  color: hsl(var(--muted-foreground));
}

.heatmap-td {
  padding: 0.5rem !important;
}

.badge-secondary {
  background: hsl(var(--secondary));
  color: hsl(var(--secondary-foreground));
  font-size: 0.6875rem;
}

.text-center { text-align: center; }

/* Tooltip */
.heatmap-tooltip {
  position: fixed;
  z-index: 9999;
  background: hsl(var(--card));
  border: 1px solid hsl(var(--border));
  border-radius: 0.5rem;
  padding: 0.625rem 0.875rem;
  box-shadow: 0 8px 24px rgba(0,0,0,0.25);
  pointer-events: none;
  min-width: 160px;
}

.tooltip-title {
  font-weight: 700;
  font-size: 0.8125rem;
  color: hsl(var(--foreground));
}

.tooltip-shift {
  font-size: 0.75rem;
  color: hsl(var(--muted-foreground));
  margin-top: 0.125rem;
}

.tooltip-status {
  font-size: 0.8125rem;
  font-weight: 600;
  margin-top: 0.375rem;
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
