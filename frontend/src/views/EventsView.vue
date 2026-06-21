<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import api from '../services/api'
import EventCard from '../components/EventCard.vue'
import AppIcon from '../components/AppIcon.vue'

const router = useRouter()

const events = ref([])
const myEventIds = ref(new Set())   // ids the student is registered for
const loading = ref(true)
const search = ref('')
const category = ref('All')
const categories = ['All', 'Technology', 'Career', 'Cultural', 'Arts', 'Sports']

// Async load: events + the student's own registrations (to mark cards).
async function load() {
  loading.value = true
  try {
    const [evRes, myRes] = await Promise.all([
      api.get('/events', { params: { category: category.value, search: search.value } }),
      api.get('/registrations/my'),
    ])
    events.value = evRes.data
    myEventIds.value = new Set(myRes.data.map((r) => r.event_id))
  } finally {
    loading.value = false
  }
}

onMounted(load)
// Re-fetch when the category filter changes.
watch(category, load)

// Debounced-ish search: reload when the user pauses typing.
let t
watch(search, () => {
  clearTimeout(t)
  t = setTimeout(load, 350)
})

async function register(id) {
  try {
    await api.post('/registrations', { event_id: id })
    await load()
  } catch (e) {
    alert(e.response?.data?.error || 'Could not register.')
  }
}
function view(id) {
  router.push(`/events/${id}`)
}
</script>

<template>
  <main class="page">
    <div class="container">
      <h1 class="page-title">Available Events</h1>
      <p class="page-subtitle">Browse and register for campus events</p>

      <div class="filters">
        <div class="input-icon search">
          <span class="icon"><AppIcon name="search" /></span>
          <input class="input" v-model="search" placeholder="Search events…" />
        </div>
        <div class="pills">
          <button v-for="c in categories" :key="c" class="pill"
                  :class="{ active: category === c }" @click="category = c">{{ c }}</button>
        </div>
      </div>

      <div v-if="loading" class="spinner">Loading events…</div>
      <div v-else-if="events.length === 0" class="empty">No events match your search.</div>
      <div v-else class="grid-3">
        <EventCard v-for="ev in events" :key="ev.id" :event="ev"
                   :registered="myEventIds.has(ev.id)"
                   @register="register" @view="view" />
      </div>
    </div>
  </main>
</template>

<style scoped>
.filters { display: flex; gap: 12px; align-items: center; margin: 24px 0 28px; flex-wrap: wrap; }
.search { flex: 1; min-width: 240px; }
.pills { display: flex; gap: 8px; flex-wrap: wrap; }
</style>
