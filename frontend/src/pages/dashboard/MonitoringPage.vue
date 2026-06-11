<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed } from 'vue'
import api from '../../lib/axios'

const activities = ref<any[]>([])
const loading = ref(true)
const searchQuery = ref('')
const currentPage = ref(1)
const totalPages = ref(1)
const perPage = ref(20)
const expandedId = ref<number | null>(null)
const filterStatus = ref('')
const autoRefreshSeconds = ref(30)
const refreshTimer = ref<ReturnType<typeof setInterval> | null>(null)
const countdown = ref(30)
const lastRefreshed = ref('')

// Stats
const stats = ref({ total: 0, completed: 0, in_progress: 0, late: 0 })

const filteredActivities = computed(() => {
  let list = activities.value
  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase()
    list = list.filter((a: any) =>
      (a.area?.name || '').toLowerCase().includes(q) ||
      (a.user?.name || '').toLowerCase().includes(q) ||
      (a.shift?.name || '').toLowerCase().includes(q) ||
      (a.status || '').toLowerCase().includes(q)
    )
  }
  if (filterStatus.value) {
    list = list.filter((a: any) => a.status === filterStatus.value)
  }
  return list
})

async function loadData(page = 1) {
  loading.value = true
  try {
    const response = await api.get('/api/v1/activities', {
      params: { page, per_page: perPage.value, date: new Date().toISOString().split('T')[0] }
    })
    const paginatedData = response.data.data
    activities.value = paginatedData.data || paginatedData
    currentPage.value = paginatedData.current_page || 1
    totalPages.value = paginatedData.last_page || 1

    // Calculate stats
    const all = activities.value
    stats.value = {
      total: all.length,
      completed: all.filter((a: any) => a.status === 'completed').length,
      in_progress: all.filter((a: any) => a.status === 'in_progress').length,
      late: all.filter((a: any) => a.is_late).length,
    }
    lastRefreshed.value = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' })
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

function toggleDetail(id: number) {
  expandedId.value = expandedId.value === id ? null : id
}

function prevPage() {
  if (currentPage.value > 1) loadData(currentPage.value - 1)
}

function nextPage() {
  if (currentPage.value < totalPages.value) loadData(currentPage.value + 1)
}

function getStatusBadge(status: string) {
  switch (status) {
    case 'completed': return { label: 'Selesai', class: 'badge-success', icon: '✓' }
    case 'in_progress': return { label: 'Proses', class: 'badge-primary', icon: '⏳' }
    default: return { label: 'Pending', class: 'badge-warning', icon: '⏸' }
  }
}

function getCompletionRate(act: any): number {
  if (!act.items || act.items.length === 0) return 0
  const checked = act.items.filter((i: any) => i.is_checked).length
  return Math.round((checked / act.items.length) * 100)
}

function formatDate(dateStr: string): string {
  if (!dateStr) return '-'
  try {
    const dateOnly = dateStr.split('T')[0].split(' ')[0]
    const parts = dateOnly.split('-')
    if (parts.length === 3) {
      return `${parts[2]}-${parts[1]}-${parts[0]}`
    }
    return dateOnly
  } catch {
    return dateStr
  }
}

function startAutoRefresh() {
  countdown.value = autoRefreshSeconds.value
  refreshTimer.value = setInterval(() => {
    countdown.value--
    if (countdown.value <= 0) {
      loadData(currentPage.value)
      countdown.value = autoRefreshSeconds.value
    }
  }, 1000)
}

function stopAutoRefresh() {
  if (refreshTimer.value) {
    clearInterval(refreshTimer.value)
    refreshTimer.value = null
  }
}

onMounted(() => {
  loadData()
  startAutoRefresh()
})

onUnmounted(() => {
  stopAutoRefresh()
})
</script>

<template>
  <div class="monitoring-page animate-fade-in">
    <!-- Header -->
    <div class="page-header">
      <div>
        <h1 class="page-title">
          <span class="title-icon">📡</span>
          Live Monitoring
        </h1>
        <p class="page-subtitle">Log aktivitas pembersihan real-time</p>
      </div>
      <div class="header-actions">
        <div class="refresh-indicator" :class="{ 'is-refreshing': loading }">
          <div class="countdown-ring">
            <svg viewBox="0 0 36 36">
              <circle cx="18" cy="18" r="15.5" fill="none" stroke="hsl(var(--border))" stroke-width="2"/>
              <circle
                cx="18" cy="18" r="15.5" fill="none"
                stroke="hsl(var(--primary))" stroke-width="2.5"
                stroke-linecap="round"
                :stroke-dasharray="`${(countdown / autoRefreshSeconds) * 97.4} 97.4`"
                transform="rotate(-90 18 18)"
              />
            </svg>
            <span class="countdown-text">{{ countdown }}s</span>
          </div>
        </div>
        <button class="btn btn-secondary" @click="loadData(currentPage)">
          <span>🔄</span> Refresh
        </button>
      </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid">
      <div class="stat-card animate-slide-up stagger-1">
        <div class="stat-icon stat-icon-total">📊</div>
        <div class="stat-info">
          <span class="stat-value animate-count">{{ stats.total }}</span>
          <span class="stat-label">Total Hari Ini</span>
        </div>
      </div>
      <div class="stat-card animate-slide-up stagger-2">
        <div class="stat-icon stat-icon-done">✅</div>
        <div class="stat-info">
          <span class="stat-value animate-count">{{ stats.completed }}</span>
          <span class="stat-label">Selesai</span>
        </div>
      </div>
      <div class="stat-card animate-slide-up stagger-3">
        <div class="stat-icon stat-icon-progress">⏳</div>
        <div class="stat-info">
          <span class="stat-value animate-count">{{ stats.in_progress }}</span>
          <span class="stat-label">Sedang Berjalan</span>
        </div>
      </div>
      <div class="stat-card animate-slide-up stagger-4">
        <div class="stat-icon stat-icon-late">⚠️</div>
        <div class="stat-info">
          <span class="stat-value animate-count">{{ stats.late }}</span>
          <span class="stat-label">Terlambat</span>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="filters-bar animate-slide-up">
      <div class="search-wrapper">
        <span class="search-icon">🔍</span>
        <input type="text" v-model="searchQuery" class="input search-input" placeholder="Cari area, petugas, shift..." />
      </div>
      <select v-model="filterStatus" class="input filter-select" @change="loadData(1)">
        <option value="">Semua Status</option>
        <option value="completed">✓ Selesai</option>
        <option value="in_progress">⏳ Sedang Proses</option>
        <option value="pending">⏸ Pending</option>
      </select>
      <div class="last-updated" v-if="lastRefreshed">
        Terakhir: {{ lastRefreshed }}
      </div>
    </div>

    <!-- Table -->
    <div class="card table-card animate-slide-up">
      <div v-if="loading && activities.length === 0" class="loading-state">
        <div class="spinner-large"></div>
        <p>Memuat data...</p>
      </div>

      <div v-else-if="filteredActivities.length === 0" class="empty-state">
        <div class="empty-icon">📭</div>
        <h3>Belum Ada Aktivitas</h3>
        <p>Data aktivitas pembersihan belum tersedia untuk hari ini.</p>
      </div>

      <div v-else class="table-responsive">
        <table class="audit-table monitoring-table">
          <thead>
            <tr>
              <th>Tanggal</th>
              <th>Area</th>
              <th>Shift</th>
              <th>Petugas</th>
              <th>Mulai</th>
              <th>Selesai</th>
              <th>Progress</th>
              <th>Status</th>
              <th>Tindakan</th>
            </tr>
          </thead>
          <tbody>
            <template v-for="act in filteredActivities" :key="act.id">
              <tr :class="{ 'row-expanded': expandedId === act.id, 'row-late': act.is_late }">
                <td class="td-date">{{ formatDate(act.date) }}</td>
                <td class="td-area">
                  <span class="area-name">{{ act.area?.name }}</span>
                  <span class="area-code">{{ act.area?.code }}</span>
                </td>
                <td>
                  <span class="badge badge-secondary">{{ act.shift?.name }}</span>
                </td>
                <td>
                  <div class="user-cell">
                    <div class="user-mini-avatar">{{ (act.user?.name || '?')[0] }}</div>
                    <span>{{ act.user?.name }}</span>
                  </div>
                </td>
                <td class="td-time">{{ act.start_time }}</td>
                <td class="td-time">{{ act.end_time || '-' }}</td>
                <td>
                  <div class="progress-cell">
                    <div class="mini-progress-bar">
                      <div class="mini-progress-fill" :style="{ width: getCompletionRate(act) + '%' }"></div>
                    </div>
                    <span class="progress-text">{{ getCompletionRate(act) }}%</span>
                  </div>
                </td>
                <td>
                  <span class="badge" :class="getStatusBadge(act.status).class">
                    {{ getStatusBadge(act.status).icon }} {{ getStatusBadge(act.status).label }}
                  </span>
                  <span v-if="act.is_late" class="badge badge-destructive ml-1">🔴 Telat</span>
                </td>
                <td>
                  <button class="btn-detail" @click="toggleDetail(act.id)">
                    {{ expandedId === act.id ? '▲ Tutup' : '▼ Detail' }}
                  </button>
                </td>
              </tr>
              <!-- Inline Detail Row -->
              <tr v-if="expandedId === act.id" class="detail-row">
                <td :colspan="9">
                  <div class="detail-panel">
                    <div class="detail-header">
                      <h4>📋 Detail Aktivitas #{{ act.id }}</h4>
                      <span class="badge" :class="getStatusBadge(act.status).class">
                        {{ getStatusBadge(act.status).label }}
                      </span>
                    </div>
                    <div class="detail-body">
                      <div class="detail-section">
                        <h5>Informasi</h5>
                        <div class="detail-grid">
                          <div class="detail-item">
                            <span class="detail-label">Area</span>
                            <span class="detail-value">{{ act.area?.name }}</span>
                          </div>
                          <div class="detail-item">
                            <span class="detail-label">Petugas</span>
                            <span class="detail-value">{{ act.user?.name }}</span>
                          </div>
                          <div class="detail-item">
                            <span class="detail-label">Shift</span>
                            <span class="detail-value">{{ act.shift?.name }}</span>
                          </div>
                          <div class="detail-item">
                            <span class="detail-label">Durasi</span>
                            <span class="detail-value">{{ act.start_time }} - {{ act.end_time || 'Belum selesai' }}</span>
                          </div>
                          <div class="detail-item">
                            <span class="detail-label">Catatan</span>
                            <span class="detail-value">{{ act.notes || 'Tidak ada catatan' }}</span>
                          </div>
                          <div class="detail-item">
                            <span class="detail-label">Approval</span>
                            <span class="badge" :class="{
                              'badge-success': act.approval_status === 'approved',
                              'badge-warning': !act.approval_status || act.approval_status === 'pending',
                              'badge-destructive': act.approval_status === 'rejected'
                            }">{{ act.approval_status || 'pending' }}</span>
                          </div>
                        </div>
                      </div>

                      <!-- Checklist Items -->
                      <div class="detail-section" v-if="act.items && act.items.length > 0">
                        <h5>Checklist ({{ act.items.filter((i:any) => i.is_checked).length }}/{{ act.items.length }})</h5>
                        <div class="checklist-progress">
                          <div class="progress-bar-lg">
                            <div class="progress-fill-lg" :style="{ width: getCompletionRate(act) + '%' }"></div>
                          </div>
                          <span>{{ getCompletionRate(act) }}%</span>
                        </div>
                        <div class="checklist-items">
                          <div v-for="item in act.items" :key="item.id" class="checklist-row" :class="{ checked: item.is_checked }">
                            <span class="check-mark">{{ item.is_checked ? '✅' : '⬜' }}</span>
                            <span>{{ item.area_object?.cleaning_object?.name || 'Item' }}</span>
                          </div>
                        </div>
                      </div>

                      <!-- Photos -->
                      <div class="detail-section" v-if="act.photos && act.photos.length > 0">
                        <h5>📸 Bukti Foto ({{ act.photos.length }})</h5>
                        <div class="photo-grid">
                          <a v-for="photo in act.photos" :key="photo.id" :href="'/storage/' + photo.file_path" target="_blank" class="photo-thumb" title="Klik untuk memperbesar">
                            <img :src="'/storage/' + photo.file_path" :alt="photo.type" loading="lazy" />
                            <span class="photo-label">{{ photo.type === 'before' ? 'Sebelum' : 'Sesudah' }}</span>
                          </a>
                        </div>
                      </div>
                    </div>
                  </div>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="!loading && totalPages > 1" class="pagination">
        <button class="btn btn-ghost btn-sm" @click="prevPage" :disabled="currentPage <= 1">← Sebelumnya</button>
        <span class="page-info">Halaman {{ currentPage }} dari {{ totalPages }}</span>
        <button class="btn btn-ghost btn-sm" @click="nextPage" :disabled="currentPage >= totalPages">Selanjutnya →</button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.monitoring-page {
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

.title-icon {
  font-size: 1.75rem;
}

.page-subtitle {
  font-size: 0.875rem;
  color: hsl(var(--muted-foreground));
  margin-top: 0.25rem;
}

.header-actions {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

/* Countdown Ring */
.refresh-indicator {
  display: flex;
  align-items: center;
}

.countdown-ring {
  width: 2.5rem;
  height: 2.5rem;
  position: relative;
}

.countdown-ring svg {
  width: 100%;
  height: 100%;
  transform: scaleX(-1);
}

.countdown-ring circle:last-child {
  transition: stroke-dasharray 1s linear;
}

.countdown-text {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.625rem;
  font-weight: 600;
  color: hsl(var(--muted-foreground));
}

/* Stats Grid */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 1rem;
}

@media (max-width: 768px) {
  .stats-grid { grid-template-columns: repeat(2, 1fr); }
}

.stat-card {
  background: hsl(var(--card));
  border: 1px solid hsl(var(--border));
  border-radius: var(--radius);
  padding: 1.25rem;
  display: flex;
  align-items: center;
  gap: 1rem;
  transition: all 0.3s ease;
  position: relative;
  overflow: hidden;
}

.stat-card::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 3px;
  opacity: 0;
  transition: opacity 0.3s;
}

.stat-card:hover::before { opacity: 1; }

.stat-card:nth-child(1)::before { background: linear-gradient(90deg, hsl(210, 100%, 56%), hsl(262, 83%, 58%)); }
.stat-card:nth-child(2)::before { background: hsl(var(--success)); }
.stat-card:nth-child(3)::before { background: hsl(var(--primary)); }
.stat-card:nth-child(4)::before { background: hsl(var(--destructive)); }

.stat-card:hover {
  border-color: hsl(var(--primary) / 0.3);
  box-shadow: 0 4px 16px hsl(var(--primary) / 0.08);
  transform: translateY(-2px);
}

.stat-icon {
  width: 3rem;
  height: 3rem;
  border-radius: 0.75rem;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.25rem;
  flex-shrink: 0;
}

.stat-icon-total { background: hsl(var(--primary) / 0.12); }
.stat-icon-done { background: hsl(var(--success) / 0.12); }
.stat-icon-progress { background: hsl(210, 100%, 56%, 0.12); }
.stat-icon-late { background: hsl(var(--destructive) / 0.12); }

.stat-info {
  display: flex;
  flex-direction: column;
}

.stat-value {
  font-size: 1.75rem;
  font-weight: 800;
  line-height: 1;
  color: hsl(var(--foreground));
}

.stat-label {
  font-size: 0.75rem;
  color: hsl(var(--muted-foreground));
  margin-top: 0.25rem;
  font-weight: 500;
}

/* Filters */
.filters-bar {
  display: flex;
  gap: 0.75rem;
  align-items: center;
  flex-wrap: wrap;
}

.search-wrapper {
  position: relative;
  flex: 1;
  min-width: 200px;
}

.search-icon {
  position: absolute;
  left: 0.75rem;
  top: 50%;
  transform: translateY(-50%);
  font-size: 0.875rem;
  pointer-events: none;
}

.search-input {
  padding-left: 2.25rem !important;
}

.filter-select {
  width: auto;
  min-width: 160px;
}

.last-updated {
  font-size: 0.75rem;
  color: hsl(var(--muted-foreground));
  white-space: nowrap;
}

/* Table Card */
.table-card {
  padding: 0;
  overflow: hidden;
}

.table-responsive {
  overflow-x: auto;
}

/* Monitoring Table Enhancements */
.monitoring-table .td-date {
  white-space: nowrap;
  font-size: 0.8125rem;
  color: hsl(var(--muted-foreground));
}

.td-area {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
}

.area-name {
  font-weight: 600;
  color: hsl(var(--foreground));
}

.area-code {
  font-size: 0.6875rem;
  color: hsl(var(--muted-foreground));
}

.td-time {
  font-family: var(--font-mono, monospace);
  font-size: 0.8125rem;
}

.user-cell {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.user-mini-avatar {
  width: 1.75rem;
  height: 1.75rem;
  border-radius: 50%;
  background: linear-gradient(135deg, hsl(210, 100%, 56%), hsl(262, 83%, 58%));
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 0.6875rem;
  font-weight: 700;
  flex-shrink: 0;
}

.badge-secondary {
  background: hsl(var(--secondary));
  color: hsl(var(--secondary-foreground));
}

/* Progress cell */
.progress-cell {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  min-width: 100px;
}

.mini-progress-bar {
  flex: 1;
  height: 6px;
  background: hsl(var(--muted));
  border-radius: 3px;
  overflow: hidden;
}

.mini-progress-fill {
  height: 100%;
  background: linear-gradient(90deg, hsl(var(--primary)), hsl(var(--success)));
  border-radius: 3px;
  transition: width 0.5s ease;
}

.progress-text {
  font-size: 0.6875rem;
  font-weight: 600;
  color: hsl(var(--muted-foreground));
  min-width: 2rem;
}

/* Row states */
.row-expanded > td {
  background: hsl(var(--primary) / 0.04);
  border-bottom-color: transparent;
}

.row-late > td {
  border-left: 3px solid transparent;
}

.row-late > td:first-child {
  border-left: 3px solid hsl(var(--destructive));
}

.ml-1 { margin-left: 0.25rem; }

/* Detail button */
.btn-detail {
  background: hsl(var(--muted));
  border: 1px solid hsl(var(--border));
  padding: 0.25rem 0.625rem;
  border-radius: 0.375rem;
  font-size: 0.75rem;
  color: hsl(var(--foreground));
  cursor: pointer;
  transition: all 0.2s;
  white-space: nowrap;
}

.btn-detail:hover {
  background: hsl(var(--primary) / 0.1);
  border-color: hsl(var(--primary) / 0.3);
  color: hsl(var(--primary));
}

/* Detail Panel */
.detail-row td {
  padding: 0 !important;
  background: hsl(var(--card)) !important;
}

.detail-panel {
  border-top: 2px solid hsl(var(--primary) / 0.2);
  border-bottom: 2px solid hsl(var(--primary) / 0.2);
  background: hsl(var(--muted) / 0.3);
  animation: slideDown 0.3s ease-out;
}

@keyframes slideDown {
  from { opacity: 0; max-height: 0; }
  to { opacity: 1; max-height: 800px; }
}

.detail-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem 1.5rem;
  border-bottom: 1px solid hsl(var(--border));
}

.detail-header h4 {
  font-size: 0.9375rem;
  font-weight: 700;
  color: hsl(var(--foreground));
  margin: 0;
}

.detail-body {
  padding: 1rem 1.5rem 1.25rem;
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.detail-section h5 {
  font-size: 0.8125rem;
  font-weight: 600;
  color: hsl(var(--foreground));
  margin: 0 0 0.625rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.detail-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 0.75rem;
}

@media (max-width: 768px) {
  .detail-grid { grid-template-columns: 1fr 1fr; }
}

.detail-item {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
}

.detail-label {
  font-size: 0.6875rem;
  color: hsl(var(--muted-foreground));
  text-transform: uppercase;
  letter-spacing: 0.06em;
  font-weight: 500;
}

.detail-value {
  font-size: 0.875rem;
  color: hsl(var(--foreground));
  font-weight: 500;
}

/* Checklist progress */
.checklist-progress {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 0.625rem;
}

.progress-bar-lg {
  flex: 1;
  height: 8px;
  background: hsl(var(--muted));
  border-radius: 4px;
  overflow: hidden;
}

.progress-fill-lg {
  height: 100%;
  background: linear-gradient(90deg, hsl(var(--primary)), hsl(var(--success)));
  border-radius: 4px;
  transition: width 0.5s ease;
}

.checklist-items {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 0.375rem;
}

.checklist-row {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.375rem 0.5rem;
  border-radius: 0.375rem;
  font-size: 0.8125rem;
  background: hsl(var(--card));
  border: 1px solid hsl(var(--border));
}

.checklist-row.checked {
  background: hsl(var(--success) / 0.06);
  border-color: hsl(var(--success) / 0.2);
}

.check-mark {
  font-size: 0.875rem;
}

/* Photos */
.photo-grid {
  display: flex;
  gap: 0.625rem;
  flex-wrap: wrap;
}

.photo-thumb {
  width: 100px;
  border-radius: 0.5rem;
  overflow: hidden;
  border: 1px solid hsl(var(--border));
  position: relative;
  transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
  cursor: pointer;
  display: block;
}

.photo-thumb:hover {
  transform: scale(1.05);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
  border-color: hsl(var(--primary) / 0.5);
}

.photo-thumb img {
  width: 100%;
  height: 80px;
  object-fit: cover;
  display: block;
}

.photo-label {
  display: block;
  text-align: center;
  font-size: 0.625rem;
  font-weight: 600;
  padding: 0.25rem;
  background: hsl(var(--muted));
  color: hsl(var(--muted-foreground));
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

/* Loading & Empty */
.loading-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 4rem 2rem;
  gap: 1rem;
  color: hsl(var(--muted-foreground));
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 4rem 2rem;
  text-align: center;
}

.empty-icon {
  font-size: 3rem;
  margin-bottom: 1rem;
}

.empty-state h3 {
  font-size: 1.125rem;
  font-weight: 600;
  color: hsl(var(--foreground));
  margin: 0 0 0.5rem;
}

.empty-state p {
  font-size: 0.875rem;
  color: hsl(var(--muted-foreground));
  margin: 0;
}

/* Pagination */
.pagination {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 1rem;
  padding: 1rem;
  border-top: 1px solid hsl(var(--border));
}

.page-info {
  font-size: 0.8125rem;
  color: hsl(var(--muted-foreground));
}

.btn-sm {
  padding: 0.25rem 0.75rem;
  font-size: 0.8125rem;
}

/* Spinner */
.spinner-large {
  width: 3rem;
  height: 3rem;
  border: 4px solid rgba(255,255,255,0.1);
  border-top-color: hsl(var(--primary));
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin { to { transform: rotate(360deg); } }
</style>
