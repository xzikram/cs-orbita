<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import api from '../../lib/axios'
import { savePendingActivity, getCachedArea } from '../../lib/db'
import { useOnline } from '../../composables/useOnline'
import { v4 as uuidv4 } from 'uuid'

const props = defineProps<{ areaId: string }>()
const router = useRouter()
const route = useRoute()
const { isOnline } = useOnline()

const uuid = route.query.uuid as string || uuidv4()
const shiftId = parseInt(route.query.shift_id as string)

const loading = ref(true)
const submitting = ref(false)
const area = ref<any>(null)
const checklist = ref<any[]>([])
const currentShift = ref<any>(null)
const startTime = ref(new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }))

const notes = ref('')
const photos = ref<Array<{ file: File; type: string; url: string }>>([])
const maxPhotos = 4

async function loadData() {
  loading.value = true
  try {
    if (isOnline.value) {
      const { data } = await api.get(`/api/v1/areas/${props.areaId}/checklist`)
      area.value = data.data.area
      checklist.value = data.data.checklist.map((group: any) => ({
        room_name: group.room_name,
        items: group.items.map((item: any) => ({
          ...item,
          is_checked: false
        }))
      }))
      currentShift.value = data.data.schedules.find((s: any) => s.shift_id === shiftId)
    } else {
      // Offline fallback
      const cached = await getCachedArea(parseInt(props.areaId))
      if (cached) {
        area.value = cached
        checklist.value = cached.checklist.map((group: any) => ({
          room_name: group.room_name,
          items: group.items.map((item: any) => ({
            ...item,
            is_checked: false
          }))
        }))
      } else {
        alert('Data area tidak tersedia offline. Silakan online dulu.')
        router.push({ name: 'mobile-dashboard' })
      }
    }
  } catch (e) {
    console.error(e)
    alert('Gagal memuat data checklist')
  } finally {
    loading.value = false
  }
}

function compressImage(file: File, maxWidth = 1200, quality = 0.7): Promise<File> {
  return new Promise((resolve) => {
    const reader = new FileReader()
    reader.readAsDataURL(file)
    reader.onload = (event) => {
      const img = new Image()
      img.src = event.target?.result as string
      img.onload = () => {
        const canvas = document.createElement('canvas')
        let width = img.width
        let height = img.height

        if (width > maxWidth) {
          height = Math.round((height * maxWidth) / width)
          width = maxWidth
        }

        canvas.width = width
        canvas.height = height

        const ctx = canvas.getContext('2d')
        ctx?.drawImage(img, 0, 0, width, height)

        canvas.toBlob(
          (blob) => {
            if (blob) {
              const compressedFile = new File([blob], file.name, {
                type: 'image/jpeg',
                lastModified: Date.now()
              })
              resolve(compressedFile)
            } else {
              resolve(file)
            }
          },
          'image/jpeg',
          quality
        )
      }
    }
  })
}

async function handlePhotoUpload(event: Event, type: 'before' | 'after') {
  const input = event.target as HTMLInputElement
  if (!input.files || input.files.length === 0) return

  if (photos.value.length >= maxPhotos) {
    alert(`Maksimal ${maxPhotos} foto`)
    return
  }

  const originalFile = input.files[0]
  try {
    const compressedFile = await compressImage(originalFile)
    const url = URL.createObjectURL(compressedFile)
    photos.value.push({ file: compressedFile, type, url })
  } catch (err) {
    console.error('Gagal mengompresi gambar:', err)
    const url = URL.createObjectURL(originalFile)
    photos.value.push({ file: originalFile, type, url })
  }
}

function removePhoto(index: number) {
  URL.revokeObjectURL(photos.value[index].url)
  photos.value.splice(index, 1)
}

const allRequiredChecked = computed(() => {
  return checklist.value.every(group => 
    group.items
      .filter((item: any) => item.is_required)
      .every((item: any) => item.is_checked)
  )
})

async function submitActivity() {
  if (!allRequiredChecked.value) {
    alert('Pastikan semua objek wajib telah dibersihkan.')
    return
  }

  submitting.value = true
  const endTime = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
  const date = new Date().toISOString().split('T')[0]

  const activityData = {
    uuid,
    area_id: parseInt(props.areaId),
    shift_id: shiftId,
    date,
    start_time: startTime.value,
    end_time: endTime,
    notes: notes.value,
    items: checklist.value.flatMap(group => 
      group.items.map((item: any) => ({
        area_object_id: item.id, // using the Pivot ID
        is_checked: item.is_checked
      }))
    )
  }

  try {
    if (isOnline.value) {
      // Online Submit
      const { data } = await api.post('/api/v1/activities', activityData)
      
      // Upload photos sequentially
      if (photos.value.length > 0) {
        const formData = new FormData()
        photos.value.forEach((photo, index) => {
          formData.append(`photos[${index}][file]`, photo.file)
          formData.append(`photos[${index}][type]`, photo.type)
        })
        await api.post(`/api/v1/activities/${data.data.id}/photos`, formData, {
          headers: { 'Content-Type': 'multipart/form-data' }
        })
      }
    } else {
      // Offline Submit -> IndexedDB
      const offlinePhotos = await Promise.all(photos.value.map(async p => ({
        blob: new Blob([await p.file.arrayBuffer()], { type: p.file.type }),
        type: p.type as 'before' | 'after'
      })))

      await savePendingActivity({
        ...activityData,
        area_name: area.value.name,
        photos: offlinePhotos,
        created_at: new Date().toISOString(),
        sync_status: 'pending'
      })
    }

    // Success -> redirect
    router.push({ name: 'mobile-dashboard' })
  } catch (e) {
    console.error(e)
    alert('Gagal menyimpan aktivitas')
  } finally {
    submitting.value = false
  }
}

onMounted(() => {
  loadData()
})
</script>

<template>
  <div class="checklist-page" v-if="!loading">
    <div class="area-header animate-slide-up">
      <div class="area-code">{{ area.code }}</div>
      <h2 class="text-xl font-bold">{{ area.name }}</h2>
      <div class="time-info mt-2">
        <span class="badge badge-primary">Mulai: {{ startTime }}</span>
      </div>
    </div>

    <!-- Checklist -->
    <div class="card p-0 mt-4 overflow-hidden animate-slide-up stagger-1">
      <div v-for="(group, gIndex) in checklist" :key="gIndex">
        <div class="p-4 border-b border-white/10 bg-white/5">
          <h3 class="font-semibold text-sm">{{ group.room_name }}</h3>
        </div>
        <div class="checklist-list">
          <label 
            v-for="item in group.items" 
            :key="item.id" 
            class="checklist-item"
            :class="{ checked: item.is_checked }"
          >
            <div class="flex items-center gap-3">
              <span class="item-icon">{{ item.icon === 'trash' ? '🗑️' : item.icon === 'floor' ? '🧹' : '🧽' }}</span>
              <div>
                <div class="text-sm font-medium">{{ item.name }}</div>
                <div v-if="item.is_required" class="text-xs text-warning">Wajib</div>
              </div>
            </div>
            <div class="custom-checkbox">
              <input type="checkbox" v-model="item.is_checked" />
              <div class="checkbox-box"></div>
            </div>
          </label>
        </div>
      </div>
    </div>

    <!-- Evidence Photos -->
    <div class="card mt-4 animate-slide-up stagger-2">
      <h3 class="font-semibold text-sm mb-3">Foto Bukti (Maks {{ maxPhotos }})</h3>
      
      <div class="photo-grid">
        <div v-for="(photo, idx) in photos" :key="idx" class="photo-preview">
          <img :src="photo.url" alt="Preview" />
          <div class="photo-type-badge">{{ photo.type === 'before' ? 'Sebelum' : 'Sesudah' }}</div>
          <button class="remove-photo" @click="removePhoto(idx)">×</button>
        </div>

        <div v-if="photos.length < maxPhotos" class="photo-add-buttons">
          <label class="photo-btn btn-secondary">
            <input type="file" accept="image/*" capture="environment" class="hidden" @change="e => handlePhotoUpload(e, 'before')" />
            <span>📷 Sebelum</span>
          </label>
          <label class="photo-btn btn-primary">
            <input type="file" accept="image/*" capture="environment" class="hidden" @change="e => handlePhotoUpload(e, 'after')" />
            <span>📸 Sesudah</span>
          </label>
        </div>
      </div>
    </div>

    <!-- Notes -->
    <div class="card mt-4 mb-6 animate-slide-up stagger-3">
      <h3 class="font-semibold text-sm mb-2">Catatan (Opsional)</h3>
      <textarea 
        v-model="notes" 
        class="input textarea bg-background" 
        placeholder="Tulis kondisi area atau masalah yang ditemukan..."
      ></textarea>
    </div>

    <!-- Submit Button -->
    <button 
      class="btn-scan mb-4 animate-slide-up stagger-4" 
      :class="{ 'opacity-50': !allRequiredChecked || submitting }"
      :disabled="!allRequiredChecked || submitting"
      @click="submitActivity"
    >
      <span v-if="submitting" class="spinner-small"></span>
      <span v-else>Selesai & Simpan</span>
    </button>
    <p v-if="!allRequiredChecked" class="text-center text-xs text-warning mb-6">
      Selesaikan semua objek wajib untuk menyimpan.
    </p>

  </div>
  
  <div v-else class="flex flex-col items-center justify-center h-64">
    <div class="spinner-large"></div>
    <p class="mt-4 text-muted-foreground">Memuat data area...</p>
  </div>
</template>

<style scoped>
.area-header {
  background: linear-gradient(135deg, hsl(222, 47%, 12%), hsl(222, 47%, 8%));
  padding: 1.5rem;
  border-radius: 1rem;
  border: 1px solid hsl(222, 47%, 16%);
  text-align: center;
}

.area-code {
  font-size: 0.75rem;
  font-weight: 600;
  color: hsl(262, 83%, 65%);
  letter-spacing: 0.05em;
  margin-bottom: 0.25rem;
}

.checklist-list {
  display: flex;
  flex-direction: column;
}

.checklist-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem;
  border-bottom: 1px solid hsl(var(--border));
  cursor: pointer;
  transition: all 0.2s;
}

.checklist-item:last-child {
  border-bottom: none;
}

.checklist-item.checked {
  background: hsl(var(--success) / 0.05);
}

.item-icon {
  font-size: 1.5rem;
  width: 2.5rem;
  height: 2.5rem;
  display: flex;
  align-items: center;
  justify-content: center;
  background: hsl(var(--muted));
  border-radius: 0.5rem;
}

.checked .item-icon {
  background: hsl(var(--success) / 0.2);
}

/* Custom Checkbox */
.custom-checkbox {
  position: relative;
  width: 1.5rem;
  height: 1.5rem;
}

.custom-checkbox input {
  opacity: 0;
  width: 0;
  height: 0;
  position: absolute;
}

.checkbox-box {
  width: 1.5rem;
  height: 1.5rem;
  border: 2px solid hsl(var(--muted-foreground));
  border-radius: 0.375rem;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
}

input:checked ~ .checkbox-box {
  background: hsl(var(--success));
  border-color: hsl(var(--success));
}

input:checked ~ .checkbox-box::after {
  content: '✓';
  color: white;
  font-size: 1rem;
  font-weight: bold;
}

/* Photo Upload */
.photo-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 0.75rem;
}

.photo-preview {
  position: relative;
  aspect-ratio: 1;
  border-radius: 0.5rem;
  overflow: hidden;
  border: 1px solid hsl(var(--border));
}

.photo-preview img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.photo-type-badge {
  position: absolute;
  bottom: 0.25rem;
  left: 0.25rem;
  background: rgba(0,0,0,0.6);
  color: white;
  font-size: 0.625rem;
  padding: 0.125rem 0.375rem;
  border-radius: 0.25rem;
  backdrop-filter: blur(4px);
}

.remove-photo {
  position: absolute;
  top: 0.25rem;
  right: 0.25rem;
  width: 1.5rem;
  height: 1.5rem;
  border-radius: 50%;
  background: hsl(var(--destructive));
  color: white;
  border: none;
  font-size: 1rem;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

.photo-add-buttons {
  grid-column: 1 / -1;
  display: flex;
  gap: 0.5rem;
}

.photo-btn {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0.75rem;
  border-radius: 0.5rem;
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
}

.hidden { display: none; }

/* Utils */
.mt-2 { margin-top: 0.5rem; }
.mt-4 { margin-top: 1rem; }
.mb-2 { margin-bottom: 0.5rem; }
.mb-3 { margin-bottom: 0.75rem; }
.mb-4 { margin-bottom: 1rem; }
.mb-6 { margin-bottom: 1.5rem; }
.p-0 { padding: 0; }
.p-4 { padding: 1rem; }
.text-center { text-align: center; }
.text-xl { font-size: 1.25rem; line-height: 1.75rem; }
.font-bold { font-weight: 700; }
.font-semibold { font-weight: 600; }
.font-medium { font-weight: 500; }
.text-sm { font-size: 0.875rem; line-height: 1.25rem; }
.text-xs { font-size: 0.75rem; line-height: 1rem; }
.text-warning { color: hsl(var(--warning)); }
.text-muted-foreground { color: hsl(var(--muted-foreground)); }
.flex { display: flex; }
.flex-col { flex-direction: column; }
.items-center { align-items: center; }
.justify-center { justify-content: center; }
.gap-3 { gap: 0.75rem; }
.border-b { border-bottom-width: 1px; }
.border-white\/10 { border-color: rgba(255,255,255,0.1); }
.bg-white\/5 { background-color: rgba(255,255,255,0.05); }
.overflow-hidden { overflow: hidden; }
.opacity-50 { opacity: 0.5; }

.spinner-small {
  width: 1rem;
  height: 1rem;
  border: 2px solid rgba(255,255,255,0.3);
  border-top-color: white;
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
  display: inline-block;
}

.spinner-large {
  width: 2rem;
  height: 2rem;
  border: 3px solid rgba(255,255,255,0.3);
  border-top-color: hsl(var(--primary));
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}
</style>
