<script setup>
import { ref } from 'vue'
import { useRouter, RouterLink } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import AppIcon from '../components/AppIcon.vue'

const auth = useAuthStore()
const router = useRouter()

const name = ref('')
const email = ref('')
const password = ref('')
const confirm = ref('')
const role = ref('student')
const error = ref('')
const loading = ref(false)
const pending = ref(false)

async function submit() {
  error.value = ''
  if (!name.value || !email.value || !password.value) {
    error.value = 'Please fill in all fields.'
    return
  }
  if (!email.value.trim().toLowerCase().endsWith('@utm.my')) {
    error.value = 'Only @utm.my email addresses are allowed.'
    return
  }
  if (password.value.length < 8) {
    error.value = 'Password must be at least 8 characters.'
    return
  }
  if (password.value !== confirm.value) {
    error.value = 'Passwords do not match.'
    return
  }
  loading.value = true
  try {
    const result = await auth.register({
      name: name.value.trim(),
      email: email.value.trim(),
      password: password.value,
      role: role.value,
    })
    if (result?.pending) {
      pending.value = true
    } else {
      router.push(auth.homeRoute())
    }
  } catch (e) {
    error.value = e.response?.data?.error || 'Unable to create account. Please try again.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="auth-wrap">
    <div class="auth-card">
      <div class="auth-logo"><AppIcon name="mortarboard" :size="26" /></div>

      <!-- Pending approval screen -->
      <template v-if="pending">
        <h1 class="auth-title">Request Submitted</h1>
        <p class="auth-sub">Your organizer account is awaiting admin approval. You will be able to log in once approved.</p>
        <RouterLink to="/login" class="btn btn-primary btn-block" style="margin-top:16px;text-align:center;display:block">Back to Sign In</RouterLink>
      </template>

      <!-- Registration form -->
      <template v-else>
        <h1 class="auth-title">Create Account</h1>
        <p class="auth-sub">Join CampusEMS today</p>

        <p v-if="error" class="form-error">{{ error }}</p>

        <div class="field">
          <label class="label">Full Name</label>
          <div class="input-icon">
            <span class="icon"><AppIcon name="user" /></span>
            <input class="input" type="text" v-model="name" placeholder="John Doe" />
          </div>
        </div>

        <div class="field">
          <label class="label">Email</label>
          <div class="input-icon">
            <span class="icon"><AppIcon name="mail" /></span>
            <input class="input" type="email" v-model="email" placeholder="your.name@utm.my" />
          </div>
          <p class="field-hint">Must be a @utm.my address</p>
        </div>

        <div class="field">
          <label class="label">Password</label>
          <div class="input-icon">
            <span class="icon"><AppIcon name="lock" /></span>
            <input class="input" type="password" v-model="password" placeholder="At least 8 characters" />
          </div>
        </div>

        <div class="field">
          <label class="label">Confirm Password</label>
          <div class="input-icon">
            <span class="icon"><AppIcon name="lock" /></span>
            <input class="input" type="password" v-model="confirm"
                   placeholder="Re-enter your password" @keyup.enter="submit" />
          </div>
        </div>

        <div class="field">
          <label class="label">Register as</label>
          <div class="toggle-group">
            <div class="toggle" :class="{ active: role === 'student' }" @click="role = 'student'">Student</div>
            <div class="toggle" :class="{ active: role === 'organizer' }" @click="role = 'organizer'">Organizer</div>
          </div>
          <p v-if="role === 'organizer'" class="field-hint">Organizer accounts require admin approval before you can log in.</p>
        </div>

        <button class="btn btn-primary btn-block" :disabled="loading" @click="submit">
          {{ loading ? 'Creating…' : 'Create Account' }}
        </button>

        <p class="auth-foot">
          Already have an account? <RouterLink to="/login">Sign in here</RouterLink>
        </p>
      </template>
    </div>
  </div>
</template>

<style scoped>
.field-hint { font-size: 12px; color: #64748b; margin: 4px 0 0 2px; }
</style>
