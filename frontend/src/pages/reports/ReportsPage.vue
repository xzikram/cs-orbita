<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '../../lib/axios'

const month = ref(new Date().getMonth() + 1)
const year = ref(new Date().getFullYear())
const areaId = ref<number | string>('')
const areas = ref<any[]>([])

const loading = ref({
  monthly: false,
  audit: false,
  'matrix-excel': false
})

// Track last download timestamp
const lastDownload = ref<Record<string, string>>({})

const toast = ref<{ show: boolean; message: string; type: string }>({ show: false, message: '', type: 'success' })

function showToast(message: string, type = 'success') {
  toast.value = { show: true, message, type }
  setTimeout(() => {
    toast.value.show = false
  }, 4000)
}

const monthNames: Record<number, string> = {
  1: 'Januari', 2: 'Februari', 3: 'Maret', 4: 'April',
  5: 'Mei', 6: 'Juni', 7: 'Juli', 8: 'Agustus',
  9: 'September', 10: 'Oktober', 11: 'November', 12: 'Desember'
}

function getPeriodLabel() {
  return `${monthNames[month.value] || ''} ${year.value}`
}

async function fetchAreas() {
  try {
    const { data } = await api.get('/api/v1/areas')
    areas.value = data.data || data
    if (areas.value.length > 0) {
      areaId.value = areas.value[0].id
    }
  } catch (e: any) {
    showToast('Gagal memuat daftar area: ' + e.message, 'error')
  }
}

onMounted(() => {
  fetchAreas()
})

function getSelectedAreaName(): string {
  if (!areaId.value) return ''
  // Use == for loose comparison since areaId may be string or number
  const found = areas.value.find(a => a.id == areaId.value)
  return found?.name || 'Unknown'
}

async function downloadReport(type: 'monthly' | 'audit' | 'matrix-excel') {
  loading.value[type] = true
  try {
    const params: any = { month: month.value, year: year.value }
    if (type === 'matrix-excel') {
      if (!areaId.value) {
        showToast('Pilih area terlebih dahulu untuk mengunduh Laporan Matrix.', 'error')
        loading.value[type] = false
        return
      }
      params.area_id = areaId.value
    }
    
    // Map type to actual backend route
    const routeMap: Record<string, string> = {
      'monthly': '/api/v1/reports/cleaning',
      'audit': '/api/v1/reports/audits',
      'matrix-excel': '/api/v1/reports/matrix-excel'
    }
    
    const response = await api.get(routeMap[type], {
      params,
      responseType: 'blob'
    })
    
    // Create blob link to download
    const url = window.URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement('a')
    link.href = url
    
    // Extract filename from header if possible
    let filename = `laporan_${type}_${year.value}_${month.value}.${type === 'matrix-excel' ? 'xls' : 'csv'}`
    const contentDisposition = response.headers['content-disposition']
    if (contentDisposition) {
      const filenameMatch = contentDisposition.match(/filename="?([^"]+)"?/)
      if (filenameMatch && filenameMatch.length === 2) {
        filename = filenameMatch[1]
      }
    }
    
    link.setAttribute('download', filename)
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)
    
    // Record last download timestamp
    lastDownload.value[type] = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
    
    showToast(`Berhasil mengunduh laporan ${type === 'matrix-excel' ? 'Excel Matrix' : 'CSV'}!`, 'success')
  } catch (e: any) {
    console.error(e)
    showToast('Gagal mengunduh laporan. Silakan coba beberapa saat lagi.', 'error')
  } finally {
    loading.value[type] = false
  }
}
</script>

<template>
  <div class="reports-page animate-fade-in">
    <!-- Toast notification -->
    <Teleport to="body">
      <Transition name="toast-slide">
        <div v-if="toast.show" class="toast" :class="`toast-${toast.type}`">
          <span class="toast-icon">{{ toast.type === 'success' ? '✅' : '❌' }}</span>
          {{ toast.message }}
        </div>
      </Transition>
    </Teleport>

    <div class="reports-header">
      <div>
        <h1 class="page-title">Laporan & Ekspor</h1>
        <p class="page-subtitle">Unduh file laporan berkala aktivitas kebersihan, audit, dan matrix ruangan.</p>
      </div>
    </div>

    <!-- Filter Periode Card -->
    <div class="card filter-card mb-6 animate-slide-up">
      <h2 class="filter-title">
        <span>📅</span> Pilih Periode Laporan
      </h2>
      <div class="filter-row">
        <div class="form-group filter-field">
          <label class="label">Bulan</label>
          <select v-model="month" class="input select-input">
            <option :value="1">Januari</option>
            <option :value="2">Februari</option>
            <option :value="3">Maret</option>
            <option :value="4">April</option>
            <option :value="5">Mei</option>
            <option :value="6">Juni</option>
            <option :value="7">Juli</option>
            <option :value="8">Agustus</option>
            <option :value="9">September</option>
            <option :value="10">Oktober</option>
            <option :value="11">November</option>
            <option :value="12">Desember</option>
          </select>
        </div>
        
        <div class="form-group filter-field">
          <label class="label">Tahun</label>
          <select v-model="year" class="input select-input">
            <option :value="new Date().getFullYear() - 1">{{ new Date().getFullYear() - 1 }}</option>
            <option :value="new Date().getFullYear()">{{ new Date().getFullYear() }}</option>
          </select>
        </div>

        <div class="form-group filter-field">
          <label class="label">Pilih Ruangan/Area <span class="label-hint">(Khusus Matrix)</span></label>
          <select v-model="areaId" class="input select-input" :disabled="areas.length === 0">
            <option value="">Pilih Area...</option>
            <option v-for="a in areas" :key="a.id" :value="a.id">{{ a.name }} [{{ a.code }}]</option>
          </select>
        </div>
      </div>
      <div class="period-indicator" v-if="month && year">
        <span class="period-indicator-icon">📋</span>
        <span>Periode aktif: <b>{{ getPeriodLabel() }}</b></span>
      </div>
    </div>

    <!-- Export Grid Layout -->
    <div class="export-grid">
      <!-- Monthly CSV Report -->
      <div class="export-card animate-slide-up stagger-1">
        <div class="export-card-body">
          <div class="export-header">
            <span class="export-title">Aktivitas Pembersihan</span>
            <span class="format-badge csv">CSV</span>
          </div>
          <div class="export-desc">
            <p>
              Mengekspor log lengkap seluruh pembersihan harian, jam mulai/selesai, pencapaian SLA tepat waktu, durasi pengerjaan, nama petugas, serta catatan kendala operasional lapangan.
            </p>
          </div>
        </div>
        <div class="export-card-footer">
          <div class="download-meta" v-if="lastDownload.monthly">
            <span class="download-meta-icon">✓</span>
            Terakhir diunduh: {{ lastDownload.monthly }}
          </div>
          <button class="btn btn-primary export-btn" @click="downloadReport('monthly')" :disabled="loading.monthly">
            <span v-if="loading.monthly" class="spinner-small"></span>
            <span>{{ loading.monthly ? 'Memproses...' : 'Unduh Laporan Aktivitas (.csv)' }}</span>
          </button>
        </div>
      </div>

      <!-- Audit CSV Report -->
      <div class="export-card animate-slide-up stagger-2">
        <div class="export-card-body">
          <div class="export-header">
            <span class="export-title">Rekap Audit & Kepatuhan</span>
            <span class="format-badge csv">CSV</span>
          </div>
          <div class="export-desc">
            <p>
              Mengekspor rekapitulasi data audit berkala oleh supervisor, rata-rata skor kebersihan/kerapihan/SOP, rincian status lulus/gagal, serta deskripsi catatan temuan inspeksi.
            </p>
          </div>
        </div>
        <div class="export-card-footer">
          <div class="download-meta" v-if="lastDownload.audit">
            <span class="download-meta-icon">✓</span>
            Terakhir diunduh: {{ lastDownload.audit }}
          </div>
          <button class="btn btn-primary export-btn" @click="downloadReport('audit')" :disabled="loading.audit">
            <span v-if="loading.audit" class="spinner-small"></span>
            <span>{{ loading.audit ? 'Memproses...' : 'Unduh Laporan Audit (.csv)' }}</span>
          </button>
        </div>
      </div>

      <!-- Matrix Excel Report -->
      <div class="export-card export-card-featured animate-slide-up stagger-3">
        <div class="export-card-body">
          <div class="export-header">
            <span class="export-title export-title-gradient">Tabel Ceklist Ruangan (Format RS JEC Orbita)</span>
            <span class="format-badge xls">EXCEL</span>
          </div>
          <div class="export-desc">
            <p>
              Mengekspor matriks visual tanda ceklist (v) per item kebersihan, per shift aktif harian (1 & 2), lengkap dengan nama ruangan, tanda tangan paraf petugas/PJ, dan penanggung jawab unit sesuai format cetak fisik RS JEC Orbita.
            </p>
          </div>
        </div>
        
        <div class="export-card-footer">
          <div class="matrix-info">
            <div class="selected-area" v-if="areaId">
              <span class="download-meta-icon">📍</span>
              Lokasi terpilih: <b>{{ getSelectedAreaName() }}</b>
            </div>
            <div class="area-warning" v-else>
              ⚠️ Pilih area/lokasi di atas untuk mengaktifkan unduhan.
            </div>
            <div class="download-meta" v-if="lastDownload['matrix-excel']">
              <span class="download-meta-icon">✓</span>
              Terakhir diunduh: {{ lastDownload['matrix-excel'] }}
            </div>
          </div>
          
          <button class="btn btn-primary export-btn" @click="downloadReport('matrix-excel')" :disabled="loading['matrix-excel'] || !areaId">
            <span v-if="loading['matrix-excel']" class="spinner-small"></span>
            <span>{{ loading['matrix-excel'] ? 'Memproses...' : 'Unduh Matrix Excel (.xls)' }}</span>
          </button>
        </div>
      </div>
    </div>
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
.reports-header {
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

/* ===== Filter Card ===== */
.filter-card {
  background: hsl(var(--card) / 0.95);
  border: 1px solid hsl(var(--primary) / 0.3);
}

.filter-title {
  font-weight: 700;
  margin-bottom: 1rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 1rem;
  color: hsl(var(--foreground));
}

.filter-row {
  display: flex;
  gap: 1.5rem;
}

.filter-field {
  flex: 1;
}

.select-input {
  width: 100%;
}

.label-hint {
  font-size: 0.75rem;
  color: hsl(var(--muted-foreground));
  font-weight: 400;
}

.period-indicator {
  display: flex;
  align-items: center;
  gap: 0.375rem;
  margin-top: 1rem;
  padding: 0.5rem 0.75rem;
  background: hsl(var(--primary) / 0.06);
  border: 1px solid hsl(var(--primary) / 0.15);
  border-radius: 0.375rem;
  font-size: 0.8125rem;
  color: hsl(var(--muted-foreground));
}

.period-indicator-icon {
  font-size: 0.875rem;
}

/* ===== Export Grid ===== */
.export-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1.5rem;
}

.export-card {
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  padding: 1.5rem;
  border-radius: 1rem;
  background: hsl(var(--card));
  border: 1px solid hsl(var(--border));
  box-shadow: 0 4px 20px rgba(0,0,0,0.1);
  transition: all 0.3s ease;
}

.export-card:hover {
  transform: translateY(-2px);
  border-color: hsl(var(--primary) / 0.4);
  box-shadow: 0 8px 30px rgba(0,0,0,0.2);
}

.export-card-featured {
  grid-column: 1 / -1;
  border-color: hsl(var(--primary) / 0.2);
  background: linear-gradient(135deg, hsl(var(--card)), hsl(var(--primary) / 0.03));
}

.export-card-body {
  flex: 1;
}

.export-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.75rem;
}

.export-title {
  font-size: 1.125rem;
  font-weight: 700;
  color: hsl(var(--foreground));
}

.export-title-gradient {
  background: linear-gradient(135deg, hsl(var(--primary)), hsl(var(--accent)));
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.format-badge {
  font-size: 0.65rem;
  font-weight: 800;
  padding: 0.1875rem 0.5rem;
  border-radius: 9999px;
  letter-spacing: 0.05em;
  flex-shrink: 0;
}

.format-badge.csv {
  background: hsl(210, 80%, 15%);
  color: hsl(210, 80%, 65%);
  border: 1px solid hsl(210, 80%, 30%);
}

.format-badge.xls {
  background: hsl(142, 70%, 15%);
  color: hsl(142, 70%, 65%);
  border: 1px solid hsl(142, 70%, 30%);
}

.export-desc {
  min-height: 60px;
}

.export-desc p {
  font-size: 0.875rem;
  color: hsl(var(--muted-foreground));
  line-height: 1.5;
}

.export-card-footer {
  margin-top: 1.5rem;
}

.export-btn {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
}

/* ===== Download Meta ===== */
.download-meta {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  font-size: 0.75rem;
  color: hsl(var(--success));
  margin-bottom: 0.5rem;
}

.download-meta-icon {
  font-size: 0.8rem;
}

.matrix-info {
  margin-bottom: 0.75rem;
}

.selected-area {
  font-size: 0.8125rem;
  color: hsl(var(--foreground));
  margin-bottom: 0.375rem;
}

.area-warning {
  font-size: 0.8125rem;
  color: hsl(var(--destructive));
  font-weight: 700;
}

/* ===== Spinner ===== */
.spinner-small {
  width: 1rem;
  height: 1rem;
  border: 2px solid rgba(255,255,255,0.3);
  border-top-color: white;
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
  display: inline-block;
  flex-shrink: 0;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* ===== Spacing ===== */
.mb-6 { margin-bottom: 1.5rem; }

/* ===== Responsive ===== */
@media (max-width: 768px) {
  .export-grid {
    grid-template-columns: 1fr;
  }

  .filter-row {
    flex-direction: column;
    gap: 1rem;
  }

  .export-card-featured {
    grid-column: auto;
  }
}
</style>
