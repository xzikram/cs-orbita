<script setup lang="ts">
import { useAuthStore } from '../../stores/auth'
import { useRouter } from 'vue-router'

const authStore = useAuthStore()
const router = useRouter()

async function handleLogout() {
  await authStore.logout()
  router.push({ name: 'login' })
}
</script>

<template>
  <div class="profile-page animate-fade-in">
    <h2 class="text-xl font-bold mb-6">Profil Saya</h2>
    
    <div class="card text-center mb-6 animate-slide-up">
      <div class="avatar mx-auto mb-3">
        {{ authStore.user?.name?.[0] || '?' }}
      </div>
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

    <button 
      class="btn btn-destructive w-full py-3 animate-slide-up stagger-2" 
      @click="handleLogout"
    >
      Logout
    </button>
  </div>
</template>

<style scoped>
.avatar {
  width: 4rem;
  height: 4rem;
  border-radius: 50%;
  background: linear-gradient(135deg, hsl(var(--primary)), hsl(var(--accent)));
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
  font-weight: 700;
  color: white;
}

.mx-auto { margin-left: auto; margin-right: auto; }
.mb-1 { margin-bottom: 0.25rem; }
.mb-3 { margin-bottom: 0.75rem; }
.mb-6 { margin-bottom: 1.5rem; }
.p-0 { padding: 0; }
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
