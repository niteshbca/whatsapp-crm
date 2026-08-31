<script setup>
import { computed, onMounted, ref } from 'vue'
import api from '../api'

const appointments = ref([])
const summary = ref({ total: 0, upcoming: 0, by_status: {} })
const error = ref('')
const loading = ref(false)

const form = ref({
  title: '',
  contact_name: '',
  phone: '',
  email: '',
  scheduled_at: '',
  notes: '',
  status: 'scheduled',
})

const statusOptions = ['scheduled', 'confirmed', 'completed', 'cancelled']

const byStatusList = computed(() => [
  { key: 'scheduled', label: 'Scheduled', value: summary.value.by_status?.scheduled ?? 0 },
  { key: 'confirmed', label: 'Confirmed', value: summary.value.by_status?.confirmed ?? 0 },
  { key: 'completed', label: 'Completed', value: summary.value.by_status?.completed ?? 0 },
  { key: 'cancelled', label: 'Cancelled', value: summary.value.by_status?.cancelled ?? 0 },
])

async function load() {
  loading.value = true
  error.value = ''

  try {
    const [listRes, summaryRes] = await Promise.all([
      api.get('/appointments'),
      api.get('/appointments/summary'),
    ])

    appointments.value = listRes.data.data || []
    summary.value = summaryRes.data.data || { total: 0, upcoming: 0, by_status: {} }
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

async function createAppointment() {
  const trimmedTitle = form.value.title.trim()
  const trimmedEmail = form.value.email.trim()

  if (!trimmedTitle || !form.value.scheduled_at) {
    error.value = 'Please add a title and schedule time.'
    return
  }

  if (trimmedEmail && !/^\S+@\S+\.\S+$/.test(trimmedEmail)) {
    error.value = 'Please enter a valid email address.'
    return
  }

  try {
    error.value = ''
    await api.post('/appointments', {
      ...form.value,
      title: trimmedTitle,
      email: trimmedEmail || null,
      scheduled_at: new Date(form.value.scheduled_at).toISOString(),
    })

    form.value = {
      title: '',
      contact_name: '',
      phone: '',
      email: '',
      scheduled_at: '',
      notes: '',
      status: 'scheduled',
    }

    await load()
  } catch (e) {
    error.value = e.message
  }
}

async function updateStatus(id, status) {
  try {
    await api.patch(`/appointments/${id}`, { status })
    await load()
  } catch (e) {
    error.value = e.message
  }
}

async function deleteAppointment(id) {
  if (!confirm('Delete this appointment?')) return

  try {
    await api.delete(`/appointments/${id}`)
    await load()
  } catch (e) {
    error.value = e.message
  }
}

onMounted(load)
</script>

<template>
  <div class="page">
    <div class="page-head dashboard-header">
      <div>
        <span class="eyebrow">Sales workflow</span>
        <h1>Appointments</h1>
      </div>
      <div class="header-actions">
        <button class="btn green" @click="load">Refresh</button>
      </div>
    </div>

    <div v-if="error" class="alert error">{{ error }}</div>

    <div class="team-kpis mb">
      <div class="team-stat emerald">
        <div class="stat-icon small-icon">T</div>
        <div>
          <div class="kpi-value">{{ summary.total }}</div>
          <div class="kpi-label">Total bookings</div>
        </div>
      </div>
      <div class="team-stat blue">
        <div class="stat-icon small-icon">U</div>
        <div>
          <div class="kpi-value">{{ summary.upcoming }}</div>
          <div class="kpi-label">Upcoming</div>
        </div>
      </div>
      <div class="team-stat violet">
        <div class="stat-icon small-icon">C</div>
        <div>
          <div class="kpi-value">{{ summary.by_status?.confirmed ?? 0 }}</div>
          <div class="kpi-label">Confirmed</div>
        </div>
      </div>
      <div class="team-stat amber">
        <div class="stat-icon small-icon">P</div>
        <div>
          <div class="kpi-value">{{ summary.by_status?.completed ?? 0 }}</div>
          <div class="kpi-label">Completed</div>
        </div>
      </div>
    </div>

    <div class="grid two mb">
      <div class="card team-card">
        <div class="panel-head">
          <h3>Book appointment</h3>
          <span class="badge ready">Quick schedule</span>
        </div>

        <div class="form-grid">
          <label class="field">
            <span class="lb">Title</span>
            <input v-model="form.title" type="text" placeholder="Demo call" />
          </label>

          <label class="field">
            <span class="lb">Contact name</span>
            <input v-model="form.contact_name" type="text" placeholder="Riya Sharma" />
          </label>

          <label class="field">
            <span class="lb">Phone</span>
            <input v-model="form.phone" type="text" placeholder="+91..." />
          </label>

          <label class="field">
            <span class="lb">Email</span>
            <input v-model="form.email" type="email" placeholder="name@example.com" />
          </label>

          <label class="field">
            <span class="lb">Time</span>
            <input v-model="form.scheduled_at" type="datetime-local" />
          </label>

          <label class="field">
            <span class="lb">Status</span>
            <select v-model="form.status">
              <option v-for="status in statusOptions" :key="status" :value="status">{{ status }}</option>
            </select>
          </label>

          <label class="field full-width">
            <span class="lb">Notes</span>
            <textarea v-model="form.notes" placeholder="Any follow-up notes or preferences"></textarea>
          </label>
        </div>

        <div class="actions mt-8">
          <button class="btn green" :disabled="loading" @click="createAppointment">{{ loading ? 'Saving…' : 'Save appointment' }}</button>
        </div>
      </div>

      <div class="card team-card">
        <div class="panel-head">
          <h3>Status overview</h3>
        </div>

        <div class="summary-stack">
          <div v-for="item in byStatusList" :key="item.key" class="summary-row">
            <span>{{ item.label }}</span>
            <strong>{{ item.value }}</strong>
          </div>
        </div>
      </div>
    </div>

    <div class="card team-card">
      <div class="panel-head mb">
        <h3>Appointment calendar</h3>
      </div>

      <div v-if="appointments.length === 0" class="empty">No appointment scheduled yet.</div>
      <div v-else class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Title</th>
              <th>Contact</th>
              <th>When</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="appointment in appointments" :key="appointment.id">
              <td>{{ appointment.title }}</td>
              <td>
                <div class="user-pill">
                  <span class="user-avatar">{{ (appointment.contact_name || 'A').charAt(0).toUpperCase() }}</span>
                  <span>{{ appointment.contact_name || 'Unknown contact' }}</span>
                </div>
              </td>
              <td>{{ new Date(appointment.scheduled_at).toLocaleString() }}</td>
              <td>
                <select :value="appointment.status" @change="updateStatus(appointment.id, $event.target.value)">
                  <option v-for="status in statusOptions" :key="status" :value="status">{{ status }}</option>
                </select>
              </td>
              <td>
                <button class="btn danger sm" @click="deleteAppointment(appointment.id)">Delete</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
