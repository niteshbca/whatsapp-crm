<script setup>
import { computed, onMounted, ref } from 'vue'
import api from '../api'

const users = ref([])
const roles = ref([])
const permissions = ref([])
const currentCompanyId = ref(null)
const loading = ref(false)
const error = ref('')
const success = ref('')
const searchQuery = ref('')
const selectedRole = ref('all')

const form = ref({
  name: '',
  email: '',
  password: '',
  role: 'employee',
  status: 'active',
  permissions: [],
})

const roleOptions = ['admin', 'manager', 'employee', 'viewer']
const statusOptions = ['active', 'inactive', 'pending']

const filteredUsers = computed(() => {
  const query = searchQuery.value.trim().toLowerCase()
  return users.value.filter((user) => {
    const roleMatch = selectedRole.value === 'all' || user.role === selectedRole.value
    const searchMatch = !query || [user.name, user.email, user.role].join(' ').toLowerCase().includes(query)
    return roleMatch && searchMatch
  })
})

const summaryCards = computed(() => {
  const active = users.value.filter((user) => user.status === 'active').length
  const admins = users.value.filter((user) => user.role === 'admin').length
  const managers = users.value.filter((user) => user.role === 'manager').length

  return [
    { label: 'Total members', value: users.value.length, tone: 'emerald' },
    { label: 'Active now', value: active, tone: 'blue' },
    { label: 'Admins', value: admins, tone: 'violet' },
    { label: 'Managers', value: managers, tone: 'amber' },
  ]
})

async function fetchAdminData() {
  loading.value = true
  error.value = ''
  success.value = ''

  try {
    const [meRes, usersRes, rolesRes, permissionsRes] = await Promise.all([
      api.get('/me').catch(() => ({ data: { company_id: null } })),
      api.get('/admin/users').catch((e) => {
        throw new Error(e.message)
      }),
      api.get('/admin/roles').catch((e) => {
        throw new Error(e.message)
      }),
      api.get('/admin/permissions').catch((e) => {
        throw new Error(e.message)
      }),
    ])

    currentCompanyId.value = meRes.data.company_id
    users.value = usersRes.data.data || []
    roles.value = rolesRes.data.data || []
    permissions.value = permissionsRes.data.data || []
  } catch (e) {
    error.value = e.message || 'Unable to load admin users.'
  } finally {
    loading.value = false
  }
}

function togglePermission(permissionSlug) {
  const index = form.value.permissions.indexOf(permissionSlug)
  if (index >= 0) {
    form.value.permissions.splice(index, 1)
  } else {
    form.value.permissions.push(permissionSlug)
  }
}

async function createUser() {
  if (!form.value.name || !form.value.email || !form.value.password) {
    error.value = 'Please fill in all required user fields.'
    success.value = ''
    return
  }

  if (!currentCompanyId.value) {
    error.value = 'You must be logged in as an admin to create a user.'
    success.value = ''
    return
  }

  try {
    loading.value = true
    error.value = ''
    success.value = ''

    await api.post('/admin/users', {
      company_id: Number(currentCompanyId.value),
      name: form.value.name,
      email: form.value.email,
      password: form.value.password,
      role: form.value.role,
      status: form.value.status,
      permissions: form.value.permissions,
    })

    success.value = 'User created successfully.'
    form.value = {
      name: '',
      email: '',
      password: '',
      role: 'employee',
      status: 'active',
      permissions: [],
    }

    await fetchAdminData()
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

async function deleteUser(userId) {
  try {
    loading.value = true
    await api.delete(`/admin/users/${userId}`)
    success.value = 'User deleted successfully.'
    await fetchAdminData()
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

onMounted(fetchAdminData)
</script>

<template>
  <div class="page">
    <div class="page-head dashboard-header">
      <div>
        <span class="eyebrow">People & permissions</span>
        <h1>Team management</h1>
      </div>
      <div class="header-actions">
        <button class="btn green" @click="fetchAdminData">Refresh</button>
      </div>
    </div>

    <div v-if="error" class="alert error">{{ error }}</div>
    <div v-if="success" class="alert success">{{ success }}</div>

    <div class="team-kpis mb">
      <div v-for="card in summaryCards" :key="card.label" class="team-stat" :class="card.tone">
        <div class="stat-icon small-icon">{{ card.label.charAt(0) }}</div>
        <div>
          <div class="kpi-value">{{ card.value }}</div>
          <div class="kpi-label">{{ card.label }}</div>
        </div>
      </div>
    </div>

    <div class="grid two mb">
      <div class="card team-card">
        <div class="panel-head">
          <h3>Create user</h3>
          <span class="badge ready">New member</span>
        </div>

        <div class="form-grid team-form-grid">
          <label class="field team-field">
            <span class="lb">Name</span>
            <input v-model="form.name" type="text" placeholder="John Smith" />
          </label>

          <label class="field team-field email-field">
            <span class="lb">Email</span>
            <input v-model="form.email" type="email" placeholder="john@company.com" />
          </label>

          <label class="field team-field password-field">
            <span class="lb">Password</span>
            <input v-model="form.password" type="password" placeholder="Min 6 chars" />
          </label>

          <label class="field team-field">
            <span class="lb">Role</span>
            <select v-model="form.role">
              <option v-for="role in roleOptions" :key="role" :value="role">{{ role }}</option>
            </select>
          </label>

          <label class="field team-field full-span-field">
            <span class="lb">Status</span>
            <select v-model="form.status">
              <option v-for="status in statusOptions" :key="status" :value="status">{{ status }}</option>
            </select>
          </label>
        </div>

        <div class="mt-8">
          <h4 class="mini-title">Permissions</h4>
          <div class="permission-box">
            <label v-for="permission in permissions" :key="permission.id" class="check-item">
              <input type="checkbox" :value="permission.slug" :checked="form.permissions.includes(permission.slug)" @change="togglePermission(permission.slug)" />
              <span>{{ permission.name }}</span>
            </label>
          </div>
        </div>

        <div class="actions mt-8">
          <button class="btn green" :disabled="loading" @click="createUser">
            {{ loading ? 'Creating…' : 'Create user' }}
          </button>
        </div>
      </div>

      <div class="card team-card">
        <div class="panel-head">
          <h3>Role overview</h3>
          <span class="badge">{{ roles.length }} roles</span>
        </div>
        <div v-if="roles.length === 0" class="empty">No roles found.</div>
        <div v-else class="mini-card-list">
          <div v-for="role in roles" :key="role.id" class="mini-card role-mini-card">
            <div class="between">
              <strong>{{ role.name }}</strong>
              <span class="badge ready">{{ role.slug }}</span>
            </div>
            <p class="hint" v-if="role.description">{{ role.description }}</p>
            <div class="tag-list">
              <span v-for="perm in role.permissions || []" :key="perm.id" class="tag">{{ perm.slug }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="card team-card">
      <div class="panel-head mb">
        <h3>Company users</h3>
        <div class="toolbar-inline">
          <input v-model="searchQuery" type="text" placeholder="Search employee..." class="search-input" />
          <select v-model="selectedRole" class="search-select">
            <option value="all">All roles</option>
            <option v-for="role in roleOptions" :key="role" :value="role">{{ role }}</option>
          </select>
        </div>
      </div>

      <div v-if="filteredUsers.length === 0" class="empty">No users in this company yet.</div>
      <div v-else class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Name</th>
              <th>Email</th>
              <th>Role</th>
              <th>Status</th>
              <th>Permissions</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="user in filteredUsers" :key="user.id">
              <td>
                <div class="user-pill">
                  <span class="user-avatar">{{ user.name.charAt(0).toUpperCase() }}</span>
                  <span>{{ user.name }}</span>
                </div>
              </td>
              <td>{{ user.email }}</td>
              <td><span class="badge">{{ user.role }}</span></td>
              <td><span class="badge" :class="user.status === 'active' ? 'ready' : user.status === 'inactive' ? 'failed' : 'qr'">{{ user.status }}</span></td>
              <td>
                <div class="tag-list compact-tags">
                  <span v-for="permission in (user.permissions || []).slice(0, 2)" :key="permission" class="tag">{{ permission }}</span>
                  <span v-if="(user.permissions || []).length > 2" class="tag muted-tag">+{{ (user.permissions || []).length - 2 }}</span>
                </div>
              </td>
              <td>
                <button class="btn danger sm" @click="deleteUser(user.id)">Delete</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
