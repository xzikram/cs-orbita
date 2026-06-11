import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { createRouter, createWebHistory } from 'vue-router'
import App from './App.vue'
import { routes } from './router'
import './assets/css/index.css'

// Capture PWA install event globally
window.addEventListener('beforeinstallprompt', (e) => {
  e.preventDefault()
  ;(window as any).deferredPrompt = e
})

const router = createRouter({
  history: createWebHistory(),
  routes,
})

// Navigation guard
router.beforeEach(async (to, _from, next) => {
  const { useAuthStore } = await import('./stores/auth')
  const authStore = useAuthStore()

  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    // Try to fetch user first
    try {
      await authStore.fetchUser()
      if (!authStore.isAuthenticated) {
        return next({ name: 'login' })
      }
    } catch {
      return next({ name: 'login' })
    }
  }

  if (to.meta.guest && authStore.isAuthenticated) {
    return next({ name: authStore.defaultRoute })
  }

  // Role check
  if (to.meta.roles && authStore.user) {
    const allowedRoles = to.meta.roles as string[]
    if (!allowedRoles.includes(authStore.user.role)) {
      return next({ name: authStore.defaultRoute })
    }
  }

  next()
})

const app = createApp(App)
app.use(createPinia())
app.use(router)
app.mount('#app')
