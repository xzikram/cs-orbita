<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import api from '../../lib/axios'

// State variables
const backups = ref<any[]>([])
const isLoading = ref(false)
const isBackingUp = ref(false)
const isRestoring = ref(false)
const isDeleting = ref(false)

// File upload state
const selectedFile = ref<File | null>(null)
const uploadProgress = ref(0)
const isDragging = ref(false)

// Safeguard restore modal state
const showRestoreModal = ref(false)
const restoreTarget = ref<'file' | 'upload'>('file')
const targetFilename = ref('')
const confirmTextInput = ref('')

// Toast state
const toast = ref<{ show: boolean; message: string; type: string }>({ show: false, message: '', type: 'success' })

function showToast(message: string, type = 'success') {
  toast.value = { show: true, message, type }
  setTimeout(() => {
    toast.value.show = false
  }, 4000)
}

// Fetch list of backups
async function fetchBackups() {
  isLoading.value = true
  try {
    const res = await api.get('/api/v1/admin/backups')
    backups.value = res.data.data
  } catch (err: any) {
    showToast('Gagal memuat daftar backup.', 'error')
  } finally {
    isLoading.value = false
  }
}

// Trigger new backup generation
async function generateBackup() {
  isBackingUp.value = true
  try {
    const res = await api.post('/api/v1/admin/backups')
    backups.value.unshift(res.data.backup)
    showToast('Backup database berhasil dibuat!')
  } catch (err: any) {
    showToast(err.response?.data?.message || 'Gagal membuat backup.', 'error')
  } finally {
    isBackingUp.value = false
  }
}

// Download backup file
async function downloadBackup(filename: string) {
  try {
    // Call download endpoint and trigger browser save
    const response = await api.get(`/api/v1/admin/backups/${filename}/download`, {
      responseType: 'blob',
    })
    const url = window.URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', filename)
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)
    showToast('File backup berhasil diunduh.')
  } catch (err: any) {
    showToast('Gagal mengunduh file backup.', 'error')
  }
}

// Delete backup file
async function deleteBackup(filename: string) {
  if (!confirm(`Apakah Anda yakin ingin menghapus file backup "${filename}"?`)) {
    return
  }

  isDeleting.value = true
  try {
    await api.delete(`/api/v1/admin/backups/${filename}`)
    backups.value = backups.value.filter((b) => b.filename !== filename)
    showToast('Backup berhasil dihapus.')
  } catch (err: any) {
    showToast('Gagal menghapus backup.', 'error')
  } finally {
    isDeleting.value = false
  }
}

// open restore safeguard confirmation modal
function confirmRestoreFromFile(filename: string) {
  restoreTarget.value = 'file'
  targetFilename.value = filename
  confirmTextInput.value = ''
  showRestoreModal.value = true
}

function confirmRestoreFromUpload() {
  if (!selectedFile.value) {
    showToast('Pilih file ZIP terlebih dahulu.', 'error')
    return
  }
  restoreTarget.value = 'upload'
  confirmTextInput.value = ''
  showRestoreModal.value = true
}

// Execute database restore
async function executeRestore() {
  if (confirmTextInput.value.trim().toUpperCase() !== 'RESTORE') {
    showToast('Teks konfirmasi salah. Silakan ketik RESTORE.', 'error')
    return
  }

  showRestoreModal.value = false
  isRestoring.value = true
  showToast('Proses pemulihan database dimulai. Jangan tutup halaman ini...', 'info')

  try {
    if (restoreTarget.value === 'file') {
      // Restore from saved backup
      await api.post(`/api/v1/admin/backups/${targetFilename.value}/restore`)
      showToast('Database berhasil dipulihkan dari cadangan lokal!')
    } else if (restoreTarget.value === 'upload') {
      // Restore from uploaded ZIP
      const formData = new FormData()
      formData.append('backup_file', selectedFile.value!)
      await api.post('/api/v1/admin/backups/restore', formData, {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      })
      showToast('Database dan file berhasil dipulihkan dari file yang diupload!')
      selectedFile.value = null
    }
  } catch (err: any) {
    showToast(err.response?.data?.message || 'Proses pemulihan gagal. Cek integritas file ZIP Anda.', 'error')
  } finally {
    isRestoring.value = false
    confirmTextInput.value = ''
  }
}

// File drag & drop handlers
function onDragOver(e: DragEvent) {
  e.preventDefault()
  isDragging.value = true
}

function onDragLeave() {
  isDragging.value = false
}

function onDrop(e: DragEvent) {
  e.preventDefault()
  isDragging.value = false
  if (e.dataTransfer?.files && e.dataTransfer.files.length > 0) {
    handleFileSelect(e.dataTransfer.files[0])
  }
}

function onFileChange(e: Event) {
  const target = e.target as HTMLInputElement
  if (target.files && target.files.length > 0) {
    handleFileSelect(target.files[0])
  }
}

function handleFileSelect(file: File) {
  if (file.name.split('.').pop()?.toLowerCase() !== 'zip') {
    showToast('Hanya mendukung file dengan format .zip', 'error')
    selectedFile.value = null
    return
  }
  selectedFile.value = file
}

function clearSelectedFile() {
  selectedFile.value = null
}

// Format file size
function formatBytes(bytes: number, decimals = 2) {
  if (bytes === 0) return '0 Bytes'
  const k = 1024
  const dm = decimals < 0 ? 0 : decimals
  const sizes = ['Bytes', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i]
}

function formatIndoDateTime(dateTimeStr: string) {
  if (!dateTimeStr) return '-'
  const date = new Date(dateTimeStr)
  return date.toLocaleString('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

onMounted(() => {
  fetchBackups()
})
</script>

<template>
  <div class="backup-restore-page animate-fade-in">
    <!-- Toast Notification -->
    <Teleport to="body">
      <Transition name="toast-slide">
        <div v-if="toast.show" class="toast" :class="`toast-${toast.type}`">
          <span class="toast-icon">{{ toast.type === 'success' ? '✅' : (toast.type === 'error' ? '❌' : '🔔') }}</span>
          {{ toast.message }}
        </div>
      </Transition>
    </Teleport>

    <!-- Page Header -->
    <div class="page-header">
      <div>
        <h1 class="page-title">Backup & Restore Database</h1>
        <p class="page-subtitle">Amankan data sistem kebersihan rumah sakit dengan membuat cadangan penuh atau memulihkan data dari file sebelumnya.</p>
      </div>
    </div>

    <!-- Cards Grid: Quick Actions -->
    <div class="action-grid mb-6">
      <!-- Create Backup Card -->
      <div class="card glass-card">
        <div class="card-body">
          <div class="action-icon">💾</div>
          <h3 class="card-heading">Generate Cadangan Baru</h3>
          <p class="card-desc text-muted-fg mb-4">
            Ekspor seluruh database serta seluruh berkas fisik foto kegiatan kebersihan & komplain ke dalam satu file arsip ZIP secara otomatis.
          </p>
          <button 
            class="btn btn-primary btn-block" 
            :disabled="isBackingUp || isRestoring" 
            @click="generateBackup"
          >
            <span v-if="isBackingUp" class="spinner-small mr-2"></span>
            <span>{{ isBackingUp ? 'Membuat Cadangan...' : 'Cadangkan Seluruh Data (ZIP)' }}</span>
          </button>
        </div>
      </div>

      <!-- Upload Restore Card -->
      <div class="card glass-card">
        <div class="card-body">
          <div class="action-icon">📤</div>
          <h3 class="card-heading">Upload & Restore Backup (ZIP)</h3>
          <p class="card-desc text-muted-fg mb-4">
            Pulihkan data dengan mengunggah file backup `.zip` eksternal dari komputer Anda. <strong class="text-destructive">Perhatian: Seluruh data saat ini akan terhapus.</strong>
          </p>
          
          <!-- Drag & Drop Zone -->
          <div 
            class="drop-zone"
            :class="{ active: isDragging, 'has-file': selectedFile !== null }"
            @dragover="onDragOver"
            @dragleave="onDragLeave"
            @drop="onDrop"
          >
            <div v-if="!selectedFile" class="drop-zone-prompt">
              <span>拖 📂 Drag & drop file .zip di sini atau </span>
              <label class="file-label">
                Pilih File
                <input type="file" class="file-input-hidden" accept=".zip" @change="onFileChange" />
              </label>
            </div>
            
            <div v-else class="selected-file-details">
              <span class="file-icon">📄</span>
              <div class="file-meta">
                <span class="file-name">{{ selectedFile.name }}</span>
                <span class="file-size">{{ formatBytes(selectedFile.size) }}</span>
              </div>
              <button class="btn-clear" title="Hapus File" @click="clearSelectedFile">✕</button>
            </div>
          </div>

          <button 
            class="btn btn-destructive btn-block mt-4" 
            :disabled="!selectedFile || isRestoring || isBackingUp" 
            @click="confirmRestoreFromUpload"
          >
            <span v-if="isRestoring" class="spinner-small mr-2"></span>
            <span>{{ isRestoring ? 'Memulihkan Data...' : 'Pulihkan Database dari File' }}</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Backup History Card -->
    <div class="card">
      <h3 class="card-heading mb-4">File Cadangan Tersimpan di Server</h3>
      
      <div v-if="isLoading" class="loading-state">
        <div class="spinner"></div>
        <p>Memuat daftar file backup...</p>
      </div>
      
      <div v-else-if="backups.length === 0" class="empty-state">
        <span class="empty-icon">🗄️</span>
        <p>Belum ada file cadangan data yang tersimpan di server lokal.</p>
      </div>
      
      <div v-else class="table-responsive">
        <table class="audit-table">
          <thead>
            <tr>
              <th>Nama File Backup</th>
              <th>Ukuran File</th>
              <th>Waktu Cadangan</th>
              <th width="280">Aksi Pemulihan & Unduhan</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="backup in backups" :key="backup.filename">
              <td class="font-mono text-sm font-semibold text-primary">
                {{ backup.filename }}
              </td>
              <td class="font-medium">{{ formatBytes(backup.size) }}</td>
              <td>{{ formatIndoDateTime(backup.created_at) }}</td>
              <td>
                <div class="action-buttons-group">
                  <button 
                    class="btn btn-sm btn-secondary flex-1" 
                    title="Download File ZIP"
                    :disabled="isRestoring || isBackingUp" 
                    @click="downloadBackup(backup.filename)"
                  >
                    💾 Download
                  </button>
                  <button 
                    class="btn btn-sm btn-success flex-1" 
                    title="Restore Database"
                    :disabled="isRestoring || isBackingUp" 
                    @click="confirmRestoreFromFile(backup.filename)"
                  >
                    🔄 Restore
                  </button>
                  <button 
                    class="btn btn-sm btn-destructive" 
                    title="Hapus Backup"
                    :disabled="isRestoring || isBackingUp" 
                    @click="deleteBackup(backup.filename)"
                  >
                    🗑️
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Restoring overlay lock screen -->
    <div v-if="isRestoring" class="restore-overlay">
      <div class="restore-progress-card animate-scale-up">
        <div class="spinner-pulse mb-4"></div>
        <h3>Sedang Memulihkan Database & File...</h3>
        <p class="text-muted-fg mt-2 text-center text-sm">
          Sistem sedang mengekstrak file ZIP cadangan. Proses ini akan menimpa seluruh tabel, data transaksi kebersihan, log approval, user, dan file fisik foto kegiatan. Jangan keluar dari halaman atau mematikan koneksi server!
        </p>
      </div>
    </div>

    <!-- Safeguard Confirmation Modal -->
    <Teleport to="body">
      <Transition name="modal-fade">
        <div v-if="showRestoreModal" class="modal-overlay" @click.self="showRestoreModal = false">
          <div class="modal-content modal-sm animate-scale-up">
            <div class="modal-header">
              <h3 class="modal-title">⚠️ Konfirmasi Pemulihan Database</h3>
              <button class="modal-close" @click="showRestoreModal = false">✕</button>
            </div>
            <div class="modal-body">
              <div class="alert-box mb-4">
                <span class="alert-icon">🚨</span>
                <div class="alert-text">
                  <strong>PERINGATAN KERAS!</strong>
                  <p class="text-xs text-muted-fg mt-1">
                    Aksi ini akan menghapus dan menimpa SELURUH DATA saat ini secara permanen. Tindakan ini tidak dapat dibatalkan.
                  </p>
                </div>
              </div>

              <p class="text-xs text-muted-fg mb-4">
                Untuk melanjutkan pemulihan database, ketik teks <strong>RESTORE</strong> di bawah ini untuk mengonfirmasi tindakan Anda:
              </p>

              <div class="form-group">
                <input 
                  type="text" 
                  v-model="confirmTextInput" 
                  class="input text-center text-sm font-bold uppercase"
                  placeholder="Ketik RESTORE di sini"
                  @keyup.enter="executeRestore"
                />
              </div>
            </div>
            <div class="modal-footer">
              <button class="btn btn-ghost btn-sm" @click="showRestoreModal = false">Batal</button>
              <button 
                class="btn btn-destructive btn-sm" 
                :disabled="confirmTextInput.trim().toUpperCase() !== 'RESTORE'"
                @click="executeRestore"
              >
                Mulai Pemulihan Data
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<style scoped>
.backup-restore-page {
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

/* Cards & Grid Layout */
.action-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
  gap: 1.5rem;
}

.glass-card {
  background: hsl(var(--card) / 0.6);
  backdrop-filter: blur(10px);
  border: 1px solid hsl(var(--border));
  transition: all 0.2s ease;
}

.glass-card:hover {
  border-color: hsl(var(--primary) / 0.25);
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.03);
}

.action-icon {
  font-size: 2rem;
  margin-bottom: 1rem;
}

.card-heading {
  font-size: 1.1rem;
  font-weight: 750;
  margin-bottom: 0.5rem;
}

.card-desc {
  font-size: 0.825rem;
  line-height: 1.5;
}

/* Drag and Drop Zone */
.drop-zone {
  border: 2px dashed hsl(var(--border));
  border-radius: var(--radius);
  padding: 1.25rem 1rem;
  text-align: center;
  transition: all 0.2s ease;
  background: hsl(var(--secondary) / 0.1);
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 90px;
}

.drop-zone.active {
  border-color: hsl(var(--primary));
  background: hsl(var(--primary) / 0.05);
}

.drop-zone.has-file {
  border-style: solid;
  border-color: hsl(var(--success) / 0.4);
  background: hsl(var(--success) / 0.03);
}

.drop-zone-prompt {
  font-size: 0.8rem;
  color: hsl(var(--muted-foreground));
}

.file-label {
  color: hsl(var(--primary));
  font-weight: 600;
  cursor: pointer;
  text-decoration: underline;
}

.file-input-hidden {
  display: none;
}

.selected-file-details {
  display: flex;
  align-items: center;
  width: 100%;
  gap: 0.75rem;
}

.file-icon {
  font-size: 1.5rem;
}

.file-meta {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  flex: 1;
  overflow: hidden;
}

.file-name {
  font-size: 0.8rem;
  font-weight: 600;
  color: hsl(var(--foreground));
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 180px;
}

.file-size {
  font-size: 0.7rem;
  color: hsl(var(--muted-foreground));
}

.btn-clear {
  background: none;
  border: none;
  color: hsl(var(--muted-foreground));
  cursor: pointer;
  font-size: 1rem;
  padding: 0.25rem;
  border-radius: 4px;
}

.btn-clear:hover {
  background: hsl(var(--destructive) / 0.1);
  color: hsl(var(--destructive));
}

/* Table Actions */
.action-buttons-group {
  display: flex;
  gap: 0.5rem;
}

.btn-block {
  width: 100%;
}

.flex-1 {
  flex: 1;
}

/* Spinner small */
.spinner-small {
  width: 16px;
  height: 16px;
  border: 2px solid transparent;
  border-top-color: currentColor;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
  display: inline-block;
  vertical-align: middle;
}

/* Loading & Empty States */
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

/* Overlays (Restore in Progress Lockscreen) */
.restore-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.8);
  backdrop-filter: blur(8px);
  z-index: 999999;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1.5rem;
}

.restore-progress-card {
  background: hsl(var(--card));
  border: 1px solid hsl(var(--border));
  border-radius: 1.25rem;
  max-width: 500px;
  padding: 2.5rem;
  display: flex;
  flex-direction: column;
  align-items: center;
  box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
}

.spinner-pulse {
  width: 60px;
  height: 60px;
  background-color: hsl(var(--destructive));
  border-radius: 50%;
  opacity: 0.8;
  animation: pulse-restore 1.2s infinite ease-in-out;
}

/* Safeguard Alert Box */
.alert-box {
  display: flex;
  gap: 0.75rem;
  background: hsl(var(--destructive) / 0.1);
  border: 1px solid hsl(var(--destructive) / 0.2);
  padding: 0.875rem;
  border-radius: 0.5rem;
  color: hsl(var(--destructive));
}

.alert-icon {
  font-size: 1.5rem;
  flex-shrink: 0;
}

.alert-text {
  display: flex;
  flex-direction: column;
  text-align: left;
}

.alert-text strong {
  font-weight: 700;
  font-size: 0.85rem;
}

/* Modals */
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
  max-width: 420px;
}

/* Global Toast */
.toast {
  position: fixed;
  top: 1.5rem;
  right: 1.5rem;
  z-index: 999999;
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

/* Keyframes */
@keyframes spin {
  to { transform: rotate(360deg); }
}

@keyframes pulse-restore {
  0% { transform: scale(0.8); opacity: 0.8; }
  50% { transform: scale(1.1); opacity: 0.3; }
  100% { transform: scale(0.8); opacity: 0.8; }
}
</style>
