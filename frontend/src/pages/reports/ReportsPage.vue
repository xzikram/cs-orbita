<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '../../lib/axios'

const month = ref(new Date().getMonth() + 1)
const year = ref(new Date().getFullYear())
const dailyAreaId = ref<number | string>('')
const matrixAreaId = ref<number | string>('')
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
      dailyAreaId.value = areas.value[0].id
      matrixAreaId.value = areas.value[0].id
    }
  } catch (e: any) {
    showToast('Gagal memuat daftar area: ' + e.message, 'error')
  }
}

onMounted(() => {
  fetchAreas()
})

function getAreaName(id: number | string): string {
  if (!id) return ''
  const found = areas.value.find(a => a.id == id)
  return found?.name || 'Unknown'
}

async function downloadReport(type: 'monthly' | 'audit' | 'matrix-excel') {
  loading.value[type] = true
  try {
    const params: any = { month: month.value, year: year.value }
    if (type === 'matrix-excel') {
      if (!matrixAreaId.value) {
        showToast('Pilih area terlebih dahulu untuk mengunduh Laporan Matrix.', 'error')
        loading.value[type] = false
        return
      }
      params.area_id = matrixAreaId.value
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
const filterMode = ref<'today' | 'month' | 'range'>('today')
const selectedDailyMonth = ref(new Date().getMonth() + 1)
const selectedDailyYear = ref(new Date().getFullYear())
const startDate = ref(new Date().toISOString().split('T')[0])
const endDate = ref(new Date().toISOString().split('T')[0])

const showDailyModal = ref(false)
const dailyActivities = ref<any[]>([])
const loadingDaily = ref(false)
const apiBaseUrl = import.meta.env.VITE_API_URL || 'http://localhost:8000'

function formatIndoShortDate(dateStr: string): string {
  if (!dateStr) return '-'
  const date = new Date(dateStr)
  return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}

function getFormattedPeriodText(): string {
  if (filterMode.value === 'today') {
    const todayStr = new Date().toISOString().split('T')[0]
    return formatIndoDate(todayStr)
  } else if (filterMode.value === 'month') {
    return `${monthNames[selectedDailyMonth.value]} ${selectedDailyYear.value}`
  } else if (filterMode.value === 'range') {
    return `${formatIndoShortDate(startDate.value)} - ${formatIndoShortDate(endDate.value)}`
  }
  return '-'
}

async function fetchDailyChecklist() {
  if (!dailyAreaId.value) {
    showToast('Pilih area terlebih dahulu untuk melihat pratinjau.', 'error')
    return
  }
  loadingDaily.value = true
  try {
    const params: any = {
      area_id: dailyAreaId.value
    }
    
    if (filterMode.value === 'today') {
      params.date = new Date().toISOString().split('T')[0]
    } else if (filterMode.value === 'month') {
      const m = selectedDailyMonth.value
      const y = selectedDailyYear.value
      params.start_date = `${y}-${String(m).padStart(2, '0')}-01`
      params.end_date = new Date(y, m, 0).toISOString().split('T')[0]
    } else if (filterMode.value === 'range') {
      params.start_date = startDate.value
      params.end_date = endDate.value
    }

    const { data } = await api.get('/api/v1/activities', { params })
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
  
  const areaName = getAreaName(dailyAreaId.value)
  const formattedIndoDateStr = getFormattedPeriodText()
  
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
          <td colspan="5" class="header-meta">PERIODE : ${formattedIndoDateStr.toUpperCase()}</td>
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
          Petugas: ${act.user?.name || '-'} | Tanggal: ${formatIndoShortDate(act.date)} | Jam: ${act.start_time || '-'} - ${act.end_time || '-'}
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
  const filenameDate = filterMode.value === 'today' 
    ? new Date().toISOString().split('T')[0]
    : (filterMode.value === 'month' ? `${selectedDailyYear.value}_${selectedDailyMonth.value}` : `${startDate.value}_to_${endDate.value}`)
  link.href = url
  link.setAttribute('download', `Ceklist_${cleanAreaName}_${filenameDate}.xls`)
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

    <!-- Page Header with Icon Badge -->
    <div class="reports-header mb-6">
      <div class="flex items-center gap-3">
        <div class="header-icon-badge">📊</div>
        <div>
          <h1 class="page-title">Laporan & Ekspor</h1>
          <p class="page-subtitle">Unduh berkas laporan berkala aktivitas kebersihan, audit, dan matrix ruangan.</p>
        </div>
      </div>
    </div>

    <!-- Export Grid Layout (Premium Cards) -->
    <div class="export-grid mb-6">
      <!-- Daily Checklist Card (Featured) -->
      <div class="export-card export-card-featured glass-card animate-slide-up stagger-1">
        <div class="export-card-body">
          <div class="export-header mb-3">
            <span class="export-title export-title-gradient">Ceklist Harian & Foto Bukti</span>
            <span class="format-badge format-badge-accent">PRATINJAU & PDF</span>
          </div>
          <div class="export-desc">
            <p class="mb-4">
              Pratinjau visual tabel ceklist kebersihan ruangan beserta foto bukti sebelum/sesudah per hari, serta cetak langsung atau simpan sebagai dokumen PDF.
            </p>
            
            <!-- Pilih Ruangan/Area -->
            <div class="form-group mb-4">
              <label class="label text-xs font-semibold">
                <span class="input-icon">🏢</span> Pilih Ruangan/Area
              </label>
              <div class="select-wrapper">
                <select v-model="dailyAreaId" class="input select-input text-xs" :disabled="areas.length === 0">
                  <option value="">Pilih Area...</option>
                  <option v-for="a in areas" :key="a.id" :value="a.id">{{ a.name }} [{{ a.code }}]</option>
                </select>
              </div>
            </div>

            <div class="form-group">
              <label class="label text-xs font-semibold">Pilih Mode Periode</label>
              <!-- iOS Style Pill Tabs -->
              <div class="filter-mode-tabs mb-4">
                <button 
                  type="button"
                  class="tab-btn" 
                  :class="{ active: filterMode === 'today' }"
                  @click="filterMode = 'today'"
                >Hari Ini</button>
                <button 
                  type="button"
                  class="tab-btn" 
                  :class="{ active: filterMode === 'month' }"
                  @click="filterMode = 'month'"
                >1 Bulan</button>
                <button 
                  type="button"
                  class="tab-btn" 
                  :class="{ active: filterMode === 'range' }"
                  @click="filterMode = 'range'"
                >Rentang Tanggal</button>
              </div>

              <!-- Today Mode -->
              <div v-if="filterMode === 'today'" class="mode-input-container">
                <div class="today-banner">
                  <span class="today-dot"></span>
                  <span class="text-xs font-semibold text-muted-foreground">Hari ini: <b class="text-foreground">{{ formatIndoDate(new Date().toISOString().split('T')[0]) }}</b></span>
                </div>
              </div>

              <!-- Month Mode -->
              <div v-else-if="filterMode === 'month'" class="mode-input-container inline-flex-container">
                <div class="flex-field">
                  <label class="label text-xs font-semibold">Bulan</label>
                  <div class="select-wrapper">
                    <select v-model="selectedDailyMonth" class="input select-input text-xs">
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
                </div>
                <div class="flex-field year-flex-field">
                  <label class="label text-xs font-semibold">Tahun</label>
                  <div class="select-wrapper">
                    <select v-model="selectedDailyYear" class="input select-input text-xs">
                      <option :value="new Date().getFullYear() - 1">{{ new Date().getFullYear() - 1 }}</option>
                      <option :value="new Date().getFullYear()">{{ new Date().getFullYear() }}</option>
                    </select>
                  </div>
                </div>
              </div>

              <!-- Range Mode -->
              <div v-else-if="filterMode === 'range'" class="mode-input-container inline-flex-container">
                <div class="flex-field">
                  <label class="label text-xs font-semibold">Tanggal Mulai</label>
                  <input type="date" v-model="startDate" class="input date-input-full text-xs" />
                </div>
                <div class="flex-field">
                  <label class="label text-xs font-semibold">Tanggal Selesai</label>
                  <input type="date" v-model="endDate" class="input date-input-full text-xs" />
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <div class="export-card-footer mt-4">
          <div class="matrix-info mb-3">
            <div class="selected-area-badge" v-if="dailyAreaId">
              <span class="badge-dot"></span>
              <span>Lokasi terpilih: <b>{{ getAreaName(dailyAreaId) }}</b></span>
            </div>
            <div class="area-warning-badge" v-else>
              ⚠️ Silakan pilih area/lokasi terlebih dahulu.
            </div>
          </div>
          <button class="btn btn-primary export-btn" @click="fetchDailyChecklist" :disabled="loadingDaily || !dailyAreaId">
            <span v-if="loadingDaily" class="spinner-small"></span>
            <span>{{ loadingDaily ? 'Memproses...' : 'Lihat Ceklist Harian (Pratinjau)' }}</span>
          </button>
        </div>
      </div>

      <!-- Matrix Excel Report -->
      <div class="export-card glass-card animate-slide-up stagger-2">
        <div class="export-card-body">
          <div class="export-header mb-3">
            <span class="export-title export-title-gradient">Tabel Ceklist Ruangan (Format JEC)</span>
            <span class="format-badge format-badge-success">EXCEL</span>
          </div>
          <div class="export-desc">
            <p class="mb-4">
              Mengekspor matriks visual tanda ceklist (v) per item kebersihan, per shift aktif harian (1 & 2), lengkap dengan nama ruangan, tanda tangan paraf petugas/PJ, dan penanggung jawab unit sesuai format cetak fisik RS JEC Orbita.
            </p>

            <!-- Pilih Ruangan/Area -->
            <div class="form-group mb-4">
              <label class="label text-xs font-semibold">
                <span class="input-icon">🏢</span> Pilih Ruangan/Area
              </label>
              <div class="select-wrapper">
                <select v-model="matrixAreaId" class="input select-input text-xs" :disabled="areas.length === 0">
                  <option value="">Pilih Area...</option>
                  <option v-for="a in areas" :key="a.id" :value="a.id">{{ a.name }} [{{ a.code }}]</option>
                </select>
              </div>
            </div>

            <!-- Bulan & Tahun Selectors -->
            <div class="inline-flex-container">
              <div class="flex-field">
                <label class="label text-xs font-semibold"><span class="input-icon">🗓️</span> Bulan</label>
                <div class="select-wrapper">
                  <select v-model="month" class="input select-input text-xs">
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
              </div>
              <div class="flex-field year-flex-field">
                <label class="label text-xs font-semibold"><span class="input-icon">📆</span> Tahun</label>
                <div class="select-wrapper">
                  <select v-model="year" class="input select-input text-xs">
                    <option :value="new Date().getFullYear() - 1">{{ new Date().getFullYear() - 1 }}</option>
                    <option :value="new Date().getFullYear()">{{ new Date().getFullYear() }}</option>
                  </select>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <div class="export-card-footer mt-4">
          <div class="matrix-info mb-3">
            <div class="selected-area-badge" v-if="matrixAreaId">
              <span class="badge-dot"></span>
              <span>Lokasi terpilih: <b>{{ getAreaName(matrixAreaId) }}</b></span>
            </div>
            <div class="area-warning-badge" v-else>
              ⚠️ Silakan pilih area/lokasi terlebih dahulu.
            </div>
            <div class="download-meta mt-2" v-if="lastDownload['matrix-excel']">
              <span class="download-meta-icon">✓</span>
              Terakhir diunduh: {{ lastDownload['matrix-excel'] }}
            </div>
          </div>
          
          <button class="btn btn-secondary export-btn" @click="downloadReport('matrix-excel')" :disabled="loading['matrix-excel'] || !matrixAreaId">
            <span v-if="loading['matrix-excel']" class="spinner-small"></span>
            <span>{{ loading['matrix-excel'] ? 'Memproses...' : 'Unduh Matrix Excel (.xls)' }}</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Daily Checklist Preview Modal (Premium Redesign) -->
    <Teleport to="body">
      <Transition name="modal-fade">
        <div v-if="showDailyModal" class="modal-overlay" @click.self="showDailyModal = false">
          <div class="modal-content modal-xl animate-scale-up">
            <div class="modal-header">
              <div class="flex items-center gap-2">
                <span class="modal-title-icon">📄</span>
                <h3 class="modal-title">Pratinjau Ceklist Harian</h3>
              </div>
              <button class="modal-close" @click="showDailyModal = false">✕</button>
            </div>
            
            <div class="modal-body modal-scrollable">
              <div id="daily-print-area">
                <div class="print-header-report">
                  <div class="print-logo">
                    <img src="/Logo%20RS%20JEC%20ORBITA.png" alt="Logo RS JEC Orbita" class="print-logo-img" />
                  </div>
                  <div class="print-meta-info text-right">
                    <h2>LAPORAN CEKLIST HARIAN & FOTO BUKTI</h2>
                    <table class="meta-print-table">
                      <tr>
                        <td>Lokasi / Area</td>
                        <td>: <b>{{ getAreaName(dailyAreaId) }}</b></td>
                      </tr>
                      <tr>
                        <td>Periode</td>
                        <td>: <b>{{ getFormattedPeriodText() }}</b></td>
                      </tr>
                    </table>
                  </div>
                </div>

                <div v-if="dailyActivities.length === 0" class="empty-print-state">
                  <div class="empty-print-icon">📭</div>
                  <p class="empty-title">Tidak Ada Aktivitas Tercatat</p>
                  <p class="empty-desc">Tidak ditemukan data ceklist kebersihan pada area dan periode terpilih.</p>
                </div>

                <div v-else v-for="(act, idx) in dailyActivities" :key="act.id" class="activity-print-section mb-6">
                  <div class="activity-print-header mb-4">
                    <div class="activity-print-shift">Petugas: {{ act.user?.name || '-' }}</div>
                    <div class="activity-print-details">
                      <span>Tanggal: <b>{{ formatIndoShortDate(act.date) }}</b></span>
                      <span class="divider">|</span>
                      <span>Waktu: <b>{{ act.start_time || '-' }} - {{ act.end_time || '-' }}</b> ({{ calculateDuration(act.start_time, act.end_time) }} Menit)</span>
                    </div>
                  </div>

                  <div class="table-responsive mb-4">
                    <table class="checklist-table-details">
                      <thead>
                        <tr>
                          <th width="50">NO</th>
                          <th width="240">RUANGAN / BAGIAN</th>
                          <th>ITEM KEBERSIHAN</th>
                          <th width="140">STATUS</th>
                          <th width="140">WAKTU CEK</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-for="(item, itemIdx) in act.items" :key="item.id">
                          <td class="text-center font-semibold">{{ itemIdx + 1 }}</td>
                          <td class="text-left font-medium text-foreground">{{ item.area_object?.room_name || 'Umum' }}</td>
                          <td class="text-left text-muted-fg">{{ item.area_object?.cleaning_object?.name || '-' }}</td>
                          <td class="text-center">
                            <span :class="item.is_checked ? 'status-clean-badge' : 'status-dirty-badge'">
                              {{ item.is_checked ? '✓' : '✗' }}
                            </span>
                          </td>
                          <td class="text-center font-mono text-xs">{{ item.checked_at ? formatTimeOnly(item.checked_at) : '-' }}</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>

                  <div class="activity-print-notes mb-4" v-if="act.notes">
                    <span class="notes-icon">📝</span>
                    <div>
                      <span class="font-bold block text-xs uppercase tracking-wider text-warning mb-1">Catatan Kendala / Keterangan</span>
                      <p class="text-sm m-0 text-muted-fg">{{ act.notes }}</p>
                    </div>
                  </div>

                  <!-- Photos Grid (Premium layout with nice cards) -->
                  <div class="activity-print-photos mb-4">
                    <div class="photo-print-column">
                      <h4 class="photo-section-title">Foto Sebelum Kerja (Before)</h4>
                      <div class="photo-print-wrapper">
                        <div v-if="getPhotosByType(act.photos, 'before').length === 0" class="no-photo-text">
                          <span class="no-photo-icon">📷</span> Tidak ada foto Before
                        </div>
                        <div v-else class="photo-gallery">
                          <img v-for="photo in getPhotosByType(act.photos, 'before')" :key="photo.id"
                               :src="`${apiBaseUrl}/storage/${photo.file_path}`" alt="Before" class="photo-print-el" />
                        </div>
                      </div>
                    </div>
                    
                    <div class="photo-print-column">
                      <h4 class="photo-section-title">Foto Sesudah Kerja (After)</h4>
                      <div class="photo-print-wrapper">
                        <div v-if="getPhotosByType(act.photos, 'after').length === 0" class="no-photo-text">
                          <span class="no-photo-icon">📷</span> Tidak ada foto After
                        </div>
                        <div v-else class="photo-gallery">
                          <img v-for="photo in getPhotosByType(act.photos, 'after')" :key="photo.id"
                               :src="`${apiBaseUrl}/storage/${photo.file_path}`" alt="After" class="photo-print-el" />
                        </div>
                      </div>
                    </div>
                  </div>

                  <div class="print-signatures">
                    <div class="signature-box">
                      <p class="sig-title">Petugas Cleaning Service</p>
                      <div class="signature-line"></div>
                      <p class="font-bold text-foreground">{{ act.user?.name || '........................' }}</p>
                    </div>
                    <div class="signature-box">
                      <p class="sig-title">Penanggung Jawab (PJ) Unit</p>
                      <div class="signature-line"></div>
                      <p class="font-bold text-foreground">........................................</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            
            <div class="modal-footer">
              <button class="btn btn-ghost btn-sm" @click="showDailyModal = false">Tutup</button>
              <button v-if="dailyActivities.length > 0" class="btn btn-secondary excel-btn-modal btn-sm" @click="exportDailyToExcel">
                🟢 Ekspor ke Excel (.xls)
              </button>
              <button v-if="dailyActivities.length > 0" class="btn btn-primary btn-sm" @click="printDailyReport">
                🖨️ Cetak / Simpan PDF
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
}

.header-icon-badge {
  display: inline-flex !important;
  align-items: center;
  justify-content: center;
  width: 3.5rem;
  height: 3.5rem;
  background: hsl(var(--primary) / 0.15);
  font-size: 2rem;
  border-radius: 0.75rem;
  border: 1px solid hsl(var(--primary) / 0.3);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
  flex-shrink: 0;
}

.page-title {
  font-size: 1.5rem;
  font-weight: 850;
  line-height: 2rem;
  color: hsl(var(--foreground));
  margin: 0;
  letter-spacing: -0.02em;
}

.page-subtitle {
  color: hsl(var(--muted-foreground));
  font-size: 0.875rem;
  margin-top: 0.125rem;
}

/* ===== Glassmorphic Filter Card ===== */
.filter-card {
  background: hsl(var(--card) / 0.75);
  border: 1px solid hsl(var(--border));
  backdrop-filter: blur(12px);
  border-radius: 1.25rem;
  padding: 1.5rem;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
  transition: all 0.3s ease;
}

.filter-card:hover {
  border-color: hsl(var(--primary) / 0.25);
  box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
}

.filter-title {
  font-weight: 800;
  margin-bottom: 1.25rem;
  display: flex;
  align-items: center;
  gap: 0.625rem;
  font-size: 1.05rem;
  color: hsl(var(--foreground));
}

.title-icon-wrapper {
  background: hsl(var(--primary) / 0.1);
  padding: 0.25rem 0.4rem;
  border-radius: 0.5rem;
}

.filter-row {
  display: flex;
  gap: 1.5rem;
}

.filter-field {
  flex: 1;
}

.filter-field .label {
  display: flex;
  align-items: center;
  gap: 0.375rem;
  font-size: 0.8rem;
  font-weight: 750;
  color: hsl(var(--muted-foreground));
  margin-bottom: 0.5rem;
}

.input-icon {
  font-size: 0.95rem;
}

.select-wrapper {
  position: relative;
}

.select-input {
  width: 100%;
  appearance: none;
  background: hsl(var(--input) / 0.3);
  border: 1px solid hsl(var(--border));
  padding: 0.625rem 2rem 0.625rem 0.875rem;
  border-radius: 0.625rem;
  font-size: 0.875rem;
  font-weight: 600;
  color: hsl(var(--foreground));
  transition: all 0.2s ease;
  cursor: pointer;
}

.select-input:focus {
  border-color: hsl(var(--primary));
  background: hsl(var(--card));
  box-shadow: 0 0 0 2px hsl(var(--primary) / 0.15);
  outline: none;
}

/* Custom Select Arrow */
.select-wrapper::after {
  content: '▼';
  font-size: 0.65rem;
  color: hsl(var(--muted-foreground));
  position: absolute;
  right: 0.875rem;
  top: 50%;
  transform: translateY(-50%);
  pointer-events: none;
}

.label-hint {
  font-size: 0.725rem;
  color: hsl(var(--muted-foreground) / 0.7);
  font-weight: 500;
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
  border-radius: 1.25rem;
  background: hsl(var(--card) / 0.8);
  border: 1px solid hsl(var(--border));
  box-shadow: 0 4px 20px rgba(0,0,0,0.05);
  transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
  backdrop-filter: blur(10px);
}

.export-card:hover {
  transform: translateY(-3px);
  border-color: hsl(var(--primary) / 0.35);
  box-shadow: 0 12px 30px rgba(0,0,0,0.12);
}

.export-card-featured {
  border-color: hsl(var(--primary) / 0.2);
  background: linear-gradient(135deg, hsl(var(--card) / 0.9), hsl(var(--primary) / 0.04));
}

.export-card-body {
  flex: 1;
}

.export-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.export-title {
  font-size: 1.1rem;
  font-weight: 800;
  color: hsl(var(--foreground));
  letter-spacing: -0.01em;
}

.export-title-gradient {
  background: linear-gradient(135deg, hsl(var(--primary)), hsl(var(--accent)));
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.format-badge {
  font-size: 0.65rem;
  font-weight: 850;
  padding: 0.25rem 0.625rem;
  border-radius: 9999px;
  letter-spacing: 0.06em;
  flex-shrink: 0;
}

.format-badge-accent {
  background: hsl(var(--primary) / 0.12);
  color: hsl(var(--primary));
  border: 1px solid hsl(var(--primary) / 0.25);
  box-shadow: 0 2px 6px hsl(var(--primary) / 0.05);
}

.format-badge-success {
  background: hsl(var(--success) / 0.12);
  color: hsl(var(--success));
  border: 1px solid hsl(var(--success) / 0.25);
  box-shadow: 0 2px 6px hsl(var(--success) / 0.05);
}

.export-desc p {
  font-size: 0.85rem;
  color: hsl(var(--muted-foreground));
  line-height: 1.55;
  margin: 0;
}

.export-card-footer {
  border-top: 1px solid hsl(var(--border) / 0.5);
  padding-top: 1.25rem;
}

.export-btn {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  padding: 0.625rem;
  border-radius: 0.625rem;
  font-weight: 700;
  font-size: 0.875rem;
  transition: all 0.2s ease;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

.export-btn:hover {
  transform: translateY(-1px);
  box-shadow: 0 6px 16px rgba(0, 0, 0, 0.1);
}

/* ===== iOS Style Pill Tabs ===== */
.filter-mode-tabs {
  display: flex;
  background: hsl(var(--input) / 0.4);
  padding: 0.25rem;
  border-radius: 0.75rem;
  gap: 0.125rem;
  border: 1px solid hsl(var(--border) / 0.5);
}

.tab-btn {
  flex: 1;
  background: transparent;
  border: none;
  padding: 0.5rem 0.75rem;
  font-size: 0.75rem;
  font-weight: 700;
  color: hsl(var(--muted-foreground));
  border-radius: 0.625rem;
  cursor: pointer;
  transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
  text-align: center;
}

.tab-btn:hover {
  color: hsl(var(--foreground));
}

.tab-btn.active {
  background: hsl(var(--card));
  color: hsl(var(--foreground));
  box-shadow: 0 3px 10px rgba(0,0,0,0.1);
}

.today-banner {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  background: hsl(var(--primary) / 0.05);
  padding: 0.375rem 0.75rem;
  border-radius: 0.5rem;
  border: 1px solid hsl(var(--primary) / 0.15);
}

.today-dot {
  width: 6px;
  height: 6px;
  background: hsl(var(--primary));
  border-radius: 50%;
  animation: pulse 1.8s infinite;
}

@keyframes pulse {
  0% { transform: scale(0.95); box-shadow: 0 0 0 0 hsl(var(--primary) / 0.7); }
  70% { transform: scale(1); box-shadow: 0 0 0 5px hsl(var(--primary) / 0); }
  100% { transform: scale(0.95); box-shadow: 0 0 0 0 hsl(var(--primary) / 0); }
}

.mode-input-container {
  margin-top: 0.75rem;
  animation: fadeIn 0.25s ease;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(4px); }
  to { opacity: 1; transform: translateY(0); }
}

.inline-flex-container {
  display: flex;
  gap: 0.75rem;
}

.flex-field {
  flex: 1;
}

.flex-field .label {
  font-size: 0.75rem;
  font-weight: 700;
  color: hsl(var(--muted-foreground));
  margin-bottom: 0.375rem;
}

.date-input-full {
  width: 100%;
  background: hsl(var(--input) / 0.3);
  border: 1px solid hsl(var(--border));
  padding: 0.5rem 0.75rem;
  border-radius: 0.625rem;
  font-weight: 600;
  color: hsl(var(--foreground));
  transition: border-color 0.2s ease;
}

.date-input-full:focus {
  border-color: hsl(var(--primary));
  outline: none;
}

/* ===== Download Meta ===== */
.download-meta {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  font-size: 0.725rem;
  color: hsl(var(--success));
  font-weight: 600;
}

.download-meta-icon {
  font-size: 0.75rem;
}

.matrix-info {
  display: flex;
  flex-direction: column;
}

.selected-area-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  font-size: 0.75rem;
  color: hsl(var(--foreground));
  background: hsl(var(--success) / 0.06);
  border: 1px solid hsl(var(--success) / 0.15);
  padding: 0.375rem 0.625rem;
  border-radius: 0.5rem;
}

.badge-dot {
  width: 5px;
  height: 5px;
  background: hsl(var(--success));
  border-radius: 50%;
}

.area-warning-badge {
  font-size: 0.75rem;
  color: hsl(var(--warning));
  background: hsl(var(--warning) / 0.05);
  border: 1px solid hsl(var(--warning) / 0.15);
  padding: 0.375rem 0.625rem;
  border-radius: 0.5rem;
  font-weight: 600;
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

/* ===== Responsive (Mobile UI adjustments) ===== */
@media (max-width: 768px) {
  .reports-header {
    margin-bottom: 1.25rem;
  }
  
  .header-icon-badge {
    font-size: 1.5rem;
    padding: 0.375rem;
  }
  
  .page-title {
    font-size: 1.35rem;
  }

  .filter-card {
    padding: 1.25rem;
  }

  .filter-row {
    flex-direction: column;
    gap: 1rem;
  }
  
  .area-select-field {
    width: 100%;
  }

  .export-grid {
    grid-template-columns: 1fr;
    gap: 1rem;
  }

  .export-card {
    padding: 1.25rem;
  }

  .inline-flex-container {
    flex-direction: column;
    gap: 0.75rem;
  }

  .year-flex-field {
    width: 100% !important;
  }
}

/* ===== Utility Classes (Tailwind Helpers) ===== */
.flex {
  display: flex !important;
}
.items-center {
  align-items: center !important;
}
.gap-2 {
  gap: 0.5rem !important;
}
.gap-3 {
  gap: 0.75rem !important;
}
.gap-4 {
  gap: 1rem !important;
}
.mb-1 { margin-bottom: 0.25rem !important; }
.mb-3 { margin-bottom: 0.75rem !important; }
.mb-4 { margin-bottom: 1rem !important; }
.mb-6 { margin-bottom: 1.5rem !important; }
.mt-2 { margin-top: 0.5rem !important; }
.mt-4 { margin-top: 1rem !important; }
.m-0 { margin: 0 !important; }
.font-semibold {
  font-weight: 600 !important;
}
.font-bold {
  font-weight: 700 !important;
}
.font-medium {
  font-weight: 500 !important;
}
.font-mono {
  font-family: var(--font-mono), monospace !important;
}
.text-xs {
  font-size: 0.75rem !important;
}
.text-sm {
  font-size: 0.875rem !important;
}
.text-right {
  text-align: right !important;
}
.text-left {
  text-align: left !important;
}
.text-center {
  text-align: center !important;
}
.text-muted-foreground {
  color: hsl(var(--muted-foreground)) !important;
}
.text-foreground {
  color: hsl(var(--foreground)) !important;
}
.text-muted-fg {
  color: hsl(var(--muted-foreground)) !important;
}
.text-warning {
  color: hsl(var(--warning)) !important;
}
.block {
  display: block !important;
}
.uppercase {
  text-transform: uppercase !important;
}
.tracking-wider {
  letter-spacing: 0.05em !important;
}
.btn-sm {
  padding: 0.375rem 0.75rem !important;
  font-size: 0.75rem !important;
}
</style>

<style>
/* ===== Daily Modal (Global/Teleport Styles) ===== */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(4, 8, 15, 0.75);
  backdrop-filter: blur(12px);
  z-index: 99999;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1.5rem;
}

.modal-content {
  background: hsl(var(--card) / 0.95);
  border: 1px solid hsl(var(--border));
  border-radius: 1.25rem;
  width: 100%;
  display: flex;
  flex-direction: column;
  box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
  overflow: hidden;
  backdrop-filter: blur(20px);
}

.modal-xl {
  max-width: 980px;
  max-height: 90vh;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem 1.5rem;
  border-bottom: 1px solid hsl(var(--border) / 0.5);
  background: hsl(var(--card) / 0.5);
}

.modal-title-icon {
  font-size: 1.25rem;
}

.modal-title {
  font-size: 1.15rem;
  font-weight: 800;
  color: hsl(var(--foreground));
  margin: 0;
  letter-spacing: -0.01em;
}

.modal-close {
  width: 2rem;
  height: 2rem;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 1px solid hsl(var(--border));
  background: hsl(var(--muted) / 0.5);
  border-radius: 0.5rem;
  cursor: pointer;
  color: hsl(var(--muted-foreground));
  font-size: 0.875rem;
  transition: all 0.2s ease;
}

.modal-close:hover {
  background: hsl(var(--destructive) / 0.15);
  color: hsl(var(--destructive));
  border-color: hsl(var(--destructive) / 0.25);
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
  border-top: 1px solid hsl(var(--border) / 0.5);
  background: hsl(var(--muted) / 0.2);
}

.btn-ghost {
  background: transparent;
  border: 1px solid hsl(var(--border));
  color: hsl(var(--foreground));
  padding: 0.5rem 1rem;
  border-radius: 0.5rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-ghost:hover {
  background: hsl(var(--muted));
  color: hsl(var(--foreground));
}

.excel-btn-modal {
  background: hsl(142, 60%, 15%) !important;
  color: hsl(142, 70%, 75%) !important;
  border: 1px solid hsl(142, 60%, 30%) !important;
  font-weight: 700;
  transition: all 0.2s ease;
}

.excel-btn-modal:hover {
  background: hsl(142, 60%, 20%) !important;
  color: hsl(142, 70%, 85%) !important;
  box-shadow: 0 4px 12px hsl(142, 60%, 10% / 0.3);
}

/* ===== Print preview layout ===== */
#daily-print-area {
  padding: 0.5rem;
}

.print-header-report {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  border-bottom: 2px solid hsl(var(--foreground));
  padding-bottom: 1.25rem;
  margin-bottom: 1.5rem;
}

.print-logo-img {
  height: 48px;
  width: auto;
  object-fit: contain;
}

.print-meta-info h2 {
  font-size: 1.15rem;
  font-weight: 850;
  margin: 0 0 0.5rem 0;
  color: hsl(var(--foreground));
  letter-spacing: -0.01em;
}

.meta-print-table {
  margin-left: auto;
  border-collapse: collapse;
}

.meta-print-table td {
  border: none !important;
  padding: 0.15rem 0.35rem;
  font-size: 0.8rem;
  text-align: left;
}

.empty-print-state {
  text-align: center;
  padding: 4rem 1.5rem;
  color: hsl(var(--muted-foreground));
  background: hsl(var(--muted) / 0.1);
  border-radius: 1rem;
  border: 1px dashed hsl(var(--border));
}

.empty-print-icon {
  font-size: 3rem;
  margin-bottom: 0.75rem;
  opacity: 0.7;
}

.empty-title {
  font-size: 1rem;
  font-weight: 800;
  color: hsl(var(--foreground));
  margin: 0;
}

.empty-desc {
  font-size: 0.8rem;
  color: hsl(var(--muted-foreground));
  margin-top: 0.25rem;
}

.activity-print-section {
  border: 1px solid hsl(var(--border));
  border-radius: 1rem;
  padding: 1.25rem;
  background: hsl(var(--card) / 0.5);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.02);
}

.activity-print-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: hsl(var(--primary) / 0.08);
  border: 1px solid hsl(var(--primary) / 0.15);
  padding: 0.75rem 1rem;
  border-radius: 0.625rem;
}

.activity-print-shift {
  font-weight: 850;
  font-size: 0.75rem;
  background: hsl(var(--primary));
  color: white;
  padding: 0.25rem 0.75rem;
  border-radius: 0.5rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.activity-print-details {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  font-size: 0.8rem;
  color: hsl(var(--muted-foreground));
}

.checklist-table-details {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.8rem;
  border-radius: 0.75rem;
  overflow: hidden;
  border: 1px solid hsl(var(--border));
}

.checklist-table-details th,
.checklist-table-details td {
  border: 1px solid hsl(var(--border) / 0.6);
  padding: 0.625rem;
}

.checklist-table-details th {
  background: hsl(var(--muted) / 0.6);
  font-weight: 800;
  text-align: center;
  color: hsl(var(--foreground));
}

.status-clean-badge {
  background: hsl(var(--success) / 0.12);
  color: hsl(var(--success));
  border: 1px solid hsl(var(--success) / 0.25);
  padding: 0.15rem 0.5rem;
  border-radius: 9999px;
  font-size: 0.725rem;
  font-weight: 800;
  display: inline-block;
}

.status-dirty-badge {
  background: hsl(var(--destructive) / 0.12);
  color: hsl(var(--destructive));
  border: 1px solid hsl(var(--destructive) / 0.25);
  padding: 0.15rem 0.5rem;
  border-radius: 9999px;
  font-size: 0.725rem;
  font-weight: 800;
  display: inline-block;
}

.activity-print-notes {
  background: hsl(var(--warning) / 0.06);
  border: 1px solid hsl(var(--warning) / 0.18);
  border-left: 4px solid hsl(var(--warning));
  padding: 0.75rem 1rem;
  border-radius: 0.5rem;
  display: flex;
  gap: 0.75rem;
}

.notes-icon {
  font-size: 1.1rem;
  flex-shrink: 0;
  margin-top: 0.125rem;
}

.activity-print-photos {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1.25rem;
}

.photo-section-title {
  font-size: 0.75rem;
  font-weight: 800;
  margin: 0 0 0.5rem 0;
  text-transform: uppercase;
  color: hsl(var(--muted-foreground));
  letter-spacing: 0.05em;
  display: flex;
  align-items: center;
  gap: 0.375rem;
}

.photo-print-wrapper {
  background: hsl(var(--muted) / 0.2);
  border: 1px dashed hsl(var(--border));
  padding: 0.75rem;
  border-radius: 0.75rem;
  min-height: 140px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.photo-gallery {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 0.75rem;
  width: 100%;
}

.photo-print-el {
  height: 110px;
  width: 100%;
  object-fit: cover;
  border-radius: 0.5rem;
  border: 1px solid hsl(var(--border));
  cursor: pointer;
  transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.2s ease;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.photo-print-el:hover {
  transform: scale(1.04);
  box-shadow: 0 6px 16px rgba(0,0,0,0.2);
}

.no-photo-text {
  font-size: 0.75rem;
  color: hsl(var(--muted-foreground));
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.375rem;
}

.no-photo-icon {
  font-size: 1.25rem;
  opacity: 0.5;
}

.print-signatures {
  display: flex;
  justify-content: space-between;
  margin-top: 1.5rem;
  padding-top: 1.25rem;
  border-top: 1px dashed hsl(var(--border) / 0.6);
}

.signature-box {
  width: 45%;
  text-align: center;
}

.sig-title {
  font-size: 0.75rem;
  margin: 0;
  color: hsl(var(--muted-foreground));
  font-weight: 700;
}

.signature-line {
  height: 55px;
  margin-bottom: 0.25rem;
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

/* Modal printing responsive */
@media (max-width: 768px) {
  .modal-overlay {
    padding: 0.75rem;
  }
  
  .modal-xl {
    max-height: 95vh;
  }

  .modal-content {
    border-radius: 1rem;
  }

  .modal-body {
    padding: 1rem;
  }

  .print-header-report {
    flex-direction: column;
    align-items: center;
    text-align: center;
    gap: 0.75rem;
  }

  .print-meta-info {
    text-align: center;
  }

  .meta-print-table {
    margin: 0.5rem auto 0 auto;
  }

  .activity-print-header {
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    text-align: center;
  }

  .activity-print-details {
    flex-direction: column;
    gap: 0.25rem;
  }

  .activity-print-details .divider {
    display: none;
  }

  .activity-print-photos {
    grid-template-columns: 1fr;
    gap: 1rem;
  }
  
  .photo-gallery {
    grid-template-columns: repeat(2, 1fr);
  }

  .print-signatures {
    flex-direction: column;
    gap: 1.5rem;
    align-items: center;
  }

  .signature-box {
    width: 80%;
  }
}

/* ===== Printing Media Queries ===== */
@media print {
  #app {
    display: none !important;
  }
  
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

  .status-clean-badge {
    color: #008000 !important;
    background: transparent !important;
    border: none !important;
  }

  .status-dirty-badge {
    color: #ff0000 !important;
    background: transparent !important;
    border: none !important;
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
