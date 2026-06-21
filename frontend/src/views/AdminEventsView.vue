<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '../services/api'
import AppIcon from '../components/AppIcon.vue'
import StatCard from '../components/StatCard.vue'
import { formatDate, utilizationColor } from '../utils/format'

const router = useRouter()
const events = ref([])
const loading = ref(true)
const search = ref('')
const category = ref('All')
const organizer = ref('All')
const statusFilter = ref('All')
const categories = ['All', 'Technology', 'Career', 'Cultural', 'Arts', 'Sports', 'Social']

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/admin/events')
    events.value = data
  } finally {
    loading.value = false
  }
}
onMounted(load)

const organizers = computed(() => {
  const set = new Set(events.value.map((e) => e.organizer_name))
  return ['All', ...Array.from(set)]
})

const totalEvents = computed(() => events.value.length)
const pendingCount = computed(() => events.value.filter((e) => e.status === 'pending').length)
const totalRegs = computed(() => events.value.reduce((s, e) => s + Number(e.registered_count), 0))
const totalCap = computed(() => events.value.reduce((s, e) => s + Number(e.capacity), 0))
const avgUtil = computed(() => totalCap.value ? Math.round((totalRegs.value / totalCap.value) * 1000) / 10 : 0)

const filtered = computed(() =>
  events.value.filter((e) => {
    const s = !search.value ||
      e.title.toLowerCase().includes(search.value.toLowerCase()) ||
      e.organizer_name.toLowerCase().includes(search.value.toLowerCase())
    const c = category.value === 'All' || e.category === category.value
    const o = organizer.value === 'All' || e.organizer_name === organizer.value
    const st = statusFilter.value === 'All' || e.status === statusFilter.value.toLowerCase()
    return s && c && o && st
  })
)

function util(e) {
  return e.capacity > 0 ? Math.round((e.registered_count / e.capacity) * 100) : 0
}

async function setStatus(e, status) {
  try {
    await api.put(`/events/${e.id}/status`, { status })
    await load()
  } catch (err) {
    alert(err.response?.data?.error || 'Could not update event status.')
  }
}

async function remove(e) {
  if (!confirm(`Delete "${e.title}"?`)) return
  try {
    await api.delete(`/events/${e.id}`)
    await load()
  } catch (err) {
    alert(err.response?.data?.error || 'Could not delete event.')
  }
}

function statusBadgeClass(status) {
  return status === 'approved' ? 'badge-green' : status === 'rejected' ? 'badge-red' : 'badge-pending'
}
</script>

<template>
  <main class="page">
    <div class="container">
      <h1 class="page-title">Event Overview</h1>
      <p class="page-subtitle">View and manage all campus events</p>

      <div class="grid-5 stats">
        <StatCard label="Total Events" :value="totalEvents" icon="calendar" tint="maroon" />
        <StatCard label="Pending Approval" :value="pendingCount" icon="clock" tint="orange" />
        <StatCard label="Total Registrations" :value="totalRegs" icon="users" tint="green" />
        <StatCard label="Total Capacity" :value="totalCap" icon="users" tint="blue" />
        <StatCard label="Avg Utilization" :value="avgUtil + '%'" icon="tag" tint="purple" />
      </div>

      <div class="card">
        <div class="toolbar">
          <div class="input-icon grow">
            <span class="icon"><AppIcon name="search" /></span>
            <input class="input" v-model="search" placeholder="Search events or organizers…" />
          </div>
        </div>
        <div class="toolbar2">
          <div class="pills">
            <button v-for="c in categories" :key="c" class="pill"
                    :class="{ active: category === c }" @click="category = c">{{ c }}</button>
          </div>
          <div class="right-filters">
            <div class="pills">
              <button v-for="st in ['All','Pending','Approved','Rejected']" :key="st"
                      class="pill" :class="{ active: statusFilter === st }" @click="statusFilter = st">
                {{ st }}
                <span v-if="st === 'Pending' && pendingCount > 0" class="pill-badge">{{ pendingCount }}</span>
              </button>
            </div>
            <select class="select org-select" v-model="organizer">
              <option v-for="o in organizers" :key="o" :value="o">{{ o === 'All' ? 'All Organizers' : o }}</option>
            </select>
          </div>
        </div>

        <div v-if="loading" class="spinner">Loading…</div>
        <div v-else class="table-wrap">
          <table class="table">
            <thead>
              <tr><th>Event Name</th><th>Organizer</th><th>Date</th><th>Category</th><th>Status</th><th>Registrations</th><th>Utilization</th><th>Actions</th></tr>
            </thead>
            <tbody>
              <tr v-for="e in filtered" :key="e.id">
                <td class="link">{{ e.title }}</td>
                <td class="org">{{ e.organizer_name }}</td>
                <td><span class="cell-icon"><AppIcon name="calendar" :size="15" /> {{ formatDate(e.event_datetime) }}</span></td>
                <td><span class="badge badge-cat">{{ e.category }}</span></td>
                <td>
                  <span class="badge" :class="statusBadgeClass(e.status)" style="text-transform:capitalize">
                    {{ e.status }}
                  </span>
                </td>
                <td>{{ e.registered_count }} / {{ e.capacity }}</td>
                <td>
                  <div class="util">
                    <div class="bar"><span :style="{ width: util(e) + '%', background: utilizationColor(util(e)) }"></span></div>
                    <span class="util-pct">{{ util(e) }}%</span>
                  </div>
                </td>
                <td class="actions">
                  <template v-if="e.status === 'pending'">
                    <button class="action-link action-approve" @click="setStatus(e, 'approved')"><AppIcon name="check" :size="15" /> Approve</button>
                    <button class="action-link action-reject" @click="setStatus(e, 'rejected')"><AppIcon name="x" :size="15" /> Reject</button>
                  </template>
                  <template v-else-if="e.status === 'approved'">
                    <button class="action-link action-reject" @click="setStatus(e, 'rejected')"><AppIcon name="x" :size="15" /> Reject</button>
                  </template>
                  <template v-else>
                    <button class="action-link action-approve" @click="setStatus(e, 'approved')"><AppIcon name="check" :size="15" /> Approve</button>
                  </template>
                  <button class="action-link action-del" @click="remove(e)"><AppIcon name="trash" :size="15" /></button>
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
.grid-5 { display: grid; grid-template-columns: repeat(5, 1fr); gap: 16px; }
.toolbar { padding: 16px 16px 0; }
.toolbar2 { display: flex; gap: 12px; padding: 14px 16px; align-items: center; justify-content: space-between; flex-wrap: wrap; }
.grow { flex: 1; }
.pills { display: flex; gap: 8px; flex-wrap: wrap; }
.right-filters { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
.pill-badge { background: #b91c1c; color: #fff; border-radius: 9999px; font-size: 11px; padding: 1px 6px; margin-left: 4px; }
.org-select { width: auto; min-width: 160px; }
.table-wrap { overflow-x: auto; }
.org { color: var(--maroon); font-weight: 500; }
.util { display: flex; align-items: center; gap: 10px; min-width: 130px; }
.util-pct { font-size: 13px; color: var(--muted); width: 38px; }
.actions { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
.action-approve { color: #15803d; }
.action-approve:hover { color: #166534; }
.action-reject { color: #b91c1c; }
.action-reject:hover { color: #991b1b; }
.badge-pending { background: #fef3c7; color: #92400e; }
@media (max-width: 900px) { .grid-5 { grid-template-columns: repeat(3, 1fr); } }
</style>
