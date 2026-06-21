<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '../services/api'
import AppIcon from '../components/AppIcon.vue'
import StatCard from '../components/StatCard.vue'

const users = ref([])
const loading = ref(true)
const search = ref('')
const filter = ref('All')   // All | Students | Organizers | Pending

// Edit modal state.
const editing = ref(null)
const editError = ref('')
const savingEdit = ref(false)

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/users')
    users.value = data
  } finally {
    loading.value = false
  }
}
onMounted(load)

// Stats.
const totalUsers = computed(() => users.value.length)
const activeUsers = computed(() => users.value.filter((u) => u.status === 'active').length)
const studentCount = computed(() => users.value.filter((u) => u.role === 'student').length)
const organizerCount = computed(() => users.value.filter((u) => u.role === 'organizer').length)
const pendingCount = computed(() => users.value.filter((u) => u.status === 'pending').length)

// Search + role/status filter.
const filtered = computed(() =>
  users.value.filter((u) => {
    const matchesSearch =
      !search.value ||
      u.name.toLowerCase().includes(search.value.toLowerCase()) ||
      u.email.toLowerCase().includes(search.value.toLowerCase())
    const matchesFilter =
      filter.value === 'All' ||
      (filter.value === 'Students' && u.role === 'student') ||
      (filter.value === 'Organizers' && u.role === 'organizer') ||
      (filter.value === 'Pending' && u.status === 'pending')
    return matchesSearch && matchesFilter
  })
)

function openEdit(u) {
  editing.value = { ...u }
  editError.value = ''
}
async function saveEdit() {
  editError.value = ''
  savingEdit.value = true
  try {
    await api.put(`/users/${editing.value.id}`, {
      name: editing.value.name,
      email: editing.value.email,
      role: editing.value.role,
      status: editing.value.status,
    })
    editing.value = null
    await load()
  } catch (e) {
    editError.value = e.response?.data?.error || 'Could not save changes.'
  } finally {
    savingEdit.value = false
  }
}
async function approve(u) {
  try {
    await api.put(`/users/${u.id}`, { name: u.name, email: u.email, role: u.role, status: 'active' })
    await load()
  } catch (e) {
    alert(e.response?.data?.error || 'Could not approve user.')
  }
}
async function remove(u) {
  if (!confirm(`Delete ${u.name}? This removes their events and registrations.`)) return
  try {
    await api.delete(`/users/${u.id}`)
    await load()
  } catch (e) {
    alert(e.response?.data?.error || 'Could not delete user.')
  }
}

function roleBadge(role) {
  return role === 'admin' ? 'badge-admin' : role === 'organizer' ? 'badge-organizer' : 'badge-student'
}
</script>

<template>
  <main class="page">
    <div class="container">
      <h1 class="page-title">User Management</h1>
      <p class="page-subtitle">Manage all registered users</p>

      <div class="grid-5 stats">
        <StatCard label="Total Users" :value="totalUsers" icon="users" tint="maroon" />
        <StatCard label="Active Users" :value="activeUsers" icon="check" tint="green" />
        <StatCard label="Students" :value="studentCount" icon="user" tint="blue" />
        <StatCard label="Organizers" :value="organizerCount" icon="user" tint="purple" />
        <StatCard label="Pending" :value="pendingCount" icon="clock" tint="orange" />
      </div>

      <div class="card">
        <div class="toolbar">
          <div class="input-icon grow">
            <span class="icon"><AppIcon name="search" /></span>
            <input class="input" v-model="search" placeholder="Search by name or email…" />
          </div>
          <div class="pills">
            <button v-for="f in ['All','Students','Organizers','Pending']" :key="f"
                    class="pill" :class="{ active: filter === f }" @click="filter = f">
              {{ f }}
              <span v-if="f === 'Pending' && pendingCount > 0" class="pill-badge">{{ pendingCount }}</span>
            </button>
          </div>
        </div>

        <div v-if="loading" class="spinner">Loading…</div>
        <div v-else class="table-wrap">
          <table class="table">
            <thead>
              <tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Events</th><th>Actions</th></tr>
            </thead>
            <tbody>
              <tr v-for="u in filtered" :key="u.id">
                <td class="link">{{ u.name }}</td>
                <td class="muted">{{ u.email }}</td>
                <td><span class="badge" :class="roleBadge(u.role)" style="text-transform:capitalize">{{ u.role }}</span></td>
                <td>
                  <span v-if="u.status === 'active'" class="badge badge-green"><AppIcon name="check" :size="13" /> Active</span>
                  <span v-else-if="u.status === 'pending'" class="badge badge-pending"><AppIcon name="clock" :size="13" /> Pending</span>
                  <span v-else class="badge badge-red"><AppIcon name="x" :size="13" /> Inactive</span>
                </td>
                <td>{{ u.event_count }}</td>
                <td class="actions">
                  <button v-if="u.status === 'pending'" class="action-link action-approve" @click="approve(u)"><AppIcon name="check" :size="15" /> Approve</button>
                  <button class="action-link action-edit" @click="openEdit(u)"><AppIcon name="edit" :size="15" /> Edit</button>
                  <button class="action-link action-del" @click="remove(u)"><AppIcon name="trash" :size="15" /> Delete</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Edit modal -->
    <div v-if="editing" class="modal-backdrop" @click.self="editing = null">
      <div class="card card-pad modal">
        <h2 class="modal-title">Edit User</h2>
        <p v-if="editError" class="form-error">{{ editError }}</p>

        <div class="field">
          <label class="label">Name</label>
          <input class="input" v-model="editing.name" />
        </div>
        <div class="field">
          <label class="label">Email</label>
          <input class="input" type="email" v-model="editing.email" />
        </div>
        <div class="two">
          <div class="field">
            <label class="label">Role</label>
            <select class="select" v-model="editing.role">
              <option value="student">Student</option>
              <option value="organizer">Organizer</option>
              <option value="admin">Admin</option>
            </select>
          </div>
          <div class="field">
            <label class="label">Status</label>
            <select class="select" v-model="editing.status">
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
              <option value="pending">Pending</option>
            </select>
          </div>
        </div>

        <div class="form-actions">
          <button class="btn btn-primary" :disabled="savingEdit" @click="saveEdit">
            {{ savingEdit ? 'Saving…' : 'Save Changes' }}
          </button>
          <button class="btn btn-muted" @click="editing = null">Cancel</button>
        </div>
      </div>
    </div>
  </main>
</template>

<style scoped>
.stats { margin: 24px 0; }
.grid-5 { display: grid; grid-template-columns: repeat(5, 1fr); gap: 16px; }
.toolbar { display: flex; gap: 12px; padding: 16px; align-items: center; flex-wrap: wrap; }
.grow { flex: 1; min-width: 220px; }
.pills { display: flex; gap: 8px; }
.pill-badge { background: #b91c1c; color: #fff; border-radius: 9999px; font-size: 11px; padding: 1px 6px; margin-left: 4px; }
.table-wrap { overflow-x: auto; }
.actions { display: flex; gap: 12px; flex-wrap: wrap; }
.action-approve { color: #15803d; }
.action-approve:hover { color: #166534; }
.badge-pending { background: #fef3c7; color: #92400e; }
.modal-backdrop { position: fixed; inset: 0; background: rgba(15,23,42,.45); display: flex; align-items: center; justify-content: center; padding: 20px; z-index: 50; }
.modal { width: 100%; max-width: 460px; }
.modal-title { font-size: 20px; font-weight: 700; margin-bottom: 16px; }
.two { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.form-actions { display: flex; gap: 12px; margin-top: 8px; }
@media (max-width: 768px) { .grid-5 { grid-template-columns: repeat(2, 1fr); } }
</style>
