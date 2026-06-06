<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '../../lib/axios'

const data = ref({
  shifts: [] as any[],
  areas: [] as any[]
})
const loading = ref(true)

async function loadData() {
  try {
    const response = await api.get('/api/v1/dashboard/heatmap')
    data.value = response.data.data
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadData()
})
</script>

<template>
  <div class="heatmap-page animate-fade-in">
    <div class="flex justify-between items-center mb-6">
      <div>
        <h1 class="text-2xl font-bold">Heatmap Area</h1>
        <p class="text-muted-foreground">Peta status kebersihan seluruh rumah sakit hari ini</p>
      </div>
      <div class="legend flex gap-4">
        <div class="flex items-center gap-2"><div class="legend-box bg-heatmap-clean"></div> Selesai</div>
        <div class="flex items-center gap-2"><div class="legend-box bg-heatmap-pending"></div> Pending</div>
        <div class="flex items-center gap-2"><div class="legend-box bg-heatmap-late"></div> Telat</div>
        <div class="flex items-center gap-2"><div class="legend-box bg-heatmap-none"></div> Kosong</div>
      </div>
    </div>

    <div v-if="loading" class="flex justify-center py-12">
      <div class="spinner-large"></div>
    </div>

    <div v-else class="card p-0 overflow-hidden animate-slide-up">
      <div class="table-responsive">
        <table class="audit-table heatmap-table">
          <thead>
            <tr>
              <th>Area</th>
              <th>Lantai / Gedung</th>
              <th>Kategori</th>
              <th v-for="shift in data.shifts" :key="shift.id" class="text-center">
                {{ shift.name }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="area in data.areas" :key="area.id">
              <td class="font-medium">{{ area.name }} <span class="text-xs text-muted-foreground block">{{ area.code }}</span></td>
              <td>{{ area.floor }}<br><span class="text-xs text-muted-foreground">{{ area.building }}</span></td>
              <td><span class="badge badge-secondary">{{ area.category }}</span></td>
              <td v-for="shift in data.shifts" :key="shift.id" class="text-center">
                <div class="heatmap-cell mx-auto" :class="`heatmap-${area.statuses[shift.id]}`" :title="area.statuses[shift.id]">
                  <span v-if="area.statuses[shift.id] === 'clean'">✓</span>
                  <span v-else-if="area.statuses[shift.id] === 'late'">!</span>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<style scoped>
.legend-box {
  width: 1rem;
  height: 1rem;
  border-radius: 0.25rem;
}

.bg-heatmap-clean { background: hsl(var(--heatmap-clean) / 0.5); border: 1px solid hsl(var(--heatmap-clean)); }
.bg-heatmap-pending { background: hsl(var(--heatmap-pending) / 0.5); border: 1px solid hsl(var(--heatmap-pending)); }
.bg-heatmap-late { background: hsl(var(--heatmap-late) / 0.5); border: 1px solid hsl(var(--heatmap-late)); }
.bg-heatmap-none { background: hsl(var(--heatmap-none) / 0.5); border: 1px solid hsl(var(--heatmap-none)); }

.heatmap-table th, .heatmap-table td {
  padding: 0.75rem 1rem;
}

.badge-secondary {
  background: hsl(var(--secondary));
  color: hsl(var(--secondary-foreground));
}

/* Utils */
.flex { display: flex; }
.justify-between { justify-content: space-between; }
.justify-center { justify-content: center; }
.items-center { align-items: center; }
.gap-2 { gap: 0.5rem; }
.gap-4 { gap: 1rem; }
.mb-6 { margin-bottom: 1.5rem; }
.py-12 { padding-top: 3rem; padding-bottom: 3rem; }
.p-0 { padding: 0; }
.mx-auto { margin-left: auto; margin-right: auto; }
.text-2xl { font-size: 1.5rem; line-height: 2rem; }
.text-xs { font-size: 0.75rem; line-height: 1rem; }
.text-center { text-align: center; }
.font-bold { font-weight: 700; }
.font-medium { font-weight: 500; }
.text-muted-foreground { color: hsl(var(--muted-foreground)); }
.block { display: block; }
.overflow-hidden { overflow: hidden; }
.table-responsive { overflow-x: auto; }

.spinner-large {
  width: 3rem;
  height: 3rem;
  border: 4px solid rgba(255,255,255,0.1);
  border-top-color: hsl(var(--primary));
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}
</style>
