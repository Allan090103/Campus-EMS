<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '../services/api'
import AppIcon from '../components/AppIcon.vue'
import { formatDate, isPast } from '../utils/format'

const regs = ref([])
const loading = ref(true)

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/registrations/my')
    regs.value = data
  } finally {
    loading.value = false
  }
}
onMounted(load)

// Split into upcoming vs past by comparing the event date to now.
const upcoming = computed(() => regs.value.filter((r) => !isPast(r.event_datetime)))
const past     = computed(() => regs.value.filter((r) => isPast(r.event_datetime)))

async function cancelReg(id) {
  if (!confirm('Cancel this registration?')) return
  try {
    await api.delete(`/registrations/${id}`)
    await load()
  } catch (e) {
    alert(e.response?.data?.error || 'Could not cancel.')
  }
}
</script>

<template>
  <main class="page">
    <div class="container">
      <h1 class="page-title">My Registrations</h1>
      <p class="page-subtitle">View and manage your event registrations</p>

      <div v-if="loading" class="spinner">Loading…</div>

      <template v-else>
        <!-- Upcoming -->
        <h2 class="section-title">Upcoming Events ({{ upcoming.length }})</h2>
        <div class="card table-wrap">
          <table v-if="upcoming.length" class="table">
            <thead>
              <tr><th>Event</th><th>Date</th><th>Venue</th><th>Category</th><th>Registered On</th><th>Actions</th></tr>
            </thead>
            <tbody>
              <tr v-for="r in upcoming" :key="r.id">
                <td class="link">{{ r.title }}</td>
                <td><span class="cell-icon"><AppIcon name="calendar" :size="15" /> {{ formatDate(r.event_datetime) }}</span></td>
                <td><span class="cell-icon"><AppIcon name="pin" :size="15" /> {{ r.venue }}</span></td>
                <td><span class="badge badge-cat"><AppIcon name="tag" :size="13" /> {{ r.category }}</span></td>
                <td>{{ formatDate(r.registered_at) }}</td>
                <td><button class="action-link action-del" @click="cancelReg(r.id)"><AppIcon name="x" :size="15" /> Cancel</button></td>
              </tr>
            </tbody>
          </table>
          <div v-else class="empty">No upcoming registrations.</div>
        </div>

        <!-- Past -->
        <h2 class="section-title">Past Events ({{ past.length }})</h2>
        <div class="card table-wrap">
          <table v-if="past.length" class="table">
            <thead>
              <tr><th>Event</th><th>Date</th><th>Venue</th><th>Category</th><th>Status</th></tr>
            </thead>
            <tbody>
              <tr v-for="r in past" :key="r.id">
                <td>{{ r.title }}</td>
                <td><span class="cell-icon"><AppIcon name="calendar" :size="15" /> {{ formatDate(r.event_datetime) }}</span></td>
                <td><span class="cell-icon"><AppIcon name="pin" :size="15" /> {{ r.venue }}</span></td>
                <td><span class="badge badge-cat"><AppIcon name="tag" :size="13" /> {{ r.category }}</span></td>
                <td>
                  <span v-if="r.attended" class="badge badge-green"><AppIcon name="check" :size="14" /> Attended</span>
                  <span v-else class="badge badge-grey">Did not attend</span>
                </td>
              </tr>
            </tbody>
          </table>
          <div v-else class="empty">No past events yet.</div>
        </div>
      </template>
    </div>
  </main>
</template>

<style scoped>
.section-title { font-size: 19px; font-weight: 700; margin: 28px 0 14px; }
.table-wrap { overflow-x: auto; }
</style>
