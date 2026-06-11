<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import api from '../../lib/axios'

const stats = ref<any>(null)
const loading = ref(true)
const currentTime = ref('')
const currentDate = ref('')
let interval: number
let clockInterval: number

function updateClock() {
  const now = new Date()
  currentTime.value = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' })
  currentDate.value = now.toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })
}

async function loadData() {
  try {
    const { data } = await api.get('/api/v1/dashboard/tv')
    stats.value = data.data
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  updateClock()
  clockInterval = window.setInterval(updateClock, 1000)
  loadData()
  interval = window.setInterval(loadData, 30000)
})

onUnmounted(() => {
  clearInterval(interval)
  clearInterval(clockInterval)
})
</script>

<template>
  <div class="tv-dashboard dark animate-fade-in">
    <!-- Header -->
    <header class="tv-header">
      <div class="logo">
        <img src="/Logo%20RS%20JEC%20ORBITA.png" alt="Logo RS JEC Orbita" class="tv-logo-img" />
        <div>
          <h1>CLEANTRACK <span class="text-accent">RS</span></h1>
        </div>
      </div>
      <div class="clock">
        <div class="time">{{ currentTime }}</div>
        <div class="date">{{ currentDate }}</div>
      </div>
    </header>

    <div v-if="loading" class="flex justify-center items-center h-full">
      <div class="spinner-xl"></div>
    </div>

    <main v-else class="tv-main">
      <!-- Big Stats -->
      <div class="tv-stats">
        <div class="card-stat animate-slide-up stagger-1">
          <h2 class="stat-label">Progress Hari Ini</h2>
          <div class="stat-value text-primary">{{ stats.completion_rate }}%</div>
          <div class="progress-bg mt-4">
            <div class="progress-fill" :style="{ width: `${stats.completion_rate}%` }"></div>
          </div>
          <p class="mt-4 text-xl text-muted-foreground">{{ stats.completed }} / {{ stats.total_areas }} Area Selesai</p>
        </div>

        <div class="card-stat border-warning animate-slide-up stagger-2">
          <h2 class="stat-label">Pending</h2>
          <div class="stat-value text-warning">{{ stats.pending }}</div>
          <p class="mt-4 text-xl text-warning/80">Area Menunggu Pembersihan</p>
        </div>

        <div class="card-stat border-destructive animate-slide-up stagger-3" :class="{'pulse-danger': stats.late > 0}">
          <h2 class="stat-label">Terlambat</h2>
          <div class="stat-value text-destructive">{{ stats.late }}</div>
          <p class="mt-4 text-xl text-destructive/80">Melewati Batas SLA</p>
        </div>
      </div>

      <!-- Lists -->
      <div class="tv-lists animate-slide-up stagger-4">
        <!-- Live Feed -->
        <div class="card p-0 overflow-hidden flex flex-col">
          <div class="bg-muted p-6 border-b border-border">
            <h2 class="text-2xl font-bold flex items-center gap-3">
              <span class="live-dot"></span> Live Feed Aktivitas
            </h2>
          </div>
          <div class="p-6 flex-1 overflow-hidden">
            <div class="activity-feed">
              <div v-for="(act, idx) in stats.recent_activities" :key="idx" class="feed-item animate-slide-up" :style="{ animationDelay: `${idx * 0.1}s` }">
                <div class="feed-time">{{ act.time }}</div>
                <div class="feed-content">
                  <div class="text-xl font-bold">{{ act.area }}</div>
                  <div class="text-muted-foreground">{{ act.user }}</div>
                </div>
                <div class="feed-status">
                  <span v-if="act.status === 'completed'" class="badge-tv badge-success">Selesai</span>
                  <span v-else class="badge-tv badge-warning">Proses</span>
                  <span v-if="act.is_late" class="badge-tv badge-destructive mt-2">Telat</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Attention Needed -->
        <div class="card p-0 overflow-hidden flex flex-col border-destructive/30">
          <div class="bg-destructive/10 p-6 border-b border-destructive/20 text-destructive">
            <h2 class="text-2xl font-bold flex items-center gap-3">
              ⚠️ Area Perlu Perhatian
            </h2>
          </div>
          <div class="p-6 flex-1 overflow-hidden">
            <div class="problem-list">
              <div v-for="area in stats.problem_areas" :key="area.id" class="problem-item">
                <span class="text-xl font-bold">{{ area.name }}</span>
                <span class="badge-tv bg-destructive/20 text-destructive">{{ area.category }}</span>
              </div>
              <div v-if="stats.problem_areas.length === 0" class="text-center text-xl text-success py-12">
                🎉 Semua area dalam kondisi baik
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>
    
    <footer class="tv-footer">
      Update terakhir: {{ stats?.last_updated }}
    </footer>
  </div>
</template>

<style scoped>
.tv-dashboard {
  height: 100vh;
  display: flex;
  flex-direction: column;
  background: hsl(222, 47%, 4%);
  color: hsl(210, 20%, 98%);
  overflow: hidden;
}

.tv-header {
  padding: 2rem 3rem;
  background: hsl(222, 47%, 6%);
  border-bottom: 1px solid hsl(222, 47%, 12%);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.logo {
  display: flex;
  align-items: center;
  gap: 1.5rem;
}

.tv-logo-img {
  height: 48px;
  width: auto;
  object-fit: contain;
}

.logo h1 {
  font-size: 2.5rem;
  font-weight: 800;
  letter-spacing: -0.02em;
  margin: 0;
  line-height: 1.1;
}

.text-accent { color: hsl(262, 83%, 65%); }

.logo p {
  font-size: 1.25rem;
  color: hsl(215, 20%, 65%);
  margin: 0;
}

.clock {
  text-align: right;
}

.time {
  font-size: 3.5rem;
  font-weight: 800;
  font-family: var(--font-mono);
  line-height: 1;
  background: linear-gradient(135deg, hsl(210, 100%, 56%), hsl(262, 83%, 58%));
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
}

.date {
  font-size: 1.25rem;
  color: hsl(215, 20%, 65%);
  margin-top: 0.5rem;
}

.tv-main {
  flex: 1;
  padding: 3rem;
  display: flex;
  flex-direction: column;
  gap: 3rem;
  overflow: hidden;
}

.tv-stats {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 3rem;
}

.tv-stats .card-stat {
  padding: 3rem;
  border-radius: 1.5rem;
  background: hsl(222, 47%, 8%);
  border-width: 2px;
}

.border-warning { border-color: hsl(var(--warning) / 0.3) !important; }
.border-destructive { border-color: hsl(var(--destructive) / 0.3) !important; }

.stat-label {
  font-size: 1.5rem;
  text-transform: uppercase;
  letter-spacing: 0.1em;
  color: hsl(var(--muted-foreground));
  margin-bottom: 1rem;
}

.stat-value {
  font-size: 6rem;
  font-weight: 800;
  line-height: 1;
  font-family: var(--font-mono);
}

.progress-bg {
  height: 1rem;
  background: hsl(222, 47%, 14%);
  border-radius: 9999px;
  overflow: hidden;
}

.progress-fill {
  height: 100%;
  background: linear-gradient(90deg, hsl(var(--primary)), hsl(var(--accent)));
  transition: width 1s ease;
}

.tv-lists {
  flex: 1;
  display: grid;
  grid-template-columns: 2fr 1fr;
  gap: 3rem;
  min-height: 0;
}

.live-dot {
  width: 1rem;
  height: 1rem;
  background: hsl(var(--destructive));
  border-radius: 50%;
  animation: pulse 2s infinite;
}

.activity-feed {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.feed-item {
  display: flex;
  align-items: center;
  gap: 2rem;
  padding: 1.5rem;
  background: hsl(222, 47%, 10%);
  border-radius: 1rem;
  border: 1px solid hsl(222, 47%, 16%);
}

.feed-time {
  font-size: 1.5rem;
  font-weight: 700;
  font-family: var(--font-mono);
  color: hsl(var(--primary));
  width: 100px;
}

.feed-content {
  flex: 1;
}

.feed-status {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
}

.badge-tv {
  padding: 0.5rem 1.5rem;
  border-radius: 9999px;
  font-size: 1.25rem;
  font-weight: 700;
  text-transform: uppercase;
}

.badge-success { background: hsl(var(--success) / 0.2); color: hsl(var(--success)); }
.badge-warning { background: hsl(var(--warning) / 0.2); color: hsl(var(--warning)); }
.badge-destructive { background: hsl(var(--destructive) / 0.2); color: hsl(var(--destructive)); }

.problem-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.problem-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.5rem;
  background: hsl(var(--destructive) / 0.05);
  border: 1px solid hsl(var(--destructive) / 0.2);
  border-radius: 1rem;
}

.tv-footer {
  text-align: center;
  padding: 1rem;
  color: hsl(var(--muted-foreground));
  font-family: var(--font-mono);
  background: hsl(222, 47%, 6%);
}

.pulse-danger {
  animation: pulse-danger-border 2s infinite;
}

@keyframes pulse {
  0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(220, 38, 38, 0.7); }
  70% { transform: scale(1); box-shadow: 0 0 0 10px rgba(220, 38, 38, 0); }
  100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(220, 38, 38, 0); }
}

@keyframes pulse-danger-border {
  0% { border-color: hsl(var(--destructive) / 0.3); }
  50% { border-color: hsl(var(--destructive) / 0.8); }
  100% { border-color: hsl(var(--destructive) / 0.3); }
}

/* Utils */
.flex { display: flex; }
.flex-col { flex-direction: column; }
.flex-1 { flex: 1; }
.items-center { align-items: center; }
.justify-between { justify-content: space-between; }
.justify-center { justify-content: center; }
.gap-3 { gap: 0.75rem; }
.mt-4 { margin-top: 1rem; }
.mt-2 { margin-top: 0.5rem; }
.p-0 { padding: 0; }
.p-6 { padding: 1.5rem; }
.py-12 { padding-top: 3rem; padding-bottom: 3rem; }
.text-center { text-align: center; }
.text-xl { font-size: 1.25rem; line-height: 1.75rem; }
.text-2xl { font-size: 1.5rem; line-height: 2rem; }
.font-bold { font-weight: 700; }
.text-primary { color: hsl(var(--primary)); }
.text-warning { color: hsl(var(--warning)); }
.text-destructive { color: hsl(var(--destructive)); }
.text-success { color: hsl(var(--success)); }
.text-muted-foreground { color: hsl(var(--muted-foreground)); }
.bg-muted { background-color: hsl(var(--muted)); }
.bg-destructive\/10 { background-color: hsl(var(--destructive) / 0.1); }
.bg-destructive\/20 { background-color: hsl(var(--destructive) / 0.2); }
.text-destructive\/80 { color: hsl(var(--destructive) / 0.8); }
.text-warning\/80 { color: hsl(var(--warning) / 0.8); }
.border-b { border-bottom-width: 1px; }
.border-border { border-color: hsl(var(--border)); }
.border-destructive\/20 { border-color: hsl(var(--destructive) / 0.2); }
.overflow-hidden { overflow: hidden; }

.spinner-xl {
  width: 5rem;
  height: 5rem;
  border: 6px solid rgba(255,255,255,0.1);
  border-top-color: hsl(var(--primary));
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}
</style>
