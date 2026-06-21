import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'

import LoginView            from '../views/LoginView.vue'
import RegisterView         from '../views/RegisterView.vue'
import EventsView           from '../views/EventsView.vue'
import EventDetailView      from '../views/EventDetailView.vue'
import MyRegistrationsView  from '../views/MyRegistrationsView.vue'
import ProfileView          from '../views/ProfileView.vue'
import OrganizerDashboardView from '../views/OrganizerDashboardView.vue'
import EventFormView        from '../views/EventFormView.vue'
import EventAttendanceView  from '../views/EventAttendanceView.vue'
import AdminUsersView       from '../views/AdminUsersView.vue'
import AdminEventsView      from '../views/AdminEventsView.vue'

/**
 * Routes. `meta.roles` lists who may enter a route. The global guard
 * below enforces it: unauthenticated users are sent to /login, and
 * users without the right role are sent to their own home page.
 */
const routes = [
  { path: '/login',    component: LoginView,    meta: { guestOnly: true } },
  { path: '/register', component: RegisterView, meta: { guestOnly: true } },

  // Student
  { path: '/events',           component: EventsView,          meta: { roles: ['student', 'admin'] } },
  { path: '/events/:id',       component: EventDetailView,      meta: { roles: ['student', 'organizer', 'admin'] } },
  { path: '/my-registrations', component: MyRegistrationsView,  meta: { roles: ['student', 'admin'] } },

  // Shared
  { path: '/profile',          component: ProfileView,         meta: { roles: ['student', 'organizer', 'admin'] } },

  // Organizer
  { path: '/dashboard',              component: OrganizerDashboardView, meta: { roles: ['organizer', 'admin'] } },
  { path: '/events/create',          component: EventFormView,          meta: { roles: ['organizer', 'admin'] } },
  { path: '/events/:id/edit',        component: EventFormView,          meta: { roles: ['organizer', 'admin'] } },
  { path: '/events/:id/attendance',  component: EventAttendanceView,    meta: { roles: ['organizer', 'admin'] } },

  // Admin
  { path: '/admin/users',  component: AdminUsersView,  meta: { roles: ['admin'] } },
  { path: '/admin/events', component: AdminEventsView, meta: { roles: ['admin'] } },

  // Default
  { path: '/', redirect: '/login' },
  { path: '/:pathMatch(.*)*', redirect: '/login' },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach((to) => {
  const auth = useAuthStore()

  // Logged-in users shouldn't see login/register — send them home.
  if (to.meta.guestOnly && auth.isLoggedIn) {
    return auth.homeRoute()
  }

  // Protected route, but not logged in.
  if (to.meta.roles && !auth.isLoggedIn) {
    return '/login'
  }

  // Logged in but wrong role for this route.
  if (to.meta.roles && !to.meta.roles.includes(auth.role)) {
    return auth.homeRoute()
  }

  return true
})

export default router
