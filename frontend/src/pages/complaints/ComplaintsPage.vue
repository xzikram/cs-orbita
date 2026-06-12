<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import api from '../../lib/axios'
import { useAuthStore } from '../../stores/auth'

const authStore = useAuthStore()
const complaints = ref<any[]>([])
const loading = ref(true)
const saving = ref(false)
const apiBaseUrl = import.meta.env.VITE_API_URL || 'http://localhost:8000'

// Filters & Pagination
const searchQuery = ref('')
const filterStatus = ref('')
const filterPriority = ref('')
const filterCategory = ref('')
const currentPage = ref(1)
const totalPages = ref(1)

// Expanded Row Details
const expandedId = ref<number | null>(null)
const expandedData = ref<any>(null)
const loadingDetail = ref(false)
const updateNotes = ref('')
const assigneeId = ref<number | null>(null)

// Photo preview modal state
const previewModal = ref({ show: false, url: '', title: '' })
function openPreview(url: string, title: string) {
  previewModal.value = { show: true, url, title }
}
function closePreview() {
  previewModal.value.show = false
}


// New Complaint Form & Photos
const showForm = ref(false)
const areas = ref<any[]>([])
const selectedFiles = ref<File[]>([])
const form = ref({
  area_id: '',
  title: '',
  category: 'kebersihan',
  description: '',
  priority: 'medium'
})

// Cleaning Staff for Assignment
const cleaningStaff = ref<any[]>([])

// Notification toast
const toast = ref<{ show: boolean; message: string; type: string }>({ show: false, message: '', type: 'success' })

function showToast(message: string, type = 'success') {
  toast.value = { show: true, message, type }
  setTimeout(() => {
    toast.value.show = false
  }, 4000)
}

async function loadData(page = 1) {
  loading.value = true
  try {
    const response = await api.get('/api/v1/complaints', {
      params: {
        page,
        status: filterStatus.value || undefined,
        priority: filterPriority.value || undefined,
        category: filterCategory.value || undefined,
      }
    })
    
    // API returns paginated structure
    const paginated = response.data
    complaints.value = paginated.data || []
    currentPage.value = paginated.current_page || 1
    totalPages.value = paginated.last_page || 1
  } catch (e: any) {
    showToast('Gagal memuat data komplain: ' + e.message, 'error')
  } finally {
    loading.value = false
  }
}

const filteredComplaints = computed(() => {
  let list = complaints.value
  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase()
    list = list.filter(c => 
      c.title.toLowerCase().includes(q) || 
      c.description.toLowerCase().includes(q) ||
      (c.area?.name || '').toLowerCase().includes(q) ||
      (c.reporter?.name || '').toLowerCase().includes(q)
    )
  }
  return list
})

async function loadAreas() {
  try {
    const { data } = await api.get('/api/v1/areas')
    areas.value = data.data || data
  } catch (e) {
    console.error('Failed to load areas:', e)
  }
}

async function fetchCleaningStaff() {
  try {
    const response = await api.get('/api/v1/cleaning-services')
    cleaningStaff.value = response.data.data
  } catch (e) {
    console.error('Failed to load cleaning staff:', e)
  }
}

function handleFileChange(e: any) {
  selectedFiles.value = Array.from(e.target.files)
}

async function submitComplaint() {
  if (!form.value.area_id) {
    showToast('Pilih area komplain terlebih dahulu', 'error')
    return
  }
  
  saving.value = true
  try {
    const formData = new FormData()
    formData.append('area_id', form.value.area_id)
    formData.append('title', form.value.title)
    formData.append('category', form.value.category)
    formData.append('description', form.value.description)
    formData.append('priority', form.value.priority)
    
    selectedFiles.value.forEach((file, idx) => {
      formData.append(`photos[${idx}]`, file)
    })

    await api.post('/api/v1/complaints', formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    })
    
    showForm.value = false
    form.value = { area_id: '', title: '', category: 'kebersihan', description: '', priority: 'medium' }
    selectedFiles.value = []
    showToast('Komplain baru berhasil dilaporkan', 'success')
    await loadData(1)
  } catch (e: any) {
    showToast('Gagal mengirim komplain: ' + (e.response?.data?.message || e.message), 'error')
  } finally {
    saving.value = false
  }
}

async function toggleDetails(id: number) {
  if (expandedId.value === id) {
    expandedId.value = null
    expandedData.value = null
    return
  }
  
  expandedId.value = id
  loadingDetail.value = true
  updateNotes.value = ''
  assigneeId.value = null
  
  try {
    const response = await api.get(`/api/v1/complaints/${id}`)
    expandedData.value = response.data.data
    if (expandedData.value?.assignee_id) {
      assigneeId.value = expandedData.value.assignee_id
    }
  } catch (e: any) {
    showToast('Gagal memuat detail komplain: ' + e.message, 'error')
    expandedId.value = null
  } finally {
    loadingDetail.value = false
  }
}

async function updateComplaintStatus(newStatus: string) {
  if (!expandedId.value) return
  
  saving.value = true
  try {
    const payload: any = {
      status: newStatus,
      notes: updateNotes.value || `Status diperbarui menjadi ${newStatus}`
    }
    
    if (assigneeId.value) {
      payload.assignee_id = assigneeId.value
    }
    
    await api.put(`/api/v1/complaints/${expandedId.value}/status`, payload)
    showToast('Status komplain berhasil diperbarui', 'success')
    updateNotes.value = ''
    
    // Refresh list and details
    await loadData(currentPage.value)
    const detailResponse = await api.get(`/api/v1/complaints/${expandedId.value}`)
    expandedData.value = detailResponse.data.data
  } catch (e: any) {
    showToast('Gagal memperbarui status: ' + e.message, 'error')
  } finally {
    saving.value = false
  }
}

async function handleAssignStaff() {
  if (!expandedId.value) return
  saving.value = true
  try {
    await api.put(`/api/v1/complaints/${expandedId.value}/status`, {
      status: expandedData.value.status,
      notes: updateNotes.value || 'Petugas penanggung jawab diperbarui',
      assignee_id: assigneeId.value || undefined
    })
    showToast('Staf berhasil ditugaskan', 'success')
    updateNotes.value = ''
    
    await loadData(currentPage.value)
    const detailResponse = await api.get(`/api/v1/complaints/${expandedId.value}`)
    expandedData.value = detailResponse.data.data
  } catch (e: any) {
    showToast('Gagal menugaskan staf: ' + e.message, 'error')
  } finally {
    saving.value = false
  }
}

function getPriorityLabel(priority: string) {
  const map: Record<string, string> = {
    low: 'Rendah',
    medium: 'Sedang',
    high: 'Tinggi',
    critical: 'Kritis'
  }
  return map[priority] || priority
}

function getStatusLabel(status: string) {
  const map: Record<string, string> = {
    open: 'Terbuka',
    in_progress: 'Diproses',
    resolved: 'Selesai',
    closed: 'Ditutup'
  }
  return map[status] || status
}

function getCategoryLabel(cat: string) {
  const map: Record<string, string> = {
    kebersihan: 'Kebersihan',
    kerusakan: 'Kerusakan Fasilitas',
    kehabisan_stok: 'Kehabisan Stok'
  }
  return map[cat] || cat
}

function getSLADeadlineString(deadlineStr: string, status: string) {
  if (['resolved', 'closed'].includes(status)) return 'Selesai'
  
  try {
    const deadline = new Date(deadlineStr)
    const diff = deadline.getTime() - Date.now()
    if (diff < 0) {
      const hours = Math.abs(Math.round(diff / 3600000))
      return `⚠️ Terlambat ${hours} jam`
    } else {
      const hours = Math.round(diff / 3600000)
      return `⏱️ Sisa ${hours} jam`
    }
  } catch {
    return '-'
  }
}

// Active filter count for badge indicator
const activeFilterCount = computed(() => {
  let count = 0
  if (filterStatus.value) count++
  if (filterPriority.value) count++
  if (filterCategory.value) count++
  return count
})

function clearAllFilters() {
  filterStatus.value = ''
  filterPriority.value = ''
  filterCategory.value = ''
  searchQuery.value = ''
  loadData(1)
}

onMounted(() => {
  loadData()
  if (authStore.user?.role === 'kepala_ruangan') {
    loadAreas()
  }
  if (['supervisor', 'administrator'].includes(authStore.user?.role || '')) {
    fetchCleaningStaff()
  }
})
</script>

<template>
  <div class="complaints-page animate-fade-in">
    <!-- Toast Message -->
    <Teleport to="body">
      <Transition name="toast-slide">
        <div v-if="toast.show" class="toast" :class="`toast-${toast.type}`">
          <span class="toast-icon">{{ toast.type === 'success' ? '✅' : '❌' }}</span>
          {{ toast.message }}
        </div>
      </Transition>
    </Teleport>

    <!-- Header -->
    <div class="complaints-header">
      <div>
        <h1 class="page-title">Komplain & Masalah</h1>
        <p class="page-subtitle">Kelola laporan unit dan komplain kebersihan ruangan.</p>
      </div>
      <button v-if="authStore.user?.role === 'kepala_ruangan'" class="btn btn-primary" @click="showForm = !showForm">
        {{ showForm ? 'Tutup Form' : '+ Buat Laporan' }}
      </button>
    </div>

    <!-- Create Complaint Modal Dialog/Card -->
    <Transition name="form-slide">
      <div v-if="showForm" class="card form-card mb-6">
        <h2 class="form-title">Laporkan Komplain Baru</h2>
        <form @submit.prevent="submitComplaint" class="form-grid">
          <div class="form-group">
            <label class="label">Area / Lokasi</label>
            <select v-model="form.area_id" class="input" required>
              <option value="">Pilih Area...</option>
              <option v-for="a in areas" :key="a.id" :value="a.id">{{ a.name }} [{{ a.code }}]</option>
            </select>
          </div>
          
          <div class="form-group">
            <label class="label">Kategori Komplain</label>
            <select v-model="form.category" class="input" required>
              <option value="kebersihan">Kebersihan</option>
              <option value="kerusakan">Kerusakan Fasilitas</option>
              <option value="kehabisan_stok">Kehabisan Stok (Sabun/Tisu/Lainnya)</option>
            </select>
          </div>

          <div class="form-group">
            <label class="label">Judul Masalah</label>
            <input type="text" v-model="form.title" class="input" placeholder="Contoh: Lantai toilet becek dan bau" required />
          </div>

          <div class="form-group">
            <label class="label">Tingkat Prioritas</label>
            <select v-model="form.priority" class="input" required>
              <option value="low">Rendah (SLA 24 jam)</option>
              <option value="medium">Sedang (SLA 12 jam)</option>
              <option value="high">Tinggi (SLA 4 jam)</option>
              <option value="critical">Kritis (SLA 1 jam)</option>
            </select>
          </div>

          <div class="form-group full-width">
            <label class="label">Deskripsi Lengkap</label>
            <textarea v-model="form.description" class="input textarea" placeholder="Jelaskan secara detail mengenai masalah kebersihan yang ditemukan..." required></textarea>
          </div>

          <div class="form-group full-width">
            <label class="label">Lampiran Foto Bukti (Maks 4)</label>
            <input type="file" multiple accept="image/*" @change="handleFileChange" class="input file-input" />
            <p class="file-info" v-if="selectedFiles.length > 0">
              Terpilih: {{ selectedFiles.length }} file ({{ selectedFiles.map(f => f.name).join(', ') }})
            </p>
          </div>

          <div class="form-actions full-width">
            <button type="button" class="btn btn-ghost" @click="showForm = false">Batal</button>
            <button type="submit" class="btn btn-primary" :disabled="saving">
              {{ saving ? 'Mengirim...' : 'Kirim Laporan' }}
            </button>
          </div>
        </form>
      </div>
    </Transition>

    <!-- Filters & Search Toolbar -->
    <div class="card toolbar-card mb-6 animate-slide-up">
      <div class="toolbar-grid">
        <div class="search-box">
          <span class="search-icon">🔍</span>
          <input type="text" v-model="searchQuery" class="input input-search" placeholder="Cari area, judul, deskripsi..." />
        </div>
        
        <div class="filters-group">
          <select v-model="filterStatus" class="input select-filter" @change="loadData(1)">
            <option value="">Semua Status</option>
            <option value="open">Terbuka</option>
            <option value="in_progress">Diproses</option>
            <option value="resolved">Selesai</option>
            <option value="closed">Ditutup</option>
          </select>

          <select v-model="filterPriority" class="input select-filter" @change="loadData(1)">
            <option value="">Semua Prioritas</option>
            <option value="low">Rendah</option>
            <option value="medium">Sedang</option>
            <option value="high">Tinggi</option>
            <option value="critical">Kritis</option>
          </select>

          <select v-model="filterCategory" class="input select-filter" @change="loadData(1)">
            <option value="">Semua Kategori</option>
            <option value="kebersihan">Kebersihan</option>
            <option value="kerusakan">Kerusakan</option>
            <option value="kehabisan_stok">Kehabisan Stok</option>
          </select>

          <button
            v-if="activeFilterCount > 0"
            class="btn btn-ghost clear-filter-btn"
            @click="clearAllFilters"
            title="Hapus semua filter"
          >
            ✕ Reset ({{ activeFilterCount }})
          </button>
        </div>
      </div>
    </div>

    <!-- Complaints Table Card -->
    <div class="card table-card animate-slide-up">
      <div v-if="loading" class="loading-container">
        <div class="spinner-large"></div>
        <p class="loading-text">Memuat data komplain...</p>
      </div>

      <div v-else class="table-responsive">
        <table class="audit-table">
          <thead>
            <tr>
              <th width="80">ID</th>
              <th width="160">Area</th>
              <th width="150">Kategori</th>
              <th>Keluhan</th>
              <th width="150">Reporter</th>
              <th width="140">Status</th>
              <th width="130">Batas SLA</th>
            </tr>
          </thead>
          <tbody>
            <template v-for="c in filteredComplaints" :key="c.id">
              <!-- Regular Table Row -->
              <tr class="clickable-row" :class="{ 'row-active': expandedId === c.id }" @click="toggleDetails(c.id)">
                <td class="cell-id">#{{ c.id }}</td>
                <td>
                  <div class="cell-area-name">{{ c.area?.name || '-' }}</div>
                  <div class="cell-area-code">{{ c.area?.code || '-' }}</div>
                </td>
                <td>
                  <span class="category-badge">{{ getCategoryLabel(c.category) }}</span>
                </td>
                <td>
                  <div class="cell-title">{{ c.title }}</div>
                  <div class="cell-date">
                    {{ new Date(c.created_at).toLocaleString('id-ID', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) }}
                  </div>
                </td>
                <td>
                  <div class="cell-reporter">{{ c.reporter?.name || '-' }}</div>
                  <div class="cell-role">{{ c.reporter?.role_label || 'Staf' }}</div>
                </td>
                <td>
                  <div class="badges-wrapper">
                    <span class="badge" :class="`badge-status-${c.status}`">
                      {{ getStatusLabel(c.status) }}
                    </span>
                    <span class="badge" :class="`badge-priority-${c.priority}`">
                      {{ getPriorityLabel(c.priority) }}
                    </span>
                  </div>
                </td>
                <td class="cell-sla">
                  <span :class="{ 
                    'sla-late': !['resolved', 'closed'].includes(c.status) && new Date(c.sla_deadline) < new Date(),
                    'sla-ok': ['resolved', 'closed'].includes(c.status)
                  }">
                    {{ getSLADeadlineString(c.sla_deadline, c.status) }}
                  </span>
                </td>
              </tr>

              <!-- Expandable Row Details -->
              <tr v-if="expandedId === c.id" class="details-row">
                <td colspan="7">
                  <div class="details-panel-wrapper">
                    <!-- Loading state for details -->
                    <div v-if="loadingDetail" class="details-loading">
                      <div class="spinner-small"></div>
                    </div>

                    <!-- Details Content -->
                    <div v-else-if="expandedData" class="details-panel-grid">
                      <!-- Left Column: Info, Desc, Images -->
                      <div class="details-main">
                        <div class="details-section">
                          <h4 class="details-heading">Deskripsi Keluhan</h4>
                          <p class="details-desc">{{ expandedData.description }}</p>
                        </div>

                        <!-- Gallery Photos -->
                        <div class="details-section" v-if="expandedData.photos && expandedData.photos.length > 0">
                          <h4 class="details-heading">Lampiran Bukti ({{ expandedData.photos.length }} Foto)</h4>
                          <div class="image-gallery">
                            <div
                              v-for="photo in expandedData.photos"
                              :key="photo.id"
                              class="gallery-thumbnail"
                              @click="openPreview(`${apiBaseUrl}/storage/${photo.file_path}`, 'Lampiran Bukti')"
                            >
                              <img :src="`${apiBaseUrl}/storage/${photo.file_path}`" alt="Lampiran Bukti" />
                              <div class="gallery-overlay">
                                <span>🔍</span>
                              </div>
                            </div>
                          </div>
                        </div>

                        <!-- Basic details -->
                        <div class="metadata-box">
                          <div class="meta-item">
                            <span class="meta-lbl">Target Selesai (SLA):</span>
                            <span class="meta-val meta-mono">{{ new Date(expandedData.sla_deadline).toLocaleString('id-ID') }}</span>
                          </div>
                          <div class="meta-item" v-if="expandedData.assignee">
                            <span class="meta-lbl">Penanggung Jawab:</span>
                            <span class="meta-val meta-primary meta-bold">{{ expandedData.assignee.name }}</span>
                          </div>
                          <div class="meta-item" v-if="expandedData.resolved_at">
                            <span class="meta-lbl">Selesai Pada:</span>
                            <span class="meta-val meta-success">{{ new Date(expandedData.resolved_at).toLocaleString('id-ID') }}</span>
                          </div>
                        </div>
                      </div>

                      <!-- Right Column: Timeline & Update Action -->
                      <div class="details-actions-timeline">
                        <!-- Timeline -->
                        <div class="timeline-box">
                          <h4 class="details-heading timeline-heading">Logs & Catatan Tindakan</h4>
                          <div class="timeline">
                            <div v-for="update in expandedData.updates" :key="update.id" class="timeline-item">
                              <div class="timeline-badge" :class="`bg-${update.status_to}`"></div>
                              <div class="timeline-content">
                                <div class="timeline-header">
                                  <span class="timeline-user">{{ update.user?.name }}</span>
                                  <span class="timeline-time">{{ new Date(update.created_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) }}</span>
                                </div>
                                <div class="timeline-status-shift">
                                  Status: <span class="timeline-status-val">{{ getStatusLabel(update.status_to) }}</span>
                                </div>
                                <p class="timeline-note" v-if="update.notes">{{ update.notes }}</p>
                              </div>
                            </div>
                          </div>
                        </div>

                        <!-- Supervisor/Admin actions -->
                        <div class="action-panel" v-if="['supervisor', 'administrator'].includes(authStore.user?.role || '')">
                          <h4 class="details-heading">Tindakan Lanjutan</h4>
                          
                          <!-- Note text input -->
                          <div class="form-group action-form-group">
                            <textarea v-model="updateNotes" class="input textarea mini-textarea" placeholder="Tambahkan catatan progres/penyelesaian di sini..."></textarea>
                          </div>

                          <!-- Staff assignment -->
                          <div class="form-group action-form-group">
                            <label class="label-sub">Tugaskan ke Staf Pembersihan</label>
                            <div class="assign-row">
                              <select v-model="assigneeId" class="input select-staff">
                                <option :value="null">Belum Ditugaskan</option>
                                <option v-for="staff in cleaningStaff" :key="staff.id" :value="staff.id">
                                  {{ staff.name }}
                                </option>
                              </select>
                              <button class="btn btn-secondary assign-btn" @click="handleAssignStaff" :disabled="saving">
                                Tugaskan
                              </button>
                            </div>
                          </div>

                          <!-- Action buttons -->
                          <div class="action-buttons">
                            <button v-if="expandedData.status === 'open'" class="btn btn-primary action-btn" @click="updateComplaintStatus('in_progress')" :disabled="saving">
                              Proses Komplain
                            </button>
                            <button v-if="expandedData.status === 'in_progress'" class="btn btn-success action-btn" @click="updateComplaintStatus('resolved')" :disabled="saving">
                              Tandai Selesai
                            </button>
                            <button v-if="expandedData.status === 'resolved'" class="btn btn-secondary action-btn" @click="updateComplaintStatus('closed')" :disabled="saving">
                              Tutup & Arsipkan
                            </button>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </td>
              </tr>
            </template>
            <tr v-if="filteredComplaints.length === 0">
              <td colspan="7" class="empty-td">
                <div class="empty-state">
                  <span class="empty-icon-tbl">📭</span>
                  <p class="empty-msg">Tidak ditemukan data komplain yang cocok.</p>
                  <p class="empty-sub" v-if="activeFilterCount > 0">
                    Coba hapus filter atau ubah kata pencarian.
                  </p>
                  <button v-if="activeFilterCount > 0" class="btn btn-ghost btn-reset-empty" @click="clearAllFilters">
                    Reset Filter
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination Footer -->
      <div class="pagination-footer" v-if="totalPages > 1">
        <span class="page-info">
          Halaman <b>{{ currentPage }}</b> dari {{ totalPages }}
        </span>
        <div class="page-buttons">
          <button class="btn btn-secondary btn-pagination" :disabled="currentPage === 1 || loading" @click="loadData(currentPage - 1)">
            ← Sebelumnya
          </button>
          <button class="btn btn-secondary btn-pagination" :disabled="currentPage === totalPages || loading" @click="loadData(currentPage + 1)">
            Selanjutnya →
          </button>
        </div>
      </div>
    </div>

    <!-- Preview Modal -->
    <Teleport to="body">
      <Transition name="modal-fade">
        <div v-if="previewModal.show" class="preview-modal-overlay" @click="closePreview">
          <div class="preview-modal-content" @click.stop>
            <button class="preview-close-btn" @click="closePreview">✕</button>
            <img :src="previewModal.url" :alt="previewModal.title" class="preview-modal-img" />
            <div class="preview-modal-caption">{{ previewModal.title }}</div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<style scoped>
/* ===== Toast ===== */
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

.toast-icon {
  font-size: 1rem;
  flex-shrink: 0;
}

.toast-slide-enter-active {
  animation: slideIn 0.3s ease;
}
.toast-slide-leave-active {
  animation: slideOut 0.3s ease;
}

@keyframes slideIn {
  from { transform: translateX(100%); opacity: 0; }
  to { transform: translateX(0); opacity: 1; }
}

@keyframes slideOut {
  from { transform: translateX(0); opacity: 1; }
  to { transform: translateX(100%); opacity: 0; }
}

/* ===== Page Header ===== */
.complaints-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
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

/* ===== Form Card ===== */
.form-card {
  background: hsl(var(--card) / 0.95);
  border: 1px solid hsl(var(--primary) / 0.3);
}

.form-title {
  font-weight: 700;
  margin-bottom: 1rem;
  font-size: 1.0625rem;
  color: hsl(var(--foreground));
}

.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1.25rem;
}

.full-width {
  grid-column: 1 / -1;
}

.textarea {
  min-height: 80px;
}

.file-input {
  padding: 0.5rem;
  font-size: 0.875rem;
}

.file-info {
  font-size: 0.75rem;
  color: hsl(var(--muted-foreground));
  margin-top: 0.25rem;
}

.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
  margin-top: 0.5rem;
}

.form-slide-enter-active,
.form-slide-leave-active {
  transition: all 0.3s ease;
}

.form-slide-enter-from,
.form-slide-leave-to {
  opacity: 0;
  transform: translateY(-12px);
}

/* ===== Toolbar Filters ===== */
.toolbar-card {
  background: hsl(var(--card) / 0.8);
  padding: 1rem 1.25rem;
}

.toolbar-grid {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
}

.search-box {
  position: relative;
  flex: 1;
}

.search-icon {
  position: absolute;
  left: 0.75rem;
  top: 50%;
  transform: translateY(-50%);
  color: hsl(var(--muted-foreground));
  font-size: 0.875rem;
}

.input-search {
  padding-left: 2.25rem;
  width: 100%;
}

.filters-group {
  display: flex;
  gap: 0.75rem;
  align-items: center;
}

.select-filter {
  min-width: 130px;
}

.clear-filter-btn {
  font-size: 0.75rem;
  padding: 0.5rem 0.75rem;
  color: hsl(var(--destructive));
  white-space: nowrap;
}

/* ===== Table ===== */
.table-card {
  padding: 0;
  overflow: hidden;
}

.table-responsive {
  overflow-x: auto;
}

/* Clickable Row */
.clickable-row {
  cursor: pointer;
  transition: background-color 0.2s;
}

.clickable-row:hover {
  background-color: hsl(var(--muted) / 0.3) !important;
}

.row-active {
  background-color: hsl(var(--primary) / 0.05) !important;
  border-left: 3px solid hsl(var(--primary));
}

/* Cell styles */
.cell-id {
  font-family: var(--font-mono);
  font-size: 0.8125rem;
  font-weight: 700;
}

.cell-area-name {
  font-weight: 500;
}

.cell-area-code {
  font-size: 0.75rem;
  color: hsl(var(--muted-foreground));
}

.cell-title {
  font-weight: 500;
  max-width: 250px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.cell-date {
  font-size: 0.75rem;
  color: hsl(var(--muted-foreground));
  margin-top: 0.125rem;
}

.cell-reporter {
  font-weight: 500;
}

.cell-role {
  font-size: 0.75rem;
  color: hsl(var(--muted-foreground));
}

.cell-sla {
  font-family: var(--font-mono);
  font-size: 0.75rem;
}

.sla-late {
  color: hsl(var(--destructive));
  font-weight: 700;
}

.sla-ok {
  color: hsl(var(--success));
}

/* Badges */
.badges-wrapper {
  display: flex;
  flex-wrap: wrap;
  gap: 0.25rem;
}

/* Category badge */
.category-badge {
  font-size: 0.75rem;
  font-weight: 500;
  padding: 0.25rem 0.5rem;
  border-radius: 0.25rem;
  background: hsl(var(--muted));
  border: 1px solid hsl(var(--border));
}

/* Status Badges */
.badge-status-open { background: hsl(var(--warning) / 0.15); color: hsl(var(--warning)); border: 1px solid hsl(var(--warning) / 0.3); }
.badge-status-in_progress { background: hsl(var(--primary) / 0.15); color: hsl(var(--primary)); border: 1px solid hsl(var(--primary) / 0.3); }
.badge-status-resolved { background: hsl(var(--success) / 0.15); color: hsl(var(--success)); border: 1px solid hsl(var(--success) / 0.3); }
.badge-status-closed { background: hsl(var(--muted-foreground) / 0.15); color: hsl(var(--muted-foreground)); border: 1px solid hsl(var(--muted-foreground) / 0.3); }

/* Priority Badges */
.badge-priority-low { background: hsl(var(--muted) / 0.4); color: hsl(var(--muted-foreground)); }
.badge-priority-medium { background: hsl(210, 80%, 15%); color: hsl(210, 80%, 60%); }
.badge-priority-high { background: hsl(38, 70%, 15%); color: hsl(38, 70%, 60%); }
.badge-priority-critical { background: hsl(0, 70%, 15%); color: hsl(0, 70%, 65%); }

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

.spinner-small {
  width: 1.5rem;
  height: 1.5rem;
  border: 3px solid rgba(255,255,255,0.1);
  border-top-color: hsl(var(--primary));
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* ===== Expanded details panel ===== */
.details-row {
  background-color: hsl(var(--card) / 0.6) !important;
}

.details-panel-wrapper {
  padding: 1.5rem;
}

.details-loading {
  display: flex;
  justify-content: center;
  padding: 1.5rem;
}

.details-panel-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 2rem;
}

.details-main {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.details-section {
  margin-bottom: 0;
}

.details-heading {
  font-size: 0.8125rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: hsl(var(--muted-foreground));
  margin-bottom: 0.5rem;
}

.details-desc {
  font-size: 0.875rem;
  line-height: 1.5;
  color: hsl(var(--foreground));
  white-space: pre-wrap;
}

/* Image gallery */
.image-gallery {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
}

.gallery-thumbnail {
  width: 90px;
  height: 90px;
  border-radius: 0.5rem;
  overflow: hidden;
  border: 1px solid hsl(var(--border));
  transition: transform 0.2s;
  display: block;
  position: relative;
  cursor: pointer;
}

.gallery-thumbnail img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.gallery-thumbnail:hover {
  transform: scale(1.05);
}

.gallery-overlay {
  position: absolute;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  opacity: 0;
  transition: opacity 0.2s ease;
  font-size: 1.5rem;
}

.gallery-thumbnail:hover .gallery-overlay {
  opacity: 1;
}

/* Metadata box */
.metadata-box {
  background: hsl(var(--muted) / 0.3);
  border: 1px solid hsl(var(--border) / 0.5);
  border-radius: 0.5rem;
  padding: 0.75rem;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  margin-top: auto;
}

.meta-item {
  display: flex;
  justify-content: space-between;
  font-size: 0.75rem;
}

.meta-lbl {
  color: hsl(var(--muted-foreground));
}

.meta-val {
  font-weight: 500;
}

.meta-mono {
  font-family: var(--font-mono);
}

.meta-primary {
  color: hsl(var(--primary));
}

.meta-bold {
  font-weight: 700;
}

.meta-success {
  color: hsl(var(--success));
}

/* Timeline updates box */
.timeline-box {
  max-height: 250px;
  overflow-y: auto;
  padding-right: 0.5rem;
}

.timeline-heading {
  margin-bottom: 0.75rem;
}

.timeline {
  position: relative;
  padding-left: 1rem;
  border-left: 2px solid hsl(var(--border));
}

.timeline-item {
  position: relative;
  margin-bottom: 1rem;
}

.timeline-item:last-child {
  margin-bottom: 0;
}

.timeline-badge {
  position: absolute;
  left: calc(-1rem - 5px);
  top: 4px;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background-color: hsl(var(--primary));
}

.bg-open { background-color: hsl(var(--warning)); }
.bg-in_progress { background-color: hsl(var(--primary)); }
.bg-resolved { background-color: hsl(var(--success)); }
.bg-closed { background-color: hsl(var(--muted-foreground)); }

.timeline-content {
  background: hsl(var(--muted) / 0.2);
  border: 1px solid hsl(var(--border) / 0.3);
  border-radius: 0.5rem;
  padding: 0.5rem 0.75rem;
}

.timeline-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.timeline-user {
  font-size: 0.75rem;
  font-weight: 700;
}

.timeline-time {
  font-size: 0.65rem;
  color: hsl(var(--muted-foreground));
}

.timeline-status-shift {
  font-size: 0.75rem;
  margin-top: 0.125rem;
  color: hsl(var(--muted-foreground));
}

.timeline-status-val {
  font-weight: 700;
  color: hsl(var(--primary));
}

.timeline-note {
  font-size: 0.75rem;
  color: hsl(var(--muted-foreground));
  margin-top: 0.25rem;
}

/* Actions Form styling */
.action-panel {
  background: hsl(var(--muted) / 0.3);
  border: 1px solid hsl(var(--border) / 0.5);
  border-radius: 0.5rem;
  padding: 0.75rem;
  margin-top: 1rem;
}

.action-form-group {
  margin-bottom: 0.75rem;
}

.label-sub {
  font-size: 0.7rem;
  font-weight: 600;
  color: hsl(var(--muted-foreground));
  margin-bottom: 0.25rem;
  display: block;
}

.mini-textarea {
  min-height: 50px;
  font-size: 0.75rem;
}

.assign-row {
  display: flex;
  gap: 0.5rem;
}

.select-staff {
  font-size: 0.75rem;
  height: 2.25rem;
  flex: 1;
}

.assign-btn {
  font-size: 0.75rem;
  flex-shrink: 0;
}

.action-buttons {
  display: flex;
  gap: 0.5rem;
  margin-top: 0.5rem;
}

.action-btn {
  font-size: 0.8125rem;
  flex: 1;
}

/* ===== Empty State ===== */
.empty-td {
  padding: 0 !important;
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 3rem 1rem;
  gap: 0.5rem;
}

.empty-icon-tbl {
  font-size: 2.5rem;
}

.empty-msg {
  font-size: 0.9375rem;
  font-weight: 500;
  color: hsl(var(--muted-foreground));
}

.empty-sub {
  font-size: 0.8125rem;
  color: hsl(var(--muted-foreground));
  opacity: 0.7;
}

.btn-reset-empty {
  font-size: 0.8125rem;
  margin-top: 0.5rem;
  color: hsl(var(--primary));
}

/* ===== Pagination Footer ===== */
.pagination-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.75rem 1.5rem;
  background: hsl(var(--card));
  border-top: 1px solid hsl(var(--border));
}

.page-info {
  font-size: 0.875rem;
  color: hsl(var(--muted-foreground));
}

.page-buttons {
  display: flex;
  gap: 0.5rem;
}

.btn-pagination {
  padding: 0.375rem 0.75rem;
  font-size: 0.75rem;
}

/* ===== Preview Modal ===== */
.preview-modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.75);
  backdrop-filter: blur(8px);
  z-index: 99999;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1.5rem;
}

.preview-modal-content {
  position: relative;
  max-width: 90vw;
  max-height: 85vh;
  background: hsl(var(--card));
  border: 1px solid hsl(var(--border) / 0.5);
  border-radius: 0.75rem;
  overflow: hidden;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
  display: flex;
  flex-direction: column;
}

.preview-modal-img {
  max-width: 100%;
  max-height: 75vh;
  object-fit: contain;
  display: block;
}

.preview-modal-caption {
  padding: 0.75rem 1.25rem;
  font-size: 0.875rem;
  font-weight: 600;
  text-align: center;
  background: hsl(var(--muted) / 0.5);
  color: hsl(var(--foreground));
  border-top: 1px solid hsl(var(--border) / 0.5);
}

.preview-close-btn {
  position: absolute;
  top: 0.75rem;
  right: 0.75rem;
  width: 2rem;
  height: 2rem;
  border-radius: 50%;
  background: rgba(0, 0, 0, 0.5);
  color: white;
  border: none;
  font-size: 0.875rem;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background 0.2s, transform 0.2s;
  z-index: 10;
}

.preview-close-btn:hover {
  background: rgba(0, 0, 0, 0.8);
  transform: scale(1.1);
}

.modal-fade-enter-active {
  animation: fadeIn 0.2s ease-out;
}
.modal-fade-enter-active .preview-modal-content {
  animation: zoomIn 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.modal-fade-leave-active {
  animation: fadeOut 0.2s ease-in;
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

@keyframes fadeOut {
  from { opacity: 1; }
  to { opacity: 0; }
}

@keyframes zoomIn {
  from { transform: scale(0.95); opacity: 0; }
  to { transform: scale(1); opacity: 1; }
}

/* ===== Spacing ===== */
.mb-6 { margin-bottom: 1.5rem; }

/* ===== Responsiveness ===== */
@media (max-width: 1024px) {
  .details-panel-grid {
    grid-template-columns: 1fr;
    gap: 1.5rem;
  }
}

@media (max-width: 768px) {
  .complaints-header {
    flex-direction: column;
    align-items: stretch;
    gap: 0.75rem;
  }

  .form-grid {
    grid-template-columns: 1fr;
  }

  .toolbar-grid {
    flex-direction: column;
    align-items: stretch;
  }

  .filters-group {
    flex-direction: column;
  }

  .select-filter {
    min-width: unset;
  }

  .page-buttons {
    flex-direction: column;
  }

  .pagination-footer {
    flex-direction: column;
    gap: 0.75rem;
    align-items: stretch;
    text-align: center;
  }
}
</style>
