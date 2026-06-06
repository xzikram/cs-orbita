<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '../../lib/axios'

const activities = ref<any[]>([])
const loading = ref(true)
const processing = ref<number | null>(null)
const apiBaseUrl = import.meta.env.VITE_API_URL || 'http://localhost:8000'

async function fetchPendingActivities() {
  loading.value = true
  try {
    // We fetch activities that are completed but approval_status is pending
    const { data } = await api.get('/api/v1/activities', {
      params: { status: 'completed' } // We will filter locally for now to keep it simple, or we can add approval_status filter to backend
    })
    
    activities.value = data.data.filter((a: any) => a.approval_status === 'pending')
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

async function approve(activityId: number, status: 'approved' | 'rejected') {
  if (status === 'rejected' && !confirm('Apakah Anda yakin ingin MENOLAK laporan ini?')) return
  processing.value = activityId
  try {
    await api.put(`/api/v1/activities/${activityId}/approve`, { status })
    // Remove from list
    activities.value = activities.value.filter(a => a.id !== activityId)
  } catch (e) {
    console.error(e)
    alert('Gagal memproses persetujuan')
  } finally {
    processing.value = null
  }
}

onMounted(() => {
  fetchPendingActivities()
})
</script>

<template>
  <div class="approval-page animate-fade-in">
    <div class="flex justify-between items-center mb-6">
      <div>
        <h1 class="text-2xl font-bold">Persetujuan Laporan (Approval)</h1>
        <p class="text-muted-foreground">Tinjau dan setujui laporan kebersihan dari Cleaning Service.</p>
      </div>
      <button class="btn btn-secondary" @click="fetchPendingActivities">
        🔄 Refresh
      </button>
    </div>

    <div v-if="loading" class="flex justify-center p-12">
      <div class="spinner-large"></div>
    </div>
    
    <div v-else-if="activities.length === 0" class="card text-center p-12 text-muted-foreground">
      <div class="text-4xl mb-4">👍</div>
      <p>Tidak ada laporan yang menunggu persetujuan.</p>
    </div>

    <div v-else class="grid-cards">
      <div v-for="act in activities" :key="act.id" class="card animate-slide-up">
        <div class="flex justify-between items-start mb-4">
          <div>
            <h3 class="font-bold text-lg">{{ act.area?.name || 'Area Tidak Diketahui' }}</h3>
            <p class="text-sm text-muted-foreground">{{ new Date(act.date).toLocaleDateString('id-ID') }} | Shift {{ act.shift?.name }}</p>
          </div>
          <span class="badge badge-warning">Menunggu</span>
        </div>

        <div class="mb-4">
          <p class="text-sm"><strong>Oleh:</strong> {{ act.user?.name }}</p>
          <p class="text-sm"><strong>Waktu:</strong> {{ act.start_time }} - {{ act.end_time }}</p>
          <p class="text-sm" v-if="act.notes"><strong>Catatan:</strong> {{ act.notes }}</p>
        </div>

        <div v-if="act.photos && act.photos.length > 0" class="photo-grid mb-4">
          <div v-for="photo in act.photos" :key="photo.id" class="photo-item">
            <img :src="`${apiBaseUrl}/storage/${photo.file_path}`" alt="Bukti" />
            <span class="photo-badge">{{ photo.type === 'after' ? 'Sesudah' : 'Sebelum' }}</span>
          </div>
        </div>

        <div class="flex gap-2 mt-4 pt-4 border-t border-white/10">
          <button class="btn btn-primary flex-1" @click="approve(act.id, 'approved')" :disabled="processing === act.id">
            <span v-if="processing === act.id" class="spinner-small mr-2"></span>
            ✓ Setujui
          </button>
          <button class="btn btn-destructive flex-1" @click="approve(act.id, 'rejected')" :disabled="processing === act.id">
            ✕ Tolak
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.grid-cards {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
  gap: 1.5rem;
}

.photo-grid {
  display: flex;
  gap: 0.5rem;
  overflow-x: auto;
  padding-bottom: 0.5rem;
}

.photo-item {
  position: relative;
  width: 100px;
  height: 100px;
  flex-shrink: 0;
  border-radius: 0.5rem;
  overflow: hidden;
  border: 1px solid hsl(var(--border));
}

.photo-item img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.photo-badge {
  position: absolute;
  bottom: 0.25rem;
  right: 0.25rem;
  font-size: 0.6rem;
  background: rgba(0,0,0,0.7);
  padding: 2px 6px;
  border-radius: 4px;
}

.border-t { border-top-width: 1px; }
.border-white\/10 { border-color: rgba(255,255,255,0.1); }
.pt-4 { padding-top: 1rem; }
.flex-1 { flex: 1; }

.spinner-large {
  width: 2rem;
  height: 2rem;
  border: 3px solid rgba(255,255,255,0.3);
  border-top-color: hsl(var(--primary));
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
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

/* Utils */
.flex { display: flex; }
.justify-between { justify-content: space-between; }
.justify-center { justify-content: center; }
.items-center { align-items: center; }
.items-start { align-items: flex-start; }
.mb-4 { margin-bottom: 1rem; }
.mb-6 { margin-bottom: 1.5rem; }
.p-12 { padding: 3rem; }
.text-center { text-align: center; }
.text-4xl { font-size: 2.25rem; }
.text-2xl { font-size: 1.5rem; font-weight: 700; }
.text-lg { font-size: 1.125rem; font-weight: 700; }
.text-sm { font-size: 0.875rem; }
.font-bold { font-weight: 700; }
.text-muted-foreground { color: hsl(var(--muted-foreground)); }
.badge-warning { background: hsl(var(--warning)/0.2); color: hsl(var(--warning)); }
.gap-2 { gap: 0.5rem; }
.mt-4 { margin-top: 1rem; }
.mr-2 { margin-right: 0.5rem; }
</style>
