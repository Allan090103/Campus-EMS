<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../services/api'
import { useAuthStore } from '../stores/auth'
import AppIcon from '../components/AppIcon.vue'
import { formatDateTime, isPast } from '../utils/format'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const event = ref(null)
const myReg = ref(null)        // this student's registration row, if any
const loading = ref(true)
const error = ref('')

async function load() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await api.get(`/events/${route.params.id}`)
    event.value = data
    if (auth.isStudent || auth.isAdmin) {
      const my = await api.get('/registrations/my')
      myReg.value = my.data.find((r) => r.event_id === Number(route.params.id)) || null
    }
  } catch (e) {
    event.value = null
    error.value = e.response?.data?.error || 'Event not available.'
  } finally {
    loading.value = false
  }
}
onMounted(load)

const spotsLeft = computed(() =>
  event.value ? Math.max(0, event.value.capacity - event.value.registered_count) : 0
)
const fillPct = computed(() =>
  event.value && event.value.capacity > 0
    ? Math.round((event.value.registered_count / event.value.capacity) * 100)
    : 0
)
const isFull = computed(() => spotsLeft.value === 0)
const past = computed(() => event.value && isPast(event.value.event_datetime))

async function register() {
  try {
    await api.post('/registrations', { event_id: event.value.id })
    await load()
  } catch (e) {
    alert(e.response?.data?.error || 'Could not register.')
  }
}
async function cancel() {
  if (!confirm('Cancel your registration for this event?')) return
  try {
    await api.delete(`/registrations/${myReg.value.id}`)
    await load()
  } catch (e) {
    alert(e.response?.data?.error || 'Could not cancel.')
  }
}
</script>

<template>
  <main class="page">
    <div class="container narrow">
      <a class="back-link" @click="router.back()"><AppIcon name="back" :size="16" /> Back</a>

      <div v-if="loading" class="spinner">Loading…</div>
      <div v-else-if="error" class="empty">{{ error }}</div>
      <div v-else-if="event" class="card card-pad detail">
        <div class="detail-head">
          <h1 class="page-title">{{ event.title }}</h1>
          <span class="badge badge-cat">{{ event.category }}</span>
        </div>

        <div class="detail-meta">
          <div class="cell-icon"><AppIcon name="calendar" :size="16" /> {{ formatDateTime(event.event_datetime) }}</div>
          <div class="cell-icon"><AppIcon name="pin" :size="16" /> {{ event.venue }}</div>
          <div class="cell-icon"><AppIcon name="user" :size="16" /> {{ event.organizer_name }}</div>
        </div>

        <p class="detail-desc">{{ event.description }}</p>

        <div class="cap">
          <div class="cap-top">
            <span>{{ event.registered_count }} / {{ event.capacity }} registered</span>
            <span class="muted">{{ spotsLeft }} spots left</span>
          </div>
          <div class="bar"><span :style="{ width: fillPct + '%', background: 'var(--maroon)' }"></span></div>
        </div>

        <div v-if="auth.isStudent" class="detail-action">
          <span v-if="myReg" class="badge badge-green"><AppIcon name="check" :size="14" /> You're registered</span>
          <button v-if="myReg && !past" class="btn btn-outline action-del" @click="cancel">Cancel Registration</button>
          <button v-else-if="!myReg && !past && !isFull" class="btn btn-primary" @click="register">Register</button>
          <span v-else-if="isFull && !myReg" class="badge badge-grey">Event is full</span>
          <span v-else-if="past" class="badge badge-grey">This event has ended</span>
        </div>
      </div>
    </div>
  </main>
</template>

<style scoped>
.narrow { max-width: 720px; }
.detail { display: flex; flex-direction: column; gap: 18px; }
.detail-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; }
.detail-meta { display: flex; flex-direction: column; gap: 9px; }
.detail-desc { color: var(--text); line-height: 1.65; margin: 0; }
.cap-top { display: flex; justify-content: space-between; font-size: 14px; margin-bottom: 8px; }
.detail-action { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; padding-top: 4px; }
</style>
