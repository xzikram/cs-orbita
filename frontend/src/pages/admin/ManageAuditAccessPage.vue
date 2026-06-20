<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import api from '../../lib/axios'
import { echo } from '../../lib/echo'

const activeTab = ref<'links' | 'approvals' | 'logs'>('links')

// State
const links = ref<any[]>([])
const pendingSessions = ref<any[]>([])
const activeSessions = ref<any[]>([])
const logs = ref<any[]>([])
const areasList = ref<any[]>([])

const monthNames: Record<number, string> = {
  1: 'Januari', 2: 'Februari', 3: 'Maret', 4: 'April',
  5: 'Mei', 6: 'Juni', 7: 'Juli', 8: 'Agustus',
  9: 'September', 10: 'Oktober', 11: 'November', 12: 'Desember'
}

const newExpiresAt = ref('')
const isGenerating = ref(false)
const isLoadingLinks = ref(false)
const isLoadingApprovals = ref(false)
const isLoadingActiveSessions = ref(false)
const isLoadingLogs = ref(false)

// QR code viewer modal
const showQrModal = ref(false)
const selectedQrUrl = ref('')
const selectedQrUuid = ref('')

const toast = ref<{ show: boolean; message: string; type: string }>({ show: false, message: '', type: 'success' })

function showToast(message: string, type = 'success') {
  toast.value = { show: true, message, type }
  setTimeout(() => {
    toast.value.show = false
  }, 4000)
}

// Fetch Functions
async function fetchLinks() {
  isLoadingLinks.value = true
  try {
    const res = await api.get('/api/v1/admin/audit-links')
    links.value = res.data.data
  } catch (err: any) {
    showToast('Gagal memuat tautan audit.', 'error')
  } finally {
    isLoadingLinks.value = false
  }
}

async function fetchPendingSessions() {
  isLoadingApprovals.value = true
  try {
    const res = await api.get('/api/v1/admin/audit-sessions/pending')
    pendingSessions.value = res.data.data
  } catch (err: any) {
    showToast('Gagal memuat persetujuan pending.', 'error')
  } finally {
    isLoadingApprovals.value = false
  }
}

async function fetchLogs() {
  isLoadingLogs.value = true
  try {
    const res = await api.get('/api/v1/admin/audit-logs')
    logs.value = res.data.data
  } catch (err: any) {
    showToast('Gagal memuat log audit.', 'error')
  } finally {
    isLoadingLogs.value = false
  }
}

async function fetchAreasList() {
  try {
    const res = await api.get('/api/v1/areas')
    areasList.value = res.data.data || res.data
  } catch (err) {
    // Ignore
  }
}

async function fetchActiveSessions() {
  isLoadingActiveSessions.value = true
  try {
    const res = await api.get('/api/v1/admin/audit-sessions/active')
    activeSessions.value = res.data.data
  } catch (err: any) {
    showToast('Gagal memuat sesi aktif.', 'error')
  } finally {
    isLoadingActiveSessions.value = false
  }
}

// Generate New Link
async function generateLink() {
  isGenerating.value = true
  try {
    const res = await api.post('/api/v1/admin/audit-links', {
      expires_at: newExpiresAt.value || null
    })
    links.value.unshift(res.data.link)
    newExpiresAt.value = ''
    showToast('Tautan audit berhasil dibuat!')
  } catch (err: any) {
    showToast(err.response?.data?.message || 'Gagal membuat tautan.', 'error')
  } finally {
    isGenerating.value = false
  }
}

// Toggle Link Status
async function toggleLink(link: any) {
  try {
    const res = await api.put(`/api/v1/admin/audit-links/${link.id}/toggle`)
    link.is_active = res.data.link.is_active
    showToast(`Tautan ${link.is_active ? 'diaktifkan' : 'dinonaktifkan'}!`)
  } catch (err: any) {
    showToast('Gagal memperbarui status tautan.', 'error')
  }
}

// Approve / Reject Session
async function approveSession(session: any, status: 'approved' | 'rejected') {
  try {
    await api.put(`/api/v1/admin/audit-sessions/${session.id}/approve`, { status })
    
    // Remove from pending list
    pendingSessions.value = pendingSessions.value.filter(s => s.id !== session.id)
    
    showToast(`Permintaan akses ${session.name} berhasil ${status === 'approved' ? 'disetujui' : 'ditolak'}!`)
    
    // Refresh links count, active sessions, and logs
    fetchActiveSessions()
    if (status === 'approved') {
      fetchLinks()
      fetchLogs()
    }
  } catch (err: any) {
    showToast('Gagal memproses persetujuan.', 'error')
  }
}

async function revokeSession(session: any) {
  if (!confirm(`Apakah Anda yakin ingin memutuskan sesi untuk ${session.name} (${session.unit})?`)) {
    return
  }
  try {
    await api.put(`/api/v1/admin/audit-sessions/${session.id}/revoke`)
    showToast(`Sesi ${session.name} berhasil diputuskan!`)
    fetchActiveSessions()
    fetchLogs()
  } catch (err: any) {
    showToast('Gagal memutuskan sesi.', 'error')
  }
}

// Helpers
function getFullUrl(uuid: string) {
  return `${window.location.origin}/audit-access/${uuid}`
}

function copyToClipboard(text: string) {
  navigator.clipboard.writeText(text).then(() => {
    showToast('URL berhasil disalin ke clipboard!')
  }).catch(() => {
    showToast('Gagal menyalin URL.', 'error')
  })
}

function openQrModal(uuid: string) {
  selectedQrUuid.value = uuid
  selectedQrUrl.value = getFullUrl(uuid)
  showQrModal.value = true
}

function getQrImageSrc(url: string) {
  return `https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=${encodeURIComponent(url)}`
}

function formatIndoDateTime(dateTimeStr: string) {
  if (!dateTimeStr) return '-'
  const date = new Date(dateTimeStr)
  return date.toLocaleString('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

function getAreaName(details: any) {
  if (!details) return '-'
  if (typeof details === 'string') {
    try {
      details = JSON.parse(details)
    } catch {
      return '-'
    }
  }
  
  if (details.area_name) return details.area_name
  if (details.area_id) {
    const area = areasList.value.find(a => a.id == details.area_id)
    return area ? area.name : `Area #${details.area_id}`
  }
  return '-'
}

function formatDateFilter(details: any) {
  if (!details) return '-'
  if (typeof details === 'string') {
    try {
      details = JSON.parse(details)
    } catch {
      return '-'
    }
  }
  
  if (details.month && details.year) {
    const mName = monthNames[details.month] || details.month
    return `Periode: ${mName} ${details.year}`
  }
  if (details.date) {
    return `Tanggal: ${details.date}`
  }
  if (details.start_date && details.end_date) {
    return `Rentang: ${details.start_date} s/d ${details.end_date}`
  }
  return '-'
}

// Real-time events
onMounted(() => {
  fetchLinks()
  fetchPendingSessions()
  fetchActiveSessions()
  fetchLogs()
  fetchAreasList()

  // Subscribe to channel
  echo.private('admin-approvals')
    .listen('.App\\Events\\AuditSessionRequested', (e: any) => {
      // Add if not already present
      if (!pendingSessions.value.some(s => s.id === e.id)) {
        pendingSessions.value.unshift(e)
        showToast(`Permintaan akses baru dari ${e.name} (${e.unit})!`, 'info')
      }
    })
})

onUnmounted(() => {
  echo.leave('admin-approvals')
})
</script>

<template>
  <div class="manage-audit-page animate-fade-in">
    <!-- Toast Notification -->
    <Teleport to="body">
      <Transition name="toast-slide">
        <div v-if="toast.show" class="toast" :class="`toast-${toast.type}`">
          <span class="toast-icon">{{ toast.type === 'success' ? '✅' : (toast.type === 'error' ? '❌' : '🔔') }}</span>
          {{ toast.message }}
        </div>
      </Transition>
    </Teleport>

    <div class="page-header">
      <div>
        <h1 class="page-title">Kelola Akses Audit</h1>
        <p class="page-subtitle">Buat barcode dan tautan sementara untuk tim audit, serta setujui akses masuk secara real-time.</p>
      </div>
    </div>

    <!-- Tab Buttons -->
    <div class="tab-container mb-6">
      <button 
        class="tab-btn-main" 
        :class="{ active: activeTab === 'links' }"
        @click="activeTab = 'links'"
      >
        🔗 Tautan Aktif
      </button>
      <button 
        class="tab-btn-main relative-btn" 
        :class="{ active: activeTab === 'approvals' }"
        @click="activeTab = 'approvals'"
      >
        ⚖️ Persetujuan Sesi
        <span v-if="pendingSessions.length > 0" class="badge-count">{{ pendingSessions.length }}</span>
      </button>
      <button 
        class="tab-btn-main" 
        :class="{ active: activeTab === 'logs' }"
        @click="activeTab = 'logs'"
      >
        📜 Log Aktivitas
      </button>
    </div>

    <!-- TAB: TAUTAN AKTIF -->
    <div v-if="activeTab === 'links'" class="tab-content animate-slide-up">
      <!-- Create Link Card -->
      <div class="card mb-6">
        <h3 class="card-heading">Generate Tautan Audit Sementara</h3>
        <form @submit.prevent="generateLink" class="generate-form">
          <div class="form-group flex-1">
            <label for="expires_at" class="label">Batas Aktif Tautan (Opsional)</label>
            <input 
              id="expires_at" 
              v-model="newExpiresAt" 
              type="date" 
              class="input date-input"
              :min="new Date().toISOString().split('T')[0]"
            />
            <p class="input-hint text-xs">Jika dikosongkan, tautan akan berlaku selamanya sampai dinonaktifkan secara manual.</p>
          </div>
          <button type="submit" :disabled="isGenerating" class="btn btn-primary generate-btn">
            {{ isGenerating ? 'Membuat...' : 'Buat Barcode & Link' }}
          </button>
        </form>
      </div>

      <!-- Links List -->
      <div class="card">
        <h3 class="card-heading mb-4">Daftar Tautan Audit</h3>
        <div v-if="isLoadingLinks" class="loading-state">
          <div class="spinner"></div>
          <p>Memuat tautan...</p>
        </div>
        <div v-else-if="links.length === 0" class="empty-state">
          <span class="empty-icon">🔗</span>
          <p>Belum ada tautan audit yang dibuat.</p>
        </div>
        <div v-else class="table-responsive">
          <table class="audit-table">
            <thead>
              <tr>
                <th>Tautan & Barcode</th>
                <th>Dibuat Oleh</th>
                <th>Masa Berlaku</th>
                <th>Status</th>
                <th>Sesi Ter-approve</th>
                <th width="150">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="link in links" :key="link.id">
                <td>
                  <div class="link-url-group">
                    <span class="url-text">{{ getFullUrl(link.uuid) }}</span>
                    <div class="btn-group-mini">
                      <button class="btn-mini" title="Salin Link" @click="copyToClipboard(getFullUrl(link.uuid))">📋</button>
                      <button class="btn-mini" title="Tampilkan Barcode" @click="openQrModal(link.uuid)">📱 QR</button>
                    </div>
                  </div>
                </td>
                <td>{{ link.creator?.name || 'Sistem' }}</td>
                <td>
                  <span v-if="link.expires_at" :class="{ 'text-red font-semibold': new Date(link.expires_at) < new Date() }">
                    s/d {{ formatIndoDateTime(link.expires_at).split(' ')[0] }}
                  </span>
                  <span v-else class="text-muted-fg font-medium">Permanen</span>
                </td>
                <td>
                  <span class="badge" :class="link.is_active ? 'badge-success' : 'badge-destructive'">
                    {{ link.is_active ? 'Aktif' : 'Non-aktif' }}
                  </span>
                </td>
                <td>
                  <span class="font-semibold">{{ link.sessions_count || 0 }} sesi</span>
                </td>
                <td>
                  <button 
                    class="btn btn-sm btn-block" 
                    :class="link.is_active ? 'btn-destructive' : 'btn-success'"
                    @click="toggleLink(link)"
                  >
                    {{ link.is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- TAB: PERSETUJUAN SESI -->
    <div v-if="activeTab === 'approvals'" class="tab-content animate-slide-up">
      <div class="card">
        <h3 class="card-heading mb-4">Permintaan Persetujuan Akses Laporan (Real-time)</h3>
        
        <div v-if="isLoadingApprovals" class="loading-state">
          <div class="spinner"></div>
          <p>Memuat permintaan...</p>
        </div>
        
        <div v-else-if="pendingSessions.length === 0" class="empty-state">
          <span class="empty-icon">🤝</span>
          <p>Tidak ada permintaan akses yang sedang menunggu persetujuan.</p>
        </div>
        
        <div v-else class="table-responsive">
          <table class="audit-table">
            <thead>
              <tr>
                <th>Nama Pengaju</th>
                <th>Unit / SPI</th>
                <th>Waktu Mengajukan</th>
                <th>Status Sesi</th>
                <th width="240">Tindakan</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="session in pendingSessions" :key="session.id">
                <td class="font-semibold">{{ session.name }}</td>
                <td>{{ session.unit }}</td>
                <td>{{ formatIndoDateTime(session.created_at) }}</td>
                <td>
                  <span class="badge badge-warning">Menunggu Persetujuan</span>
                </td>
                <td>
                  <div class="flex-actions-row">
                    <button class="btn btn-success btn-sm flex-1" @click="approveSession(session, 'approved')">
                      Approve (Setujui)
                    </button>
                    <button class="btn btn-destructive btn-sm" @click="approveSession(session, 'rejected')">
                      Tolak
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Active Auditor Sessions Card -->
      <div class="card mt-6">
        <h3 class="card-heading mb-4">Sesi Auditor Yang Sedang Aktif</h3>
        
        <div v-if="isLoadingActiveSessions" class="loading-state">
          <div class="spinner"></div>
          <p>Memuat sesi aktif...</p>
        </div>
        
        <div v-else-if="activeSessions.length === 0" class="empty-state">
          <span class="empty-icon">👥</span>
          <p>Tidak ada sesi auditor yang sedang aktif saat ini.</p>
        </div>
        
        <div v-else class="table-responsive">
          <table class="audit-table">
            <thead>
              <tr>
                <th>Nama Auditor</th>
                <th>Unit / SPI</th>
                <th>Waktu Disetujui</th>
                <th>Berlaku Sampai</th>
                <th width="150">Tindakan</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="session in activeSessions" :key="session.id">
                <td class="font-semibold">{{ session.name }}</td>
                <td>{{ session.unit }}</td>
                <td>{{ formatIndoDateTime(session.approved_at) }}</td>
                <td>
                  <span class="text-success font-medium">
                    {{ session.expires_at ? formatIndoDateTime(session.expires_at) : 'Akhir Hari Ini' }}
                  </span>
                </td>
                <td>
                  <button class="btn btn-destructive btn-sm btn-block" @click="revokeSession(session)">
                    Putuskan Sesi
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- TAB: LOG AKTIVITAS -->
    <div v-if="activeTab === 'logs'" class="tab-content animate-slide-up">
      <div class="card">
        <h3 class="card-heading mb-4">Log Akses Laporan Audit</h3>
        
        <div v-if="isLoadingLogs" class="loading-state">
          <div class="spinner"></div>
          <p>Memuat log...</p>
        </div>
        
        <div v-else-if="logs.length === 0" class="empty-state">
          <span class="empty-icon">📜</span>
          <p>Belum ada aktivitas akses laporan yang tercatat.</p>
        </div>
        
        <div v-else class="table-responsive">
          <table class="audit-table">
            <thead>
              <tr>
                <th>Waktu Akses</th>
                <th>Auditor (Unit)</th>
                <th>Area</th>
                <th>Laporan Yang Dibuka</th>
                <th>Filter Parameter</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="log in logs" :key="log.id">
                <td>{{ formatIndoDateTime(log.accessed_at) }}</td>
                <td class="font-semibold">
                  {{ log.audit_session?.name || 'Tamu' }}
                  <span class="text-xs text-muted-fg block">Unit: {{ log.audit_session?.unit || '-' }}</span>
                </td>
                <td class="font-semibold text-primary">
                  {{ getAreaName(log.details) }}
                </td>
                <td>
                  <span class="badge badge-primary">{{ log.report_type }}</span>
                </td>
                <td class="text-muted-fg font-medium">
                  {{ formatDateFilter(log.details) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Modal QR Code -->
    <Teleport to="body">
      <Transition name="modal-fade">
        <div v-if="showQrModal" class="modal-overlay" @click.self="showQrModal = false">
          <div class="modal-content modal-sm animate-scale-up">
            <div class="modal-header">
              <h3 class="modal-title">📱 Barcode Akses Audit</h3>
              <button class="modal-close" @click="showQrModal = false">✕</button>
            </div>
            <div class="modal-body qr-modal-body">
              <p class="qr-hint text-xs text-muted-fg mb-4 text-center">Scan QR code di bawah menggunakan kamera HP untuk langsung menuju portal input identitas auditor.</p>
              <div class="qr-container">
                <img :src="getQrImageSrc(selectedQrUrl)" alt="Akses QR Code" class="qr-image" />
              </div>
              <div class="qr-url-display mt-4">
                <input type="text" readonly :value="selectedQrUrl" class="input text-center text-xs" @click="(e: any) => e.target.select()" />
              </div>
            </div>
            <div class="modal-footer">
              <button class="btn btn-ghost btn-sm" @click="showQrModal = false">Tutup</button>
              <button class="btn btn-primary btn-sm" @click="copyToClipboard(selectedQrUrl)">Salin Link</button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<style scoped>
.manage-audit-page {
  padding: 1rem 0;
}

.page-header {
  margin-bottom: 2rem;
}

.page-title {
  font-size: 1.75rem;
  font-weight: 850;
  color: hsl(var(--foreground));
  letter-spacing: -0.02em;
}

.page-subtitle {
  font-size: 0.9rem;
  color: hsl(var(--muted-foreground));
  margin-top: 0.25rem;
}

/* Tab styling */
.tab-container {
  display: flex;
  gap: 0.5rem;
  border-bottom: 1px solid hsl(var(--border));
  padding-bottom: 1px;
}

.tab-btn-main {
  padding: 0.75rem 1.5rem;
  background: transparent;
  border: none;
  border-bottom: 2px solid transparent;
  color: hsl(var(--muted-foreground));
  font-weight: 600;
  font-size: 0.9rem;
  cursor: pointer;
  transition: all 0.2s ease;
}

.tab-btn-main:hover {
  color: hsl(var(--foreground));
  background: hsl(var(--muted) / 0.4);
  border-radius: var(--radius) var(--radius) 0 0;
}

.tab-btn-main.active {
  color: hsl(var(--primary));
  border-bottom-color: hsl(var(--primary));
}

.relative-btn {
  position: relative;
}

.badge-count {
  position: absolute;
  top: 0.25rem;
  right: 0.25rem;
  background: hsl(var(--destructive));
  color: white;
  font-size: 0.65rem;
  font-weight: 700;
  height: 16px;
  min-width: 16px;
  padding: 0 4px;
  border-radius: 9999px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

/* Form */
.card-heading {
  font-size: 1.1rem;
  font-weight: 750;
  margin-bottom: 1rem;
}

.generate-form {
  display: flex;
  align-items: flex-end;
  gap: 1rem;
  flex-wrap: wrap;
}

.generate-btn {
  height: 2.5rem;
  align-self: flex-end;
  margin-bottom: 1.25rem; /* Match help hint spacing */
}

/* Tables */
.table-responsive {
  width: 100%;
  overflow-x: auto;
}

.link-url-group {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.url-text {
  max-width: 250px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-family: var(--font-mono);
  font-size: 0.8rem;
  color: hsl(var(--primary));
}

.btn-group-mini {
  display: flex;
  gap: 0.25rem;
}

.btn-mini {
  background: hsl(var(--muted));
  border: 1px solid hsl(var(--border));
  border-radius: 4px;
  padding: 0.2rem 0.4rem;
  cursor: pointer;
  font-size: 0.75rem;
  transition: all 0.15s ease;
}

.btn-mini:hover {
  background: hsl(var(--primary) / 0.15);
  border-color: hsl(var(--primary) / 0.3);
}

.flex-actions-row {
  display: flex;
  gap: 0.5rem;
}

.flex-1 {
  flex: 1;
}

/* State views */
.loading-state, .empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  padding: 3rem 1.5rem;
  color: hsl(var(--muted-foreground));
}

.spinner {
  width: 32px;
  height: 32px;
  border: 3px solid hsl(var(--border));
  border-top-color: hsl(var(--primary));
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin-bottom: 1rem;
}

.empty-icon {
  font-size: 2.5rem;
  margin-bottom: 0.75rem;
  opacity: 0.6;
}

/* QR Code Modal Content */
.qr-modal-body {
  display: flex;
  flex-direction: column;
  align-items: center;
}

.qr-container {
  background: white;
  padding: 1rem;
  border-radius: 8px;
  border: 1px solid hsl(var(--border));
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

.qr-image {
  width: 200px;
  height: 200px;
  display: block;
}

.qr-url-display {
  width: 100%;
}

/* Modal overlay and content positioning */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.6);
  backdrop-filter: blur(4px);
  z-index: 99999;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1.5rem;
}

.modal-content {
  background: hsl(var(--card));
  border: 1px solid hsl(var(--border));
  border-radius: 1rem;
  width: 100%;
  display: flex;
  flex-direction: column;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid hsl(var(--border));
}

.modal-title {
  font-size: 1.125rem;
  font-weight: 700;
  color: hsl(var(--foreground));
  margin: 0;
}

.modal-close {
  width: 2rem;
  height: 2rem;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 1px solid hsl(var(--border));
  background: hsl(var(--muted));
  border-radius: 0.375rem;
  cursor: pointer;
  color: hsl(var(--muted-foreground));
  font-size: 0.875rem;
  transition: all 0.2s;
  outline: none;
}

.modal-close:hover {
  background: hsl(var(--destructive) / 0.1);
  color: hsl(var(--destructive));
}

.modal-body {
  padding: 1.5rem;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
  padding: 1rem 1.5rem;
  border-top: 1px solid hsl(var(--border));
  background: hsl(var(--muted) / 0.2);
  border-bottom-left-radius: 1rem;
  border-bottom-right-radius: 1rem;
}

.modal-sm {
  max-width: 380px;
}

/* Modal Transitions */
.modal-fade-enter-active,
.modal-fade-leave-active {
  transition: opacity 0.3s ease;
}

.modal-fade-enter-from,
.modal-fade-leave-to {
  opacity: 0;
}

.modal-fade-enter-active .modal-content,
.modal-fade-leave-active .modal-content {
  transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.modal-fade-enter-from .modal-content,
.modal-fade-leave-to .modal-content {
  transform: scale(0.95);
  opacity: 0;
}

/* Global Toast from page override */
.toast {
  position: fixed;
  top: 1.5rem;
  right: 1.5rem;
  z-index: 99999;
  padding: 0.75rem 1.5rem;
  border-radius: 0.5rem;
  color: white;
  font-weight: 500;
  font-size: 0.875rem;
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
  display: flex;
  align-items: center;
  gap: 0.5rem;
}
.toast-success { background: hsl(var(--success)); }
.toast-error { background: hsl(var(--destructive)); }
.toast-info { background: hsl(var(--primary)); }

.toast-icon {
  font-size: 1rem;
  flex-shrink: 0;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}
</style>
