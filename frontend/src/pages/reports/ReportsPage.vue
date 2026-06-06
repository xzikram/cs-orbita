<script setup lang="ts">
import { ref } from 'vue'
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

async function fetchAreas() {
  try {
    const { data } = await api.get('/api/v1/areas')
    areas.value = data.data
    if (areas.value.length > 0) {
      areaId.value = areas.value[0].id
    }
  } catch (e) {
    console.error('Failed to load areas', e)
  }
}

import { onMounted } from 'vue'
onMounted(() => {
  fetchAreas()
})

async function downloadReport(type: 'monthly' | 'audit' | 'matrix-excel') {
  loading.value[type as keyof typeof loading.value] = true
  try {
    const params: any = { month: month.value, year: year.value }
    if (type === 'matrix-excel') {
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
  } catch (e) {
    console.error(e)
    alert('Gagal mengunduh laporan')
  } finally {
    loading.value[type as keyof typeof loading.value] = false
  }
}
</script>

<template>
  <div class="reports-page animate-fade-in">
    <div class="flex justify-between items-center mb-6">
      <div>
        <h1 class="text-2xl font-bold">Laporan & Ekspor</h1>
        <p class="text-muted-foreground">Unduh laporan aktivitas kebersihan dan hasil audit</p>
      </div>
    </div>

    <!-- Filter Card -->
    <div class="card mb-6 animate-slide-up border-primary">
      <h2 class="font-bold mb-4">Pilih Periode Laporan</h2>
      <div class="flex gap-4">
        <div class="form-group flex-1">
          <label class="label">Bulan</label>
          <select v-model="month" class="input">
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
          <select v-model="year" class="input">
            <option :value="new Date().getFullYear() - 1">{{ new Date().getFullYear() - 1 }}</option>
            <option :value="new Date().getFullYear()">{{ new Date().getFullYear() }}</option>
          </select>
        </div>
        <div class="form-group flex-1">
          <label class="label">Area (Khusus Matrix)</label>
          <select v-model="areaId" class="input">
            <option v-for="a in areas" :key="a.id" :value="a.id">{{ a.name }}</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Export Cards -->
    <div class="export-grid">
      <div class="card-stat animate-slide-up stagger-1">
        <div class="stat-header">
          <span class="stat-title">Laporan Kebersihan Bulanan</span>
          <span class="stat-icon text-primary">📊</span>
        </div>
        <p class="text-muted-foreground text-sm mb-4">
          Export data aktivitas pembersihan harian, status SLA, durasi pengerjaan, dan catatan kendala per area.
        </p>
        <button class="btn btn-primary w-full" @click="downloadReport('monthly')" :disabled="loading.monthly">
          <span v-if="loading.monthly" class="spinner-small mr-2"></span>
          {{ loading.monthly ? 'Memproses...' : 'Unduh CSV (Aktivitas)' }}
        </button>
      </div>

      <div class="card-stat animate-slide-up stagger-2">
        <div class="stat-header">
          <span class="stat-title">Laporan Audit & Inspeksi</span>
          <span class="stat-icon text-accent">📋</span>
        </div>
        <p class="text-muted-foreground text-sm mb-4">
          Export rekapitulasi skor audit, temuan inspeksi supervisor, dan kepatuhan standar kebersihan rumah sakit.
        </p>
        <button class="btn btn-primary w-full" @click="downloadReport('audit')" :disabled="loading.audit">
          <span v-if="loading.audit" class="spinner-small mr-2"></span>
          {{ loading.audit ? 'Memproses...' : 'Unduh CSV (Audit)' }}
        </button>
      </div>

      <div class="card-stat animate-slide-up stagger-3" style="grid-column: 1 / -1;">
        <div class="stat-header">
          <span class="stat-title">Laporan Matrix Ceklist Kebersihan</span>
          <span class="stat-icon text-accent">📝</span>
        </div>
        <p class="text-muted-foreground text-sm mb-4">
          Export laporan kebersihan dalam format Matrix / Tabel per ruangan seperti format standar RS JEC Orbita.
        </p>
        <button class="btn btn-primary w-full" @click="downloadReport('matrix-excel')" :disabled="loading['matrix-excel']">
          <span v-if="loading['matrix-excel']" class="spinner-small mr-2"></span>
          {{ loading['matrix-excel'] ? 'Memproses...' : 'Unduh Excel (.xls)' }}
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.export-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1.5rem;
}

.border-primary { border-color: hsl(var(--primary)); }
.w-full { width: 100%; }
.flex-1 { flex: 1; }
.mr-2 { margin-right: 0.5rem; }

/* Utils */
.flex { display: flex; }
.justify-between { justify-content: space-between; }
.items-center { align-items: center; }
.gap-4 { gap: 1rem; }
.mb-4 { margin-bottom: 1rem; }
.mb-6 { margin-bottom: 1.5rem; }
.text-2xl { font-size: 1.5rem; line-height: 2rem; }
.text-sm { font-size: 0.875rem; line-height: 1.25rem; }
.font-bold { font-weight: 700; }
.text-primary { color: hsl(var(--primary)); }
.text-accent { color: hsl(var(--accent)); }
.text-muted-foreground { color: hsl(var(--muted-foreground)); }

.stat-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.5rem;
}

.stat-title {
  font-size: 1rem;
  font-weight: 700;
}

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

@media (max-width: 768px) {
  .export-grid { grid-template-columns: 1fr; }
  .flex.gap-4 { flex-direction: column; }
}
</style>
