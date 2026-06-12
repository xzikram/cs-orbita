<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '../../lib/axios'

const activities = ref<any[]>([])
const loading = ref(true)

async function loadHistory() {
  loading.value = true
  try {
    const { data } = await api.get('/api/v1/activities/today')
    activities.value = data.data
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadHistory()
})
</script>

<template>
  <div class="history-page animate-fade-in">
    <h2 class="text-xl font-bold mb-4">Riwayat Hari Ini</h2>
    
    <div v-if="loading" class="text-center py-8">
      <div class="spinner-small"></div>
    </div>
    
    <div v-else-if="activities.length === 0" class="text-center py-8 text-muted-foreground">
      Belum ada aktivitas hari ini.
    </div>
    
    <div v-else class="timeline">
      <div v-for="(activity, index) in activities" :key="activity.id" class="timeline-item animate-slide-up" :style="{ animationDelay: `${index * 0.1}s` }">
        <div class="timeline-dot" :class="activity.status === 'completed' ? 'bg-success' : 'bg-warning'"></div>
        <div class="card p-3 w-full">
          <div class="flex justify-between items-start mb-1">
            <h3 class="font-semibold text-sm">{{ activity.area?.name }}</h3>
            <span class="text-xs text-muted-foreground">{{ activity.start_time }}</span>
          </div>
          <p class="text-xs text-muted-foreground mb-2">{{ activity.shift?.name }}</p>
          <div class="flex gap-2">
            <span class="badge" :class="activity.status === 'completed' ? 'badge-success' : 'badge-warning'">
              {{ activity.status === 'completed' ? 'Selesai' : 'Pending' }}
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.timeline {
  position: relative;
  padding-left: 1.5rem;
}

.timeline::before {
  content: '';
  position: absolute;
  top: 0;
  bottom: 0;
  left: 0.75rem;
  width: 2px;
  background: hsl(var(--border));
  transform: translateX(-50%);
}

.timeline-item {
  position: relative;
  margin-bottom: 1rem;
  display: flex;
  align-items: center;
}

.timeline-dot {
  position: absolute;
  left: -1.5rem;
  width: 0.75rem;
  height: 0.75rem;
  border-radius: 50%;
  transform: translateX(-50%);
  border: 2px solid hsl(var(--background));
}

.bg-success { background-color: hsl(var(--success)); }
.bg-warning { background-color: hsl(var(--warning)); }

.w-full { width: 100%; }
.p-3 { padding: 0.75rem; }
.flex { display: flex; }
.justify-between { justify-content: space-between; }
.items-start { align-items: flex-start; }
.gap-2 { gap: 0.5rem; }
.mb-1 { margin-bottom: 0.25rem; }
.mb-2 { margin-bottom: 0.5rem; }
.mb-4 { margin-bottom: 1rem; }
.text-xl { font-size: 1.25rem; line-height: 1.75rem; }
.text-sm { font-size: 0.875rem; line-height: 1.25rem; }
.text-xs { font-size: 0.75rem; line-height: 1rem; }
.font-bold { font-weight: 700; }
.font-semibold { font-weight: 600; }
.text-muted-foreground { color: hsl(var(--muted-foreground)); }
.py-8 { padding-top: 2rem; padding-bottom: 2rem; }
.text-center { text-align: center; }

.spinner-small {
  width: 1.5rem;
  height: 1.5rem;
  border: 2px solid rgba(255,255,255,0.3);
  border-top-color: hsl(var(--primary));
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
  display: inline-block;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}
</style>
