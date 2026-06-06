<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '../../lib/axios'

const data = ref({
  date: new Date().toISOString().split('T')[0],
  shifts: [] as any[],
  areas: [] as any[]
})
const loading = ref(true)
const selectedDate = ref(new Date().toISOString().split('T')[0])

async function loadData() {
  loading.value = true
  try {
    const response = await api.get('/api/v1/dashboard/audit-grid', {
      params: { date: selectedDate.value }
    })
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
  <div class="audit-grid-page animate-fade-in">
    <div class="flex justify-between items-center mb-6">
      <div>
        <h1 class="text-2xl font-bold">Audit Grid</h1>
        <p class="text-muted-foreground">Monitoring detail kebersihan per objek dan shift</p>
      </div>
      <div class="flex gap-4">
        <input 
          type="date" 
          v-model="selectedDate" 
          @change="loadData"
          class="input bg-card w-auto" 
        />
        <button class="btn btn-secondary" @click="loadData">
          <span>🔄</span> Refresh
        </button>
      </div>
    </div>

    <div v-if="loading" class="flex justify-center py-12">
      <div class="spinner-large"></div>
    </div>

    <div v-else class="card p-0 overflow-hidden animate-slide-up">
      <div class="table-responsive max-h-[70vh]">
        <table class="audit-table border-collapse">
          <thead>
            <tr>
              <th class="sticky left-0 bg-muted z-10 w-48">Area / Objek</th>
              <th v-for="shift in data.shifts" :key="shift.id" class="text-center w-32 border-l border-border">
                {{ shift.name }}
              </th>
            </tr>
          </thead>
          <tbody>
            <template v-for="area in data.areas" :key="area.area_id">
              <!-- Area Header Row -->
              <tr class="bg-muted/50 border-y-2 border-border">
                <td class="font-bold sticky left-0 bg-muted/90 z-10 backdrop-blur" colspan="4">
                  {{ area.area_name }}
                </td>
              </tr>
              <!-- Object Rows -->
              <tr v-for="(obj, idx) in area.objects" :key="`${area.area_id}-${idx}`">
                <td class="pl-8 sticky left-0 bg-card z-10">{{ obj.object_name }}</td>
                <td v-for="shift in data.shifts" :key="shift.id" class="text-center border-l border-border bg-card">
                  <span v-if="obj.shifts[shift.id] === true" class="check-icon">✓</span>
                  <span v-else-if="obj.shifts[shift.id] === false" class="cross-icon">✗</span>
                  <span v-else class="text-muted-foreground text-xs">-</span>
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
.audit-grid-page {
  display: flex;
  flex-direction: column;
  height: calc(100vh - 3rem);
}

.table-responsive {
  overflow: auto;
}

.audit-table th {
  top: 0;
}

.max-h-\[70vh\] {
  max-height: 70vh;
}

.border-collapse { border-collapse: collapse; }
.sticky { position: sticky; }
.left-0 { left: 0; }
.z-10 { z-index: 10; }
.w-48 { width: 12rem; }
.w-32 { width: 8rem; }
.w-auto { width: auto; }
.border-l { border-left-width: 1px; }
.border-y-2 { border-top-width: 2px; border-bottom-width: 2px; }
.bg-muted\/50 { background-color: hsl(var(--muted) / 0.5); }
.bg-muted\/90 { background-color: hsl(var(--muted) / 0.9); }
.backdrop-blur { backdrop-filter: blur(4px); }
.pl-8 { padding-left: 2rem; }
.bg-card { background-color: hsl(var(--card)); }

.check-icon {
  color: hsl(var(--success));
  font-weight: 800;
  font-size: 1.25rem;
}

.cross-icon {
  color: hsl(var(--destructive));
  font-weight: 800;
  font-size: 1.25rem;
}

/* Utils */
.flex { display: flex; }
.flex-col { flex-direction: column; }
.justify-between { justify-content: space-between; }
.justify-center { justify-content: center; }
.items-center { align-items: center; }
.gap-4 { gap: 1rem; }
.mb-6 { margin-bottom: 1.5rem; }
.py-12 { padding-top: 3rem; padding-bottom: 3rem; }
.p-0 { padding: 0; }
.text-2xl { font-size: 1.5rem; line-height: 2rem; }
.text-xs { font-size: 0.75rem; line-height: 1rem; }
.text-center { text-align: center; }
.font-bold { font-weight: 700; }
.text-muted-foreground { color: hsl(var(--muted-foreground)); }
.overflow-hidden { overflow: hidden; }
.border-border { border-color: hsl(var(--border)); }

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
