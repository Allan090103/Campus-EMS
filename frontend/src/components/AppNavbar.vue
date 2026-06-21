<script setup>
import { computed } from 'vue'
import { useRouter, RouterLink } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import AppIcon from './AppIcon.vue'

const auth = useAuthStore()
const router = useRouter()

// Links shown depend on the logged-in user's role.
const links = computed(() => {
  if (auth.isStudent) {
    return [
      { to: '/events', label: 'Events' },
      { to: '/my-registrations', label: 'My Registrations' },
    ]
  }
  if (auth.isOrganizer) {
    return [{ to: '/dashboard', label: 'Dashboard' }]
  }
  if (auth.isAdmin) {
    return [
      { to: '/admin/users', label: 'Users' },
      { to: '/admin/events', label: 'Events' },
    ]
  }
  return []
})

function logout() {
  auth.logout()
  router.push('/login')
}
</script>

<template>
  <header class="nav">
    <div class="container nav-inner">
      <RouterLink :to="auth.homeRoute()" class="brand">
        <span class="brand-badge"><AppIcon name="mortarboard" :size="18" /></span>
        <span class="brand-name">CampusEMS</span>
      </RouterLink>

      <nav class="nav-links">
        <RouterLink v-for="l in links" :key="l.to" :to="l.to" class="nav-link">
          {{ l.label }}
        </RouterLink>
        <RouterLink to="/profile" class="nav-link nav-profile">
          <AppIcon name="user" :size="17" /> Profile
        </RouterLink>
        <button class="nav-logout" @click="logout">Logout</button>
      </nav>
    </div>
  </header>
</template>

<style scoped>
.nav { background: #fff; border-bottom: 1px solid var(--border); }
.nav-inner { display: flex; align-items: center; justify-content: space-between; height: 64px; }
.brand { display: flex; align-items: center; gap: 10px; }
.brand:hover { text-decoration: none; }
.brand-badge { width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg, #7f1d1d, #be185d); color: #fff; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 8px rgba(127,29,29,0.35); }
.brand-name { font-weight: 700; font-size: 18px; color: var(--text-strong); }
.nav-links { display: flex; align-items: center; gap: 26px; }
.nav-link { color: var(--muted); font-weight: 500; display: inline-flex; align-items: center; gap: 6px; }
.nav-link:hover { color: var(--maroon); text-decoration: none; }
.nav-link.router-link-active { color: var(--maroon); }
.nav-logout { background: none; border: none; color: var(--muted); font-weight: 500; cursor: pointer; font-size: 15px; }
.nav-logout:hover { color: var(--maroon); }
@media (max-width: 600px) {
  .nav-links { gap: 14px; }
  .nav-link span:last-child { display: none; }
}
</style>
