<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import api from '../../lib/axios'

const areas = ref<any[]>([])
const loading = ref(true)
const generating = ref(false)
const expandedArea = ref<number | null>(null)
const searchQuery = ref('')
const filterBuilding = ref('')
const filterCategory = ref('')

const buildings = computed(() => {
  const set = new Set(areas.value.map((a: any) => a.floor?.building?.name).filter(Boolean))
  return Array.from(set).sort()
})

const categories = computed(() => {
  const set = new Set(areas.value.map((a: any) => a.category).filter(Boolean))
  return Array.from(set).sort()
})

const filteredAreas = computed(() => {
  let list = areas.value
  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase()
    list = list.filter((a: any) =>
      a.name.toLowerCase().includes(q) ||
      a.code.toLowerCase().includes(q) ||
      (a.floor?.name || '').toLowerCase().includes(q)
    )
  }
  if (filterBuilding.value) {
    list = list.filter((a: any) => a.floor?.building?.name === filterBuilding.value)
  }
  if (filterCategory.value) {
    list = list.filter((a: any) => a.category === filterCategory.value)
  }
  return list
})

const stats = computed(() => {
  const all = areas.value
  const totalObjects = all.reduce((sum: number, a: any) => sum + (a.area_objects?.length || 0), 0)
  const withQr = all.filter((a: any) => a.qr_code).length
  return {
    total: all.length,
    totalObjects,
    withQr,
    withoutQr: all.length - withQr,
  }
})

function toggleArea(id: number) {
  expandedArea.value = expandedArea.value === id ? null : id
}

function groupByRoom(objects: any[]) {
  if (!objects) return {}
  return objects.reduce((acc: any, obj: any) => {
    const room = obj.room_name || 'Umum'
    if (!acc[room]) acc[room] = []
    acc[room].push(obj)
    return acc
  }, {})
}

async function generateAllQr() {
  if (!confirm('Generate QR Code untuk semua area yang belum memiliki?')) return
  generating.value = true
  try {
    await api.post('/api/v1/qr-codes/generate-all')
    await fetchAreas()
  } catch (e) {
    console.error(e)
    alert('Gagal menggenerate QR Codes')
  } finally {
    generating.value = false
  }
}

async function printQr(area: any) {
  if (!area.qr_code) {
    alert('QR Code belum dibuat untuk area ini.')
    return
  }

  const printWin = window.open('', '_blank')
  if (!printWin) return

  printWin.document.write(`
    <html>
      <head>
        <title>Print QR - ${area.name}</title>
        <style>
          @page { size: A5 portrait; margin: 0; }
          body {
            font-family: sans-serif; text-align: center; padding: 0; margin: 0;
            background: #fff; color: #000; display: flex; align-items: center;
            justify-content: center; min-height: 100vh; box-sizing: border-box;
          }
          .card {
            border: 2px solid #000; padding: 2.5rem 2rem; width: 118mm; height: 180mm;
            border-radius: 1rem; display: flex; flex-direction: column;
            align-items: center; justify-content: space-between; box-sizing: border-box;
          }
          .print-logo { height: 38px; width: auto; object-fit: contain; margin-bottom: 0.5rem; }
          .header-info { text-align: center; width: 100%; }
          .title { font-size: 22px; font-weight: bold; margin-top: 0.5rem; margin-bottom: 0.25rem; }
          .code { font-size: 14px; color: #666; text-transform: uppercase; letter-spacing: 0.05em; }
          #qr-container { display: flex; justify-content: center; align-items: center; margin: auto 0; }
          #qr-container img { width: 75mm; height: 75mm; object-fit: contain; }
          .footer { margin-top: 1rem; font-size: 11px; font-weight: bold; color: #888; letter-spacing: 0.05em; }
        </style>
        <script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"><\/script>
      </head>
      <body>
        <div class="card">
          <img src="/Logo%20RS%20JEC%20ORBITA.png" alt="Logo RS JEC Orbita" class="print-logo" />
          <div class="header-info">
            <div class="title">${area.name}</div>
            <div class="code">${area.code}</div>
          </div>
          <div id="qr-container"></div>
          <div class="footer">CLEANTRACK RS</div>
        </div>
        <script>
          const qr = qrcode(0, 'M');
          qr.addData(JSON.stringify({ type: 'cleantrack', uuid: '${area.qr_code.uuid}', area_code: '${area.code}' }));
          qr.make();
          document.getElementById('qr-container').innerHTML = qr.createImgTag(8, 0);
          setTimeout(() => window.print(), 500);
        <\/script>
      </body>
    </html>
  `)
  printWin.document.close()
}

async function fetchAreas() {
  loading.value = true
  try {
    const { data } = await api.get('/api/v1/areas')
    areas.value = data.data
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchAreas()
})
</script>

<template>
  <div class="areas-page animate-fade-in">
    <!-- Header -->
    <div class="page-header">
      <div>
        <h1 class="page-title"><span class="title-icon">🏢</span> Kelola Area</h1>
        <p class="page-subtitle">Daftar area pembersihan yang terdaftar di sistem</p>
      </div>
      <div class="header-actions">
        <button class="btn btn-secondary" @click="fetchAreas">🔄 Refresh</button>
        <button class="btn btn-primary" @click="generateAllQr" :disabled="generating">
          {{ generating ? '⏳ Generating...' : '📱 Generate Missing QR' }}
        </button>
      </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
      <div class="stat-card animate-slide-up stagger-1">
        <div class="stat-icon stat-icon-total">🏥</div>
        <div class="stat-info">
          <span class="stat-value">{{ stats.total }}</span>
          <span class="stat-label">Total Area</span>
        </div>
      </div>
      <div class="stat-card animate-slide-up stagger-2">
        <div class="stat-icon stat-icon-objects">📦</div>
        <div class="stat-info">
          <span class="stat-value">{{ stats.totalObjects }}</span>
          <span class="stat-label">Total Objek</span>
        </div>
      </div>
      <div class="stat-card animate-slide-up stagger-3">
        <div class="stat-icon stat-icon-qr">✅</div>
        <div class="stat-info">
          <span class="stat-value">{{ stats.withQr }}</span>
          <span class="stat-label">QR Aktif</span>
        </div>
      </div>
      <div class="stat-card animate-slide-up stagger-4">
        <div class="stat-icon stat-icon-noqr">⚠️</div>
        <div class="stat-info">
          <span class="stat-value">{{ stats.withoutQr }}</span>
          <span class="stat-label">Tanpa QR</span>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="filters-bar animate-slide-up">
      <div class="search-wrapper">
        <span class="search-icon">🔍</span>
        <input type="text" v-model="searchQuery" class="input search-input" placeholder="Cari area, kode, atau lantai..." />
      </div>
      <select v-model="filterBuilding" class="input filter-select">
        <option value="">Semua Gedung</option>
        <option v-for="b in buildings" :key="b" :value="b">{{ b }}</option>
      </select>
      <select v-model="filterCategory" class="input filter-select">
        <option value="">Semua Kategori</option>
        <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
      </select>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading-state">
      <div class="spinner-large"></div>
      <p>Memuat data area...</p>
    </div>

    <!-- Table -->
    <div v-else class="card table-card animate-slide-up">
      <div v-if="filteredAreas.length === 0" class="empty-state">
        <div class="empty-icon">🏢</div>
        <h3>Tidak ada area ditemukan</h3>
        <p>Coba ubah filter atau tambahkan area baru.</p>
      </div>

      <div v-else class="table-responsive">
        <table class="audit-table areas-table">
          <thead>
            <tr>
              <th>Kode</th>
              <th>Nama Area</th>
              <th>Kategori</th>
              <th>Lokasi</th>
              <th>Objek</th>
              <th>QR</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            <template v-for="area in filteredAreas" :key="area.id">
              <tr class="area-row" :class="{ expanded: expandedArea === area.id }" @click="toggleArea(area.id)">
                <td>
                  <span class="area-code-badge">{{ area.code }}</span>
                </td>
                <td>
                  <span class="area-name-cell">{{ area.name }}</span>
                </td>
                <td>
                  <span class="badge badge-category">{{ area.category }}</span>
                </td>
                <td>
                  <div class="location-cell">
                    <span class="building-name">{{ area.floor?.building?.name || '-' }}</span>
                    <span class="floor-name">{{ area.floor?.name || '-' }}</span>
                  </div>
                </td>
                <td>
                  <span class="object-count">{{ area.area_objects?.length || 0 }}</span>
                </td>
                <td>
                  <span v-if="area.qr_code" class="qr-badge qr-active">✅ Aktif</span>
                  <span v-else class="qr-badge qr-missing">⚠️ Belum</span>
                </td>
                <td @click.stop>
                  <div class="action-btns">
                    <button class="action-btn" @click="toggleArea(area.id)" :title="expandedArea === area.id ? 'Tutup' : 'Detail'">
                      {{ expandedArea === area.id ? '▲' : '▼' }}
                    </button>
                    <button v-if="area.qr_code" class="action-btn action-print" @click="printQr(area)" title="Cetak QR">🖨️</button>
                  </div>
                </td>
              </tr>
              <!-- Expanded Detail -->
              <tr v-if="expandedArea === area.id" class="detail-row">
                <td colspan="7">
                  <div class="detail-panel">
                    <div class="detail-header">
                      <h4>📋 Rincian Area & Ruangan</h4>
                      <span class="detail-count">{{ area.area_objects?.length || 0 }} objek pembersihan</span>
                    </div>
                    <div v-if="area.area_objects && area.area_objects.length > 0" class="rooms-grid">
                      <div v-for="(objects, roomName) in groupByRoom(area.area_objects)" :key="roomName" class="room-card">
                        <div class="room-header">
                          <span class="room-name">🏠 {{ roomName || 'Umum' }}</span>
                          <span class="room-count">{{ objects.length }} item</span>
                        </div>
                        <ul class="object-list">
                          <li v-for="obj in objects" :key="obj.id">
                            <span class="obj-icon">{{ obj.cleaning_object?.icon || '🔹' }}</span>
                            <span class="obj-name">{{ obj.cleaning_object?.name || 'Unknown' }}</span>
                            <span v-if="obj.is_required" class="obj-required">Wajib</span>
                          </li>
                        </ul>
                      </div>
                    </div>
                    <div v-else class="no-objects">
                      <p>Belum ada objek pembersihan untuk area ini.</p>
                    </div>
                  </div>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<style scoped>
.areas-page {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

/* Header */
.page-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; }
.page-title { font-size: 1.5rem; font-weight: 700; color: hsl(var(--foreground)); display: flex; align-items: center; gap: 0.5rem; margin: 0; }
.title-icon { font-size: 1.75rem; }
.page-subtitle { font-size: 0.875rem; color: hsl(var(--muted-foreground)); margin-top: 0.25rem; }
.header-actions { display: flex; gap: 0.5rem; }

/* Stats */
.stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; }
@media (max-width: 768px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }

.stat-card {
  background: hsl(var(--card)); border: 1px solid hsl(var(--border)); border-radius: var(--radius);
  padding: 1.25rem; display: flex; align-items: center; gap: 1rem;
  transition: all 0.3s; position: relative; overflow: hidden;
}
.stat-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; opacity: 0; transition: opacity 0.3s; }
.stat-card:hover::before { opacity: 1; }
.stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 16px hsl(var(--primary) / 0.08); }
.stat-card:nth-child(1)::before { background: hsl(var(--primary)); }
.stat-card:nth-child(2)::before { background: hsl(var(--accent)); }
.stat-card:nth-child(3)::before { background: hsl(var(--success)); }
.stat-card:nth-child(4)::before { background: hsl(var(--warning)); }

.stat-icon { font-size: 1.25rem; width: 3rem; height: 3rem; display: flex; align-items: center; justify-content: center; border-radius: 0.75rem; flex-shrink: 0; }
.stat-icon-total { background: hsl(var(--primary) / 0.12); }
.stat-icon-objects { background: hsl(var(--accent) / 0.12); }
.stat-icon-qr { background: hsl(var(--success) / 0.12); }
.stat-icon-noqr { background: hsl(var(--warning) / 0.12); }
.stat-info { display: flex; flex-direction: column; }
.stat-value { font-size: 1.75rem; font-weight: 800; line-height: 1; color: hsl(var(--foreground)); }
.stat-label { font-size: 0.75rem; color: hsl(var(--muted-foreground)); margin-top: 0.25rem; font-weight: 500; }

/* Filters */
.filters-bar { display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap; }
.search-wrapper { position: relative; flex: 1; min-width: 200px; }
.search-icon { position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); font-size: 0.875rem; pointer-events: none; }
.search-input { padding-left: 2.25rem !important; }
.filter-select { width: auto; min-width: 150px; }

/* Table */
.table-card { padding: 0; overflow: hidden; }
.table-responsive { overflow-x: auto; }

.area-row { cursor: pointer; transition: background 0.2s; }
.area-row:hover { background: hsl(var(--muted) / 0.3) !important; }
.area-row.expanded td { background: hsl(var(--primary) / 0.04); border-bottom-color: transparent; }

.area-code-badge {
  font-family: var(--font-mono, monospace);
  font-size: 0.8125rem;
  font-weight: 700;
  color: hsl(var(--primary));
  background: hsl(var(--primary) / 0.1);
  padding: 0.25rem 0.5rem;
  border-radius: 0.25rem;
}

.area-name-cell { font-weight: 600; font-size: 0.875rem; }

.badge-category {
  background: hsl(var(--accent) / 0.12);
  color: hsl(var(--accent));
  font-size: 0.6875rem;
}

.location-cell { display: flex; flex-direction: column; gap: 0.0625rem; }
.building-name { font-size: 0.8125rem; font-weight: 500; }
.floor-name { font-size: 0.6875rem; color: hsl(var(--muted-foreground)); }

.object-count {
  font-weight: 700;
  font-size: 0.875rem;
  color: hsl(var(--foreground));
  background: hsl(var(--muted));
  padding: 0.25rem 0.625rem;
  border-radius: 0.375rem;
  display: inline-block;
}

.qr-badge { font-size: 0.75rem; font-weight: 500; padding: 0.25rem 0.5rem; border-radius: 0.375rem; }
.qr-active { background: hsl(var(--success) / 0.12); color: hsl(var(--success)); }
.qr-missing { background: hsl(var(--warning) / 0.12); color: hsl(var(--warning)); }

/* Action Buttons */
.action-btns { display: flex; gap: 0.25rem; }
.action-btn {
  width: 2rem; height: 2rem; display: flex; align-items: center; justify-content: center;
  border: 1px solid hsl(var(--border)); background: hsl(var(--card));
  border-radius: 0.375rem; cursor: pointer; font-size: 0.75rem; transition: all 0.2s;
  color: hsl(var(--muted-foreground));
}
.action-btn:hover { background: hsl(var(--muted)); color: hsl(var(--foreground)); }
.action-print:hover { background: hsl(var(--primary) / 0.1); border-color: hsl(var(--primary) / 0.3); }

/* Detail Panel */
.detail-row td { padding: 0 !important; background: hsl(var(--card)) !important; }

.detail-panel {
  border-top: 2px solid hsl(var(--primary) / 0.2);
  border-bottom: 2px solid hsl(var(--primary) / 0.2);
  background: hsl(var(--muted) / 0.2);
  padding: 1.25rem 1.5rem;
  animation: slideDown 0.3s ease-out;
}

@keyframes slideDown {
  from { opacity: 0; max-height: 0; }
  to { opacity: 1; max-height: 600px; }
}

.detail-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}

.detail-header h4 { font-size: 0.9375rem; font-weight: 700; color: hsl(var(--foreground)); margin: 0; }
.detail-count { font-size: 0.75rem; color: hsl(var(--muted-foreground)); background: hsl(var(--muted)); padding: 0.25rem 0.625rem; border-radius: 0.25rem; }

.rooms-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 0.75rem;
}

.room-card {
  background: hsl(var(--card));
  border: 1px solid hsl(var(--border));
  border-radius: 0.5rem;
  overflow: hidden;
}

.room-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.625rem 0.75rem;
  background: hsl(var(--muted) / 0.5);
  border-bottom: 1px solid hsl(var(--border));
}

.room-name { font-weight: 600; font-size: 0.8125rem; }
.room-count { font-size: 0.6875rem; color: hsl(var(--muted-foreground)); background: hsl(var(--muted)); padding: 0.125rem 0.375rem; border-radius: 0.25rem; }

.object-list { list-style: none; padding: 0.5rem 0.75rem; margin: 0; }
.object-list li {
  display: flex;
  align-items: center;
  gap: 0.375rem;
  padding: 0.25rem 0;
  font-size: 0.8125rem;
  color: hsl(var(--foreground) / 0.85);
}

.obj-icon { font-size: 0.875rem; flex-shrink: 0; }
.obj-name { flex: 1; }
.obj-required {
  font-size: 0.5625rem;
  font-weight: 600;
  text-transform: uppercase;
  color: hsl(var(--destructive));
  background: hsl(var(--destructive) / 0.1);
  padding: 0.125rem 0.25rem;
  border-radius: 0.125rem;
  letter-spacing: 0.04em;
}

.no-objects { text-align: center; padding: 1rem; color: hsl(var(--muted-foreground)); font-size: 0.875rem; }

/* Loading/Empty */
.loading-state { display: flex; flex-direction: column; align-items: center; padding: 4rem 2rem; gap: 1rem; color: hsl(var(--muted-foreground)); }
.empty-state { display: flex; flex-direction: column; align-items: center; padding: 4rem 2rem; text-align: center; }
.empty-icon { font-size: 3rem; margin-bottom: 1rem; }
.empty-state h3 { font-size: 1.125rem; font-weight: 600; color: hsl(var(--foreground)); margin: 0 0 0.5rem; }
.empty-state p { font-size: 0.875rem; color: hsl(var(--muted-foreground)); margin: 0; }

.spinner-large { width: 3rem; height: 3rem; border: 4px solid rgba(255,255,255,0.1); border-top-color: hsl(var(--primary)); border-radius: 50%; animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>
