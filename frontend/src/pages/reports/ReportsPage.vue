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

// Daily Checklist variables & functions
const selectedDailyDate = ref(new Date().toISOString().split('T')[0])
const showDailyModal = ref(false)
const dailyActivities = ref<any[]>([])
const loadingDaily = ref(false)
const apiBaseUrl = import.meta.env.VITE_API_URL || 'http://localhost:8000'

async function fetchDailyChecklist() {
  if (!areaId.value) {
    showToast('Pilih area terlebih dahulu untuk melihat pratinjau.', 'error')
    return
  }
  loadingDaily.value = true
  try {
    const { data } = await api.get('/api/v1/activities', {
      params: {
        date: selectedDailyDate.value,
        area_id: areaId.value
      }
    })
    dailyActivities.value = data.data || []
    showDailyModal.value = true
  } catch (e: any) {
    showToast('Gagal memuat data ceklist: ' + e.message, 'error')
  } finally {
    loadingDaily.value = false
  }
}

function printDailyReport() {
  window.print()
}

function exportDailyToExcel() {
  if (dailyActivities.value.length === 0) return
  
  const areaName = getSelectedAreaName()
  const formattedDate = selectedDailyDate.value
  const formattedIndoDateStr = formatIndoDate(formattedDate)
  
  let html = `
    <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
    <head>
      <meta charset="utf-8">
      <style>
        table { border-collapse: collapse; font-family: Arial, sans-serif; font-size: 11px; }
        th, td { border: 1px solid black; padding: 5px; text-align: center; }
        .text-left { text-align: left; }
        .text-right { text-align: right; }
        .bold { font-weight: bold; }
        .header-title { font-size: 14px; border: none; text-align: center; font-weight: bold; }
        .header-meta { border: none; text-align: left; font-weight: bold; }
        .shift-header { background-color: #d9e1f2; font-weight: bold; text-align: left; }
      </style>
    </head>
    <body>
      <table>
        <tr>
          <td colspan="5" class="header-title">LAPORAN CEKLIST KEBERSIHAN HARIAN - RS JEC ORBITA MAKASSAR</td>
        </tr>
        <tr>
          <td colspan="5" class="header-meta">LOKASI : ${areaName.toUpperCase()}</td>
        </tr>
        <tr>
          <td colspan="5" class="header-meta">TANGGAL : ${formattedIndoDateStr.toUpperCase()}</td>
        </tr>
        <tr>
          <td colspan="5" style="border:none; height:10px;"></td>
        </tr>
      </table>

      <table>
        <thead>
          <tr style="background-color: #eaeaea; font-weight: bold;">
            <th width="30">NO</th>
            <th width="150">RUANGAN / BAGIAN</th>
            <th width="200">ITEM KEBERSIHAN</th>
            <th width="120">STATUS</th>
            <th width="120">WAKTU CEK</th>
          </tr>
        </thead>
        <tbody>
  `

  dailyActivities.value.forEach((act: any) => {
    html += `
      <tr class="shift-header">
        <td colspan="5" class="bold text-left" style="background-color: #d9e1f2;">
          Petugas: ${act.user?.name || '-'} | Jam: ${act.start_time || '-'} - ${act.end_time || '-'}
        </td>
      </tr>
    `
    
    if (act.items && act.items.length > 0) {
      act.items.forEach((item: any, itemIdx: number) => {
        const room = item.area_object?.room_name || 'Umum'
        const objName = item.area_object?.cleaning_object?.name || '-'
        const status = item.is_checked ? 'BERSIH (v)' : 'KOTOR (x)'
        const checkedAt = item.checked_at ? formatTimeOnly(item.checked_at) : '-'
        
        html += `
          <tr>
            <td>${itemIdx + 1}</td>
            <td class="text-left">${room}</td>
            <td class="text-left">${objName}</td>
            <td style="color: ${item.is_checked ? 'green' : 'red'}; font-weight: bold;">${status}</td>
            <td>${checkedAt}</td>
          </tr>
        `
      })
    } else {
      html += `
        <tr>
          <td colspan="5" style="color: gray;">Tidak ada item ceklist</td>
        </tr>
      `
    }
    
    if (act.notes) {
      html += `
        <tr>
          <td colspan="5" class="text-left" style="font-style: italic; background-color: #fff2cc;">
            <b>Catatan Kendala:</b> ${act.notes}
          </td>
        </tr>
      `
    }
  })

  html += `
        </tbody>
      </table>
      <br>
      <table style="border:none;">
        <tr>
          <td colspan="2" style="border:none;" class="text-left">
            Ket : v BERSIH<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;X KOTOR
          </td>
          <td style="border:none;"></td>
          <td colspan="2" style="border:none;" class="text-center bold">PJ ${areaName.toUpperCase()}</td>
        </tr>
        <tr><td colspan="5" style="border:none; height:40px;"></td></tr>
        <tr>
          <td colspan="2" style="border:none;" class="text-left bold">(Housekeeping RS)</td>
          <td style="border:none;"></td>
          <td colspan="2" style="border:none;" class="text-center bold">........................................</td>
        </tr>
      </table>
    </body>
    </html>
  `

  const blob = new Blob([html], { type: 'application/vnd.ms-excel' })
  const url = window.URL.createObjectURL(blob)
  const link = document.createElement('a')
  const cleanAreaName = areaName.replace(/[^a-zA-Z0-9]/g, '_')
  link.href = url
  link.setAttribute('download', `Ceklist_Harian_${cleanAreaName}_${formattedDate}.xls`)
  document.body.appendChild(link)
  link.click()
  link.remove()
  window.URL.revokeObjectURL(url)
  showToast('Berhasil mengunduh Laporan Ceklist Excel!', 'success')
}

function formatIndoDate(dateStr: string): string {
  if (!dateStr) return '-'
  const date = new Date(dateStr)
  return date.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
}

function getStatusLabel(status: any): string {
  if (typeof status === 'object' && status !== null) {
    return status.label || '-'
  }
  const labels: Record<string, string> = {
    'pending': 'Pending',
    'in_progress': 'Sedang Dikerjakan',
    'completed': 'Selesai',
    'verified': 'Terverifikasi',
    'rejected': 'Ditolak'
  }
  return labels[status] || status || '-'
}

function getStatusBadgeClass(status: any): string {
  const s = (typeof status === 'object' && status !== null) ? status.value : status
  switch (s) {
    case 'completed': return 'badge-success'
    case 'in_progress': return 'badge-info'
    case 'pending': return 'badge-warning'
    default: return 'badge-secondary'
  }
}

function formatTimeOnly(dateTimeStr: string): string {
  if (!dateTimeStr) return '-'
  try {
    const date = new Date(dateTimeStr)
    return date.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
  } catch (e) {
    return dateTimeStr
  }
}

function getPhotosByType(photos: any[], type: 'before' | 'after') {
  if (!photos) return []
  return photos.filter(p => p.type === type)
}

function calculateDuration(start: string, end: string): number | string {
  if (!start || !end) return '-'
  try {
    const [sh, sm] = start.split(':').map(Number)
    const [eh, em] = end.split(':').map(Number)
    const startDate = new Date()
    startDate.setHours(sh, sm, 0)
    const endDate = new Date()
    endDate.setHours(eh, em, 0)
    const diff = (endDate.getTime() - startDate.getTime()) / 1000 / 60
    return diff >= 0 ? diff : diff + 1440
  } catch (e) {
    return '-'
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
          <label class="label">Pilih Ruangan/Area <span class="label-hint">(Untuk Excel & Ceklist)</span></label>
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
              Mengekspor log lengkap seluruh pembersihan harian, jam mulai/selesai, durasi pengerjaan, nama petugas, serta catatan kendala operasional lapangan.
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

      <!-- Daily Checklist Card -->
      <div class="export-card export-card-featured animate-slide-up stagger-3">
        <div class="export-card-body">
          <div class="export-header">
            <span class="export-title export-title-gradient">Ceklist Harian & Foto Bukti</span>
            <span class="format-badge csv">PRATINJAU & PDF</span>
          </div>
          <div class="export-desc">
            <p>
              Pratinjau visual tabel ceklist kebersihan ruangan beserta foto bukti sebelum/sesudah per hari, serta cetak langsung atau simpan sebagai dokumen PDF.
            </p>
            <div class="form-group mt-4">
              <label class="label text-xs">Pilih Tanggal Laporan</label>
              <input type="date" v-model="selectedDailyDate" class="input date-input-full" style="width: 100%;" />
            </div>
          </div>
        </div>
        <div class="export-card-footer">
          <div class="matrix-info">
            <div class="selected-area" v-if="areaId">
              <span class="download-meta-icon">📍</span>
              Lokasi terpilih: <b>{{ getSelectedAreaName() }}</b>
            </div>
            <div class="area-warning" v-else>
              ⚠️ Pilih area/lokasi di atas untuk melihat pratinjau.
            </div>
          </div>
          <button class="btn btn-secondary export-btn" @click="fetchDailyChecklist" :disabled="loadingDaily || !areaId">
            <span v-if="loadingDaily" class="spinner-small"></span>
            <span>{{ loadingDaily ? 'Memproses...' : 'Lihat Ceklist Harian (Pratinjau)' }}</span>
          </button>
        </div>
      </div>

      <!-- Matrix Excel Report -->
      <div class="export-card export-card-featured animate-slide-up stagger-4">
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

    <!-- Daily Checklist Preview Modal -->
    <Teleport to="body">
      <Transition name="modal-fade">
        <div v-if="showDailyModal" class="modal-overlay" @click.self="showDailyModal = false">
          <div class="modal-content modal-xl animate-scale-up">
            <div class="modal-header">
              <h3 class="modal-title">📄 Pratinjau Ceklist Harian</h3>
              <button class="modal-close" @click="showDailyModal = false">✕</button>
            </div>
            
            <div class="modal-body modal-scrollable">
              <div id="daily-print-area">
                <div class="print-header-report">
                  <div class="print-logo">
                    <img src="/Logo%20RS%20JEC%20ORBITA.png" alt="Logo RS JEC Orbita" class="print-logo-img" />
                  </div>
                  <div class="print-meta-info">
                    <h2>LAPORAN CEKLIST HARIAN & FOTO BUKTI</h2>
                    <table class="meta-print-table">
                      <tr>
                        <td>Lokasi / Area</td>
                        <td>: <b>{{ getSelectedAreaName() }}</b></td>
                      </tr>
                      <tr>
                        <td>Hari / Tanggal</td>
                        <td>: <b>{{ formatIndoDate(selectedDailyDate) }}</b></td>
                      </tr>
                    </table>
                  </div>
                </div>

                <div v-if="dailyActivities.length === 0" class="empty-print-state">
                  <div class="empty-print-icon">📭</div>
                  <p>Tidak ada aktivitas kebersihan yang tercatat untuk area dan tanggal ini.</p>
                </div>

                <div v-else v-for="(act, idx) in dailyActivities" :key="act.id" class="activity-print-section">
                  <div class="activity-print-header">
                    <div class="activity-print-shift">Petugas: {{ act.user?.name || '-' }}</div>
                    <div class="activity-print-details">
                      <span>Waktu: <b>{{ act.start_time || '-' }} - {{ act.end_time || '-' }}</b> ({{ calculateDuration(act.start_time, act.end_time) }} Menit)</span>
                    </div>
                  </div>

                  <table class="checklist-table-details">
                    <thead>
                      <tr>
                        <th width="40">NO</th>
                        <th width="200">RUANGAN / BAGIAN</th>
                        <th>ITEM KEBERSIHAN</th>
                        <th width="120">STATUS</th>
                        <th width="120">WAKTU CEK</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="(item, itemIdx) in act.items" :key="item.id">
                        <td>{{ itemIdx + 1 }}</td>
                        <td class="text-left font-medium">{{ item.area_object?.room_name || 'Umum' }}</td>
                        <td class="text-left">{{ item.area_object?.cleaning_object?.name || '-' }}</td>
                        <td class="text-center">
                          <span :class="item.is_checked ? 'status-clean' : 'status-dirty'">
                            {{ item.is_checked ? '✓ Bersih' : '✗ Kotor' }}
                          </span>
                        </td>
                        <td class="text-center">{{ item.checked_at ? formatTimeOnly(item.checked_at) : '-' }}</td>
                      </tr>
                    </tbody>
                  </table>

                  <div class="activity-print-notes" v-if="act.notes">
                    <b>Catatan Kendala/Keterangan:</b>
                    <p>{{ act.notes }}</p>
                  </div>

                  <div class="activity-print-photos">
                    <div class="photo-print-column">
                      <h4>Foto Sebelum Kerja (Before)</h4>
                      <div class="photo-print-wrapper">
                        <div v-if="getPhotosByType(act.photos, 'before').length === 0" class="no-photo-text">
                          Tidak ada foto Before
                        </div>
                        <img v-for="photo in getPhotosByType(act.photos, 'before')" :key="photo.id"
                             :src="`${apiBaseUrl}/storage/${photo.file_path}`" alt="Before" class="photo-print-el" />
                      </div>
                    </div>
                    
                    <div class="photo-print-column">
                      <h4>Foto Sesudah Kerja (After)</h4>
                      <div class="photo-print-wrapper">
                        <div v-if="getPhotosByType(act.photos, 'after').length === 0" class="no-photo-text">
                          Tidak ada foto After
                        </div>
                        <img v-for="photo in getPhotosByType(act.photos, 'after')" :key="photo.id"
                             :src="`${apiBaseUrl}/storage/${photo.file_path}`" alt="After" class="photo-print-el" />
                      </div>
                    </div>
                  </div>

                  <div class="print-signatures">
                    <div class="signature-box">
                      <p>Petugas Cleaning Service</p>
                      <div class="signature-line"></div>
                      <p class="font-bold">{{ act.user?.name || '........................' }}</p>
                    </div>
                    <div class="signature-box">
                      <p>Penanggung Jawab (PJ) Unit</p>
                      <div class="signature-line"></div>
                      <p class="font-bold">........................................</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            
            <div class="modal-footer">
              <button class="btn btn-ghost" @click="showDailyModal = false">Tutup</button>
              <button v-if="dailyActivities.length > 0" class="btn btn-secondary excel-btn-modal" @click="exportDailyToExcel">
                🟢 Ekspor ke Excel (.xls)
              </button>
              <button v-if="dailyActivities.length > 0" class="btn btn-primary" @click="printDailyReport">
                🖨️ Cetak Laporan / Simpan PDF
              </button>
            </div>
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

/* ===== Responsive ===== */
@media (max-width: 768px) {
  .export-grid {
    grid-template-columns: 1fr;
  }

  .filter-row {
    flex-direction: column;
    gap: 1rem;
  }
}
</style>

<style>
/* ===== Daily Modal (Global for Teleport) ===== */
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

.modal-xl {
  max-width: 950px;
  max-height: 90vh;
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
}

.modal-close:hover {
  background: hsl(var(--destructive) / 0.1);
  color: hsl(var(--destructive));
}

.modal-body {
  padding: 1.5rem;
}

.modal-scrollable {
  overflow-y: auto;
  flex: 1;
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

.btn-ghost {
  background: transparent;
  border: 1px solid hsl(var(--border));
  color: hsl(var(--foreground));
  padding: 0.5rem 1rem;
  border-radius: 0.5rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}
.btn-ghost:hover {
  background: hsl(var(--muted));
}

.excel-btn-modal {
  background-color: hsl(142, 70%, 15%) !important;
  color: hsl(142, 70%, 75%) !important;
  border: 1px solid hsl(142, 70%, 30%) !important;
  transition: all 0.2s;
}
.excel-btn-modal:hover {
  background-color: hsl(142, 70%, 20%) !important;
  color: hsl(142, 70%, 85%) !important;
}

/* ===== Print Report Area Stylings ===== */
.print-header-report {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  border-bottom: 2px solid hsl(var(--foreground));
  padding-bottom: 1rem;
  margin-bottom: 1.5rem;
}

.print-logo {
  display: flex;
}

.print-logo-img {
  height: 50px;
  width: auto;
  object-fit: contain;
}

.print-meta-info {
  text-align: right;
}

.print-meta-info h2 {
  font-size: 1.25rem;
  font-weight: 800;
  margin: 0 0 0.5rem 0;
  color: hsl(var(--foreground));
}

.meta-print-table {
  margin-left: auto;
  border-collapse: collapse;
}

.meta-print-table td {
  border: none !important;
  padding: 0.125rem 0.25rem;
  font-size: 0.8125rem;
  text-align: left;
}

.empty-print-state {
  text-align: center;
  padding: 3rem 1rem;
  color: hsl(var(--muted-foreground));
}

.empty-print-icon {
  font-size: 2.5rem;
  margin-bottom: 0.5rem;
}

.activity-print-section {
  margin-bottom: 2.5rem;
  border: 1px solid hsl(var(--border));
  border-radius: 0.75rem;
  padding: 1.25rem;
  background: hsl(var(--card));
}

.activity-print-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: hsl(var(--primary) / 0.06);
  border: 1px solid hsl(var(--primary) / 0.12);
  padding: 0.75rem 1rem;
  border-radius: 0.5rem;
  margin-bottom: 1rem;
}

.activity-print-shift {
  font-weight: 800;
  font-size: 0.875rem;
  background: hsl(var(--primary));
  color: white;
  padding: 0.25rem 0.75rem;
  border-radius: 0.375rem;
  text-transform: uppercase;
}

.activity-print-details {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  font-size: 0.8125rem;
  color: hsl(var(--muted-foreground));
}

.divider {
  color: hsl(var(--border));
}

.text-late {
  color: hsl(var(--destructive));
}

.text-ontime {
  color: hsl(var(--success));
}

.checklist-table-details {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 1rem;
  font-size: 0.8125rem;
}

.checklist-table-details th,
.checklist-table-details td {
  border: 1px solid hsl(var(--border));
  padding: 0.5rem;
}

.checklist-table-details th {
  background: hsl(var(--muted) / 0.5);
  font-weight: 700;
  text-align: center;
}

.font-medium {
  font-weight: 500;
}

.status-clean {
  color: hsl(var(--success));
  font-weight: 700;
}

.status-dirty {
  color: hsl(var(--destructive));
  font-weight: 700;
}

.activity-print-notes {
  background: hsl(var(--muted) / 0.3);
  border-left: 3px solid hsl(var(--warning));
  padding: 0.5rem 0.75rem;
  border-radius: 0.25rem;
  font-size: 0.75rem;
  margin-bottom: 1.25rem;
}

.activity-print-notes p {
  margin: 0.25rem 0 0 0;
}

.activity-print-photos {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.photo-print-column h4 {
  font-size: 0.75rem;
  font-weight: 700;
  margin: 0 0 0.5rem 0;
  text-transform: uppercase;
  color: hsl(var(--muted-foreground));
}

.photo-print-wrapper {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  background: hsl(var(--muted) / 0.2);
  border: 1px dashed hsl(var(--border));
  padding: 0.5rem;
  border-radius: 0.5rem;
  min-height: 120px;
  align-items: center;
  justify-content: flex-start;
}

.photo-print-el {
  height: 100px;
  width: calc(50% - 0.25rem);
  min-width: 80px;
  max-width: 150px;
  object-fit: cover;
  border-radius: 0.25rem;
  border: 1px solid hsl(var(--border));
  cursor: pointer;
  transition: transform 0.2s;
}

.photo-print-el:hover {
  transform: scale(1.05);
}

.no-photo-text {
  font-size: 0.75rem;
  color: hsl(var(--muted-foreground));
}

.print-signatures {
  display: flex;
  justify-content: space-between;
  margin-top: 1.5rem;
  padding-top: 1rem;
  border-top: 1px dashed hsl(var(--border));
}

.signature-box {
  width: 45%;
  text-align: center;
}

.signature-box p {
  font-size: 0.75rem;
  margin: 0;
  color: hsl(var(--muted-foreground));
}

.signature-line {
  height: 50px;
  margin-bottom: 0.25rem;
}

.font-bold {
  font-weight: 700;
  color: hsl(var(--foreground)) !important;
}

/* Animations */
.modal-fade-enter-active, .modal-fade-leave-active {
  transition: opacity 0.3s ease;
}
.modal-fade-enter-from, .modal-fade-leave-to {
  opacity: 0;
}
.modal-fade-enter-active .modal-content {
  animation: modalScaleIn 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}
.modal-fade-leave-active .modal-content {
  animation: modalScaleOut 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes modalScaleIn {
  from { transform: scale(0.95); opacity: 0; }
  to { transform: scale(1); opacity: 1; }
}
@keyframes modalScaleOut {
  from { transform: scale(1); opacity: 1; }
  to { transform: scale(0.95); opacity: 0; }
}

/* ===== Printing Media Queries ===== */
@media print {
  /* Hide main app wrapper completely to avoid background leaks and blank spaces */
  #app {
    display: none !important;
  }
  
  /* Reset modal overlay container for printing */
  .modal-overlay {
    position: absolute !important;
    inset: 0 !important;
    background: white !important;
    padding: 0 !important;
    z-index: auto !important;
    backdrop-filter: none !important;
    display: block !important;
    overflow: visible !important;
  }

  .modal-content {
    background: white !important;
    border: none !important;
    box-shadow: none !important;
    max-height: none !important;
    width: 100% !important;
    overflow: visible !important;
    display: block !important;
  }

  /* Hide print controls, headers, footers and close buttons */
  .modal-header,
  .modal-footer,
  .modal-close,
  button,
  .btn {
    display: none !important;
  }

  .modal-body {
    padding: 0 !important;
    overflow: visible !important;
  }

  #daily-print-area {
    width: 100% !important;
    max-width: 800px !important;
    margin: 0 auto !important;
    padding: 40px 30px !important;
    box-sizing: border-box !important;
    background: white !important;
    color: black !important;
  }

  .checklist-table-details {
    width: 95% !important;
    margin: 0 auto 1.5rem auto !important;
  }

  .activity-print-section {
    page-break-after: always;
    border: none !important;
    padding: 0 !important;
    margin: 0 auto 2rem auto !important;
    background: white !important;
    max-width: 740px !important;
  }

  .activity-print-section:last-child {
    page-break-after: avoid;
  }

  .activity-print-header {
    background: #f0f0f0 !important;
    border: 1px solid #ccc !important;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }

  .activity-print-shift {
    background: #000 !important;
    color: #fff !important;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }

  .checklist-table-details th {
    background: #eaeaea !important;
    color: #000 !important;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }

  .status-clean {
    color: #008000 !important;
  }

  .status-dirty {
    color: #ff0000 !important;
  }

  .photo-print-wrapper {
    background: white !important;
    border: 1px solid #ddd !important;
    display: flex !important;
    flex-wrap: wrap !important;
    justify-content: flex-start !important;
  }

  .photo-print-el {
    height: 120px !important;
    width: calc(50% - 0.25rem) !important;
    max-width: 150px !important;
    object-fit: cover !important;
  }
}
</style>
