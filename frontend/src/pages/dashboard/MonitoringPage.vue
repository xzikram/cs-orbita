<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import api from '../../lib/axios'

const activities = ref<any[]>([])
const loading = ref(true)
const searchQuery = ref('')
const currentPage = ref(1)
const totalPages = ref(1)
const perPage = ref(20)
const selectedDetail = ref<any>(null)

const filteredActivities = computed(() => {
  if (!searchQuery.value) return activities.value
  const q = searchQuery.value.toLowerCase()
  return activities.value.filter((a: any) =>
    (a.area?.name || '').toLowerCase().includes(q) ||
    (a.user?.name || '').toLowerCase().includes(q) ||
    (a.shift?.name || '').toLowerCase().includes(q) ||
    (a.status || '').toLowerCase().includes(q)
  )
})

async function loadData(page = 1) {
  loading.value = true
  try {
    const response = await api.get('/api/v1/activities', {
      params: { page, per_page: perPage.value }
    })
    const paginatedData = response.data.data
    activities.value = paginatedData.data || paginatedData
    currentPage.value = paginatedData.current_page || 1
    totalPages.value = paginatedData.last_page || 1
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

function showDetail(act: any) {
  selectedDetail.value = selectedDetail.value?.id === act.id ? null : act
}

function prevPage() {
  if (currentPage.value > 1) loadData(currentPage.value - 1)
}

function nextPage() {
  if (currentPage.value < totalPages.value) loadData(currentPage.value + 1)
}

onMounted(() => {
  loadData()
})
</script>

<template>
  <div class="monitoring-page animate-fade-in">
    <div class="flex justify-between items-center mb-6">
      <div>
        <h1 class="text-2xl font-bold">Live Monitoring</h1>
        <p class="text-muted-foreground">Log aktivitas pembersihan real-time</p>
      </div>
      <button class="btn btn-secondary" @click="loadData(currentPage)">
        <span>🔄</span> Refresh
      </button>
    </div>

    <!-- Search -->
    <div class="mb-4">
      <input type="text" v-model="searchQuery" class="input" placeholder="🔍 Cari area, petugas, shift..." />
    </div>

    <div class="card p-0 overflow-hidden animate-slide-up">
      <div v-if="loading" class="flex justify-center py-12">
        <div class="spinner-large"></div>
      </div>
      
      <div v-else class="table-responsive">
        <table class="audit-table">
          <thead>
            <tr>
              <th>Tanggal</th>
              <th>Area</th>
              <th>Shift</th>
              <th>Petugas</th>
              <th>Mulai</th>
              <th>Selesai</th>
              <th>Status</th>
              <th>Tindakan</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="act in filteredActivities" :key="act.id">
              <td>{{ act.date }}</td>
              <td class="font-medium">{{ act.area?.name }}</td>
              <td>{{ act.shift?.name }}</td>
              <td>{{ act.user?.name }}</td>
              <td>{{ act.start_time }}</td>
              <td>{{ act.end_time || '-' }}</td>
              <td>
                <span class="badge" :class="act.status === 'completed' ? 'badge-success' : 'badge-warning'">
                  {{ act.status === 'completed' ? 'Selesai' : 'Pending' }}
                </span>
                <span v-if="act.is_late" class="badge badge-destructive ml-2">Telat</span>
              </td>
              <td>
                <button class="btn btn-ghost text-xs" @click="showDetail(act)">
                  {{ selectedDetail?.id === act.id ? '▲ Tutup' : '▼ Detail' }}
                </button>
              </td>
            </tr>
            <!-- Detail Row -->
            <tr v-if="selectedDetail">
              <td colspan="8" class="p-0">
                <div class="detail-panel">
                  <h4 class="font-bold mb-2">Detail Aktivitas #{{ selectedDetail.id }}</h4>
                  <div class="detail-grid">
                    <div><strong>Area:</strong> {{ selectedDetail.area?.name }}</div>
                    <div><strong>Petugas:</strong> {{ selectedDetail.user?.name }}</div>
                    <div><strong>Durasi:</strong> {{ selectedDetail.start_time }} - {{ selectedDetail.end_time || 'Belum selesai' }}</div>
                    <div><strong>Catatan:</strong> {{ selectedDetail.notes || '-' }}</div>
                    <div><strong>Approval:</strong> 
                      <span class="badge" :class="{
                        'badge-success': selectedDetail.approval_status === 'approved',
                        'badge-warning': selectedDetail.approval_status === 'pending',
                        'badge-destructive': selectedDetail.approval_status === 'rejected'
                      }">{{ selectedDetail.approval_status || 'pending' }}</span>
                    </div>
                  </div>
                </div>
              </td>
            </tr>
            <tr v-if="filteredActivities.length === 0">
              <td colspan="8" class="text-center py-8 text-muted-foreground">Tidak ada data aktivitas.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="!loading && totalPages > 1" class="pagination">
        <button class="btn btn-ghost btn-sm" @click="prevPage" :disabled="currentPage <= 1">← Prev</button>
        <span class="text-sm text-muted-foreground">Halaman {{ currentPage }} dari {{ totalPages }}</span>
        <button class="btn btn-ghost btn-sm" @click="nextPage" :disabled="currentPage >= totalPages">Next →</button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.table-responsive { overflow-x: auto; }

.detail-panel {
  padding: 1.5rem;
  background: rgba(0, 0, 0, 0.2);
  border-top: 1px solid hsl(var(--border));
  border-bottom: 1px solid hsl(var(--border));
}

.detail-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.5rem;
  font-size: 0.875rem;
}

.pagination {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 1rem;
  padding: 1rem;
  border-top: 1px solid hsl(var(--border));
}

/* Utils */
.flex { display: flex; }
.justify-between { justify-content: space-between; }
.justify-center { justify-content: center; }
.items-center { align-items: center; }
.mb-2 { margin-bottom: 0.5rem; }
.mb-4 { margin-bottom: 1rem; }
.mb-6 { margin-bottom: 1.5rem; }
.ml-2 { margin-left: 0.5rem; }
.py-12 { padding-top: 3rem; padding-bottom: 3rem; }
.py-8 { padding-top: 2rem; padding-bottom: 2rem; }
.p-0 { padding: 0; }
.text-2xl { font-size: 1.5rem; line-height: 2rem; }
.text-sm { font-size: 0.875rem; }
.text-xs { font-size: 0.75rem; line-height: 1rem; }
.text-center { text-align: center; }
.font-bold { font-weight: 700; }
.font-medium { font-weight: 500; }
.text-muted-foreground { color: hsl(var(--muted-foreground)); }
.overflow-hidden { overflow: hidden; }

.btn-sm { padding: 0.25rem 0.75rem; font-size: 0.8rem; }

.spinner-large {
  width: 3rem; height: 3rem;
  border: 4px solid rgba(255,255,255,0.1);
  border-top-color: hsl(var(--primary));
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
</style>
