<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { Html5Qrcode } from 'html5-qrcode'
import api from '../../lib/axios'

const router = useRouter()
const error = ref('')
const loading = ref(false)
let html5QrCode: Html5Qrcode | null = null

async function onScanSuccess(decodedText: string) {
  if (loading.value) return
  
  loading.value = true
  error.value = ''

  try {
    // Parse JSON if valid
    let uuid = decodedText
    try {
      const data = JSON.parse(decodedText)
      if (data.uuid && data.type === 'cleantrack') {
        uuid = data.uuid
      }
    } catch {
      // Not JSON, assume raw UUID
    }

    // Call API to verify QR
    const { data } = await api.get(`/api/v1/qr/scan/${uuid}`)
    
    // Stop camera
    if (html5QrCode) {
      await html5QrCode.stop()
    }

    if (data.data.already_cleaned) {
      error.value = 'Area ini sudah dibersihkan pada shift ini.'
      loading.value = false
      return
    }

    // Go to checklist
    router.push({ 
      name: 'mobile-checklist', 
      params: { areaId: data.data.area.id },
      query: { 
        uuid: uuid,
        shift_id: data.data.current_shift.id 
      }
    })
  } catch (err: any) {
    error.value = err.response?.data?.message || 'QR Code tidak valid.'
    loading.value = false
  }
}

function onScanFailure(errorMessage: string) {
  // Ignore regular frame failures
}

onMounted(() => {
  html5QrCode = new Html5Qrcode("qr-reader")
  
  const config = { fps: 10, qrbox: { width: 250, height: 250 } }
  
  html5QrCode.start(
    { facingMode: "environment" },
    config,
    onScanSuccess,
    onScanFailure
  ).catch(err => {
    error.value = "Kamera tidak dapat diakses. Pastikan Anda telah memberikan izin."
    console.error(err)
  })
})

onUnmounted(() => {
  if (html5QrCode && html5QrCode.isScanning) {
    html5QrCode.stop().catch(console.error)
  }
})
</script>

<template>
  <div class="scan-container animate-fade-in">
    <div class="scan-header text-center mb-6">
      <h2 class="text-xl font-bold mb-2">Scan QR Area</h2>
      <p class="text-sm text-muted-foreground">Arahkan kamera ke QR Code yang terdapat pada area/ruangan</p>
    </div>

    <div v-if="error" class="error-banner mb-4">
      {{ error }}
    </div>

    <div class="qr-wrapper">
      <div id="qr-reader"></div>
      
      <!-- Overlay Loader -->
      <div v-if="loading" class="qr-loader-overlay">
        <div class="spinner-large"></div>
        <p class="mt-3 text-sm font-medium">Memverifikasi Area...</p>
      </div>
    </div>

    <div class="manual-entry mt-6 text-center">
      <p class="text-xs text-muted-foreground mb-3">Atau masukkan kode area secara manual</p>
      <button class="btn btn-secondary w-full" @click="router.push({ name: 'mobile-dashboard' })">
        Kembali ke Dashboard
      </button>
    </div>
  </div>
</template>

<style scoped>
.scan-container {
  display: flex;
  flex-direction: column;
  height: 100%;
}

.qr-wrapper {
  position: relative;
  border-radius: 1rem;
  overflow: hidden;
  background: #000;
  box-shadow: 0 10px 30px rgba(0,0,0,0.5);
  border: 1px solid hsl(var(--border));
}

#qr-reader {
  width: 100%;
  min-height: 300px;
}

#qr-reader video {
  object-fit: cover !important;
  border-radius: 1rem;
}

.qr-loader-overlay {
  position: absolute;
  inset: 0;
  background: rgba(0,0,0,0.8);
  backdrop-filter: blur(4px);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  z-index: 10;
  color: white;
}

.error-banner {
  background: hsl(var(--destructive) / 0.15);
  color: hsl(var(--destructive));
  padding: 0.75rem 1rem;
  border-radius: 0.5rem;
  font-size: 0.875rem;
  text-align: center;
  border: 1px solid hsl(var(--destructive) / 0.3);
}

.text-center { text-align: center; }
.text-xl { font-size: 1.25rem; line-height: 1.75rem; }
.font-bold { font-weight: 700; }
.mb-2 { margin-bottom: 0.5rem; }
.mb-4 { margin-bottom: 1rem; }
.mb-6 { margin-bottom: 1.5rem; }
.mt-3 { margin-top: 0.75rem; }
.mt-6 { margin-top: 1.5rem; }
.w-full { width: 100%; }

.spinner-large {
  width: 3rem;
  height: 3rem;
  border: 4px solid rgba(255,255,255,0.3);
  border-top-color: hsl(var(--primary));
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* Override html5-qrcode styles */
:deep(#qr-reader__scan_region) {
  background: transparent;
}
:deep(#qr-reader__dashboard) {
  padding: 1rem;
  background: hsl(var(--card));
}
:deep(#qr-reader button) {
  background: hsl(var(--primary));
  color: white;
  border: none;
  padding: 0.5rem 1rem;
  border-radius: 0.5rem;
  cursor: pointer;
  margin: 0.5rem;
}
:deep(#qr-reader a) {
  display: none;
}
</style>
