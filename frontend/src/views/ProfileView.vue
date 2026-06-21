<script setup>
import { ref, onMounted } from 'vue'
import api from '../services/api'
import { useAuthStore } from '../stores/auth'
import AppIcon from '../components/AppIcon.vue'

const auth = useAuthStore()

const name = ref('')
const email = ref('')
const password = ref('')
const confirm = ref('')
const role = ref('')
const loading = ref(true)
const saving = ref(false)
const error = ref('')
const success = ref('')

onMounted(async () => {
  try {
    const { data } = await api.get('/profile')
    name.value = data.name
    email.value = data.email
    role.value = data.role
  } finally {
    loading.value = false
  }
})

async function save() {
  error.value = ''; success.value = ''
  if (!name.value || !email.value) {
    error.value = 'Name and email are required.'
    return
  }
  if (password.value) {
    if (password.value.length < 8) { error.value = 'New password must be at least 8 characters.'; return }
    if (password.value !== confirm.value) { error.value = 'Passwords do not match.'; return }
  }
  saving.value = true
  try {
    const payload = { name: name.value.trim(), email: email.value.trim() }
    if (password.value) payload.password = password.value
    const { data } = await api.put('/profile', payload)
    // Keep the navbar/user state in sync.
    auth.user = { ...auth.user, name: data.name, email: data.email }
    password.value = ''; confirm.value = ''
    success.value = 'Profile updated.'
  } catch (e) {
    error.value = e.response?.data?.error || 'Could not save changes.'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <main class="page">
    <div class="container narrow">
      <h1 class="page-title">My Profile</h1>
      <p class="page-subtitle">View and update your account details</p>

      <div v-if="loading" class="spinner">Loading…</div>

      <div v-else class="card card-pad profile">
        <p v-if="error" class="form-error">{{ error }}</p>
        <p v-if="success" class="form-success">{{ success }}</p>

        <div class="field">
          <label class="label">Full Name</label>
          <div class="input-icon">
            <span class="icon"><AppIcon name="user" /></span>
            <input class="input" v-model="name" />
          </div>
        </div>

        <div class="field">
          <label class="label">Email</label>
          <div class="input-icon">
            <span class="icon"><AppIcon name="mail" /></span>
            <input class="input" type="email" v-model="email" />
          </div>
        </div>

        <div class="field">
          <label class="label">Role</label>
          <input class="input" :value="role" disabled style="text-transform: capitalize;" />
        </div>

        <hr class="div" />
        <h3 class="sub">Change Password</h3>
        <p class="muted small">Leave blank to keep your current password.</p>

        <div class="field">
          <label class="label">New Password</label>
          <div class="input-icon">
            <span class="icon"><AppIcon name="lock" /></span>
            <input class="input" type="password" v-model="password" placeholder="At least 8 characters" />
          </div>
        </div>

        <div class="field">
          <label class="label">Confirm New Password</label>
          <div class="input-icon">
            <span class="icon"><AppIcon name="lock" /></span>
            <input class="input" type="password" v-model="confirm" placeholder="Re-enter new password" />
          </div>
        </div>

        <button class="btn btn-primary" :disabled="saving" @click="save">
          {{ saving ? 'Saving…' : 'Save Changes' }}
        </button>
      </div>
    </div>
  </main>
</template>

<style scoped>
.narrow { max-width: 640px; }
.form-success { background: var(--green-bg); color: var(--green); padding: 10px 13px; border-radius: var(--radius-sm); font-size: 14px; margin-bottom: 16px; }
.div { border: none; border-top: 1px solid var(--border); margin: 8px 0 20px; }
.sub { font-size: 17px; font-weight: 700; }
.small { font-size: 13px; margin: 4px 0 16px; }
</style>
