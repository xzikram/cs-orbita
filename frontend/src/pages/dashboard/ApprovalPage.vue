<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '../../lib/axios'

const activities = ref<any[]>([])
const loading = ref(true)
const processing = ref<number | null>(null)
const activeTab = ref<'pending' | 'approved' | 'rejected'>('pending')
const apiBaseUrl = import.meta.env.VITE_API_URL || 'http://localhost:8000'
const expandedId = ref<number | null>(null)
const rejectModal = ref<{ show: boolean; activityId: number | null; reason: string }>({
  show: false, activityId: null, reason: ''
})
const toast = ref<{ show: boolean; message: string; type: string }>({ show: false, message: '', type: 'success' })

const stats = ref({ pending: 0, approved: 0, rejected: 0 })

const tabs = [
  { key: 'pending' as const, label: 'Menunggu', icon: '⏳', badge: 'badge-warning' },
  { key: 'approved' as const, label: 'Disetujui', icon: '✅', badge: 'badge-success' },
  { key: 'rejected' as const, label: 'Ditolak', icon: '❌', badge: 'badge-destructive' },
]

async function fetchActivities() {
  loading.value = true
  try {
    const { data } = await api.get('/api/v1/activities', {
      params: {
        status: 'completed',
        approval_status: activeTab.value,
        per_page: 50
      }
    })
    const result = data.data
    activities.value = result.data || result
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

async function fetchStats() {
  try {
    const [pending, approved, rejected] = await Promise.all([
      api.get('/api/v1/activities', { params: { status: 'completed', approval_status: 'pending', per_page: 1 } }),
      api.get('/api/v1/activities', { params: { status: 'completed', approval_status: 'approved', per_page: 1 } }),
      api.get('/api/v1/activities', { params: { status: 'completed', approval_status: 'rejected', per_page: 1 } }),
    ])
    stats.value = {
      pending: pending.data.data?.total || (pending.data.data?.data || pending.data.data || []).length,
      approved: approved.data.data?.total || (approved.data.data?.data || approved.data.data || []).length,
      rejected: rejected.data.data?.total || (rejected.data.data?.data || rejected.data.data || []).length,
    }
  } catch (e) {
    console.error(e)
  }
}

async function approve(activityId: number) {
  processing.value = activityId
  try {
    await api.put(`/api/v1/activities/${activityId}/approve`, { status: 'approved' })
    activities.value = activities.value.filter(a => a.id !== activityId)
    stats.value.pending = Math.max(0, stats.value.pending - 1)
    stats.value.approved++
    showToast('Laporan berhasil disetujui ✅', 'success')
  } catch (e) {
    console.error(e)
    showToast('Gagal memproses persetujuan', 'error')
  } finally {
    processing.value = null
  }
}

function openRejectModal(activityId: number) {
  rejectModal.value = { show: true, activityId, reason: '' }
}

async function confirmReject() {
  if (!rejectModal.value.activityId) return
  processing.value = rejectModal.value.activityId
  try {
    await api.put(`/api/v1/activities/${rejectModal.value.activityId}/approve`, {
      status: 'rejected',
      reason: rejectModal.value.reason
    })
    activities.value = activities.value.filter(a => a.id !== rejectModal.value.activityId)
    stats.value.pending = Math.max(0, stats.value.pending - 1)
    stats.value.rejected++
    rejectModal.value = { show: false, activityId: null, reason: '' }
    showToast('Laporan ditolak', 'warning')
  } catch (e) {
    console.error(e)
    showToast('Gagal memproses penolakan', 'error')
  } finally {
    processing.value = null
  }
}

function toggleExpand(id: number) {
  expandedId.value = expandedId.value === id ? null : id
}

function getCompletionRate(act: any): number {
  if (!act.items || act.items.length === 0) return 0
  return Math.round((act.items.filter((i: any) => i.is_checked).length / act.items.length) * 100)
}

function showToast(message: string, type: string) {
  toast.value = { show: true, message, type }
  setTimeout(() => { toast.value.show = false }, 3000)
}

function switchTab(tab: 'pending' | 'approved' | 'rejected') {
  activeTab.value = tab
  expandedId.value = null
  fetchActivities()
}

onMounted(() => {
  fetchActivities()
  fetchStats()
})
</script>

<template>
  <div class="approval-page animate-fade-in">
    <!-- Toast -->
    <Teleport to="body">
      <Transition name="toast">
        <div v-if="toast.show" class="toast-notification" :class="'toast-' + toast.type">
          {{ toast.message }}
        </div>
      </Transition>
    </Teleport>

    <!-- Header -->
    <div class="page-header">
      <div>
        <h1 class="page-title"><span class="title-icon">✅</span> Persetujuan Laporan</h1>
        <p class="page-subtitle">Tinjau dan setujui laporan kebersihan dari Cleaning Service</p>
      </div>
      <button class="btn btn-secondary" @click="fetchActivities(); fetchStats()">🔄 Refresh</button>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
      <div class="stat-card stat-pending animate-slide-up stagger-1">
        <div class="stat-icon">⏳</div>
        <div class="stat-info">
          <span class="stat-value">{{ stats.pending }}</span>
          <span class="stat-label">Menunggu</span>
        </div>
      </div>
      <div class="stat-card stat-approved animate-slide-up stagger-2">
        <div class="stat-icon">✅</div>
        <div class="stat-info">
          <span class="stat-value">{{ stats.approved }}</span>
          <span class="stat-label">Disetujui</span>
        </div>
      </div>
      <div class="stat-card stat-rejected animate-slide-up stagger-3">
        <div class="stat-icon">❌</div>
        <div class="stat-info">
          <span class="stat-value">{{ stats.rejected }}</span>
          <span class="stat-label">Ditolak</span>
        </div>
      </div>
    </div>

    <!-- Tabs -->
    <div class="tabs-bar animate-slide-up">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        class="tab-btn"
        :class="{ active: activeTab === tab.key }"
        @click="switchTab(tab.key)"
      >
        <span>{{ tab.icon }}</span>
        <span>{{ tab.label }}</span>
      </button>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading-state">
      <div class="spinner-large"></div>
      <p>Memuat data...</p>
    </div>

    <!-- Empty -->
    <div v-else-if="activities.length === 0" class="empty-state card">
      <div class="empty-icon">{{ activeTab === 'pending' ? '👍' : '📭' }}</div>
      <h3>{{ activeTab === 'pending' ? 'Semua laporan sudah ditinjau!' : 'Belum ada data' }}</h3>
      <p>{{ activeTab === 'pending' ? 'Tidak ada laporan yang menunggu persetujuan saat ini.' : `Belum ada laporan yang ${activeTab === 'approved' ? 'disetujui' : 'ditolak'}.` }}</p>
    </div>

    <!-- Cards Grid -->
    <div v-else class="grid-cards">
      <div v-for="act in activities" :key="act.id" class="approval-card card animate-slide-up">
        <!-- Card Header -->
        <div class="card-header">
          <div class="card-header-left">
            <h3 class="card-area-name">{{ act.area?.name || 'Area Tidak Diketahui' }}</h3>
            <div class="card-meta">
              <span class="meta-item">📅 {{ new Date(act.date).toLocaleDateString('id-ID', { weekday: 'short', day: 'numeric', month: 'short' }) }}</span>
              <span class="meta-item">🕐 {{ act.shift?.name }}</span>
            </div>
          </div>
          <span class="badge" :class="{
            'badge-warning': act.approval_status === 'pending',
            'badge-success': act.approval_status === 'approved',
            'badge-destructive': act.approval_status === 'rejected',
          }">{{ act.approval_status === 'pending' ? 'Menunggu' : act.approval_status === 'approved' ? 'Disetujui' : 'Ditolak' }}</span>
        </div>

        <!-- Card Info -->
        <div class="card-info">
          <div class="info-row">
            <div class="user-cell">
              <div class="user-mini-avatar">{{ (act.user?.name || '?')[0] }}</div>
              <div>
                <div class="user-name">{{ act.user?.name }}</div>
                <div class="user-time">{{ act.start_time }} - {{ act.end_time }}</div>
              </div>
            </div>
          </div>
          <div v-if="act.notes" class="info-notes">
            <span class="notes-label">📝 Catatan:</span> {{ act.notes }}
          </div>
        </div>

        <!-- Completion Progress -->
        <div v-if="act.items && act.items.length > 0" class="card-progress" @click="toggleExpand(act.id)">
          <div class="progress-header">
            <span class="progress-title">Checklist ({{ act.items.filter((i:any) => i.is_checked).length }}/{{ act.items.length }})</span>
            <span class="progress-pct">{{ getCompletionRate(act) }}%</span>
          </div>
          <div class="progress-bar">
            <div class="progress-fill" :style="{ width: getCompletionRate(act) + '%' }"></div>
          </div>
          <!-- Expanded checklist -->
          <div v-if="expandedId === act.id" class="checklist-expand">
            <div v-for="item in act.items" :key="item.id" class="checklist-row" :class="{ checked: item.is_checked }">
              <span>{{ item.is_checked ? '✅' : '⬜' }}</span>
              <span>{{ item.area_object?.cleaning_object?.name || 'Item' }}</span>
            </div>
          </div>
          <button class="expand-toggle">{{ expandedId === act.id ? '▲ Tutup' : '▼ Lihat Detail' }}</button>
        </div>

        <!-- Photos -->
        <div v-if="act.photos && act.photos.length > 0" class="card-photos">
          <div v-for="photo in act.photos" :key="photo.id" class="photo-item">
            <img :src="`${apiBaseUrl}/storage/${photo.file_path}`" alt="Bukti" loading="lazy" />
            <span class="photo-badge">{{ photo.type === 'after' ? 'Sesudah' : 'Sebelum' }}</span>
          </div>
        </div>

        <!-- Actions (only for pending) -->
        <div v-if="activeTab === 'pending'" class="card-actions">
          <button
            class="btn btn-approve"
            @click="approve(act.id)"
            :disabled="processing === act.id"
          >
            <span v-if="processing === act.id" class="spinner-small"></span>
            ✓ Setujui
          </button>
          <button
            class="btn btn-reject"
            @click="openRejectModal(act.id)"
            :disabled="processing === act.id"
          >
            ✕ Tolak
          </button>
        </div>
      </div>
    </div>

    <!-- Reject Modal -->
    <Teleport to="body">
      <div v-if="rejectModal.show" class="modal-overlay" @click.self="rejectModal.show = false">
        <div class="modal-content">
          <h3 class="modal-title">❌ Tolak Laporan</h3>
          <p class="modal-desc">Apakah Anda yakin ingin menolak laporan ini?</p>
          <div class="form-group">
            <label class="label">Alasan Penolakan (Opsional)</label>
            <textarea v-model="rejectModal.reason" class="input textarea" placeholder="Masukkan alasan penolakan..."></textarea>
          </div>
          <div class="modal-actions">
            <button class="btn btn-ghost" @click="rejectModal.show = false">Batal</button>
            <button class="btn btn-destructive" @click="confirmReject" :disabled="processing !== null">
              <span v-if="processing !== null" class="spinner-small"></span>
              Tolak Laporan
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
.approval-page {
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

/* Stats */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1rem;
}
@media (max-width: 640px) { .stats-grid { grid-template-columns: 1fr; } }

.stat-card {
  background: hsl(var(--card));
  border: 1px solid hsl(var(--border));
  border-radius: var(--radius);
  padding: 1.25rem;
  display: flex;
  align-items: center;
  gap: 1rem;
  transition: all 0.3s;
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
.stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 16px hsl(var(--primary) / 0.08); }

.stat-pending::before { background: hsl(var(--warning)); }
.stat-approved::before { background: hsl(var(--success)); }
.stat-rejected::before { background: hsl(var(--destructive)); }

.stat-icon {
  font-size: 1.5rem;
  width: 3rem;
  height: 3rem;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 0.75rem;
  background: hsl(var(--muted));
  flex-shrink: 0;
}
.stat-info { display: flex; flex-direction: column; }
.stat-value { font-size: 1.75rem; font-weight: 800; line-height: 1; color: hsl(var(--foreground)); }
.stat-label { font-size: 0.75rem; color: hsl(var(--muted-foreground)); margin-top: 0.25rem; font-weight: 500; }

/* Tabs */
.tabs-bar {
  display: flex;
  gap: 0.25rem;
  background: hsl(var(--muted));
  padding: 0.25rem;
  border-radius: var(--radius);
}
.tab-btn {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  padding: 0.625rem 1rem;
  border: none;
  background: transparent;
  color: hsl(var(--muted-foreground));
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
  border-radius: calc(var(--radius) - 2px);
  transition: all 0.2s;
}
.tab-btn:hover { color: hsl(var(--foreground)); }
.tab-btn.active {
  background: hsl(var(--card));
  color: hsl(var(--foreground));
  font-weight: 600;
  box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

/* Cards Grid */
.grid-cards {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
  gap: 1.25rem;
}
@media (max-width: 480px) { .grid-cards { grid-template-columns: 1fr; } }

.approval-card {
  display: flex;
  flex-direction: column;
  gap: 0;
  padding: 0;
  overflow: hidden;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding: 1.25rem 1.25rem 0.75rem;
}
.card-area-name {
  font-size: 1rem;
  font-weight: 700;
  color: hsl(var(--foreground));
  margin: 0;
}
.card-meta {
  display: flex;
  gap: 0.75rem;
  margin-top: 0.25rem;
}
.meta-item {
  font-size: 0.75rem;
  color: hsl(var(--muted-foreground));
}

.card-info {
  padding: 0 1.25rem;
}
.info-row {
  margin-bottom: 0.5rem;
}
.user-cell {
  display: flex;
  align-items: center;
  gap: 0.625rem;
}
.user-mini-avatar {
  width: 2rem;
  height: 2rem;
  border-radius: 50%;
  background: linear-gradient(135deg, hsl(210, 100%, 56%), hsl(262, 83%, 58%));
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 0.75rem;
  font-weight: 700;
  flex-shrink: 0;
}
.user-name { font-size: 0.875rem; font-weight: 600; color: hsl(var(--foreground)); }
.user-time { font-size: 0.75rem; color: hsl(var(--muted-foreground)); }
.info-notes {
  font-size: 0.8125rem;
  color: hsl(var(--muted-foreground));
  padding: 0.5rem 0.75rem;
  background: hsl(var(--muted) / 0.5);
  border-radius: 0.375rem;
  margin-top: 0.5rem;
}
.notes-label { font-weight: 600; }

/* Progress */
.card-progress {
  padding: 0.75rem 1.25rem;
  cursor: pointer;
}
.progress-header {
  display: flex;
  justify-content: space-between;
  margin-bottom: 0.375rem;
}
.progress-title { font-size: 0.75rem; font-weight: 600; color: hsl(var(--foreground)); }
.progress-pct { font-size: 0.75rem; font-weight: 700; color: hsl(var(--primary)); }

.progress-bar {
  height: 6px;
  background: hsl(var(--muted));
  border-radius: 3px;
  overflow: hidden;
}
.progress-fill {
  height: 100%;
  background: linear-gradient(90deg, hsl(var(--primary)), hsl(var(--success)));
  border-radius: 3px;
  transition: width 0.5s ease;
}

.checklist-expand {
  margin-top: 0.625rem;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}
.checklist-row {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.25rem 0.5rem;
  border-radius: 0.25rem;
  font-size: 0.8125rem;
  background: hsl(var(--card));
  border: 1px solid hsl(var(--border));
}
.checklist-row.checked {
  background: hsl(var(--success) / 0.06);
  border-color: hsl(var(--success) / 0.2);
}
.expand-toggle {
  display: block;
  width: 100%;
  text-align: center;
  margin-top: 0.5rem;
  background: none;
  border: none;
  font-size: 0.75rem;
  color: hsl(var(--primary));
  cursor: pointer;
  font-weight: 500;
}
.expand-toggle:hover { text-decoration: underline; }

/* Photos */
.card-photos {
  display: flex;
  gap: 0.5rem;
  padding: 0.75rem 1.25rem;
  overflow-x: auto;
}
.photo-item {
  position: relative;
  width: 90px;
  height: 90px;
  flex-shrink: 0;
  border-radius: 0.5rem;
  overflow: hidden;
  border: 1px solid hsl(var(--border));
}
.photo-item img { width: 100%; height: 100%; object-fit: cover; }
.photo-badge {
  position: absolute;
  bottom: 0.25rem;
  right: 0.25rem;
  font-size: 0.5625rem;
  background: rgba(0,0,0,0.75);
  color: white;
  padding: 2px 6px;
  border-radius: 4px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}

/* Actions */
.card-actions {
  display: flex;
  gap: 0.5rem;
  padding: 0.75rem 1.25rem 1.25rem;
  border-top: 1px solid hsl(var(--border));
  margin-top: 0.5rem;
}
.btn-approve {
  flex: 1;
  background: hsl(var(--success));
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.375rem;
}
.btn-approve:hover { background: hsl(var(--success) / 0.9); transform: translateY(-1px); }
.btn-reject {
  flex: 1;
  background: hsl(var(--destructive) / 0.15);
  color: hsl(var(--destructive));
  border: 1px solid hsl(var(--destructive) / 0.3);
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.375rem;
}
.btn-reject:hover { background: hsl(var(--destructive) / 0.25); }

/* Modal */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.6);
  backdrop-filter: blur(4px);
  z-index: 9999;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
}
.modal-content {
  background: hsl(var(--card));
  border: 1px solid hsl(var(--border));
  border-radius: var(--radius);
  padding: 1.5rem;
  width: 100%;
  max-width: 440px;
  box-shadow: 0 16px 48px rgba(0,0,0,0.3);
}
.modal-title { font-size: 1.125rem; font-weight: 700; color: hsl(var(--foreground)); margin: 0 0 0.5rem; }
.modal-desc { font-size: 0.875rem; color: hsl(var(--muted-foreground)); margin: 0 0 1rem; }
.modal-actions { display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1rem; }

.form-group { margin-bottom: 0.75rem; }
.textarea { min-height: 80px; resize: vertical; }

/* Toast */
.toast-notification {
  position: fixed;
  top: 1.5rem;
  right: 1.5rem;
  z-index: 99999;
  padding: 0.75rem 1.25rem;
  border-radius: var(--radius);
  font-size: 0.875rem;
  font-weight: 500;
  box-shadow: 0 8px 24px rgba(0,0,0,0.3);
  color: white;
}
.toast-success { background: hsl(var(--success)); }
.toast-warning { background: hsl(var(--warning)); color: black; }
.toast-error { background: hsl(var(--destructive)); }

.toast-enter-active, .toast-leave-active { transition: all 0.3s ease; }
.toast-enter-from { transform: translateX(100%); opacity: 0; }
.toast-leave-to { transform: translateY(-20px); opacity: 0; }

/* Loading/Empty */
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
.spinner-small {
  width: 1rem; height: 1rem;
  border: 2px solid rgba(255,255,255,0.3);
  border-top-color: white;
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
  display: inline-block;
}
@keyframes spin { to { transform: rotate(360deg); } }
</style>
