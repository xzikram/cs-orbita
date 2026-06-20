<script setup lang="ts">
import { useAuthStore } from './stores/auth'
import { onMounted, watch, ref, computed } from 'vue'
import { useOnline } from './composables/useOnline'
import { useRoute } from 'vue-router'

const authStore = useAuthStore()
const route = useRoute()
const { isOnline } = useOnline()
const showSyncBanner = ref(false)

// Route detection for mobile navbar adjustments
const isMobileRoute = computed(() => route.path.startsWith('/m'))

// PWA installation states
const pwaPlatform = ref<'android' | 'ios' | 'other'>('other')
const showInstallBanner = ref(false)
const deferredPrompt = ref<any>(null)

function checkPlatform() {
  const ua = navigator.userAgent.toLowerCase()
  const isAndroid = ua.indexOf('android') > -1
  const isIos = /ipad|iphone|ipod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1)
  
  if (isAndroid) {
    pwaPlatform.value = 'android'
  } else if (isIos) {
    pwaPlatform.value = 'ios'
  } else {
    pwaPlatform.value = 'other'
  }

  // Temporary dismiss check (7 days)
  const dismissedUntil = localStorage.getItem('pwa_install_dismissed_until')
  const isDismissed = dismissedUntil ? parseInt(dismissedUntil, 10) > Date.now() : false
  
  const isStandalone = window.matchMedia('(display-mode: standalone)').matches || (navigator as any).standalone
  
  showInstallBanner.value = !isStandalone && !isDismissed
}

function setupInstallPrompt() {
  if ((window as any).deferredPrompt) {
    deferredPrompt.value = (window as any).deferredPrompt
  }
  
  window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault()
    deferredPrompt.value = e
    checkPlatform()
  })
}

async function triggerNativeInstall() {
  const promptEvent = deferredPrompt.value
  if (!promptEvent) return
  
  promptEvent.prompt()
  const { outcome } = await promptEvent.userChoice
  if (outcome === 'accepted') {
    showInstallBanner.value = false
  }
  deferredPrompt.value = null
}

function dismissInstallBanner() {
  const expiry = Date.now() + 7 * 24 * 60 * 60 * 1000 // 7 days
  localStorage.setItem('pwa_install_dismissed_until', expiry.toString())
  showInstallBanner.value = false
}

onMounted(async () => {
  checkPlatform()
  setupInstallPrompt()
  
  try {
    await authStore.fetchUser()
  } catch {
    // Not logged in
  }
})

watch(isOnline, (online) => {
  if (online) {
    showSyncBanner.value = true
    setTimeout(() => { showSyncBanner.value = false }, 3000)
  }
})
</script>

<template>
  <div id="cleantrack-app" class="min-h-screen bg-background text-foreground">
    <!-- Online/Offline indicator -->
    <Transition name="slide-down">
      <div
        v-if="!isOnline"
        class="fixed top-0 left-0 right-0 z-[9999] bg-warning/90 text-warning-foreground text-center py-2 text-sm font-medium backdrop-blur-sm"
      >
        ⚡ Mode Offline — Data akan disinkronkan saat online
      </div>
    </Transition>

    <Transition name="slide-down">
      <div
        v-if="showSyncBanner"
        class="fixed top-0 left-0 right-0 z-[9999] bg-success/90 text-white text-center py-2 text-sm font-medium backdrop-blur-sm"
      >
        ✓ Kembali online — Menyinkronkan data...
      </div>
    </Transition>

    <!-- Global PWA Install Banner -->
    <Transition name="slide-up">
      <div 
        v-if="showInstallBanner" 
        class="pwa-install-banner animate-slide-up" 
        :class="{ 'with-mobile-nav': isMobileRoute }"
      >
        <div class="install-banner-header">
          <div class="flex items-center gap-3 text-left">
            <span class="app-icon-badge">📲</span>
            <div>
              <h3 class="text-sm font-bold text-foreground">Instal Aplikasi CleanTrack</h3>
              <p class="text-xs text-muted-foreground">Jadikan aplikasi lebih cepat & mudah diakses di HP.</p>
            </div>
          </div>
          <button class="close-banner-btn" @click="dismissInstallBanner" title="Sembunyikan">✕</button>
        </div>

        <div class="install-instructions mt-3 text-left">
          <!-- iOS Guide -->
          <div v-if="pwaPlatform === 'ios'">
            <p class="instruction-title text-xs font-semibold mb-1"><b>Cara pasang di iPhone (Safari):</b></p>
            <ol class="instruction-list text-xs">
              <li>Tekan tombol <b>Bagikan/Share</b> <span class="badge-icon">📤</span> di Safari.</li>
              <li>Pilih <b>"Tambahkan ke Layar Utama"</b> / <b>"Add to Home Screen"</b> <span class="badge-icon">➕</span>.</li>
            </ol>
          </div>

          <!-- Android Guide -->
          <div v-else-if="pwaPlatform === 'android'">
            <div v-if="deferredPrompt" class="flex flex-col gap-2">
              <button class="btn btn-primary text-xs w-full py-2 flex items-center justify-center gap-2" @click="triggerNativeInstall">
                <span>📲</span> Pasang Aplikasi Sekarang
              </button>
            </div>
            <div v-else>
              <p class="instruction-title text-xs font-semibold mb-1"><b>Cara pasang di Android (Chrome):</b></p>
              <ol class="instruction-list text-xs">
                <li>Ketuk menu <span class="badge-icon">⋮</span> di pojok kanan atas Chrome.</li>
                <li>Pilih opsi <b>"Instal Aplikasi"</b> atau <b>"Tambahkan ke Layar Utama"</b>.</li>
              </ol>
            </div>
          </div>

          <!-- Fallback/Desktop Guide -->
          <div v-else>
            <p class="instruction-title text-xs font-semibold mb-1"><b>Cara pasang di Browser:</b></p>
            <p class="instruction-desc text-xs text-muted-foreground">
              Tekan tombol 📥 <b>Pasang Aplikasi</b> di bagian kolom alamat URL browser Anda.
            </p>
          </div>
        </div>
      </div>
    </Transition>

    <RouterView />
  </div>
</template>

<style>
/* Online/Offline Banner Animations */
.slide-down-enter-active,
.slide-down-leave-active {
  transition: transform 0.3s ease, opacity 0.3s ease;
}
.slide-down-enter-from,
.slide-down-leave-to {
  transform: translateY(-100%);
  opacity: 0;
}

/* PWA Global Install Banner Styling */
.pwa-install-banner {
  position: fixed;
  bottom: 1rem;
  left: 1rem;
  right: 1rem;
  z-index: 9999;
  max-width: 440px;
  margin: 0 auto;
  background: hsl(var(--card) / 0.85);
  border: 1px solid hsl(var(--primary) / 0.3);
  backdrop-filter: blur(12px);
  padding: 1rem;
  border-radius: 1rem;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
  transition: bottom 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

/* Adjust position if mobile bottom navigation bar is visible (max-width of container is 480px) */
.pwa-install-banner.with-mobile-nav {
  bottom: 5.5rem;
}

.install-banner-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
}

.app-icon-badge {
  font-size: 1.75rem;
  line-height: 1;
}

.close-banner-btn {
  background: transparent;
  border: none;
  color: hsl(var(--muted-foreground));
  font-size: 1rem;
  cursor: pointer;
  padding: 0.125rem 0.25rem;
  transition: color 0.2s;
  line-height: 1;
}

.close-banner-btn:hover {
  color: hsl(var(--foreground));
}

.install-instructions {
  border-top: 1px solid hsl(var(--border) / 0.5);
  padding-top: 0.75rem;
}

.instruction-title {
  color: hsl(var(--foreground));
}

.instruction-list {
  padding-left: 1.25rem;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  color: hsl(var(--muted-foreground));
}

.badge-icon {
  background: hsl(var(--muted));
  border: 1px solid hsl(var(--border));
  padding: 0.05rem 0.25rem;
  border-radius: 0.25rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 0.7rem;
  font-weight: bold;
}

/* Transition Slide-Up */
.slide-up-enter-active,
.slide-up-leave-active {
  transition: transform 0.35s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.35s ease;
}
.slide-up-enter-from,
.slide-up-leave-to {
  transform: translateY(30px);
  opacity: 0;
}
</style>
