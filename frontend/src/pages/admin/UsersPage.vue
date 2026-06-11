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
const showModal = ref(false)
const editingUser = ref<User | null>(null)
const searchQuery = ref('')
const filterRole = ref('')
const filterStatus = ref('')
const currentPage = ref(1)
const totalPages = ref(1)
const saving = ref(false)
const toast = ref<{ show: boolean; message: string; type: string }>({ show: false, message: '', type: 'success' })

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
  { value: 'cleaning_service', label: 'Cleaning Service', icon: '🧹' },
  { value: 'supervisor', label: 'Supervisor', icon: '👷' },
  { value: 'kepala_ruangan', label: 'Kepala Ruangan', icon: '🏠' },
  { value: 'manajemen', label: 'Manajemen', icon: '📊' },
  { value: 'administrator', label: 'Administrator', icon: '⚙️' },
]

const stats = computed(() => {
  const all = users.value
  return {
    total: all.length,
    active: all.filter(u => u.is_active).length,
    inactive: all.filter(u => !u.is_active).length,
    byRole: roles.map(r => ({
      ...r,
      count: all.filter(u => u.role === r.value).length
    }))
  }
})

const filteredUsers = computed(() => {
  let list = users.value
  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase()
    list = list.filter(u =>
      u.name.toLowerCase().includes(q) ||
      (u.username || '').toLowerCase().includes(q) ||
      (u.email || '').toLowerCase().includes(q) ||
      (u.employee_id || '').toLowerCase().includes(q)
    )
  }
  if (filterRole.value) {
    list = list.filter(u => u.role === filterRole.value)
  }
  if (filterStatus.value === 'active') {
    list = list.filter(u => u.is_active)
  } else if (filterStatus.value === 'inactive') {
    list = list.filter(u => !u.is_active)
  }
  return list
})

async function fetchUsers(page = 1) {
  loading.value = true
  try {
    const response = await api.get('/api/v1/admin/users', {
      params: { per_page: 100, page }
    })
    const paginated = response.data
    users.value = paginated.data || []
    currentPage.value = paginated.current_page || 1
    totalPages.value = paginated.last_page || 1
  } catch (err: any) {
    showToast('Gagal mengambil data user: ' + err.message, 'error')
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

function openAddModal() {
  editingUser.value = null
  form.value = {
    name: '', username: '', email: '', employee_id: '',
    password: '', role: 'cleaning_service', is_active: true, area_ids: []
  }
  showModal.value = true
}

function openEditModal(user: User) {
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
  showModal.value = true
}

async function saveUser() {
  saving.value = true
  try {
    const payload: any = { ...form.value }
    if (!payload.password) delete payload.password

    if (editingUser.value) {
      await api.put(`/api/v1/admin/users/${editingUser.value.id}`, payload)
      showToast('User berhasil diperbarui ✅', 'success')
    } else {
      await api.post('/api/v1/admin/users', payload)
      showToast('User baru berhasil ditambahkan ✅', 'success')
    }
    showModal.value = false
    await fetchUsers()
  } catch (err: any) {
    showToast('Gagal menyimpan: ' + (err.response?.data?.message || err.message), 'error')
  } finally {
    saving.value = false
  }
}

async function deleteUser(user: User) {
  if (!confirm(`Hapus user "${user.name}"? Tindakan ini tidak bisa dibatalkan.`)) return
  try {
    await api.delete(`/api/v1/admin/users/${user.id}`)
    showToast(`User "${user.name}" berhasil dihapus`, 'success')
    await fetchUsers()
  } catch (err: any) {
    showToast('Gagal menghapus: ' + (err.response?.data?.message || err.message), 'error')
  }
}

async function toggleActive(user: User) {
  try {
    await api.put(`/api/v1/admin/users/${user.id}`, { is_active: !user.is_active })
    showToast(`User "${user.name}" ${!user.is_active ? 'diaktifkan' : 'dinonaktifkan'}`, 'success')
    await fetchUsers()
  } catch (err: any) {
    showToast('Gagal mengubah status: ' + (err.response?.data?.message || err.message), 'error')
  }
}

async function resetDevice(user: User) {
  if (!confirm(`Reset perangkat untuk ${user.name}?`)) return
  try {
    await api.post(`/api/v1/admin/users/${user.id}/reset-device`)
    showToast(`Perangkat ${user.name} berhasil direset 📱`, 'success')
    await fetchUsers()
  } catch (err: any) {
    showToast('Gagal mereset perangkat', 'error')
  }
}

function getRoleBadgeClass(role: string) {
  switch (role) {
    case 'cleaning_service': return 'badge-primary'
    case 'supervisor': return 'badge-warning'
    case 'administrator': return 'badge-success'
    default: return 'badge-secondary'
  }
}

function getRoleIcon(role: string) {
  return roles.find(r => r.value === role)?.icon || '👤'
}

function showToast(message: string, type: string) {
  toast.value = { show: true, message, type }
  setTimeout(() => { toast.value.show = false }, 3000)
}

function selectAllAreas() {
  form.value.area_ids = areas.value.map((a: any) => a.id)
}

function deselectAllAreas() {
  form.value.area_ids = []
}

onMounted(() => {
  fetchUsers()
  fetchAreas()
})
</script>

<template>
  <div class="users-page animate-fade-in">
    <!-- Toast -->
    <Teleport to="body">
      <Transition name="toast">
        <div v-if="toast.show" class="toast-notification" :class="'toast-' + toast.type">
          {{ toast.message }}
        </div>
      </Transition>
    </Teleport>

    <!-- Header -->
    <div class="page-header">
      <div>
        <h1 class="page-title"><span class="title-icon">👥</span> Kelola Pengguna</h1>
        <p class="page-subtitle">Manajemen akun pengguna, role, dan akses perangkat</p>
      </div>
      <div class="header-actions">
        <button class="btn btn-secondary" @click="fetchUsers()">🔄 Refresh</button>
        <button class="btn btn-primary" @click="openAddModal">+ Tambah User</button>
      </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
      <div class="stat-card animate-slide-up stagger-1">
        <div class="stat-icon stat-icon-total">👥</div>
        <div class="stat-info">
          <span class="stat-value">{{ stats.total }}</span>
          <span class="stat-label">Total User</span>
        </div>
      </div>
      <div class="stat-card animate-slide-up stagger-2">
        <div class="stat-icon stat-icon-active">✅</div>
        <div class="stat-info">
          <span class="stat-value">{{ stats.active }}</span>
          <span class="stat-label">Aktif</span>
        </div>
      </div>
      <div class="stat-card animate-slide-up stagger-3">
        <div class="stat-icon stat-icon-inactive">❌</div>
        <div class="stat-info">
          <span class="stat-value">{{ stats.inactive }}</span>
          <span class="stat-label">Non-Aktif</span>
        </div>
      </div>
      <div class="stat-card animate-slide-up stagger-4">
        <div class="stat-icon stat-icon-roles">🏷️</div>
        <div class="stat-info">
          <span class="stat-value">{{ roles.length }}</span>
          <span class="stat-label">Jenis Role</span>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="filters-bar animate-slide-up">
      <div class="search-wrapper">
        <span class="search-icon">🔍</span>
        <input type="text" v-model="searchQuery" class="input search-input" placeholder="Cari nama, username, email..." />
      </div>
      <select v-model="filterRole" class="input filter-select">
        <option value="">Semua Role</option>
        <option v-for="r in roles" :key="r.value" :value="r.value">{{ r.icon }} {{ r.label }}</option>
      </select>
      <select v-model="filterStatus" class="input filter-select">
        <option value="">Semua Status</option>
        <option value="active">✅ Aktif</option>
        <option value="inactive">❌ Non-Aktif</option>
      </select>
    </div>

    <!-- Table -->
    <div class="card table-card animate-slide-up">
      <div v-if="loading" class="loading-state">
        <div class="spinner-large"></div>
        <p>Memuat data pengguna...</p>
      </div>

      <div v-else-if="filteredUsers.length === 0" class="empty-state">
        <div class="empty-icon">👤</div>
        <h3>Tidak ada pengguna ditemukan</h3>
        <p>Coba ubah filter atau tambahkan pengguna baru.</p>
      </div>

      <div v-else class="table-responsive">
        <table class="audit-table users-table">
          <thead>
            <tr>
              <th>Pengguna</th>
              <th>Role</th>
              <th>Status</th>
              <th>Perangkat</th>
              <th>Area Akses</th>
              <th class="text-right">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="user in filteredUsers" :key="user.id">
              <td>
                <div class="user-cell">
                  <div class="user-avatar">{{ (user.name || '?')[0] }}</div>
                  <div class="user-info-cell">
                    <span class="user-name-main">{{ user.name }}</span>
                    <span class="user-username">@{{ user.username || '-' }}</span>
                    <span v-if="user.employee_id" class="user-emp-id">ID: {{ user.employee_id }}</span>
                  </div>
                </div>
              </td>
              <td>
                <span class="badge" :class="getRoleBadgeClass(user.role)">
                  {{ getRoleIcon(user.role) }} {{ user.role_label || user.role }}
                </span>
              </td>
              <td>
                <button class="status-toggle" :class="user.is_active ? 'status-active' : 'status-inactive'" @click="toggleActive(user)">
                  <span class="status-dot"></span>
                  {{ user.is_active ? 'Aktif' : 'Non-Aktif' }}
                </button>
              </td>
              <td>
                <span v-if="user.device_id" class="device-badge device-locked">
                  🔒 {{ user.device_id.substring(0, 8) }}...
                </span>
                <span v-else class="device-badge device-free">🔓 Bebas</span>
              </td>
              <td>
                <div class="area-tags" v-if="user.areas && user.areas.length > 0">
                  <span v-for="a in user.areas.slice(0, 3)" :key="a.id" class="area-tag">{{ a.name }}</span>
                  <span v-if="user.areas.length > 3" class="area-tag area-tag-more">+{{ user.areas.length - 3 }}</span>
                </div>
                <span v-else class="no-area">-</span>
              </td>
              <td class="text-right">
                <div class="action-btns">
                  <button class="action-btn action-edit" @click="openEditModal(user)" title="Edit">✏️</button>
                  <button v-if="user.device_id" class="action-btn action-reset" @click="resetDevice(user)" title="Reset Device">📱</button>
                  <button class="action-btn action-delete" @click="deleteUser(user)" title="Hapus">🗑️</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Modal Form -->
    <Teleport to="body">
      <div v-if="showModal" class="modal-overlay" @click.self="showModal = false">
        <div class="modal-content modal-lg">
          <div class="modal-header">
            <h3 class="modal-title">{{ editingUser ? '✏️ Edit User' : '➕ Tambah User Baru' }}</h3>
            <button class="modal-close" @click="showModal = false">✕</button>
          </div>
          <form @submit.prevent="saveUser" class="modal-body">
            <div class="form-grid">
              <div class="form-group">
                <label class="label">Nama Lengkap *</label>
                <input type="text" v-model="form.name" class="input" required placeholder="Nama lengkap" />
              </div>
              <div class="form-group">
                <label class="label">Username *</label>
                <input type="text" v-model="form.username" class="input" required placeholder="username" />
              </div>
              <div class="form-group">
                <label class="label">Email</label>
                <input type="email" v-model="form.email" class="input" placeholder="email@cleantrack.id (opsional)" />
              </div>
              <div class="form-group">
                <label class="label">ID Karyawan</label>
                <input type="text" v-model="form.employee_id" class="input" placeholder="CS002 (opsional)" />
              </div>
              <div class="form-group">
                <label class="label">Password {{ editingUser ? '(kosongkan jika tidak diubah)' : '*' }}</label>
                <input type="password" v-model="form.password" class="input" :required="!editingUser" placeholder="Min. 8 karakter" />
              </div>
              <div class="form-group">
                <label class="label">Role *</label>
                <select v-model="form.role" class="input" required>
                  <option v-for="r in roles" :key="r.value" :value="r.value">{{ r.icon }} {{ r.label }}</option>
                </select>
              </div>
            </div>

            <div class="form-group">
              <div class="checkbox-field">
                <input type="checkbox" v-model="form.is_active" id="is_active_modal" />
                <label for="is_active_modal">User Aktif</label>
              </div>
            </div>

            <!-- Area Access Group -->
            <div class="form-group">
              <div class="area-group-header">
                <label class="label">Akses Area Pembersihan</label>
                <div class="area-group-actions">
                  <button type="button" class="btn-link" @click="selectAllAreas">Pilih Semua</button>
                  <button type="button" class="btn-link" @click="deselectAllAreas">Hapus Semua</button>
                </div>
              </div>
              <div class="area-checkbox-grid">
                <label v-for="area in areas" :key="area.id" class="area-checkbox-item" :class="{ selected: form.area_ids.includes(area.id) }">
                  <input type="checkbox" :value="area.id" v-model="form.area_ids" />
                  <div class="area-cb-info">
                    <span class="area-cb-name">{{ area.name }}</span>
                    <span class="area-cb-code">{{ area.code }}</span>
                  </div>
                </label>
              </div>
              <div class="area-selected-count">{{ form.area_ids.length }} area dipilih</div>
            </div>

            <div class="modal-footer">
              <button type="button" class="btn btn-ghost" @click="showModal = false">Batal</button>
              <button type="submit" class="btn btn-primary" :disabled="saving">
                <span v-if="saving" class="spinner-small"></span>
                {{ editingUser ? 'Simpan Perubahan' : 'Tambah User' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
.users-page {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

/* Header */
.page-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; }
.page-title { font-size: 1.5rem; font-weight: 700; color: hsl(var(--foreground)); display: flex; align-items: center; gap: 0.5rem; margin: 0; }
.title-icon { font-size: 1.75rem; }
.page-subtitle { font-size: 0.875rem; color: hsl(var(--muted-foreground)); margin-top: 0.25rem; }
.header-actions { display: flex; gap: 0.5rem; }

/* Stats */
.stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; }
@media (max-width: 768px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }

.stat-card {
  background: hsl(var(--card)); border: 1px solid hsl(var(--border)); border-radius: var(--radius);
  padding: 1.25rem; display: flex; align-items: center; gap: 1rem;
  transition: all 0.3s; position: relative; overflow: hidden;
}
.stat-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; opacity: 0; transition: opacity 0.3s; }
.stat-card:hover::before { opacity: 1; }
.stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 16px hsl(var(--primary) / 0.08); }
.stat-card:nth-child(1)::before { background: hsl(var(--primary)); }
.stat-card:nth-child(2)::before { background: hsl(var(--success)); }
.stat-card:nth-child(3)::before { background: hsl(var(--destructive)); }
.stat-card:nth-child(4)::before { background: hsl(var(--accent)); }

.stat-icon { font-size: 1.25rem; width: 3rem; height: 3rem; display: flex; align-items: center; justify-content: center; border-radius: 0.75rem; flex-shrink: 0; }
.stat-icon-total { background: hsl(var(--primary) / 0.12); }
.stat-icon-active { background: hsl(var(--success) / 0.12); }
.stat-icon-inactive { background: hsl(var(--destructive) / 0.12); }
.stat-icon-roles { background: hsl(var(--accent) / 0.12); }
.stat-info { display: flex; flex-direction: column; }
.stat-value { font-size: 1.75rem; font-weight: 800; line-height: 1; color: hsl(var(--foreground)); }
.stat-label { font-size: 0.75rem; color: hsl(var(--muted-foreground)); margin-top: 0.25rem; font-weight: 500; }

/* Filters */
.filters-bar { display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap; }
.search-wrapper { position: relative; flex: 1; min-width: 200px; }
.search-icon { position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); font-size: 0.875rem; pointer-events: none; }
.search-input { padding-left: 2.25rem !important; }
.filter-select { width: auto; min-width: 150px; }

/* Table */
.table-card { padding: 0; overflow: hidden; }
.table-responsive { overflow-x: auto; }

.users-table td { vertical-align: middle; }
.text-right { text-align: right; }

.user-cell { display: flex; align-items: center; gap: 0.75rem; }
.user-avatar {
  width: 2.25rem; height: 2.25rem; border-radius: 50%;
  background: linear-gradient(135deg, hsl(210, 100%, 56%), hsl(262, 83%, 58%));
  display: flex; align-items: center; justify-content: center;
  color: white; font-size: 0.75rem; font-weight: 700; flex-shrink: 0;
}
.user-info-cell { display: flex; flex-direction: column; gap: 0.0625rem; }
.user-name-main { font-weight: 600; font-size: 0.875rem; color: hsl(var(--foreground)); }
.user-username { font-size: 0.75rem; color: hsl(var(--muted-foreground)); }
.user-emp-id { font-size: 0.6875rem; color: hsl(var(--muted-foreground) / 0.7); }

.badge-secondary { background: hsl(var(--secondary)); color: hsl(var(--secondary-foreground)); }

/* Status Toggle */
.status-toggle {
  display: inline-flex; align-items: center; gap: 0.375rem;
  padding: 0.25rem 0.625rem; border-radius: 9999px;
  font-size: 0.75rem; font-weight: 500; cursor: pointer;
  border: 1px solid transparent; transition: all 0.2s;
}
.status-active { background: hsl(var(--success) / 0.12); color: hsl(var(--success)); border-color: hsl(var(--success) / 0.2); }
.status-inactive { background: hsl(var(--destructive) / 0.12); color: hsl(var(--destructive)); border-color: hsl(var(--destructive) / 0.2); }
.status-dot {
  width: 6px; height: 6px; border-radius: 50%;
}
.status-active .status-dot { background: hsl(var(--success)); }
.status-inactive .status-dot { background: hsl(var(--destructive)); }

/* Device */
.device-badge { font-size: 0.75rem; padding: 0.25rem 0.5rem; border-radius: 0.375rem; }
.device-locked { background: hsl(var(--success) / 0.1); color: hsl(var(--success)); }
.device-free { color: hsl(var(--muted-foreground)); }

/* Area Tags */
.area-tags { display: flex; flex-wrap: wrap; gap: 0.25rem; }
.area-tag {
  font-size: 0.625rem; padding: 0.125rem 0.375rem;
  background: hsl(var(--secondary)); color: hsl(var(--secondary-foreground));
  border-radius: 0.25rem; white-space: nowrap;
}
.area-tag-more { background: hsl(var(--primary) / 0.15); color: hsl(var(--primary)); font-weight: 600; }
.no-area { font-size: 0.75rem; color: hsl(var(--muted-foreground)); }

/* Action Buttons */
.action-btns { display: flex; gap: 0.25rem; justify-content: flex-end; }
.action-btn {
  width: 2rem; height: 2rem; display: flex; align-items: center; justify-content: center;
  border: 1px solid hsl(var(--border)); background: hsl(var(--card));
  border-radius: 0.375rem; cursor: pointer; font-size: 0.875rem; transition: all 0.2s;
}
.action-edit:hover { background: hsl(var(--primary) / 0.1); border-color: hsl(var(--primary) / 0.3); }
.action-reset:hover { background: hsl(var(--warning) / 0.1); border-color: hsl(var(--warning) / 0.3); }
.action-delete:hover { background: hsl(var(--destructive) / 0.1); border-color: hsl(var(--destructive) / 0.3); }

/* Modal */
.modal-overlay {
  position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px);
  z-index: 9999; display: flex; align-items: center; justify-content: center; padding: 1rem;
}
.modal-content {
  background: hsl(var(--card)); border: 1px solid hsl(var(--border));
  border-radius: var(--radius); width: 100%; box-shadow: 0 16px 48px rgba(0,0,0,0.3);
  max-height: 90vh; overflow-y: auto;
}
.modal-lg { max-width: 640px; }
.modal-header {
  display: flex; justify-content: space-between; align-items: center;
  padding: 1.25rem 1.5rem; border-bottom: 1px solid hsl(var(--border));
}
.modal-title { font-size: 1.125rem; font-weight: 700; color: hsl(var(--foreground)); margin: 0; }
.modal-close {
  width: 2rem; height: 2rem; display: flex; align-items: center; justify-content: center;
  border: 1px solid hsl(var(--border)); background: hsl(var(--muted)); border-radius: 0.375rem;
  cursor: pointer; color: hsl(var(--muted-foreground)); font-size: 0.875rem;
}
.modal-close:hover { background: hsl(var(--destructive) / 0.1); color: hsl(var(--destructive)); }
.modal-body { padding: 1.5rem; }
.modal-footer { display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1.25rem; padding-top: 1rem; border-top: 1px solid hsl(var(--border)); }

.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem; }
@media (max-width: 640px) { .form-grid { grid-template-columns: 1fr; } }
.form-group { margin-bottom: 0.75rem; }

.checkbox-field { display: flex; align-items: center; gap: 0.5rem; }
.checkbox-field input { width: 1.125rem; height: 1.125rem; cursor: pointer; }
.checkbox-field label { font-size: 0.875rem; cursor: pointer; }

/* Area Group */
.area-group-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem; }
.area-group-actions { display: flex; gap: 0.75rem; }
.btn-link {
  background: none; border: none; color: hsl(var(--primary));
  font-size: 0.75rem; cursor: pointer; font-weight: 500; text-decoration: underline;
}
.area-checkbox-grid {
  display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 0.375rem; max-height: 200px; overflow-y: auto;
  padding: 0.5rem; background: hsl(var(--muted) / 0.3);
  border: 1px solid hsl(var(--border)); border-radius: 0.5rem;
}
.area-checkbox-item {
  display: flex; align-items: center; gap: 0.5rem;
  padding: 0.375rem 0.5rem; border-radius: 0.375rem;
  border: 1px solid transparent; cursor: pointer; transition: all 0.2s;
}
.area-checkbox-item:hover { background: hsl(var(--muted) / 0.5); border-color: hsl(var(--primary) / 0.2); }
.area-checkbox-item.selected { background: hsl(var(--primary) / 0.08); border-color: hsl(var(--primary) / 0.3); }
.area-checkbox-item input { width: 0.875rem; height: 0.875rem; cursor: pointer; flex-shrink: 0; }
.area-cb-info { display: flex; flex-direction: column; }
.area-cb-name { font-size: 0.8125rem; font-weight: 500; }
.area-cb-code { font-size: 0.625rem; color: hsl(var(--muted-foreground)); }
.area-selected-count { font-size: 0.75rem; color: hsl(var(--muted-foreground)); margin-top: 0.375rem; font-weight: 500; }

/* Toast */
.toast-notification { position: fixed; top: 1.5rem; right: 1.5rem; z-index: 99999; padding: 0.75rem 1.25rem; border-radius: var(--radius); font-size: 0.875rem; font-weight: 500; box-shadow: 0 8px 24px rgba(0,0,0,0.3); color: white; }
.toast-success { background: hsl(var(--success)); }
.toast-warning { background: hsl(var(--warning)); color: black; }
.toast-error { background: hsl(var(--destructive)); }
.toast-enter-active, .toast-leave-active { transition: all 0.3s ease; }
.toast-enter-from { transform: translateX(100%); opacity: 0; }
.toast-leave-to { transform: translateY(-20px); opacity: 0; }

/* Loading/Empty */
.loading-state { display: flex; flex-direction: column; align-items: center; padding: 4rem 2rem; gap: 1rem; color: hsl(var(--muted-foreground)); }
.empty-state { display: flex; flex-direction: column; align-items: center; padding: 4rem 2rem; text-align: center; }
.empty-icon { font-size: 3rem; margin-bottom: 1rem; }
.empty-state h3 { font-size: 1.125rem; font-weight: 600; color: hsl(var(--foreground)); margin: 0 0 0.5rem; }
.empty-state p { font-size: 0.875rem; color: hsl(var(--muted-foreground)); margin: 0; }

.spinner-large { width: 3rem; height: 3rem; border: 4px solid rgba(255,255,255,0.1); border-top-color: hsl(var(--primary)); border-radius: 50%; animation: spin 0.8s linear infinite; }
.spinner-small { width: 1rem; height: 1rem; border: 2px solid rgba(255,255,255,0.3); border-top-color: white; border-radius: 50%; animation: spin 0.6s linear infinite; display: inline-block; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>
