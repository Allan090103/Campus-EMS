<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../services/api'
import AppIcon from '../components/AppIcon.vue'
import { formatDateTime, isPast } from '../utils/format'

const route = useRoute()
const router = useRouter()

const event = ref(null)
const participants = ref([])
const loading = ref(true)
const saving = ref(false)
const message = ref('')

async function load() {
  loading.value = true
  try {
    const { data } = await api.get(`/events/${route.params.id}/registrations`)
    event.value = data.event
    participants.value = data.participants.map((p) => ({ ...p, attended: !!p.attended }))
  } finally {
    loading.value = false
  }
}
onMounted(load)

const canMark = computed(() => event.value && isPast(event.value.event_datetime))

async function save() {
  message.value = ''
  saving.value = true
  try {
    // Persist each participant's attendance flag.
    await Promise.all(
      participants.value.map((p) =>
        api.put(`/registrations/${p.id}/attend`, { attended: p.attended })
      )
    )
    message.value = 'Attendance saved.'
  } catch (e) {
    message.value = e.response?.data?.error || 'Could not save attendance.'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <main class="page">
    <div class="container narrow">
      <a class="back-link" @click="router.push('/dashboard')"><AppIcon name="back" :size="16" /> Back to Dashboard</a>

      <div v-if="loading" class="spinner">Loading…</div>

      <template v-else-if="event">
        <h1 class="page-title">{{ event.title }}</h1>
        <p class="page-subtitle"><AppIcon name="calendar" :size="14" /> {{ formatDateTime(event.event_datetime) }} · {{ event.venue }}</p>

        <div v-if="!canMark" class="notice">
          Attendance can only be marked after the event has taken place.
        </div>

        <div class="card table-wrap" style="margin-top:18px">
          <table v-if="participants.length" class="table">
            <thead>
              <tr><th>Participant</th><th>Email</th><th style="text-align:center">Attended</th></tr>
            </thead>
            <tbody>
              <tr v-for="p in participants" :key="p.id">
                <td class="link">{{ p.name }}</td>
                <td class="muted">{{ p.email }}</td>
                <td style="text-align:center">
                  <input type="checkbox" v-model="p.attended" :disabled="!canMark" class="chk" />
                </td>
              </tr>
            </tbody>
          </table>
          <div v-else class="empty">No participants registered yet.</div>
        </div>

        <div v-if="participants.length && canMark" class="save-row">
          <span v-if="message" class="muted">{{ message }}</span>
          <button class="btn btn-primary" :disabled="saving" @click="save">
            {{ saving ? 'Saving…' : 'Submit Attendance' }}
          </button>
        </div>
      </template>
    </div>
  </main>
</template>

<style scoped>
.narrow { max-width: 760px; }
.notice { background: #fff7ed; color: #9a3412; border: 1px solid #fed7aa; padding: 11px 14px; border-radius: var(--radius-sm); font-size: 14px; margin-top: 16px; }
.table-wrap { overflow-x: auto; }
.chk { width: 18px; height: 18px; accent-color: var(--maroon); cursor: pointer; }
.save-row { display: flex; align-items: center; justify-content: flex-end; gap: 16px; margin-top: 18px; }
</style>
