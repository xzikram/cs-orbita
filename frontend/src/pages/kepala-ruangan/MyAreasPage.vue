<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '../../lib/axios'

const areas = ref<any[]>([])
const loading = ref(true)
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

async function fetchMyAreas() {
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
  fetchMyAreas()
})
</script>

<template>
  <div class="my-areas-page animate-fade-in">
    <div class="flex justify-between items-center mb-6">
      <div>
        <h1 class="text-2xl font-bold">Area Saya</h1>
        <p class="text-muted-foreground">Daftar area di bawah tanggung jawab Anda.</p>
      </div>
      <button class="btn btn-secondary" @click="fetchMyAreas">🔄 Refresh</button>
    </div>

    <div v-if="loading" class="flex justify-center p-12">
      <div class="spinner-large"></div>
    </div>

    <div v-else-if="areas.length === 0" class="card text-center p-12 text-muted-foreground">
      <div class="text-4xl mb-4">🏠</div>
      <p>Belum ada area yang ditugaskan kepada Anda.</p>
    </div>

    <div v-else class="areas-grid">
      <div v-for="area in areas" :key="area.id" class="card area-card animate-slide-up" @click="toggleArea(area.id)">
        <div class="area-header">
          <div>
            <h3 class="font-bold text-lg">{{ area.name }}</h3>
            <p class="text-sm text-muted-foreground">{{ area.floor?.name }} · {{ area.floor?.building?.name }}</p>
          </div>
          <span class="badge badge-primary">{{ area.code }}</span>
        </div>

        <div class="area-stats">
          <div class="mini-stat">
            <span class="mini-stat-value">{{ area.area_objects?.length || 0 }}</span>
            <span class="mini-stat-label">Item Ceklis</span>
          </div>
          <div class="mini-stat">
            <span class="mini-stat-value">{{ Object.keys(groupByRoom(area.area_objects)).length }}</span>
            <span class="mini-stat-label">Ruangan</span>
          </div>
        </div>

        <!-- Expanded Detail -->
        <div v-if="expandedArea === area.id" class="area-detail" @click.stop>
          <h4 class="font-bold mb-3 text-primary">Rincian Ruangan</h4>
          <div class="rooms-grid">
            <div v-for="(objects, roomName) in groupByRoom(area.area_objects)" :key="roomName" class="room-card">
              <div class="room-header">{{ roomName }}</div>
              <ul class="object-list">
                <li v-for="obj in objects" :key="obj.id">
                  🔹 {{ obj.cleaning_object?.name || 'Unknown' }}
                </li>
              </ul>
            </div>
          </div>
        </div>

        <div class="area-footer">
          <span class="text-xs text-muted-foreground">{{ expandedArea === area.id ? '▲ Tutup detail' : '▼ Lihat detail' }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.areas-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
  gap: 1.5rem;
}

.area-card { cursor: pointer; transition: transform 0.2s, box-shadow 0.2s; }
.area-card:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,0.3); }

.area-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1rem;
}

.area-stats {
  display: flex;
  gap: 1.5rem;
  padding: 0.75rem 0;
  border-top: 1px solid hsl(var(--border));
  border-bottom: 1px solid hsl(var(--border));
  margin-bottom: 0.75rem;
}

.mini-stat { display: flex; flex-direction: column; }
.mini-stat-value { font-size: 1.5rem; font-weight: 700; color: hsl(var(--primary)); }
.mini-stat-label { font-size: 0.75rem; color: hsl(var(--muted-foreground)); }

.area-detail {
  padding: 1rem 0;
  border-bottom: 1px solid hsl(var(--border));
  margin-bottom: 0.75rem;
}

.rooms-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 0.75rem;
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
  font-size: 0.8rem;
  border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.object-list {
  list-style: none;
  padding: 0.5rem 0.75rem;
  margin: 0;
  font-size: 0.75rem;
  color: hsl(var(--muted-foreground));
}
.object-list li { margin-bottom: 0.2rem; }

.area-footer { text-align: center; }

/* Utils */
.flex { display: flex; }
.justify-between { justify-content: space-between; }
.justify-center { justify-content: center; }
.items-center { align-items: center; }
.mb-3 { margin-bottom: 0.75rem; }
.mb-4 { margin-bottom: 1rem; }
.mb-6 { margin-bottom: 1.5rem; }
.p-12 { padding: 3rem; }
.text-2xl { font-size: 1.5rem; font-weight: 700; }
.text-lg { font-size: 1.125rem; }
.text-sm { font-size: 0.875rem; }
.text-xs { font-size: 0.75rem; }
.text-4xl { font-size: 2.25rem; }
.text-center { text-align: center; }
.font-bold { font-weight: 700; }
.text-primary { color: hsl(var(--primary)); }
.text-muted-foreground { color: hsl(var(--muted-foreground)); }
.badge-primary { background: hsl(var(--primary) / 0.2); color: hsl(var(--primary)); }

.spinner-large {
  width: 2rem; height: 2rem;
  border: 3px solid rgba(255,255,255,0.3);
  border-top-color: hsl(var(--primary));
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

@media (max-width: 768px) { .areas-grid { grid-template-columns: 1fr; } }
</style>
