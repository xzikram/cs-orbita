<script setup lang="ts">
import { ref } from 'vue'
import { useAuthStore } from '../../stores/auth'
import { useRouter } from 'vue-router'
import api from '../../lib/axios'
import { cacheUserProfile } from '../../lib/db'

const authStore = useAuthStore()
const router = useRouter()

// Avatar upload state
const fileInput = ref<HTMLInputElement | null>(null)
const uploadingAvatar = ref(false)
const avatarError = ref('')
const avatarSuccess = ref('')

// Password change state
const currentPassword = ref('')
const password = ref('')
const passwordConfirmation = ref('')
const pwdSubmitting = ref(false)
const pwdError = ref('')
const pwdSuccess = ref('')

async function handleLogout() {
  await authStore.logout()
  router.push({ name: 'login' })
}

function triggerAvatarUpload() {
  fileInput.value?.click()
}

async function onAvatarSelected(event: Event) {
  const input = event.target as HTMLInputElement
  if (!input.files || input.files.length === 0) return

  const file = input.files[0]
  // Max size 2MB
  if (file.size > 2 * 1024 * 1024) {
    avatarError.value = 'Ukuran foto maksimal 2MB'
    avatarSuccess.value = ''
    input.value = ''
    return
  }

  uploadingAvatar.value = true
  avatarError.value = ''
  avatarSuccess.value = ''

  const formData = new FormData()
  formData.append('avatar', file)
  formData.append('_method', 'PUT') // Method spoofing for Laravel PUT with multipart upload

  try {
    const { data } = await api.post('/api/v1/auth/profile', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    })
    authStore.user = data.user
    await cacheUserProfile(data.user)
    avatarSuccess.value = 'Foto profil berhasil diperbarui'
  } catch (err: any) {
    console.error(err)
    avatarError.value = err.response?.data?.message || 'Gagal memperbarui foto profil'
  } finally {
    uploadingAvatar.value = false
    input.value = ''
  }
}

async function handlePasswordChange() {
  if (!currentPassword.value || !password.value || !passwordConfirmation.value) {
    pwdError.value = 'Semua field harus diisi'
    pwdSuccess.value = ''
    return
  }

  if (password.value.length < 8) {
    pwdError.value = 'Password baru minimal 8 karakter'
    pwdSuccess.value = ''
    return
  }

  if (password.value !== passwordConfirmation.value) {
    pwdError.value = 'Konfirmasi password baru tidak cocok'
    pwdSuccess.value = ''
    return
  }

  pwdSubmitting.value = true
  pwdError.value = ''
  pwdSuccess.value = ''

  try {
    await api.put('/api/v1/auth/password', {
      current_password: currentPassword.value,
      password: password.value,
      password_confirmation: passwordConfirmation.value,
    })
    pwdSuccess.value = 'Password berhasil diubah'
    // Clear fields
    currentPassword.value = ''
    password.value = ''
    passwordConfirmation.value = ''
  } catch (err: any) {
    console.error(err)
    if (err.response?.status === 422) {
      const errors = err.response.data.errors
      if (errors?.current_password) {
        pwdError.value = errors.current_password[0]
      } else if (errors?.password) {
        pwdError.value = errors.password[0]
      } else {
        pwdError.value = err.response.data.message || 'Gagal mengubah password'
      }
    } else {
      pwdError.value = err.response?.data?.message || 'Gagal mengubah password'
    }
  } finally {
    pwdSubmitting.value = false
  }
}
</script>

<template>
  <div class="profile-page animate-fade-in">
    <h2 class="text-xl font-bold mb-6">Profil Saya</h2>
    
    <div class="card text-center mb-6 animate-slide-up">
      <!-- Interactive Avatar Wrapper with Edit Overlay -->
      <div class="avatar-container mx-auto mb-3">
        <div class="avatar-wrapper" @click="triggerAvatarUpload">
          <div class="avatar">
            <img v-if="authStore.user?.avatar" :src="authStore.user.avatar" alt="Avatar" class="avatar-img" />
            <span v-else>{{ authStore.user?.name?.[0] || '?' }}</span>
          </div>
          <div class="avatar-overlay" :class="{ 'uploading': uploadingAvatar }">
            <span v-if="uploadingAvatar" class="spinner-small"></span>
            <span v-else class="camera-icon">📷</span>
          </div>
        </div>
      </div>
      <input type="file" ref="fileInput" accept="image/*" class="hidden" @change="onAvatarSelected" />
      
      <p v-if="avatarSuccess" class="text-xs text-success mb-2 font-medium">✅ {{ avatarSuccess }}</p>
      <p v-if="avatarError" class="text-xs text-destructive mb-2 font-medium">⚠️ {{ avatarError }}</p>

      <h3 class="font-bold text-lg">{{ authStore.user?.name }}</h3>
      <p class="text-sm text-primary mb-1">{{ authStore.user?.employee_id }}</p>
      <p class="text-xs text-muted-foreground">{{ authStore.user?.role_label }}</p>
    </div>

    <div class="card mb-6 p-0 overflow-hidden animate-slide-up stagger-1">
      <div class="list-item">
        <span class="text-muted-foreground text-sm">Email</span>
        <span class="font-medium text-sm">{{ authStore.user?.email }}</span>
      </div>
      <div class="list-item">
        <span class="text-muted-foreground text-sm">No. HP</span>
        <span class="font-medium text-sm">{{ authStore.user?.phone || '-' }}</span>
      </div>
    </div>

    <!-- Password Change Form -->
    <div class="card mb-6 animate-slide-up stagger-2">
      <h3 class="font-bold text-sm mb-4">Ganti Password</h3>
      
      <div v-if="pwdSuccess" class="alert alert-success mb-4 text-xs">
        ✅ {{ pwdSuccess }}
      </div>
      <div v-if="pwdError" class="alert alert-error mb-4 text-xs">
        ⚠️ {{ pwdError }}
      </div>

      <form @submit.prevent="handlePasswordChange" class="form-container">
        <div class="form-group mb-3">
          <label class="form-label text-xs text-muted-foreground mb-1 block">Password Saat Ini</label>
          <input 
            type="password" 
            v-model="currentPassword" 
            class="input bg-background/50 border-white/10" 
            placeholder="Ketik password lama..."
            required 
          />
        </div>

        <div class="form-group mb-3">
          <label class="form-label text-xs text-muted-foreground mb-1 block">Password Baru</label>
          <input 
            type="password" 
            v-model="password" 
            class="input bg-background/50 border-white/10" 
            placeholder="Minimal 8 karakter..."
            required 
          />
        </div>

        <div class="form-group mb-4">
          <label class="form-label text-xs text-muted-foreground mb-1 block">Konfirmasi Password Baru</label>
          <input 
            type="password" 
            v-model="passwordConfirmation" 
            class="input bg-background/50 border-white/10" 
            placeholder="Ketik ulang password baru..."
            required 
          />
        </div>

        <button 
          type="submit" 
          class="btn btn-primary w-full py-2-5" 
          :disabled="pwdSubmitting"
        >
          <span v-if="pwdSubmitting" class="spinner-small mr-2"></span>
          <span>Simpan Password</span>
        </button>
      </form>
    </div>

    <button 
      class="btn btn-destructive w-full py-3 animate-slide-up stagger-3" 
      @click="handleLogout"
    >
      Logout
    </button>
  </div>
</template>

<style scoped>
.avatar-container {
  width: 5rem;
  height: 5rem;
  position: relative;
}

.avatar-wrapper {
  width: 100%;
  height: 100%;
  position: relative;
  cursor: pointer;
  border-radius: 50%;
  overflow: hidden;
  border: 2px solid hsl(var(--primary) / 0.3);
  transition: all 0.2s;
}

.avatar-wrapper:hover {
  border-color: hsl(var(--primary));
  transform: scale(1.02);
}

.avatar {
  width: 100%;
  height: 100%;
  background: linear-gradient(135deg, hsl(var(--primary)), hsl(var(--accent)));
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.75rem;
  font-weight: 700;
  color: white;
}

.avatar-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.avatar-overlay {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.4);
  display: flex;
  align-items: center;
  justify-content: center;
  opacity: 0;
  transition: opacity 0.2s;
}

.avatar-wrapper:hover .avatar-overlay,
.avatar-overlay.uploading {
  opacity: 1;
}

.camera-icon {
  font-size: 1.25rem;
  color: white;
  filter: drop-shadow(0 2px 4px rgba(0,0,0,0.5));
}

.text-success {
  color: hsl(var(--success));
}

.text-destructive {
  color: hsl(var(--destructive));
}

/* Form Styles */
.form-container {
  text-align: left;
}

.form-group {
  display: flex;
  flex-direction: column;
}

.block {
  display: block;
}

/* Alerts */
.alert {
  padding: 0.75rem 1rem;
  border-radius: var(--radius);
  font-weight: 500;
  border: 1px solid transparent;
}

.alert-success {
  background: hsl(var(--success) / 0.1);
  color: hsl(var(--success));
  border-color: hsl(var(--success) / 0.2);
}

.alert-error {
  background: hsl(var(--destructive) / 0.1);
  color: hsl(var(--destructive));
  border-color: hsl(var(--destructive) / 0.2);
}

/* Spinner */
.spinner-small {
  width: 1rem;
  height: 1rem;
  border: 2px solid rgba(255,255,255,0.3);
  border-top-color: white;
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
  display: inline-block;
}

.mr-2 {
  margin-right: 0.5rem;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.mx-auto { margin-left: auto; margin-right: auto; }
.mb-1 { margin-bottom: 0.25rem; }
.mb-2 { margin-bottom: 0.5rem; }
.mb-3 { margin-bottom: 0.75rem; }
.mb-4 { margin-bottom: 1rem; }
.mb-6 { margin-bottom: 1.5rem; }
.p-0 { padding: 0; }
.py-2-5 { padding-top: 0.625rem; padding-bottom: 0.625rem; }
.py-3 { padding-top: 0.75rem; padding-bottom: 0.75rem; }
.w-full { width: 100%; }
.overflow-hidden { overflow: hidden; }

.text-xl { font-size: 1.25rem; line-height: 1.75rem; }
.text-lg { font-size: 1.125rem; line-height: 1.75rem; }
.text-sm { font-size: 0.875rem; line-height: 1.25rem; }
.text-xs { font-size: 0.75rem; line-height: 1rem; }
.font-bold { font-weight: 700; }
.font-medium { font-weight: 500; }
.text-center { text-align: center; }
.text-primary { color: hsl(var(--primary)); }
.text-muted-foreground { color: hsl(var(--muted-foreground)); }

.list-item {
  display: flex;
  justify-content: space-between;
  padding: 1rem;
  border-bottom: 1px solid hsl(var(--border));
}

.list-item:last-child {
  border-bottom: none;
}
</style>
