<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '../../lib/axios'

const areas = ref<any[]>([])
const loading = ref(true)

async function loadAreas() {
  loading.value = true
  try {
    const { data } = await api.get('/api/v1/auth/me')
    areas.value = data.user.areas
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadAreas()
})
</script>

<template>
  <div class="tasks-page animate-fade-in">
    <h2 class="text-xl font-bold mb-4">Area Tugas Saya</h2>
    
    <div v-if="loading" class="text-center py-8">
      <div class="spinner-small"></div>
    </div>
    
    <div v-else-if="areas.length === 0" class="text-center py-8 text-muted-foreground">
      Belum ada area yang ditugaskan kepada Anda.
    </div>
    
    <div v-else class="area-list">
      <div v-for="area in areas" :key="area.id" class="card area-card mb-3 animate-slide-up">
        <div class="area-code">{{ area.code }}</div>
        <h3 class="font-semibold">{{ area.name }}</h3>
      </div>
    </div>
  </div>
</template>

<style scoped>
.area-card {
  padding: 1rem;
  border-left: 4px solid hsl(var(--primary));
}

.area-code {
  font-size: 0.75rem;
  color: hsl(var(--muted-foreground));
  margin-bottom: 0.25rem;
}

.text-xl { font-size: 1.25rem; line-height: 1.75rem; }
.font-bold { font-weight: 700; }
.font-semibold { font-weight: 600; }
.mb-3 { margin-bottom: 0.75rem; }
.mb-4 { margin-bottom: 1rem; }
.py-8 { padding-top: 2rem; padding-bottom: 2rem; }
.text-center { text-align: center; }
.text-muted-foreground { color: hsl(var(--muted-foreground)); }

.spinner-small {
  width: 1.5rem;
  height: 1.5rem;
  border: 2px solid rgba(255,255,255,0.3);
  border-top-color: hsl(var(--primary));
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
  display: inline-block;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}
</style>
