<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import api from '../../lib/axios'

interface User {
  id: number
  name: string
  username: string | null
  email: string | null
  employee_id?: string | null
  role: string
  role_label: string
  device_id: string | null
  is_active: boolean
  areas?: Array<{ id: number; code: string; name: string }>
}

const users = ref<User[]>([])
const areas = ref<any[]>([])
const loading = ref(true)
const error = ref('')
const showForm = ref(false)
const editingUser = ref<User | null>(null)
const searchQuery = ref('')

const form = ref({
  name: '',
  username: '',
  email: '',
  employee_id: '',
  password: '',
  role: 'cleaning_service',
  is_active: true,
  area_ids: [] as number[]
})

const roles = [
  { value: 'cleaning_service', label: 'Cleaning Service' },
  { value: 'supervisor', label: 'Supervisor' },
  { value: 'kepala_ruangan', label: 'Kepala Ruangan' },
  { value: 'manajemen', label: 'Manajemen' },
  { value: 'administrator', label: 'Administrator' },
]

const filteredUsers = computed(() => {
  if (!searchQuery.value) return users.value
  const q = searchQuery.value.toLowerCase()
  return users.value.filter(u =>
    u.name.toLowerCase().includes(q) ||
    (u.username || '').toLowerCase().includes(q) ||
    (u.email || '').toLowerCase().includes(q) ||
    u.role.toLowerCase().includes(q)
  )
})

async function fetchUsers() {
  loading.value = true
  try {
    const response = await api.get('/api/v1/admin/users?per_page=100')
    users.value = response.data.data || response.data
  } catch (err: any) {
    error.value = 'Gagal mengambil data user: ' + err.message
  } finally {
    loading.value = false
  }
}

async function fetchAreas() {
  try {
    const response = await api.get('/api/v1/areas')
    areas.value = response.data.data || response.data
  } catch (err: any) {
    console.error('Gagal mengambil data area:', err)
  }
}

function openAddForm() {
  editingUser.value = null
  form.value = { 
    name: '', 
    username: '', 
    email: '', 
    employee_id: '',
    password: '', 
    role: 'cleaning_service', 
    is_active: true,
    area_ids: []
  }
  showForm.value = true
}

function openEditForm(user: User) {
  editingUser.value = user
  form.value = {
    name: user.name,
    username: user.username || '',
    email: user.email || '',
    employee_id: user.employee_id || '',
    password: '',
    role: user.role,
    is_active: user.is_active,
    area_ids: user.areas ? user.areas.map(a => a.id) : []
  }
  showForm.value = true
}

async function saveUser() {
  try {
    const payload: any = { ...form.value }
    if (!payload.password) delete payload.password

    if (editingUser.value) {
      await api.put(`/api/v1/admin/users/${editingUser.value.id}`, payload)
    } else {
      await api.post('/api/v1/admin/users', payload)
    }
    showForm.value = false
    await fetchUsers()
  } catch (err: any) {
    alert('Gagal menyimpan: ' + (err.response?.data?.message || err.message))
  }
}

async function deleteUser(user: User) {
  if (!confirm(`Hapus user "${user.name}"? Tindakan ini tidak bisa dibatalkan.`)) return
  try {
    await api.delete(`/api/v1/admin/users/${user.id}`)
    await fetchUsers()
  } catch (err: any) {
    alert('Gagal menghapus: ' + (err.response?.data?.message || err.message))
  }
}

async function toggleActive(user: User) {
  try {
    await api.put(`/api/v1/admin/users/${user.id}`, { is_active: !user.is_active })
    await fetchUsers()
  } catch (err: any) {
    alert('Gagal mengubah status: ' + (err.response?.data?.message || err.message))
  }
}

async function resetDevice(user: User) {
  if (!confirm(`Reset perangkat untuk ${user.name}? User akan dapat login di perangkat baru.`)) return
  try {
    await api.post(`/api/v1/admin/users/${user.id}/reset-device`)
    alert(`Berhasil mereset perangkat ${user.name}`)
    await fetchUsers()
  } catch (err: any) {
    alert('Gagal mereset perangkat: ' + (err.response?.data?.message || err.message))
  }
}

onMounted(() => {
  fetchUsers()
  fetchAreas()
})
</script>

<template>
  <div class="users-page animate-fade-in">
    <div class="flex justify-between items-center mb-6">
      <div>
        <h1 class="text-2xl font-bold">Kelola Pengguna</h1>
        <p class="text-muted-foreground">Manajemen akun pengguna, role, dan akses perangkat.</p>
      </div>
      <div class="flex gap-3">
        <button class="btn btn-secondary" @click="fetchUsers">🔄 Refresh</button>
        <button class="btn btn-primary" @click="openAddForm">+ Tambah User</button>
      </div>
    </div>

    <!-- Search -->
    <div class="mb-4">
      <input type="text" v-model="searchQuery" class="input" placeholder="🔍 Cari nama, username, email, atau role..." />
    </div>

    <!-- Add/Edit Form -->
    <div v-if="showForm" class="card mb-6 animate-slide-up form-card">
      <h2 class="font-bold mb-4">{{ editingUser ? 'Edit User' : 'Tambah User Baru' }}</h2>
      <form @submit.prevent="saveUser" class="form-grid">
        <div class="form-group">
          <label class="label">Nama Lengkap</label>
          <input type="text" v-model="form.name" class="input" required />
        </div>
        <div class="form-group">
          <label class="label">Username</label>
          <input type="text" v-model="form.username" class="input" required />
        </div>
        <div class="form-group">
          <label class="label">Email</label>
          <input type="email" v-model="form.email" class="input" placeholder="contoh@cleantrack.id (Opsional)" />
        </div>
        <div class="form-group">
          <label class="label">ID Karyawan / Employee ID</label>
          <input type="text" v-model="form.employee_id" class="input" placeholder="Misal: CS002 (Opsional)" />
        </div>
        <div class="form-group">
          <label class="label">Password {{ editingUser ? '(kosongkan jika tidak diubah)' : '' }}</label>
          <input type="password" v-model="form.password" class="input" :required="!editingUser" />
        </div>
        <div class="form-group">
          <label class="label">Role</label>
          <select v-model="form.role" class="input" required>
            <option v-for="r in roles" :key="r.value" :value="r.value">{{ r.label }}</option>
          </select>
        </div>
        <div class="form-group flex items-center gap-2 pt-6">
          <input type="checkbox" v-model="form.is_active" id="is_active" />
          <label for="is_active">Aktif</label>
        </div>
        
        <!-- Akses Area (Akses Group) -->
        <div class="form-group full-width mt-4">
          <label class="label font-bold mb-2">Akses Area Pembersihan (Akses Group)</label>
          <div class="area-checkbox-grid">
            <label v-for="area in areas" :key="area.id" class="area-checkbox-item">
              <input type="checkbox" :value="area.id" v-model="form.area_ids" />
              <div class="area-info ml-2">
                <span class="area-name font-medium text-sm">{{ area.name }}</span>
                <span class="area-code text-xs text-muted-foreground ml-1">({{ area.code }})</span>
              </div>
            </label>
          </div>
        </div>

        <div class="form-group full-width flex justify-end gap-2 mt-4">
          <button type="button" class="btn btn-ghost" @click="showForm = false">Batal</button>
          <button type="submit" class="btn btn-primary">{{ editingUser ? 'Simpan Perubahan' : 'Tambah User' }}</button>
        </div>
      </form>
    </div>

    <div v-if="error" class="error-banner mb-4">{{ error }}</div>

    <div class="card p-0 overflow-x-auto">
      <table class="audit-table">
        <thead>
          <tr>
            <th>Nama</th>
            <th>Username / Email</th>
            <th>Role</th>
            <th>Status</th>
            <th>Perangkat</th>
            <th class="text-right">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="6" class="text-center py-8 text-muted-foreground">
              <span class="spinner-small inline-block mr-2"></span> Memuat data...
            </td>
          </tr>
          <tr v-else-if="filteredUsers.length === 0">
            <td colspan="6" class="text-center py-8 text-muted-foreground">Tidak ada data user.</td>
          </tr>
          <tr v-else v-for="user in filteredUsers" :key="user.id">
            <td class="font-medium">{{ user.name }}</td>
            <td>
              <div class="font-semibold">{{ user.username || '-' }}</div>
              <div class="text-xs text-muted-foreground">{{ user.email || '-' }} <span v-if="user.employee_id" class="text-muted-foreground/60">({{ user.employee_id }})</span></div>
              
              <!-- Assigned Areas Badges -->
              <div v-if="user.areas && user.areas.length > 0" class="mt-1-5 flex flex-wrap gap-1">
                <span v-for="a in user.areas" :key="a.id" class="badge badge-secondary text-2xs px-1.5 py-0.5" style="font-size: 0.7rem; opacity: 0.9;">
                  📍 {{ a.name }}
                </span>
              </div>
            </td>
            <td>
              <span class="badge" :class="{
                'badge-primary': user.role === 'cleaning_service',
                'badge-warning': user.role === 'supervisor',
                'badge-success': user.role === 'administrator',
                'badge-secondary': user.role === 'manajemen' || user.role === 'kepala_ruangan'
              }">
                {{ user.role_label || user.role }}
              </span>
            </td>
            <td>
              <span class="badge cursor-pointer" :class="user.is_active ? 'badge-success' : 'badge-destructive'" @click="toggleActive(user)">
                {{ user.is_active ? '✅ Aktif' : '❌ Nonaktif' }}
              </span>
            </td>
            <td>
              <span v-if="user.device_id" class="badge badge-success">
                🔒 {{ user.device_id.substring(0, 8) }}...
              </span>
              <span v-else class="text-muted-foreground text-sm">🔓 Bebas</span>
            </td>
            <td class="text-right">
              <div class="flex justify-end gap-2">
                <button class="btn btn-sm btn-ghost" @click="openEditForm(user)" title="Edit">✏️</button>
                <button v-if="user.device_id" class="btn btn-sm btn-ghost text-warning" @click="resetDevice(user)" title="Reset Device">📱</button>
                <button class="btn btn-sm btn-ghost text-destructive" @click="deleteUser(user)" title="Hapus">🗑️</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<style scoped>
.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}
.form-card { border: 1px solid hsl(var(--primary) / 0.3); }
.full-width { grid-column: 1 / -1; }
.error-banner {
  background: hsl(var(--destructive) / 0.15);
  color: hsl(var(--destructive));
  padding: 0.75rem 1rem;
  border-radius: 0.5rem;
  font-size: 0.875rem;
  text-align: center;
  border: 1px solid hsl(var(--destructive) / 0.3);
}

.area-checkbox-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 0.5rem;
  max-height: 200px;
  overflow-y: auto;
  padding: 0.75rem;
  background: rgba(255,255,255,0.02);
  border: 1px solid hsl(var(--border));
  border-radius: 0.5rem;
}
.area-checkbox-item {
  display: flex;
  align-items: center;
  padding: 0.4rem 0.6rem;
  background: rgba(255,255,255,0.03);
  border-radius: 0.375rem;
  border: 1px solid transparent;
  cursor: pointer;
  transition: all 0.2s;
}
.area-checkbox-item:hover {
  background: rgba(255,255,255,0.07);
  border-color: hsl(var(--primary) / 0.3);
}
.area-checkbox-item input[type="checkbox"] {
  width: 1rem;
  height: 1rem;
  cursor: pointer;
}
.ml-2 { margin-left: 0.5rem; }
.ml-1 { margin-left: 0.25rem; }
.mt-1-5 { margin-top: 0.375rem; }
.flex-wrap { flex-wrap: wrap; }

/* Utils */
.flex { display: flex; }
.justify-between { justify-content: space-between; }
.justify-end { justify-content: flex-end; }
.items-center { align-items: center; }
.gap-2 { gap: 0.5rem; }
.gap-3 { gap: 0.75rem; }
.mb-4 { margin-bottom: 1rem; }
.mb-6 { margin-bottom: 1.5rem; }
.mt-4 { margin-top: 1rem; }
.pt-6 { padding-top: 1.5rem; }
.p-0 { padding: 0; }
.py-8 { padding-top: 2rem; padding-bottom: 2rem; }
.text-2xl { font-size: 1.5rem; line-height: 2rem; }
.text-sm { font-size: 0.875rem; }
.text-xs { font-size: 0.75rem; }
.font-bold { font-weight: 700; }
.font-medium { font-weight: 500; }
.text-right { text-align: right; }
.text-center { text-align: center; }
.inline-block { display: inline-block; }
.mr-2 { margin-right: 0.5rem; }
.overflow-x-auto { overflow-x: auto; }
.cursor-pointer { cursor: pointer; }
.text-warning { color: hsl(var(--warning)); }
.text-destructive { color: hsl(var(--destructive)); }
.text-muted-foreground { color: hsl(var(--muted-foreground)); }
.badge-secondary { background: hsl(var(--secondary)); color: hsl(var(--secondary-foreground)); }

.btn-sm {
  padding: 0.25rem 0.5rem;
  font-size: 0.8rem;
  border-radius: 0.375rem;
}

.spinner-small {
  width: 1rem; height: 1rem;
  border: 2px solid hsl(var(--muted));
  border-top-color: hsl(var(--primary));
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
}

@keyframes spin { to { transform: rotate(360deg); } }

@media (max-width: 768px) {
  .form-grid { grid-template-columns: 1fr; }
}
</style>
