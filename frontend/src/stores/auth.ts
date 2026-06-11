import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { v4 as uuidv4 } from 'uuid'
import api from '../lib/axios'
import { cacheUserProfile, getCachedUserProfile, clearOfflineCache } from '../lib/db'

export interface User {
  id: number
  name: string
  email: string
  employee_id: string
  phone: string | null
  role: string
  role_label: string
  avatar: string | null
  is_active: boolean
  areas: Array<{ id: number; code: string; name: string }>
}

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const loading = ref(false)

  const isAuthenticated = computed(() => !!user.value)
  const isCleaningService = computed(() => user.value?.role === 'cleaning_service')
  const isSupervisor = computed(() => user.value?.role === 'supervisor')
  const isKepalaRuangan = computed(() => user.value?.role === 'kepala_ruangan')
  const isAdmin = computed(() => user.value?.role === 'administrator')
  const isManajemen = computed(() => user.value?.role === 'manajemen')
  const hasDashboardAccess = computed(() =>
    ['supervisor', 'administrator', 'manajemen', 'kepala_ruangan'].includes(user.value?.role || '')
  )

  const getDeviceId = () => {
    let deviceId = localStorage.getItem('device_id')
    if (!deviceId) {
      deviceId = uuidv4()
      localStorage.setItem('device_id', deviceId)
    }
    return deviceId
  }

  const defaultRoute = computed(() => {
    if (!user.value) return 'login'
    switch (user.value.role) {
      case 'cleaning_service': return 'mobile-dashboard'
      case 'supervisor': return 'supervisor-dashboard'
      case 'kepala_ruangan': return 'my-areas'
      case 'administrator': return 'admin-dashboard'
      case 'manajemen': return 'kpi-dashboard'
      default: return 'login'
    }
  })

  async function login(email: string, password: string) {
    loading.value = true
    try {
      await api.get('/sanctum/csrf-cookie')
      const { data } = await api.post('/api/v1/auth/login', { 
        email, 
        password,
        device_id: getDeviceId() 
      })
      user.value = data.user
      await cacheUserProfile(data.user)
      return data
    } finally {
      loading.value = false
    }
  }

  async function logout() {
    try {
      await api.post('/api/v1/auth/logout')
    } finally {
      await clearOfflineCache()
      user.value = null
    }
  }

  async function fetchUser() {
    try {
      const { data } = await api.get('/api/v1/auth/me')
      user.value = data.user
      await cacheUserProfile(data.user)
    } catch {
      // Try to load from cache (offline)
      const cached = await getCachedUserProfile()
      if (cached) {
        user.value = cached
      }
    }
  }

  return {
    user,
    loading,
    isAuthenticated,
    isCleaningService,
    isSupervisor,
    isKepalaRuangan,
    isAdmin,
    isManajemen,
    hasDashboardAccess,
    defaultRoute,
    login,
    logout,
    fetchUser,
  }
})
