<script setup>
import { computed, onMounted, ref } from 'vue'
import api from '../api'

const reminders = ref([])
const summary = ref({ total: 0, upcoming: 0, by_channel: {} })
const error = ref('')
const loading = ref(false)

const form = ref({
  title: '',
  channel: 'whatsapp',
  scheduled_for: '',
  message: '',
  status: 'scheduled',
})

const channelOptions = ['whatsapp', 'email']
const statusOptions = ['scheduled', 'sent', 'failed', 'cancelled']

const byChannelList = computed(() => [
  { key: 'whatsapp', label: 'WhatsApp', value: summary.value.by_channel?.whatsapp ?? 0 },
  { key: 'email', label: 'Email', value: summary.value.by_channel?.email ?? 0 },
])

async function load() {
  loading.value = true
  error.value = ''

  try {
    const [listRes, summaryRes] = await Promise.all([
      api.get('/reminders'),
      api.get('/reminders/summary'),
    ])

    reminders.value = listRes.data.data || []
    summary.value = summaryRes.data.data || { total: 0, upcoming: 0, by_channel: {} }
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

async function createReminder() {
  const trimmedTitle = form.value.title.trim()
  const trimmedMessage = form.value.message.trim()

  if (!trimmedTitle || !form.value.scheduled_for || !trimmedMessage) {
    error.value = 'Please fill in the title, message, and scheduled time.'
    return
  }

  try {
    error.value = ''
    await api.post('/reminders', {
      ...form.value,
      title: trimmedTitle,
      message: trimmedMessage,
      scheduled_for: new Date(form.value.scheduled_for).toISOString(),
    })

    form.value = {
      title: '',
      channel: 'whatsapp',
      scheduled_for: '',
      message: '',
      status: 'scheduled',
    }

    await load()
  } catch (e) {
    error.value = e.message
  }
}

async function deleteReminder(id) {
  if (!confirm('Delete this reminder?')) return

  try {
    await api.delete(`/reminders/${id}`)
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
        <span class="eyebrow">Automation</span>
        <h1>Reminders</h1>
      </div>
      <div class="header-actions">
        <button class="btn green" @click="load">Refresh</button>
      </div>
    </div>

    <div v-if="error" class="alert error">{{ error }}</div>

    <div class="team-kpis mb">
      <div class="team-stat emerald">
        <div class="stat-icon small-icon">R</div>
        <div>
          <div class="kpi-value">{{ summary.total }}</div>
          <div class="kpi-label">Total reminders</div>
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
        <div class="stat-icon small-icon">W</div>
        <div>
          <div class="kpi-value">{{ summary.by_channel?.whatsapp ?? 0 }}</div>
          <div class="kpi-label">WhatsApp</div>
        </div>
      </div>
      <div class="team-stat amber">
        <div class="stat-icon small-icon">E</div>
        <div>
          <div class="kpi-value">{{ summary.by_channel?.email ?? 0 }}</div>
          <div class="kpi-label">Email</div>
        </div>
      </div>
    </div>

    <div class="grid two mb">
      <div class="card team-card">
        <div class="panel-head">
          <h3>Create reminder</h3>
          <span class="badge ready">Automation</span>
        </div>

        <div class="form-grid">
          <label class="field">
            <span class="lb">Title</span>
            <input v-model="form.title" type="text" placeholder="Follow-up reminder" />
          </label>

          <label class="field">
            <span class="lb">Channel</span>
            <select v-model="form.channel">
              <option v-for="channel in channelOptions" :key="channel" :value="channel">{{ channel }}</option>
            </select>
          </label>

          <label class="field">
            <span class="lb">Scheduled for</span>
            <input v-model="form.scheduled_for" type="datetime-local" />
          </label>

          <label class="field">
            <span class="lb">Status</span>
            <select v-model="form.status">
              <option v-for="status in statusOptions" :key="status" :value="status">{{ status }}</option>
            </select>
          </label>

          <label class="field full-width">
            <span class="lb">Message</span>
            <textarea v-model="form.message" placeholder="Hi {{name}}, we are confirming your appointment..."></textarea>
          </label>
        </div>

        <div class="actions mt-8">
          <button class="btn green" :disabled="loading" @click="createReminder">
            {{ loading ? 'Saving…' : 'Save reminder' }}
          </button>
        </div>
      </div>

      <div class="card team-card">
        <div class="panel-head">
          <h3>Channel overview</h3>
        </div>

        <div class="summary-stack">
          <div v-for="item in byChannelList" :key="item.key" class="summary-row">
            <span>{{ item.label }}</span>
            <strong>{{ item.value }}</strong>
          </div>
        </div>
      </div>
    </div>

    <div class="card team-card">
      <div class="panel-head mb">
        <h3>Reminder queue</h3>
      </div>

      <div v-if="reminders.length === 0" class="empty">No reminders created yet.</div>
      <div v-else class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Title</th>
              <th>Channel</th>
              <th>Scheduled</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="reminder in reminders" :key="reminder.id">
              <td>{{ reminder.title }}</td>
              <td><span class="badge">{{ reminder.channel }}</span></td>
              <td>{{ new Date(reminder.scheduled_for).toLocaleString() }}</td>
              <td><span class="badge" :class="reminder.status === 'sent' ? 'ready' : reminder.status === 'failed' ? 'failed' : 'qr'">{{ reminder.status }}</span></td>
              <td><button class="btn danger sm" @click="deleteReminder(reminder.id)">Delete</button></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
