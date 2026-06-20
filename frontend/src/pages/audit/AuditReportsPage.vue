<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../../lib/axios'
import { echo } from '../../lib/echo'

const route = useRoute()
const router = useRouter()
const sessionUuid = route.params.sessionUuid as string

const sessionToken = localStorage.getItem('audit_session_token')
const isValidating = ref(true)
const sessionError = ref('')
const auditorName = ref('')
const auditorUnit = ref('')

const month = ref(new Date().getMonth() + 1)
const year = ref(new Date().getFullYear())
const areaId = ref<number | string>('')
const areas = ref<any[]>([])

const loading = ref({
  monthly: false,
  audit: false,
  'matrix-excel': false
})

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

const checkInterval = ref<any>(null)

onMounted(async () => {
  // Validate token matches route param
  if (!sessionToken || sessionToken !== sessionUuid) {
    sessionError.value = 'Token sesi tidak ditemukan atau tidak valid. Silakan ajukan izin akses kembali.'
    isValidating.value = false
    return
  }

  // Verify session status with backend
  try {
    const res = await api.get(`/api/v1/public/audit-session/${sessionUuid}`)
    if (res.data.status !== 'approved') {
      sessionError.value = 'Sesi Anda belum disetujui atau sudah berakhir.'
      isValidating.value = false
      return
    }

    // Get session metadata from local storage if available
    auditorName.value = localStorage.getItem('audit_name') || 'Auditor'
    auditorUnit.value = localStorage.getItem('audit_unit') || 'SPI / Management'

    // Fetch areas using public endpoint
    await fetchAreas()

    // Start listening for session changes (revocation/expiration)
    startSessionListener(sessionUuid)
  } catch (err: any) {
    sessionError.value = err.response?.data?.message || 'Gagal memverifikasi status sesi audit.'
  } finally {
    isValidating.value = false
  }
})

onUnmounted(() => {
  stopPolling()
  if (sessionUuid) {
    echo.leave(`audit-session.${sessionUuid}`)
  }
})

function startSessionListener(uuid: string) {
  // 1. WebSocket (Echo)
  echo.channel(`audit-session.${uuid}`)
    .listen('.App\\Events\\AuditSessionApproved', (e: any) => {
      if (e.status !== 'approved') {
        sessionError.value = 'Sesi Anda telah diputus oleh administrator.'
        stopPolling()
      }
    })

  // 2. Polling Fallback (every 10 seconds)
  checkInterval.value = setInterval(async () => {
    try {
      const res = await api.get(`/api/v1/public/audit-session/${uuid}`)
      if (res.data.status !== 'approved') {
        sessionError.value = 'Sesi Anda telah berakhir atau diputus oleh administrator.'
        stopPolling()
      }
    } catch (err: any) {
      if (err.response?.status === 404 || err.response?.status === 403) {
        sessionError.value = 'Sesi Anda telah berakhir atau diputus oleh administrator.'
        stopPolling()
      }
    }
  }, 10000)
}

function stopPolling() {
  if (checkInterval.value) {
    clearInterval(checkInterval.value)
    checkInterval.value = null
  }
}

async function fetchAreas() {
  try {
    const { data } = await api.get('/api/v1/public/areas', {
      headers: { 'X-Audit-Session-Token': sessionUuid }
    })
    areas.value = data.data || []
    if (areas.value.length > 0) {
      areaId.value = areas.value[0].id
    }
  } catch (e: any) {
    showToast('Gagal memuat daftar area: ' + e.message, 'error')
  }
}

function getSelectedAreaName(): string {
  if (!areaId.value) return ''
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
      params.area_id = areaId.value;
    }

    const routeMap: Record<string, string> = {
      'monthly': '/api/v1/public/audit-reports/cleaning',
      'audit': '/api/v1/public/audit-reports/audits',
      'matrix-excel': '/api/v1/public/audit-reports/matrix-excel'
    }

    const response = await api.get(routeMap[type], {
      params,
      responseType: 'blob',
      headers: { 'X-Audit-Session-Token': sessionUuid }
    })

    const url = window.URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement('a')
    link.href = url

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

    lastDownload.value[type] = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
    showToast(`Berhasil mengunduh laporan ${type === 'matrix-excel' ? 'Excel Matrix' : 'CSV'}!`, 'success')
  } catch (e: any) {
    showToast('Gagal mengunduh laporan. Silakan coba kembali.', 'error')
  } finally {
    loading.value[type] = false
  }
}

// Daily Checklist variables & functions
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
  if (!areaId.value) {
    showToast('Pilih area terlebih dahulu untuk melihat pratinjau.', 'error')
    return
  }
  loadingDaily.value = true
  try {
    const params: any = { area_id: areaId.value }
    
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

    const { data } = await api.get('/api/v1/public/activities', {
      params,
      headers: { 'X-Audit-Session-Token': sessionUuid }
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
  const formattedIndoDateStr = getFormattedPeriodText()
  
  let html = `
    <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
    <head>
      <meta charset="utf-8">
      <style>
        table { border-collapse: collapse; font-family: Arial, sans-serif; font-size: 11px; }
        th, td { border: 1px solid black; padding: 5px; text-align: center; }
        .text-left { text-align: left; }
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
  link.href = url
  link.setAttribute('download', `Ceklist_${areaName.replace(/[^a-zA-Z0-9]/g, '_')}_${getPeriodLabel()}.xls`)
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

function logoutAudit() {
  const linkUuid = localStorage.getItem('audit_link_uuid')
  localStorage.removeItem('audit_session_token')
  localStorage.removeItem('audit_session_uuid')
  if (linkUuid) {
    router.push({ name: 'audit-gateway', params: { linkUuid } })
  } else {
    router.push('/')
  }
}
</script>

<template>
  <div class="audit-reports-wrapper">
    <!-- Toast notification -->
    <Teleport to="body">
      <Transition name="toast-slide">
        <div v-if="toast.show" class="toast" :class="`toast-${toast.type}`">
          <span class="toast-icon">{{ toast.type === 'success' ? '✅' : '❌' }}</span>
          {{ toast.message }}
        </div>
      </Transition>
    </Teleport>

    <!-- Validating status screen -->
    <div v-if="isValidating" class="fullscreen-state">
      <div class="spinner"></div>
      <p>Memverifikasi sesi portal laporan...</p>
    </div>

    <!-- Error screen -->
    <div v-else-if="sessionError" class="fullscreen-state error-screen">
      <div class="icon-circle error">⚠️</div>
      <h2>Akses Ditangguhkan</h2>
      <p>{{ sessionError }}</p>
      <button class="btn btn-primary" @click="logoutAudit">Kembali ke Gateway</button>
    </div>

    <!-- Main portal view -->
    <div v-else class="portal-container animate-fade-in">
      <header class="portal-header">
        <div class="header-main">
          <img src="/Logo%20RS%20JEC%20ORBITA.png" alt="Logo RS" class="hospital-logo" />
          <div class="portal-identity">
            <h1>Portal Laporan Audit</h1>
            <p>Sesi Sementara Aktif</p>
          </div>
        </div>
        <div class="auditor-meta-header">
          <div class="auditor-info">
            <span class="avatar-guest">👤</span>
            <div class="text-group">
              <span class="auditor-name">{{ auditorName }}</span>
              <span class="auditor-unit">Unit: {{ auditorUnit }}</span>
            </div>
          </div>
          <button class="btn btn-secondary btn-sm" @click="logoutAudit">Keluar Sesi</button>
        </div>
      </header>

      <!-- Period Filter Card -->
      <section class="card filter-card mb-6 animate-slide-up">
        <h2 class="filter-title">
          <span>📅</span> Pilih Periode Laporan & Area
        </h2>
        <div class="filter-row">
          <div class="form-group filter-field">
            <label class="label">Bulan</label>
            <select v-model="month" class="input select-input">
              <option v-for="(name, index) in monthNames" :key="index" :value="Number(index)">{{ name }}</option>
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
            <label class="label">Pilih Ruangan/Area</label>
            <select v-model="areaId" class="input select-input" :disabled="areas.length === 0">
              <option value="">Pilih Area...</option>
              <option v-for="a in areas" :key="a.id" :value="a.id">{{ a.name }} [{{ a.code }}]</option>
            </select>
          </div>
        </div>
        <div class="period-indicator" v-if="month && year">
          <span>Aktivitas periode: <b>{{ getPeriodLabel() }}</b></span>
        </div>
      </section>

      <!-- Export Grid -->
      <div class="export-grid">
        <!-- Daily Checklist Card -->
        <div class="export-card animate-slide-up stagger-1">
          <div class="export-card-body">
            <div class="export-header">
              <span class="export-title">Ceklist Harian & Foto Bukti</span>
              <span class="format-badge preview">PRATINJAU & PDF</span>
            </div>
            <div class="export-desc">
              <p>Pratinjau visual tabel ceklist kebersihan ruangan beserta foto bukti sebelum/sesudah per hari, cetak PDF, atau simpan file.</p>
              
              <div class="form-group mt-4">
                <label class="label text-xs">Pilih Mode Periode</label>
                <div class="filter-mode-tabs mb-3">
                  <button type="button" class="tab-btn" :class="{ active: filterMode === 'today' }" @click="filterMode = 'today'">Hari Ini</button>
                  <button type="button" class="tab-btn" :class="{ active: filterMode === 'month' }" @click="filterMode = 'month'">1 Bulan</button>
                  <button type="button" class="tab-btn" :class="{ active: filterMode === 'range' }" @click="filterMode = 'range'">Rentang</button>
                </div>

                <div v-if="filterMode === 'today'" class="mode-input-container">
                  <span class="text-xs text-muted-fg">Hari ini: <b>{{ formatIndoDate(new Date().toISOString().split('T')[0]) }}</b></span>
                </div>

                <div v-else-if="filterMode === 'month'" class="mode-input-container inline-flex-container">
                  <div class="flex-field">
                    <select v-model="selectedDailyMonth" class="input select-input text-xs">
                      <option v-for="(name, index) in monthNames" :key="index" :value="Number(index)">{{ name }}</option>
                    </select>
                  </div>
                  <div class="flex-field">
                    <select v-model="selectedDailyYear" class="input select-input text-xs">
                      <option :value="new Date().getFullYear() - 1">{{ new Date().getFullYear() - 1 }}</option>
                      <option :value="new Date().getFullYear()">{{ new Date().getFullYear() }}</option>
                    </select>
                  </div>
                </div>

                <div v-else-if="filterMode === 'range'" class="mode-input-container inline-flex-container">
                  <input type="date" v-model="startDate" class="input text-xs" />
                  <input type="date" v-model="endDate" class="input text-xs" />
                </div>
              </div>
            </div>
          </div>
          <div class="export-card-footer">
            <button class="btn btn-secondary export-btn" @click="fetchDailyChecklist" :disabled="loadingDaily || !areaId">
              <span v-if="loadingDaily" class="spinner-small"></span>
              <span>{{ loadingDaily ? 'Memuat...' : 'Lihat Ceklist Harian' }}</span>
            </button>
          </div>
        </div>

        <!-- Matrix Excel Report -->
        <div class="export-card animate-slide-up stagger-2">
          <div class="export-card-body">
            <div class="export-header">
              <span class="export-title">Tabel Ceklist Ruangan (Matrix)</span>
              <span class="format-badge xls">EXCEL</span>
            </div>
            <div class="export-desc">
              <p>Unduh matriks ceklist (v) per item kebersihan dan shift harian (1 & 2) lengkap dengan paraf petugas & PJ unit sesuai format fisik RS JEC Orbita.</p>
            </div>
          </div>
          <div class="export-card-footer">
            <button class="btn btn-primary export-btn" @click="downloadReport('matrix-excel')" :disabled="loading['matrix-excel'] || !areaId">
              <span v-if="loading['matrix-excel']" class="spinner-small"></span>
              <span>Unduh Matrix Excel (.xls)</span>
            </button>
          </div>
        </div>

        <!-- General Cleaning Activities CSV -->
        <div class="export-card animate-slide-up stagger-3">
          <div class="export-card-body">
            <div class="export-header">
              <span class="export-title">Raw CSV Laporan Kebersihan</span>
              <span class="format-badge csv">CSV</span>
            </div>
            <div class="export-desc">
              <p>Unduh seluruh data mentah log pembersihan harian, jam pengerjaan, nama petugas, dan durasi pengerjaan dalam format CSV untuk diolah kembali.</p>
            </div>
          </div>
          <div class="export-card-footer">
            <button class="btn btn-primary export-btn" @click="downloadReport('monthly')" :disabled="loading.monthly">
              <span v-if="loading.monthly" class="spinner-small"></span>
              <span>Unduh CSV Kebersihan (.csv)</span>
            </button>
          </div>
        </div>

        <!-- Audit Score Summary CSV -->
        <div class="export-card animate-slide-up stagger-4">
          <div class="export-card-body">
            <div class="export-header">
              <span class="export-title">Laporan Nilai Audit Supervisor</span>
              <span class="format-badge csv">CSV</span>
            </div>
            <div class="export-desc">
              <p>Unduh rekap skor kebersihan, SOP, status inspeksi dan rincian temuan audit yang dikumpulkan oleh Supervisor dalam file CSV.</p>
            </div>
          </div>
          <div class="export-card-footer">
            <button class="btn btn-primary export-btn" @click="downloadReport('audit')" :disabled="loading.audit">
              <span v-if="loading.audit" class="spinner-small"></span>
              <span>Unduh CSV Audit (.csv)</span>
            </button>
          </div>
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
                    <img src="/Logo%20RS%20JEC%20ORBITA.png" alt="Logo RS" class="print-logo-img" />
                  </div>
                  <div class="print-meta-info">
                    <h2>LAPORAN CEKLIST HARIAN & FOTO BUKTI</h2>
                    <table class="meta-print-table">
                      <tr>
                        <td>Lokasi / Area</td>
                        <td>: <b>{{ getSelectedAreaName() }}</b></td>
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
                  <p>Tidak ada aktivitas kebersihan yang tercatat pada area dan periode ini.</p>
                </div>

                <div v-else v-for="act in dailyActivities" :key="act.id" class="activity-print-section">
                  <div class="activity-print-header">
                    <div class="activity-print-shift">Petugas: {{ act.user?.name || '-' }}</div>
                    <div class="activity-print-details">
                      <span>Tanggal: <b>{{ formatIndoShortDate(act.date) }}</b></span>
                      <span class="divider">|</span>
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
.audit-reports-wrapper {
  min-height: 100vh;
  background: hsl(var(--background));
  color: hsl(var(--foreground));
}

.fullscreen-state {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  padding: 2rem;
  background: hsl(var(--background));
  gap: 1.5rem;
}

.error-screen h2 {
  font-size: 1.75rem;
  font-weight: 700;
  color: hsl(var(--destructive));
}

.error-screen p {
  color: hsl(var(--muted-foreground));
  max-width: 400px;
  line-height: 1.5;
}

.portal-container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 2rem 1.5rem;
}

.portal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 1.5rem;
  padding-bottom: 2rem;
  border-bottom: 1px solid hsl(var(--border));
  margin-bottom: 2.5rem;
}

.header-main {
  display: flex;
  align-items: center;
  gap: 1.25rem;
}

.hospital-logo {
  height: 56px;
  object-fit: contain;
}

.portal-identity h1 {
  font-size: 1.75rem;
  font-weight: 800;
  color: hsl(var(--foreground));
  letter-spacing: -0.02em;
}

.portal-identity p {
  font-size: 0.875rem;
  color: hsl(var(--success));
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
}

.portal-identity p::before {
  content: '';
  width: 8px;
  height: 8px;
  background-color: hsl(var(--success));
  border-radius: 50%;
  display: inline-block;
  animation: pulse-dot 1.5s infinite;
}

.auditor-meta-header {
  display: flex;
  align-items: center;
  gap: 1.5rem;
  background: hsl(var(--card));
  padding: 0.75rem 1.25rem;
  border-radius: var(--radius);
  border: 1px solid hsl(var(--border));
}

.auditor-info {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.avatar-guest {
  font-size: 1.5rem;
}

.text-group {
  display: flex;
  flex-direction: column;
}

.auditor-name {
  font-weight: 600;
  font-size: 0.9rem;
}

.auditor-unit {
  font-size: 0.75rem;
  color: hsl(var(--muted-foreground));
}

/* Filter Card */
.filter-card {
  padding: 2rem;
}

.filter-title {
  font-size: 1.125rem;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 1.25rem;
}

.filter-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 1.25rem;
}

.period-indicator {
  margin-top: 1.25rem;
  padding-top: 1rem;
  border-top: 1px solid hsl(var(--border));
  font-size: 0.85rem;
  color: hsl(var(--muted-foreground));
}

/* Export Grid */
.export-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 1.5rem;
}

.export-card {
  background: hsl(var(--card));
  border: 1px solid hsl(var(--border));
  border-radius: var(--radius);
  display: flex;
  flex-direction: column;
  transition: all 0.25s ease;
  overflow: hidden;
}

.export-card:hover {
  border-color: hsl(var(--primary) / 0.3);
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.04);
}

.export-card-body {
  padding: 1.5rem;
  flex: 1;
}

.export-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 1rem;
  margin-bottom: 1rem;
}

.export-title {
  font-size: 1.1rem;
  font-weight: 700;
  color: hsl(var(--foreground));
  line-height: 1.3;
}

.format-badge {
  font-size: 0.65rem;
  font-weight: 700;
  padding: 0.25rem 0.5rem;
  border-radius: 4px;
  letter-spacing: 0.05em;
}

.format-badge.preview { background: hsl(var(--primary) / 0.15); color: hsl(var(--primary)); }
.format-badge.xls { background: 142 76% 46% / 0.15; color: hsl(var(--success)); }
.format-badge.csv { background: hsl(var(--accent) / 0.15); color: hsl(var(--accent)); }

.export-desc p {
  font-size: 0.875rem;
  color: hsl(var(--muted-foreground));
  line-height: 1.5;
}

.export-card-footer {
  padding: 1.25rem 1.5rem;
  background: hsl(var(--secondary) / 0.3);
  border-top: 1px solid hsl(var(--border));
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.export-btn {
  width: 100%;
}

.tab-btn {
  padding: 0.35rem 0.75rem;
  font-size: 0.75rem;
  border: 1px solid hsl(var(--border));
  background: transparent;
  color: hsl(var(--foreground));
  border-radius: 4px;
  cursor: pointer;
  font-weight: 500;
}

.tab-btn.active {
  background: hsl(var(--primary));
  color: white;
  border-color: hsl(var(--primary));
}

.filter-mode-tabs {
  display: flex;
  gap: 0.35rem;
}

.inline-flex-container {
  display: flex;
  gap: 0.5rem;
}

/* Spinner */
.spinner {
  width: 48px;
  height: 48px;
  border: 3px solid hsl(var(--border));
  border-top-color: hsl(var(--primary));
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

.spinner-small {
  width: 16px;
  height: 16px;
  border: 2px solid transparent;
  border-top-color: currentColor;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

/* Preview Modal & Print Styles */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 1000;
  background-color: rgba(0, 0, 0, 0.4);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1.5rem;
}

.modal-content {
  background: hsl(var(--card));
  border: 1px solid hsl(var(--border));
  border-radius: var(--radius);
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  width: 100%;
}

.modal-xl {
  max-width: 900px;
}

.modal-header {
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid hsl(var(--border));
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.modal-title {
  font-size: 1.25rem;
  font-weight: 700;
}

.modal-close {
  background: none;
  border: none;
  font-size: 1.25rem;
  cursor: pointer;
  color: hsl(var(--muted-foreground));
}

.modal-body {
  padding: 1.5rem;
  overflow-y: auto;
  flex: 1;
}

.modal-footer {
  padding: 1.25rem 1.5rem;
  border-top: 1px solid hsl(var(--border));
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
  background: hsl(var(--secondary) / 0.1);
}

.checklist-table-details {
  width: 100%;
  border-collapse: collapse;
  margin: 1.5rem 0;
}

.checklist-table-details th,
.checklist-table-details td {
  border: 1px solid hsl(var(--border));
  padding: 0.75rem;
  font-size: 0.85rem;
}

.checklist-table-details th {
  background-color: hsl(var(--muted));
  font-weight: 600;
}

.status-clean {
  color: hsl(var(--success));
  font-weight: 700;
}

.status-dirty {
  color: hsl(var(--destructive));
  font-weight: 700;
}

.print-header-report {
  display: flex;
  align-items: center;
  gap: 2rem;
  border-bottom: 3px double hsl(var(--border));
  padding-bottom: 1.5rem;
  margin-bottom: 1.5rem;
}

.print-logo-img {
  height: 64px;
}

.print-meta-info h2 {
  font-size: 1.4rem;
  font-weight: 800;
  margin-bottom: 0.5rem;
}

.meta-print-table td {
  padding: 0.15rem 0.5rem;
  font-size: 0.875rem;
}

.activity-print-section {
  margin-bottom: 3rem;
  page-break-inside: avoid;
}

.activity-print-header {
  background: hsl(var(--secondary) / 0.5);
  padding: 0.75rem 1rem;
  border-radius: 4px;
  display: flex;
  justify-content: space-between;
  font-weight: 600;
  font-size: 0.9rem;
}

.activity-print-notes {
  background: hsl(38 92% 50% / 0.08);
  border: 1px solid hsl(var(--warning) / 0.2);
  padding: 0.875rem;
  border-radius: 4px;
  font-size: 0.85rem;
  margin: 1rem 0;
}

.activity-print-photos {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1.5rem;
  margin: 1.5rem 0;
}

.photo-print-column h4 {
  font-size: 0.875rem;
  margin-bottom: 0.5rem;
  color: hsl(var(--muted-foreground));
}

.photo-print-wrapper {
  background: hsl(var(--muted));
  border: 1px dashed hsl(var(--border));
  aspect-ratio: 16/9;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  overflow: hidden;
  padding: 0.5rem;
}

.photo-print-el {
  height: 100%;
  width: auto;
  object-fit: cover;
  border-radius: 4px;
}

.no-photo-text {
  font-size: 0.75rem;
  color: hsl(var(--muted-foreground));
}

.print-signatures {
  display: flex;
  justify-content: space-between;
  margin-top: 2rem;
  padding: 0 2rem;
}

.signature-box {
  text-align: center;
  width: 200px;
  font-size: 0.85rem;
}

.signature-line {
  margin-top: 4rem;
  border-top: 1px solid hsl(var(--foreground));
  margin-bottom: 0.5rem;
}

/* Print styles override */
@media print {
  body * {
    visibility: hidden;
  }
  .modal-overlay,
  #daily-print-area,
  #daily-print-area * {
    visibility: visible;
  }
  .modal-overlay {
    position: absolute;
    left: 0;
    top: 0;
    width: 100%;
    height: auto;
    background: transparent;
    padding: 0;
  }
  .modal-content {
    border: none;
    height: auto;
    max-height: none;
    overflow: visible;
  }
  .modal-header,
  .modal-footer {
    display: none !important;
  }
  .modal-body {
    overflow: visible !important;
    padding: 0;
  }
  .activity-print-section {
    page-break-after: always;
  }
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

@keyframes pulse-dot {
  0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
  70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); }
  100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
}
</style>
