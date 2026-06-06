<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '../../lib/axios'

const areas = ref<any[]>([])
const loading = ref(true)
const generating = ref(false)
const expandedArea = ref<number | null>(null)

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
    await fetchAreas() // Reload areas to get new QR data
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
          body { font-family: sans-serif; text-align: center; padding: 2rem; }
          .card { border: 2px solid #000; padding: 2rem; display: inline-block; border-radius: 1rem; }
          .title { font-size: 24px; font-weight: bold; margin-bottom: 1rem; }
          .code { font-size: 16px; color: #666; margin-bottom: 2rem; }
        </style>
        <script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"><\/script>
      </head>
      <body>
        <div class="card">
          <div class="title">${area.name}</div>
          <div class="code">${area.code}</div>
          <div id="qr-container"></div>
          <p style="margin-top: 1rem; font-size: 12px;">CLEANTRACK RS</p>
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
    <div class="flex justify-between items-center mb-6">
      <div>
        <h1 class="text-2xl font-bold">Kelola Area</h1>
        <p class="text-muted-foreground">Daftar area pembersihan yang terdaftar di sistem.</p>
      </div>
      <div class="flex gap-3">
        <button class="btn btn-secondary" @click="fetchAreas">
          🔄 Refresh
        </button>
        <button class="btn btn-primary" @click="generateAllQr" :disabled="generating">
          {{ generating ? 'Generating...' : 'Generate Missing QR' }}
        </button>
      </div>
    </div>

    <div v-if="loading" class="flex justify-center p-12">
      <div class="spinner-large"></div>
    </div>
    
    <div v-else class="card p-0 overflow-hidden">
      <table class="data-table">
        <thead>
          <tr>
            <th>Kode</th>
            <th>Nama Area</th>
            <th>Kategori</th>
            <th>Lantai</th>
            <th>Gedung</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <template v-for="area in areas" :key="area.id">
            <tr class="cursor-pointer" @click="toggleArea(area.id)">
              <td class="font-bold text-primary">{{ area.code }}</td>
              <td>{{ area.name }}</td>
              <td>
                <span class="badge" style="background: hsl(var(--primary)/0.1); color: hsl(var(--primary));">
                  {{ area.category }}
                </span>
              </td>
              <td>{{ area.floor?.name || '-' }}</td>
              <td>
                <span>{{ area.floor?.building?.name || '-' }}</span>
              </td>
              <td @click="toggleArea(area.id)">
                <div class="flex gap-2 items-center">
                  <span class="text-xs mr-2">{{ expandedArea === area.id ? '▲ Detail' : '▼ Detail' }}</span>
                  <button v-if="area.qr_code" class="btn btn-ghost text-xs" @click.stop="printQr(area)">
                    🖨️ Cetak QR
                  </button>
                  <span v-else class="text-xs text-warning">Belum ada QR</span>
                </div>
              </td>
            </tr>
            <!-- Expanded Details -->
            <tr v-if="expandedArea === area.id" class="expanded-row">
              <td colspan="6" class="p-0">
                <div class="p-6 bg-black/20 border-b border-white/10">
                  <h3 class="font-bold text-lg mb-4 text-primary">Rincian Area & Ruangan</h3>
                  <div class="grid-rooms">
                    <div v-for="(objects, roomName) in groupByRoom(area.area_objects)" :key="roomName" class="room-card">
                      <div class="room-header">{{ roomName || 'Umum' }}</div>
                      <ul class="object-list">
                        <li v-for="obj in objects" :key="obj.id">
                          <span class="mr-2">{{ obj.cleaning_object?.icon || '🔹' }}</span>
                          {{ obj.cleaning_object?.name || 'Unknown' }}
                        </li>
                      </ul>
                    </div>
                  </div>
                </div>
              </td>
            </tr>
          </template>
          <tr v-if="areas.length === 0">
            <td colspan="5" class="text-center p-8 text-muted-foreground">Belum ada area yang terdaftar.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<style scoped>
.data-table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
}

.data-table th, .data-table td {
  padding: 1rem;
  border-bottom: 1px solid hsl(var(--border));
}

.data-table th {
  background: rgba(255, 255, 255, 0.02);
  font-weight: 600;
  font-size: 0.875rem;
  color: hsl(var(--muted-foreground));
}

.data-table tr:last-child td {
  border-bottom: none;
}

.data-table tbody tr:hover {
  background: rgba(255, 255, 255, 0.02);
}

.spinner-large {
  width: 2rem;
  height: 2rem;
  border: 3px solid rgba(255,255,255,0.3);
  border-top-color: hsl(var(--primary));
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* Utils */
.flex { display: flex; }
.justify-between { justify-content: space-between; }
.justify-center { justify-content: center; }
.items-center { align-items: center; }
.mb-6 { margin-bottom: 1.5rem; }
.p-0 { padding: 0; }
.p-8 { padding: 2rem; }
.p-12 { padding: 3rem; }
.overflow-hidden { overflow: hidden; }
.text-center { text-align: center; }
.text-2xl { font-size: 1.5rem; font-weight: 700; }
.font-bold { font-weight: 700; }
.text-muted-foreground { color: hsl(var(--muted-foreground)); }

.cursor-pointer { cursor: pointer; }
.bg-black\/20 { background-color: rgba(0, 0, 0, 0.2); }
.border-white\/10 { border-color: rgba(255, 255, 255, 0.1); }
.text-lg { font-size: 1.125rem; }
.mb-4 { margin-bottom: 1rem; }
.mr-2 { margin-right: 0.5rem; }

.grid-rooms {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 1rem;
}

.room-card {
  background: rgba(255, 255, 255, 0.05);
  border-radius: 0.5rem;
  overflow: hidden;
  border: 1px solid rgba(255, 255, 255, 0.1);
}

.room-header {
  padding: 0.5rem 0.75rem;
  background: rgba(255, 255, 255, 0.05);
  font-weight: 600;
  font-size: 0.875rem;
  border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.object-list {
  list-style: none;
  padding: 0.5rem 0.75rem;
  margin: 0;
  font-size: 0.8125rem;
  color: hsl(var(--muted-foreground));
}

.object-list li {
  margin-bottom: 0.25rem;
}

.object-list li:last-child {
  margin-bottom: 0;
}
</style>
