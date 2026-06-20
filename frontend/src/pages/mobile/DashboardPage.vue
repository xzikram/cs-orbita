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

const activeProgresses = ref<Array<{
  id?: number
  uuid?: string
  area_name: string
  percentage: number
  checked_count: number
  total_count: number
  is_offline?: boolean
}>>([])



async function fetchDashboardData() {
  loading.value = true
  try {
    const { data } = await api.get('/api/v1/dashboard/mobile')
    todayTotal.value = data.data.today_total
    todayCompleted.value = data.data.today_completed

    // Get online activities
    const onlineActs = data.data.activities || []

    // Get offline pending activities
    const offlineActs = await getPendingActivities()

    // Map offline activities
    const mappedOffline = offlineActs.map(p => {
      const total = p.items.length
      const checked = p.items.filter(i => i.is_checked).length
      return {
        uuid: p.uuid,
        area_name: p.area_name,
        percentage: total > 0 ? Math.round((checked / total) * 100) : 0,
        checked_count: checked,
        total_count: total,
        is_offline: true
      }
    })

    // Map online activities (excluding ones that are in the offline sync queue by UUID)
    const pendingUuids = new Set(offlineActs.map(p => p.uuid))
    const mappedOnline = onlineActs
      .filter((a: any) => !pendingUuids.has(a.uuid))
      .map((a: any) => ({
        id: a.id,
        uuid: a.uuid,
        area_name: a.area_name,
        percentage: a.percentage,
        checked_count: a.checked_count,
        total_count: a.total_count,
        is_offline: false
      }))

    // Combine them
    activeProgresses.value = [...mappedOffline, ...mappedOnline]
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
        <p class="text-xs text-muted-foreground uppercase tracking-wider mb-1">Dikerjakan</p>
        <h2 class="text-3xl font-bold text-success">{{ loading ? '-' : todayCompleted }}</h2>
      </div>
    </div>

    <!-- Progress Per Lantai / Area -->
    <div class="mb-6 animate-slide-up stagger-3">
      <h3 class="text-xs text-muted-foreground uppercase tracking-wider mb-3 font-semibold">Progress Area Pengerjaan</h3>
      <div v-if="activeProgresses.length > 0" class="flex flex-col gap-3">
        <div v-for="prog in activeProgresses" :key="prog.id || prog.uuid" class="card progress-card-item">
          <div class="flex justify-between items-center mb-2">
            <div class="flex items-center gap-2">
              <span class="area-badge">🏢</span>
              <span class="font-bold text-sm text-foreground">{{ prog.area_name }}</span>
              <span v-if="prog.is_offline" class="badge-offline">Offline</span>
            </div>
            <span class="text-xs font-bold text-primary">{{ prog.checked_count }}/{{ prog.total_count }} ({{ prog.percentage }}%)</span>
          </div>
          <div class="progress-bar-bg">
            <div class="progress-bar-fill" :style="{ width: `${prog.percentage}%` }"></div>
          </div>
        </div>
      </div>
      <div v-else class="card text-center py-6 text-muted-foreground">
        <p class="text-xs">Belum ada area yang mulai dikerjakan.</p>
        <p class="text-[10px] mt-1 text-muted-foreground/60">Scan QR Code area untuk memulai checklist.</p>
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

/* Install PWA Banner Styling */
.install-banner-card {
  background: hsl(var(--card) / 0.85);
  border: 1px solid hsl(var(--primary) / 0.3);
  backdrop-filter: blur(12px);
  position: relative;
  padding: 1rem;
  border-radius: 1rem;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
}

.install-banner-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
}

.app-icon-badge {
  font-size: 1.75rem;
  line-height: 1;
}

.close-banner-btn {
  background: transparent;
  border: none;
  color: hsl(var(--muted-foreground));
  font-size: 1rem;
  cursor: pointer;
  padding: 0.125rem 0.25rem;
  transition: color 0.2s;
  line-height: 1;
}

.close-banner-btn:hover {
  color: hsl(var(--foreground));
}

.install-instructions {
  border-top: 1px solid hsl(var(--border) / 0.5);
  padding-top: 0.75rem;
  font-size: 0.75rem;
}

.instruction-title {
  margin-bottom: 0.375rem;
  color: hsl(var(--foreground));
}

.instruction-list {
  padding-left: 1rem;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
  color: hsl(var(--muted-foreground));
}

.badge-icon {
  background: hsl(var(--muted));
  border: 1px solid hsl(var(--border));
  padding: 0.05rem 0.25rem;
  border-radius: 0.25rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 0.7rem;
  font-weight: bold;
}

/* Sync and Other Stats */
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
.flex-col { display: flex; flex-direction: column; }
.gap-2 { gap: 0.5rem; }
.w-full { width: 100%; }
.py-2 { padding-top: 0.5rem; padding-bottom: 0.5rem; }
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

.badge-offline {
  background: hsl(var(--warning) / 0.15);
  color: hsl(var(--warning));
  border: 1px solid hsl(var(--warning) / 0.3);
  padding: 0.1rem 0.35rem;
  border-radius: 0.25rem;
  font-size: 0.65rem;
  font-weight: 600;
  display: inline-block;
  line-height: 1;
}

.area-badge {
  font-size: 0.95rem;
  line-height: 1;
}

.progress-card-item {
  transition: transform 0.2s ease, box-shadow 0.2s ease;
  padding: 1rem;
}

.progress-card-item:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 24px rgba(0, 0, 0, 0.15);
}

@keyframes spin {
  to { transform: rotate(360deg); }
}
</style>
