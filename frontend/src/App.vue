<script setup>
import { computed } from 'vue'
import { useRoute, RouterView } from 'vue-router'
import { useAuthStore } from './stores/auth'
import AppNavbar from './components/AppNavbar.vue'

const route = useRoute()
const auth = useAuthStore()

// The login & register pages are full-screen and have no navbar.
const showNav = computed(() => auth.isLoggedIn && !route.meta.guestOnly)
</script>

<template>
  <AppNavbar v-if="showNav" />
  <RouterView />
</template>
