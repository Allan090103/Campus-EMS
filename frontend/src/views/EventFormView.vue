<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../services/api'
import AppIcon from '../components/AppIcon.vue'

const route = useRoute()
const router = useRouter()

// Edit mode if the URL carries an :id.
const editId = computed(() => route.params.id || null)
const isEdit = computed(() => !!editId.value)

const form = ref({
  title: '', description: '', date: '', time: '',
  venue: '', capacity: '', category: 'Technology',
})
const categories = ['Technology', 'Career', 'Cultural', 'Arts', 'Sports', 'Social']
const error = ref('')
const saving = ref(false)
const loading = ref(false)

// In edit mode, load the event and split its datetime into date + time inputs.
onMounted(async () => {
  if (!isEdit.value) return
  loading.value = true
  try {
    const { data } = await api.get(`/events/${editId.value}`)
    const [d, t] = data.event_datetime.split(' ')
    form.value = {
      title: data.title, description: data.description,
      date: d, time: (t || '').slice(0, 5),
      venue: data.venue, capacity: data.capacity, category: data.category,
    }
  } finally {
    loading.value = false
  }
})

async function submit() {
  error.value = ''
  const f = form.value
  if (!f.title || !f.description || !f.date || !f.time || !f.venue || !f.capacity) {
    error.value = 'Please fill in all fields.'
    return
  }
  if (Number(f.capacity) <= 0) {
    error.value = 'Capacity must be greater than 0.'
    return
  }
  // Merge the two inputs into a single DATETIME string for the API.
  const event_datetime = `${f.date} ${f.time}:00`

  saving.value = true
  try {
    const payload = {
      title: f.title, description: f.description, event_datetime,
      venue: f.venue, capacity: Number(f.capacity), category: f.category,
    }
    if (isEdit.value) {
      await api.put(`/events/${editId.value}`, payload)
    } else {
      await api.post('/events', payload)
    }
    router.push('/dashboard')
  } catch (e) {
    error.value = e.response?.data?.error || 'Could not save the event.'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <main class="page">
    <div class="container narrow">
      <a class="back-link" @click="router.push('/dashboard')"><AppIcon name="back" :size="16" /> Back to Dashboard</a>

      <div class="card card-pad">
        <h1 class="page-title">{{ isEdit ? 'Edit Event' : 'Create New Event' }}</h1>
        <p class="page-subtitle">{{ isEdit ? 'Update the details of your event' : 'Fill in the details to create a new event' }}</p>

        <div v-if="loading" class="spinner">Loading…</div>
        <template v-else>
          <p v-if="error" class="form-error" style="margin-top:18px">{{ error }}</p>

          <div class="field" style="margin-top:18px">
            <label class="label">Event Title</label>
            <input class="input" v-model="form.title" placeholder="e.g., Tech Summit 2026" />
          </div>

          <div class="field">
            <label class="label">Description</label>
            <textarea class="textarea" v-model="form.description" placeholder="Provide a detailed description of your event…"></textarea>
          </div>

          <div class="two">
            <div class="field">
              <label class="label">Date</label>
              <input class="input" type="date" v-model="form.date" />
            </div>
            <div class="field">
              <label class="label">Time</label>
              <input class="input" type="time" v-model="form.time" />
            </div>
          </div>

          <div class="field">
            <label class="label">Venue</label>
            <input class="input" v-model="form.venue" placeholder="e.g., Engineering Building Auditorium" />
          </div>

          <div class="two">
            <div class="field">
              <label class="label">Capacity</label>
              <input class="input" type="number" min="1" v-model="form.capacity" placeholder="e.g., 200" />
            </div>
            <div class="field">
              <label class="label">Category</label>
              <select class="select" v-model="form.category">
                <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
              </select>
            </div>
          </div>

          <div class="form-actions">
            <button class="btn btn-primary" :disabled="saving" @click="submit">
              {{ saving ? 'Saving…' : (isEdit ? 'Save Changes' : 'Create Event') }}
            </button>
            <button class="btn btn-muted" @click="router.push('/dashboard')">Cancel</button>
          </div>
        </template>
      </div>
    </div>
  </main>
</template>

<style scoped>
.narrow { max-width: 680px; }
.two { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.form-actions { display: flex; gap: 12px; margin-top: 8px; }
@media (max-width: 520px) { .two { grid-template-columns: 1fr; } }
</style>
