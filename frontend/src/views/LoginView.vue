<script setup>
import { ref } from 'vue'
import { useRouter, RouterLink } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import AppIcon from '../components/AppIcon.vue'

const auth = useAuthStore()
const router = useRouter()

const email = ref('')
const password = ref('')
const error = ref('')
const loading = ref(false)

async function submit() {
  error.value = ''
  // Client-side validation.
  if (!email.value || !password.value) {
    error.value = 'Please enter your email and password.'
    return
  }
  loading.value = true
  try {
    await auth.login(email.value.trim(), password.value)
    router.push(auth.homeRoute())
  } catch (e) {
    error.value = e.response?.data?.error || 'Unable to sign in. Please try again.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="auth-wrap">
    <div class="auth-card">
      <div class="auth-logo"><AppIcon name="mortarboard" :size="26" /></div>
      <h1 class="auth-title">Welcome Back</h1>
      <p class="auth-sub">Sign in to your CampusEMS account</p>

      <p v-if="error" class="form-error">{{ error }}</p>

      <div class="field">
        <label class="label">Email</label>
        <div class="input-icon">
          <span class="icon"><AppIcon name="mail" /></span>
          <input class="input" type="email" v-model="email"
                 placeholder="your.email@university.edu" @keyup.enter="submit" />
        </div>
      </div>

      <div class="field">
        <label class="label">Password</label>
        <div class="input-icon">
          <span class="icon"><AppIcon name="lock" /></span>
          <input class="input" type="password" v-model="password"
                 placeholder="Enter your password" @keyup.enter="submit" />
        </div>
      </div>

      <button class="btn btn-primary btn-block" :disabled="loading" @click="submit">
        {{ loading ? 'Signing in…' : 'Sign In' }}
      </button>

      <p class="auth-foot">
        Don't have an account? <RouterLink to="/register">Register here</RouterLink>
      </p>
    </div>
  </div>
</template>
