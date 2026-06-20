<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../../lib/axios'
import { echo } from '../../lib/echo'

const route = useRoute()
const router = useRouter()

const linkUuid = route.params.linkUuid as string
const isVerifyingLink = ref(true)
const linkError = ref('')
const name = ref('')
const unit = ref('')
const isSubmitting = ref(false)

// Session state
const sessionUuid = ref(localStorage.getItem('audit_session_uuid') || '')
const sessionStatus = ref('') // 'pending', 'approved', 'rejected', ''
const checkInterval = ref<any>(null)

// Verify the link on load
onMounted(async () => {
  try {
    const res = await api.get(`/api/v1/public/audit-link/${linkUuid}`)
    if (!res.data.valid) {
      linkError.value = res.data.message || 'Tautan ini tidak valid atau kedaluwarsa.'
    } else {
      localStorage.setItem('audit_link_uuid', linkUuid)
      // If we already have a session, check its status
      if (sessionUuid.value) {
        await checkExistingSession()
      }
    }
  } catch (err: any) {
    linkError.value = err.response?.data?.message || 'Gagal memverifikasi tautan audit.'
  } finally {
    isVerifyingLink.value = false
  }
})

onUnmounted(() => {
  stopPolling()
  if (sessionUuid.value) {
    echo.leave(`audit-session.${sessionUuid.value}`)
  }
})

// Check if an existing session is already approved
async function checkExistingSession() {
  try {
    const res = await api.get(`/api/v1/public/audit-session/${sessionUuid.value}`)
    sessionStatus.value = res.data.status

    if (res.data.status === 'approved') {
      localStorage.setItem('audit_session_token', sessionUuid.value)
      router.push({ name: 'audit-reports', params: { sessionUuid: sessionUuid.value } })
    } else if (res.data.status === 'pending') {
      startRealtimeListener(sessionUuid.value)
    } else {
      // Clear session if rejected/expired
      clearSession()
    }
  } catch {
    clearSession()
  }
}

function clearSession() {
  localStorage.removeItem('audit_session_uuid')
  localStorage.removeItem('audit_session_token')
  sessionUuid.value = ''
  sessionStatus.value = ''
}

// Submit request access
async function submitAccessRequest() {
  if (!name.value.trim() || !unit.value.trim()) return

  isSubmitting.value = true
  try {
    const res = await api.post(`/api/v1/public/audit-link/${linkUuid}/session`, {
      name: name.value,
      unit: unit.value,
    })

    const uuid = res.data.session_uuid
    sessionUuid.value = uuid
    sessionStatus.value = 'pending'
    localStorage.setItem('audit_session_uuid', uuid)

    // Setup real-time listening & polling fallback
    startRealtimeListener(uuid)
  } catch (err: any) {
    alert(err.response?.data?.message || 'Gagal mengirimkan permintaan akses.')
  } finally {
    isSubmitting.value = false
  }
}

// Start WebSocket + Polling
function startRealtimeListener(uuid: string) {
  stopPolling()

  // 1. WebSocket (Echo)
  echo.channel(`audit-session.${uuid}`)
    .listen('.App\\Events\\AuditSessionApproved', (e: any) => {
      sessionStatus.value = e.status
      if (e.status === 'approved') {
        localStorage.setItem('audit_session_token', uuid)
        stopPolling()
        router.push({ name: 'audit-reports', params: { sessionUuid: uuid } })
      }
    })

  // 2. Polling Fallback (every 3 seconds)
  checkInterval.value = setInterval(async () => {
    try {
      const res = await api.get(`/api/v1/public/audit-session/${uuid}`)
      sessionStatus.value = res.data.status
      if (res.data.status === 'approved') {
        localStorage.setItem('audit_session_token', uuid)
        stopPolling()
        router.push({ name: 'audit-reports', params: { sessionUuid: uuid } })
      } else if (res.data.status === 'rejected') {
        stopPolling()
      }
    } catch (err) {
      // Ignore polling errors to prevent disruptive logs
    }
  }, 3000)
}

function stopPolling() {
  if (checkInterval.value) {
    clearInterval(checkInterval.value)
    checkInterval.value = null
  }
}
</script>

<template>
  <div class="audit-gateway-container">
    <div class="gateway-card animate-fade-in">
      <div class="card-header">
        <img src="/Logo%20RS%20JEC%20ORBITA.png" alt="Logo RS JEC Orbita" class="gateway-logo" />
        <h1 class="gateway-title">Portal Audit</h1>
        <p class="gateway-subtitle">Akses Laporan Sementara</p>
      </div>

      <!-- Verification state -->
      <div v-if="isVerifyingLink" class="gateway-state">
        <div class="spinner"></div>
        <p>Memverifikasi tautan audit...</p>
      </div>

      <!-- Invalid link state -->
      <div v-else-if="linkError" class="gateway-state error-state">
        <div class="icon-circle error">⚠️</div>
        <h3>Tautan Tidak Valid</h3>
        <p>{{ linkError }}</p>
      </div>

      <!-- Pending approval state -->
      <div v-else-if="sessionStatus === 'pending'" class="gateway-state pending-state">
        <div class="spinner-pulse"></div>
        <h3>Menunggu Persetujuan</h3>
        <p>Permintaan akses Anda telah diajukan ke administrator.</p>
        <div class="auditor-badge">
          <span>Pengaju: <strong>{{ name || 'Auditor' }}</strong> ({{ unit || 'SPI / Manajemen' }})</span>
        </div>
        <p class="waiting-hint">Halaman ini akan terbuka secara otomatis begitu Admin menyetujui akses Anda.</p>
        <button class="btn btn-secondary btn-sm" @click="clearSession">
          Batalkan & Kirim Ulang
        </button>
      </div>

      <!-- Rejected state -->
      <div v-else-if="sessionStatus === 'rejected'" class="gateway-state error-state">
        <div class="icon-circle error">❌</div>
        <h3>Akses Ditolak</h3>
        <p>Permintaan akses Anda ditolak oleh administrator.</p>
        <button class="btn btn-primary" @click="clearSession">
          Coba Lagi
        </button>
      </div>

      <!-- Request form state -->
      <form v-else @submit.prevent="submitAccessRequest" class="gateway-form">
        <p class="form-desc">
          Silakan masukkan Nama dan Unit/Instansi Anda untuk mengajukan izin akses melihat laporan.
        </p>

        <div class="form-group">
          <label for="name" class="label">Nama Lengkap</label>
          <input
            id="name"
            v-model="name"
            type="text"
            required
            class="input"
            placeholder="Contoh: Dr. Budi Santoso"
          />
        </div>

        <div class="form-group">
          <label for="unit" class="label">Unit / Lembaga</label>
          <input
            id="unit"
            v-model="unit"
            type="text"
            required
            class="input"
            placeholder="Contoh: SPI / Management"
          />
        </div>

        <button type="submit" :disabled="isSubmitting" class="btn btn-primary btn-block">
          <span v-if="isSubmitting">Mengirim...</span>
          <span v-else>Minta Izin Akses</span>
        </button>
      </form>
    </div>
  </div>
</template>

<style scoped>
.audit-gateway-container {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: radial-gradient(circle at top right, hsl(var(--primary) / 0.12), transparent 40%),
              radial-gradient(circle at bottom left, hsl(var(--accent) / 0.1), transparent 40%),
              hsl(var(--background));
  padding: 1.5rem;
}

.gateway-card {
  width: 100%;
  max-width: 440px;
  background: hsl(var(--card) / 0.7);
  backdrop-filter: blur(20px);
  border: 1px solid hsl(var(--border));
  border-radius: calc(var(--radius) * 1.5);
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.05);
  padding: 2.5rem 2rem;
}

.card-header {
  text-align: center;
  margin-bottom: 2rem;
}

.gateway-logo {
  height: 48px;
  margin-bottom: 1rem;
  object-fit: contain;
}

.gateway-title {
  font-size: 1.5rem;
  font-weight: 700;
  color: hsl(var(--foreground));
  margin-bottom: 0.25rem;
}

.gateway-subtitle {
  font-size: 0.875rem;
  color: hsl(var(--muted-foreground));
  font-weight: 500;
}

.gateway-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  padding: 1rem 0;
}

.gateway-state p {
  color: hsl(var(--muted-foreground));
  font-size: 0.9rem;
  margin-top: 0.5rem;
}

.gateway-state h3 {
  font-size: 1.25rem;
  font-weight: 600;
  margin-top: 1rem;
  margin-bottom: 0.5rem;
}

.error-state p {
  margin-bottom: 1.5rem;
}

.icon-circle {
  width: 56px;
  height: 56px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.75rem;
  background: hsl(var(--destructive) / 0.1);
  color: hsl(var(--destructive));
  border: 1px solid hsl(var(--destructive) / 0.2);
}

.icon-circle.error {
  background: hsl(var(--destructive) / 0.1);
}

.spinner {
  width: 40px;
  height: 40px;
  border: 3px solid hsl(var(--muted));
  border-top-color: hsl(var(--primary));
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

.spinner-pulse {
  width: 50px;
  height: 50px;
  background-color: hsl(var(--primary));
  border-radius: 50%;
  opacity: 0.6;
  animation: pulse 1.2s infinite ease-in-out;
}

.auditor-badge {
  background: hsl(var(--secondary));
  color: hsl(var(--secondary-foreground));
  padding: 0.5rem 1rem;
  border-radius: var(--radius);
  font-size: 0.8rem;
  margin: 1.25rem 0;
  border: 1px solid hsl(var(--border));
  width: 100%;
  text-align: center;
}

.waiting-hint {
  font-size: 0.75rem !important;
  line-height: 1.4;
  margin-bottom: 1.5rem;
}

.gateway-form {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.form-desc {
  font-size: 0.875rem;
  color: hsl(var(--muted-foreground));
  text-align: center;
  line-height: 1.5;
  margin-bottom: 0.5rem;
}

.form-group {
  display: flex;
  flex-direction: column;
}

.btn-block {
  width: 100%;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

@keyframes pulse {
  0% { transform: scale(0.8); opacity: 0.8; }
  50% { transform: scale(1.1); opacity: 0.3; }
  100% { transform: scale(0.8); opacity: 0.8; }
}
</style>
