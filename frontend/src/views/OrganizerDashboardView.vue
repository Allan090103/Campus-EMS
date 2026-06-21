<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '../services/api'
import StatCard from '../components/StatCard.vue'
import AppIcon from '../components/AppIcon.vue'
import { formatDate } from '../utils/format'

const router = useRouter()
const events = ref([])
const loading = ref(true)

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/organizer/events')
    events.value = data
  } finally {
    loading.value = false
  }
}
onMounted(load)

// Stats derived from the organizer's own events (single source of data).
const totalEvents = computed(() => events.value.length)
const totalRegs = computed(() => events.value.reduce((s, e) => s + Number(e.registered_count), 0))
const totalCap = computed(() => events.value.reduce((s, e) => s + Number(e.capacity), 0))

async function remove(ev) {
  if (!confirm(`Delete "${ev.title}"? This also removes its registrations.`)) return
  try {
    await api.delete(`/events/${ev.id}`)
    await load()
  } catch (e) {
    alert(e.response?.data?.error || 'Could not delete event.')
  }
}
</script>

<template>
  <main class="page">
    <div class="container">
      <div class="between">
        <div>
          <h1 class="page-title">Organizer Dashboard</h1>
          <p class="page-subtitle">Manage your events and registrations</p>
        </div>
        <button class="btn btn-primary" @click="router.push('/events/create')">
          <AppIcon name="plus" :size="17" /> Create New Event
        </button>
      </div>

      <div class="grid-3 stats">
        <StatCard label="Total Events" :value="totalEvents" icon="calendar" tint="maroon" />
        <StatCard label="Total Registrations" :value="totalRegs" icon="users" tint="green" />
        <StatCard label="Total Capacity" :value="totalCap" icon="users" tint="blue" />
      </div>

      <div class="card">
        <div class="card-pad"><h2 class="tbl-title">My Events</h2></div>
        <div v-if="loading" class="spinner">Loading…</div>
        <div v-else-if="events.length === 0" class="empty">You haven't created any events yet.</div>
        <div v-else class="table-wrap">
          <table class="table">
            <thead>
              <tr><th>Event Name</th><th>Date</th><th>Category</th><th>Status</th><th>Registrations</th><th>Capacity</th><th>Actions</th></tr>
            </thead>
            <tbody>
              <tr v-for="ev in events" :key="ev.id">
                <td><a class="link" @click="router.push(`/events/${ev.id}/attendance`)" style="cursor:pointer">{{ ev.title }}</a></td>
                <td>{{ formatDate(ev.event_datetime) }}</td>
                <td><span class="badge badge-cat">{{ ev.category }}</span></td>
                <td>
                  <span v-if="ev.status === 'approved'" class="badge badge-green"><AppIcon name="check" :size="13" /> Approved</span>
                  <span v-else-if="ev.status === 'rejected'" class="badge badge-red"><AppIcon name="x" :size="13" /> Rejected</span>
                  <span v-else class="badge badge-pending"><AppIcon name="clock" :size="13" /> Pending</span>
                </td>
                <td>{{ ev.registered_count }}</td>
                <td>{{ ev.capacity }}</td>
                <td class="actions">
                  <button class="action-link action-edit" @click="router.push(`/events/${ev.id}/edit`)"><AppIcon name="edit" :size="15" /> Edit</button>
                  <button class="action-link action-del" @click="remove(ev)"><AppIcon name="trash" :size="15" /> Delete</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </main>
</template>

<style scoped>
.stats { margin: 24px 0; }
.tbl-title { font-size: 18px; font-weight: 700; }
.table-wrap { overflow-x: auto; }
.actions { display: flex; gap: 16px; }
.badge-pending { background: #fef3c7; color: #92400e; }
</style>
