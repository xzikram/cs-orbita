<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '../../lib/axios'
import { useAuthStore } from '../../stores/auth'

const authStore = useAuthStore()
const complaints = ref<any[]>([])
const loading = ref(true)

// New Complaint Form
const showForm = ref(false)
const areas = ref<any[]>([])
const form = ref({
  area_id: '',
  title: '',
  category: 'kebersihan',
  description: '',
  priority: 'medium'
})

async function loadData() {
  loading.value = true
  try {
    const { data } = await api.get('/api/v1/complaints')
    complaints.value = data.data
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

async function loadAreas() {
  try {
    const { data } = await api.get('/api/v1/areas')
    areas.value = data.data
  } catch (e) {
    console.error(e)
  }
}

async function submitComplaint() {
  try {
    await api.post('/api/v1/complaints', form.value)
    showForm.value = false
    form.value = { area_id: '', title: '', category: 'kebersihan', description: '', priority: 'medium' }
    await loadData()
  } catch (e) {
    console.error(e)
    alert('Gagal mengirim komplain')
  }
}

async function updateStatus(id: number, status: string) {
  try {
    await api.put(`/api/v1/complaints/${id}/status`, { status, notes: `Status diperbarui menjadi ${status}` })
    await loadData()
  } catch (e) {
    console.error(e)
  }
}

onMounted(() => {
  loadData()
  if (authStore.user?.role === 'kepala_ruangan') {
    loadAreas()
  }
})
</script>

<template>
  <div class="complaints-page animate-fade-in">
    <div class="flex justify-between items-center mb-6">
      <div>
        <h1 class="text-2xl font-bold">Komplain & Masalah</h1>
        <p class="text-muted-foreground">Kelola laporan terkait kebersihan area</p>
      </div>
      <button v-if="authStore.user?.role === 'kepala_ruangan'" class="btn btn-primary" @click="showForm = true">
        + Buat Laporan
      </button>
    </div>

    <!-- New Complaint Modal (Simplified as inline form for now) -->
    <div v-if="showForm" class="card mb-6 animate-slide-up border-primary">
      <h2 class="font-bold mb-4">Buat Laporan Baru</h2>
      <form @submit.prevent="submitComplaint" class="form-grid">
        <div class="form-group">
          <label class="label">Area</label>
          <select v-model="form.area_id" class="input" required>
            <option value="">Pilih Area...</option>
            <option v-for="a in areas" :key="a.id" :value="a.id">{{ a.name }}</option>
          </select>
        </div>
        <div class="form-group">
          <label class="label">Kategori</label>
          <select v-model="form.category" class="input" required>
            <option value="kebersihan">Kebersihan</option>
            <option value="kerusakan">Kerusakan Fasilitas</option>
            <option value="kehabisan_stok">Kehabisan Stok (Sabun/Tissue)</option>
          </select>
        </div>
        <div class="form-group">
          <label class="label">Judul</label>
          <input type="text" v-model="form.title" class="input" required />
        </div>
        <div class="form-group">
          <label class="label">Prioritas</label>
          <select v-model="form.priority" class="input" required>
            <option value="low">Rendah</option>
            <option value="medium">Sedang</option>
            <option value="high">Tinggi</option>
            <option value="critical">Kritis</option>
          </select>
        </div>
        <div class="form-group full-width">
          <label class="label">Deskripsi</label>
          <textarea v-model="form.description" class="input textarea" required></textarea>
        </div>
        <div class="form-group full-width flex justify-end gap-2 mt-4">
          <button type="button" class="btn btn-ghost" @click="showForm = false">Batal</button>
          <button type="submit" class="btn btn-primary">Kirim Laporan</button>
        </div>
      </form>
    </div>

    <div class="card p-0 overflow-hidden animate-slide-up">
      <div v-if="loading" class="flex justify-center py-12">
        <div class="spinner-large"></div>
      </div>
      
      <div v-else class="table-responsive">
        <table class="audit-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Area</th>
              <th>Kategori</th>
              <th>Judul</th>
              <th>Pelapor</th>
              <th>Status</th>
              <th v-if="authStore.user?.role === 'supervisor'">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="c in complaints" :key="c.id">
              <td class="font-mono text-sm">#{{ c.id }}</td>
              <td class="font-medium">{{ c.area?.name }}</td>
              <td>{{ c.category }}</td>
              <td>
                <div class="font-medium">{{ c.title }}</div>
                <div class="text-xs text-muted-foreground">{{ new Date(c.created_at).toLocaleString('id-ID') }}</div>
              </td>
              <td>{{ c.reporter?.name }}</td>
              <td>
                <span class="badge" :class="{
                  'badge-warning': c.status === 'open' || c.status === 'in_progress',
                  'badge-success': c.status === 'resolved' || c.status === 'closed'
                }">
                  {{ c.status }}
                </span>
                <span v-if="c.priority === 'high' || c.priority === 'critical'" class="badge badge-destructive ml-2">
                  {{ c.priority }}
                </span>
              </td>
              <td v-if="authStore.user?.role === 'supervisor'">
                <div class="flex gap-2">
                  <button v-if="c.status === 'open'" class="btn btn-ghost text-xs text-primary" @click="updateStatus(c.id, 'in_progress')">Proses</button>
                  <button v-if="c.status === 'in_progress'" class="btn btn-ghost text-xs text-success" @click="updateStatus(c.id, 'resolved')">Selesai</button>
                </div>
              </td>
            </tr>
            <tr v-if="complaints.length === 0">
              <td colspan="7" class="text-center py-8 text-muted-foreground">Belum ada komplain</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<style scoped>
.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

.full-width {
  grid-column: 1 / -1;
}

.border-primary { border-color: hsl(var(--primary)); }

/* Utils */
.flex { display: flex; }
.justify-between { justify-content: space-between; }
.justify-center { justify-content: center; }
.justify-end { justify-content: flex-end; }
.items-center { align-items: center; }
.gap-2 { gap: 0.5rem; }
.mb-4 { margin-bottom: 1rem; }
.mb-6 { margin-bottom: 1.5rem; }
.mt-4 { margin-top: 1rem; }
.py-12 { padding-top: 3rem; padding-bottom: 3rem; }
.py-8 { padding-top: 2rem; padding-bottom: 2rem; }
.p-0 { padding: 0; }
.ml-2 { margin-left: 0.5rem; }
.text-2xl { font-size: 1.5rem; line-height: 2rem; }
.text-sm { font-size: 0.875rem; line-height: 1.25rem; }
.text-xs { font-size: 0.75rem; line-height: 1rem; }
.text-center { text-align: center; }
.font-bold { font-weight: 700; }
.font-medium { font-weight: 500; }
.font-mono { font-family: var(--font-mono); }
.text-muted-foreground { color: hsl(var(--muted-foreground)); }
.text-primary { color: hsl(var(--primary)); }
.text-success { color: hsl(var(--success)); }
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

@media (max-width: 768px) {
  .form-grid { grid-template-columns: 1fr; }
}
</style>
