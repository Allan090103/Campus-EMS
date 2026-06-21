import { defineStore } from 'pinia'
import api from '../services/api'

/**
 * Auth store — the single source of truth for "who is logged in".
 *
 * Per our proposal, the JWT is held in MEMORY (Pinia state), not in
 * localStorage, as a security practice. Trade-off: refreshing the browser
 * logs the user out. If you prefer the token to survive a refresh, set
 * PERSIST = true below (it will then mirror the token into localStorage).
 */
const PERSIST = true

export const useAuthStore = defineStore('auth', {
  state: () => ({
    token: PERSIST ? localStorage.getItem('ems_token') : null,
    user: PERSIST && localStorage.getItem('ems_user')
      ? JSON.parse(localStorage.getItem('ems_user'))
      : null,
  }),

  getters: {
    isLoggedIn: (s) => !!s.token,
    role:       (s) => s.user?.role || null,
    isStudent:  (s) => s.user?.role === 'student',
    isOrganizer:(s) => s.user?.role === 'organizer',
    isAdmin:    (s) => s.user?.role === 'admin',
  },

  actions: {
    /** Store credentials and attach the token to every future request. */
    setSession(token, user) {
      this.token = token
      this.user = user
      api.defaults.headers.common['Authorization'] = `Bearer ${token}`
      if (PERSIST) {
        localStorage.setItem('ems_token', token)
        localStorage.setItem('ems_user', JSON.stringify(user))
      }
    },

    async login(email, password) {
      const { data } = await api.post('/auth/login', { email, password })
      this.setSession(data.token, data.user)
      return data.user
    },

    async register(payload) {
      const { data } = await api.post('/auth/register', payload)
      if (data.pending) return data   // organizer awaiting admin approval — no session
      this.setSession(data.token, data.user)
      return data
    },

    logout() {
      this.token = null
      this.user = null
      delete api.defaults.headers.common['Authorization']
      if (PERSIST) {
        localStorage.removeItem('ems_token')
        localStorage.removeItem('ems_user')
      }
    },

    /** Restore the Authorization header after a refresh (only if PERSIST). */
    restore() {
      if (this.token) {
        api.defaults.headers.common['Authorization'] = `Bearer ${this.token}`
      }
    },

    /** Default landing route for each role after login. */
    homeRoute() {
      if (this.isAdmin) return '/admin/users'
      if (this.isOrganizer) return '/dashboard'
      return '/events'
    },
  },
})
