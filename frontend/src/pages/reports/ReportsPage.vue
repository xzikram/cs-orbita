<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '../../lib/axios'

const month = ref(new Date().getMonth() + 1)
const year = ref(new Date().getFullYear())
const areaId = ref<string>('')
const areas = ref<any[]>([])

const loading = ref({
  monthly: false,
  audit: false,
  'matrix-excel': false
})

const toast = ref<{ show: boolean; message: string; type: string }>({ show: false, message: '', type: 'success' })

function showToast(message: string, type = 'success') {
  toast.value = { show: true, message, type }
  setTimeout(() => {
    toast.value.show = false
  }, 4000)
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
    <div v-if="toast.show" class="toast" :class="`toast-${toast.type}`">
      {{ toast.message }}
    </div>

    <div class="flex justify-between items-center mb-6">
      <div>
        <h1 class="text-2xl font-bold">Laporan & Ekspor</h1>
        <p class="text-muted-foreground">Unduh file laporan berkala aktivitas kebersihan, audit, dan matrix ruangan.</p>
      </div>
    </div>

    <!-- Filter Periode Card -->
    <div class="card filter-card mb-6 animate-slide-up">
      <h2 class="font-bold mb-4 flex items-center gap-2">
        <span>📅</span> Pilih Periode Laporan
      </h2>
      <div class="filter-row">
        <div class="form-group flex-1">
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
        
        <div class="form-group flex-1">
          <label class="label">Tahun</label>
          <select v-model="year" class="input select-input">
            <option :value="new Date().getFullYear() - 1">{{ new Date().getFullYear() - 1 }}</option>
            <option :value="new Date().getFullYear()">{{ new Date().getFullYear() }}</option>
          </select>
        </div>

        <div class="form-group flex-1">
          <label class="label">Pilih Ruangan/Area <span class="text-xs text-muted-foreground">(Khusus Matrix)</span></label>
          <select v-model="areaId" class="input select-input" :disabled="areas.length === 0">
            <option value="">Pilih Area...</option>
            <option v-for="a in areas" :key="a.id" :value="a.id">{{ a.name }} [{{ a.code }}]</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Export Grid Layout -->
    <div class="export-grid">
      <!-- Monthly CSV Report -->
      <div class="card-stat animate-slide-up stagger-1 flex flex-col justify-between">
        <div>
          <div class="stat-header">
            <span class="stat-title-custom">Aktivitas Pembersihan</span>
            <span class="format-badge csv">CSV</span>
          </div>
          <div class="desc-box">
            <p class="text-sm text-muted-foreground mt-3">
              Mengekspor log lengkap seluruh pembersihan harian, jam mulai/selesai, pencapaian SLA tepat waktu, durasi pengerjaan, nama petugas, serta catatan kendala operasional lapangan.
            </p>
          </div>
        </div>
        <button class="btn btn-primary w-full mt-6 flex items-center justify-center gap-2" @click="downloadReport('monthly')" :disabled="loading.monthly">
          <span v-if="loading.monthly" class="spinner-small"></span>
          <span>{{ loading.monthly ? 'Memproses...' : 'Unduh Laporan Aktivitas (.csv)' }}</span>
        </button>
      </div>

      <!-- Audit CSV Report -->
      <div class="card-stat animate-slide-up stagger-2 flex flex-col justify-between">
        <div>
          <div class="stat-header">
            <span class="stat-title-custom">Rekap Audit & Kepatuhan</span>
            <span class="format-badge csv">CSV</span>
          </div>
          <div class="desc-box">
            <p class="text-sm text-muted-foreground mt-3">
              Mengekspor rekapitulasi data audit berkala oleh supervisor, rata-rata skor kebersihan/kerapihan/SOP, rincian status lulus/gagal, serta deskripsi catatan temuan inspeksi.
            </p>
          </div>
        </div>
        <button class="btn btn-primary w-full mt-6 flex items-center justify-center gap-2" @click="downloadReport('audit')" :disabled="loading.audit">
          <span v-if="loading.audit" class="spinner-small"></span>
          <span>{{ loading.audit ? 'Memproses...' : 'Unduh Laporan Audit (.csv)' }}</span>
        </button>
      </div>

      <!-- Matrix Excel Report -->
      <div class="card-stat animate-slide-up stagger-3 flex flex-col justify-between full-width">
        <div>
          <div class="stat-header">
            <span class="stat-title-custom text-primary-gradient">Tabel Ceklist Ruangan (Format RS JEC Orbita)</span>
            <span class="format-badge xls">EXCEL</span>
          </div>
          <div class="desc-box">
            <p class="text-sm text-muted-foreground mt-2">
              Mengekspor matriks visual tanda ceklist (v) per item kebersihan, per shift aktif harian (1 & 2), lengkap dengan nama ruangan, tanda tangan paraf petugas/PJ, dan penanggung jawab unit sesuai format cetak fisik RS JEC Orbita.
            </p>
          </div>
        </div>
        
        <div class="flex items-center gap-4 mt-6">
          <div class="flex-1 text-xs text-muted-foreground" v-if="areaId">
            Lokasi terpilih: <b>{{ areas.find(a => a.id === areaId)?.name || 'Unknown' }}</b>
          </div>
          <div class="flex-1 text-xs text-destructive font-bold" v-else>
            ⚠️ Pilih area/lokasi di atas untuk mengaktifkan unduhan.
          </div>
          
          <button class="btn btn-primary btn-xls-action" @click="downloadReport('matrix-excel')" :disabled="loading['matrix-excel'] || !areaId">
            <span v-if="loading['matrix-excel']" class="spinner-small"></span>
            <span>{{ loading['matrix-excel'] ? 'Memproses...' : 'Unduh Matrix Excel (.xls)' }}</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Toast Notification */
.toast {
  position: fixed;
  top: 1.5rem;
  right: 1.5rem;
  z-index: 1000;
  padding: 0.75rem 1.5rem;
  border-radius: 0.5rem;
  color: white;
  font-weight: 500;
  font-size: 0.875rem;
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
  animation: slideIn 0.3s ease;
}
.toast-success { background: hsl(var(--success)); }
.toast-error { background: hsl(var(--destructive)); }

@keyframes slideIn {
  from { transform: translateX(100%); opacity: 0; }
  to { transform: translateX(0); opacity: 1; }
}

/* Filter Card Layout */
.filter-card {
  background: hsl(var(--card) / 0.95);
  border: 1px solid hsl(var(--primary) / 0.3);
}

.filter-row {
  display: flex;
  gap: 1.5rem;
}

.select-input {
  width: 100%;
}

/* Export Grid Styling */
.export-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1.5rem;
}

.card-stat {
  padding: 1.5rem;
  border-radius: 1rem;
  background: hsl(var(--card));
  border: 1px solid hsl(var(--border));
  box-shadow: 0 4px 20px rgba(0,0,0,0.1);
  transition: all 0.3s ease;
}

.card-stat:hover {
  transform: translateY(-2px);
  border-color: hsl(var(--primary) / 0.4);
  box-shadow: 0 8px 30px rgba(0,0,0,0.2);
}

.full-width {
  grid-column: 1 / -1;
}

.stat-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.stat-title-custom {
  font-size: 1.125rem;
  font-weight: 700;
  color: hsl(var(--foreground));
}

.text-primary-gradient {
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

.desc-box {
  min-height: 80px;
}

.btn-xls-action {
  min-width: 240px;
}

/* Utils */
.w-full { width: 100%; }
.mt-6 { margin-top: 1.5rem; }
.mt-3 { margin-top: 0.75rem; }
.mt-2 { margin-top: 0.5rem; }
.gap-2 { gap: 0.5rem; }
.gap-4 { gap: 1rem; }
.flex-1 { flex: 1; }

.spinner-small {
  width: 1rem;
  height: 1rem;
  border: 2px solid rgba(255,255,255,0.3);
  border-top-color: white;
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
  display: inline-block;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* Responsive */
@media (max-width: 768px) {
  .export-grid {
    grid-template-columns: 1fr;
  }
  .filter-row {
    flex-direction: column;
    gap: 1rem;
  }
  .flex.items-center.gap-4 {
    flex-direction: column;
    align-items: stretch;
  }
  .btn-xls-action {
    width: 100%;
    min-width: unset;
  }
}
</style>
