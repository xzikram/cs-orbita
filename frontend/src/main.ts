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

  // Wait for initial session fetch if not yet initialized
  if (!authStore.initialized) {
    try {
      await authStore.fetchUser()
    } catch {
      // Ignore
    }
  }

  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    return next({ name: 'login' })
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

// Handle 401 auth errors via custom event (from axios interceptor)
// Using debounce flag to prevent multiple rapid redirects
let isRedirectingToLogin = false
window.addEventListener('auth:unauthorized', () => {
  if (router.currentRoute.value.meta.requiresAuth && !isRedirectingToLogin && router.currentRoute.value.name !== 'login') {
    isRedirectingToLogin = true
    router.push({ name: 'login' }).finally(() => {
      // Reset flag after a short delay to allow future redirects
      setTimeout(() => { isRedirectingToLogin = false }, 2000)
    })
  }
})

app.mount('#app')
