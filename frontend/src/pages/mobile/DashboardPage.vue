<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import api from '../../lib/axios'
import { getPendingCount, updateActivitySyncStatus, getPendingActivities } from '../../lib/db'
import { useOnline } from '../../composables/useOnline'

const router = useRouter()
const { isOnline } = useOnline()

const todayTotal = ref(0)
const todayCompleted = ref(0)
const pendingSyncCount = ref(0)
const loading = ref(true)
const syncing = ref(false)

const completionRate = computed(() => {
  if (todayTotal.value === 0) return 0
  return Math.round((todayCompleted.value / todayTotal.value) * 100)
})

async function fetchDashboardData() {
  loading.value = true
  try {
    const { data } = await api.get('/api/v1/dashboard/mobile')
    todayTotal.value = data.data.today_total
    todayCompleted.value = data.data.today_completed
  } catch (e) {
    console.error('Failed to load dashboard', e)
  } finally {
    loading.value = false
  }
}

async function checkOfflineQueue() {
  pendingSyncCount.value = await getPendingCount()
}

async function syncOfflineData() {
  if (!isOnline.value || pendingSyncCount.value === 0) return

  syncing.value = true
  try {
    const pending = await getPendingActivities()
    
    // Only send basic structure required by batch API
    const batch = pending.map(p => ({
      uuid: p.uuid,
      area_id: p.area_id,
      shift_id: p.shift_id,
      date: p.date,
      start_time: p.start_time,
      end_time: p.end_time,
      notes: p.notes,
      items: p.items,
    }))

    const { data } = await api.post('/api/v1/sync/batch', { activities: batch })
    
    // Process photos if there are any
    for (const activity of pending) {
      if (activity.photos && activity.photos.length > 0) {
        // Find if this activity synced successfully
        const result = data.data.find((r: any) => r.uuid === activity.uuid)
        if (result && result.status !== 'failed') {
          // Upload photos
          const formData = new FormData()
          activity.photos.forEach((photo, index) => {
            formData.append(`photos[${index}][file]`, photo.blob, `photo_${index}.jpg`)
            formData.append(`photos[${index}][type]`, photo.type)
          })
          
          await api.post(`/api/v1/activities/${result.id}/photos`, formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
          })
        }
      }
      
      // Update local DB status
      await updateActivitySyncStatus(activity.uuid, 'synced')
    }

    await checkOfflineQueue()
    await fetchDashboardData()
  } catch (e) {
    console.error('Sync failed', e)
  } finally {
    syncing.value = false
  }
}

function startScan() {
  router.push({ name: 'mobile-scan' })
}

onMounted(() => {
  fetchDashboardData()
  checkOfflineQueue()
})
</script>

<template>
  <div class="mobile-dashboard">
    <!-- Sync Card -->
    <div v-if="pendingSyncCount > 0" class="card sync-card mb-4 animate-slide-up">
      <div class="sync-info">
        <div class="sync-icon">🔄</div>
        <div>
          <h3 class="text-sm font-semibold">{{ pendingSyncCount }} Data Pending</h3>
          <p class="text-xs text-muted-foreground">Belum tersinkron ke server</p>
        </div>
      </div>
      <button 
        class="btn btn-primary text-xs py-1.5 px-3" 
        :disabled="!isOnline || syncing"
        @click="syncOfflineData"
      >
        <span v-if="syncing" class="spinner-small"></span>
        <span v-else>Sync Sekarang</span>
      </button>
    </div>

    <!-- Main Stats -->
    <div class="grid grid-cols-2 gap-4 mb-6">
      <div class="card-stat animate-slide-up stagger-1">
        <p class="text-xs text-muted-foreground uppercase tracking-wider mb-1">Total Tugas</p>
        <h2 class="text-3xl font-bold">{{ loading ? '-' : todayTotal }}</h2>
      </div>
      <div class="card-stat animate-slide-up stagger-2">
        <p class="text-xs text-muted-foreground uppercase tracking-wider mb-1">Selesai</p>
        <h2 class="text-3xl font-bold text-success">{{ loading ? '-' : todayCompleted }}</h2>
      </div>
    </div>

    <!-- Progress -->
    <div class="card mb-6 animate-slide-up stagger-3">
      <div class="flex justify-between items-center mb-2">
        <h3 class="text-sm font-medium">Progress Hari Ini</h3>
        <span class="text-sm font-bold text-primary">{{ completionRate }}%</span>
      </div>
      <div class="progress-bar-bg">
        <div class="progress-bar-fill" :style="{ width: `${completionRate}%` }"></div>
      </div>
    </div>

    <!-- Big Action Button -->
    <div class="scan-action animate-slide-up stagger-4">
      <button class="btn-scan" @click="startScan">
        <div class="scan-icon-large">📷</div>
        <span>Scan QR Area</span>
      </button>
      <p class="text-xs text-center text-muted-foreground mt-3">
        Mulai pembersihan dengan scan QR Code di area
      </p>
    </div>
  </div>
</template>

<style scoped>
.mobile-dashboard {
  padding-top: 0.5rem;
}

.sync-card {
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-color: hsl(var(--warning) / 0.3);
  background: hsl(var(--warning) / 0.05);
  padding: 1rem;
}

.sync-info {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.sync-icon {
  font-size: 1.5rem;
}

.grid {
  display: grid;
}

.grid-cols-2 {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.gap-4 {
  gap: 1rem;
}

.mb-4 {
  margin-bottom: 1rem;
}

.mb-6 {
  margin-bottom: 1.5rem;
}

.text-xs { font-size: 0.75rem; line-height: 1rem; }
.text-sm { font-size: 0.875rem; line-height: 1.25rem; }
.text-3xl { font-size: 1.875rem; line-height: 2.25rem; }
.font-medium { font-weight: 500; }
.font-semibold { font-weight: 600; }
.font-bold { font-weight: 700; }
.uppercase { text-transform: uppercase; }
.tracking-wider { letter-spacing: 0.05em; }

.flex { display: flex; }
.justify-between { justify-content: space-between; }
.items-center { align-items: center; }
.text-center { text-align: center; }
.mt-3 { margin-top: 0.75rem; }
.text-primary { color: hsl(var(--primary)); }
.text-success { color: hsl(var(--success)); }
.text-muted-foreground { color: hsl(var(--muted-foreground)); }

.progress-bar-bg {
  width: 100%;
  height: 0.5rem;
  background: hsl(var(--muted));
  border-radius: 9999px;
  overflow: hidden;
}

.progress-bar-fill {
  height: 100%;
  background: linear-gradient(90deg, hsl(var(--primary)), hsl(var(--accent)));
  border-radius: 9999px;
  transition: width 0.5s ease-out;
}

.scan-action {
  margin-top: 2rem;
}

.scan-icon-large {
  font-size: 2.5rem;
  margin-bottom: 0.5rem;
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
</style>
