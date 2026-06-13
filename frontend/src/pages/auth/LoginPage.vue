<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'

const authStore = useAuthStore()
const router = useRouter()

const email = ref('')
const password = ref('')
const error = ref('')
const showPassword = ref(false)

async function handleLogin() {
  error.value = ''
  try {
    await authStore.login(email.value, password.value)
    router.push({ name: authStore.defaultRoute })
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Login gagal. Periksa email dan password Anda.'
  }
}
</script>

<template>
  <div class="login-container">
    <div class="login-bg-pattern"></div>

    <div class="login-card animate-slide-up">
      <!-- Logo / Header -->
      <div class="login-header">
        <div class="logo-icon">
          <img src="/Logo%20RS%20JEC%20ORBITA.png" alt="Logo RS JEC Orbita" class="login-logo-img" />
        </div>
        <h1>CLEANTRACK <span class="text-accent">RS</span></h1>
        <p class="subtitle">Sistem Monitoring Cleaning Service</p>
      </div>

      <!-- Login Form -->
      <form @submit.prevent="handleLogin" class="login-form">
        <div v-if="error" class="error-alert">
          <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><circle cx="8" cy="8" r="7" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M8 4v4M8 10.5v.5"/></svg>
          {{ error }}
        </div>

        <div class="form-group">
          <label class="label" for="email">Username / Email</label>
          <input
            id="email"
            v-model="email"
            type="text"
            class="input"
            placeholder="cs002 atau contoh@cleantrack.id"
            required
            autocomplete="username"
          />
        </div>

        <div class="form-group">
          <label class="label" for="password">Password</label>
          <div class="password-wrapper">
            <input
              id="password"
              v-model="password"
              :type="showPassword ? 'text' : 'password'"
              class="input"
              placeholder="••••••••"
              required
              autocomplete="current-password"
            />
            <button
              type="button"
              class="password-toggle"
              @click="showPassword = !showPassword"
            >
              {{ showPassword ? '🙈' : '👁️' }}
            </button>
          </div>
        </div>

        <button type="submit" class="btn btn-primary btn-lg login-btn" :disabled="authStore.loading">
          <span v-if="authStore.loading" class="spinner"></span>
          <span v-else>Masuk</span>
        </button>
      </form>
    </div>
  </div>
</template>

<style scoped>
.login-container {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
  position: relative;
  overflow: hidden;
  background: hsl(var(--background));
}

.login-bg-pattern {
  position: absolute;
  inset: 0;
  background:
    radial-gradient(ellipse at 20% 50%, hsl(var(--primary) / 0.08) 0%, transparent 50%),
    radial-gradient(ellipse at 80% 20%, hsl(var(--accent) / 0.06) 0%, transparent 50%),
    radial-gradient(ellipse at 50% 80%, hsl(var(--success) / 0.04) 0%, transparent 50%);
}

.login-card {
  width: 100%;
  max-width: 420px;
  background: hsl(var(--card));
  border: 1px solid hsl(var(--border));
  border-radius: 1.25rem;
  padding: 2.5rem;
  position: relative;
  z-index: 1;
  box-shadow: 0 25px 60px rgba(0, 0, 0, 0.3);
}

.login-header {
  text-align: center;
  margin-bottom: 2rem;
}

.logo-icon {
  margin-bottom: 1.5rem;
  display: flex;
  justify-content: center;
  align-items: center;
  width: 100%;
}

.login-logo-img {
  max-width: 100%;
  height: auto;
  max-height: 80px;
  object-fit: contain;
}

.login-header h1 {
  font-size: 1.75rem;
  font-weight: 800;
  letter-spacing: -0.02em;
  color: hsl(var(--foreground));
}

.text-accent {
  color: hsl(var(--accent));
}

.subtitle {
  font-size: 0.875rem;
  color: hsl(var(--muted-foreground));
  margin-top: 0.25rem;
}

.hospital-name {
  font-size: 0.75rem;
  color: hsl(var(--muted-foreground));
  margin-top: 0.25rem;
  font-weight: 500;
}

.login-form {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.form-group {
  display: flex;
  flex-direction: column;
}

.password-wrapper {
  position: relative;
}

.password-toggle {
  position: absolute;
  right: 0.75rem;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  cursor: pointer;
  font-size: 1rem;
  padding: 0.25rem;
}

.login-btn {
  width: 100%;
  margin-top: 0.5rem;
}

.error-alert {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem 1rem;
  background: hsl(var(--destructive) / 0.1);
  border: 1px solid hsl(var(--destructive) / 0.2);
  border-radius: 0.625rem;
  color: hsl(var(--destructive));
  font-size: 0.875rem;
}

.spinner {
  width: 1.25rem;
  height: 1.25rem;
  border: 2px solid rgba(255,255,255,0.3);
  border-top-color: white;
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}
</style>
