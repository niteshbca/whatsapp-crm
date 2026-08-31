<script setup>
import { computed, onMounted, ref } from 'vue'
import api from '../api'
import { refreshWhatsapp, whatsapp, statusLabels } from '../store'

const companies = ref([])
const accounts = ref([])
const selectedCompanyId = ref(null)
const loading = ref(false)
const error = ref('')
const success = ref('')

const form = ref({
  label: 'Primary WhatsApp Number',
  session_name: '',
  phone_number: '',
})

const selectedCompany = computed(() => {
  return companies.value.find((company) => String(company.id) === String(selectedCompanyId.value)) || companies.value[0] || null
})

const stats = computed(() => ({
  total: accounts.value.length,
  connected: accounts.value.filter((account) => account.status === 'connected').length,
  disconnected: accounts.value.filter((account) => account.status !== 'connected').length,
}))

async function loadCompanies() {
  loading.value = true
  error.value = ''

  try {
    const { data } = await api.get('/companies')
    companies.value = data.data || []

    if (!selectedCompanyId.value && companies.value.length) {
      selectedCompanyId.value = String(companies.value[0].id)
    }

    if (selectedCompanyId.value) {
      await loadAccounts()
      await refreshWhatsapp(Number(selectedCompanyId.value))
    }
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

async function loadAccounts() {
  if (!selectedCompanyId.value) {
    accounts.value = []
    return
  }

  try {
    const { data } = await api.get(`/companies/${selectedCompanyId.value}/accounts`)
    accounts.value = data.data || []
    form.value.session_name = `company-${selectedCompanyId.value}-primary`
  } catch (e) {
    error.value = e.message
  }
}

async function connectWhatsApp() {
  if (!selectedCompanyId.value) return

  loading.value = true
  error.value = ''
  success.value = ''

  try {
    await api.post('/whatsapp/connect', {
      company_id: Number(selectedCompanyId.value),
      session_name: form.value.session_name || `company-${selectedCompanyId.value}-primary`,
    })

    for (let i = 0; i < 12; i++) {
      await refreshWhatsapp(Number(selectedCompanyId.value))
      if (whatsapp.status === 'qr' || whatsapp.connected) {
        break
      }
      await new Promise((resolve) => setTimeout(resolve, 700))
    }

    if (whatsapp.status === 'service_down') {
      error.value = 'WhatsApp bridge is offline. Start the Node service first.'
      return
    }

    if (whatsapp.status === 'qr') {
      success.value = 'QR code generated. Scan it on your phone.'
      return
    }

    if (!whatsapp.connected && !whatsapp.qr) {
      error.value = 'Connection request sent. Wait a moment before trying again.'
      return
    }

    await api.post(`/companies/${selectedCompanyId.value}/connect-number`, {
      label: form.value.label || 'Primary WhatsApp Number',
      session_name: form.value.session_name || `company-${selectedCompanyId.value}-primary`,
      phone_number: form.value.phone_number || whatsapp.phone || null,
    })

    success.value = 'WhatsApp account linked successfully.'
    await loadAccounts()
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

async function disconnectWhatsApp() {
  if (!selectedCompanyId.value) return

  try {
    loading.value = true
    await api.post(`/companies/${selectedCompanyId.value}/logout-number`)
    await refreshWhatsapp(Number(selectedCompanyId.value))
    await loadAccounts()
    success.value = 'WhatsApp account disconnected.'
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

function clearStatus() {
  error.value = ''
  success.value = ''
}

onMounted(async () => {
  await loadCompanies()
})
</script>

<template>
  <div class="page">
    <div class="page-head row between">
      <div>
        <h1>WhatsApp accounts</h1>
        <p>Manage company WhatsApp sessions, QR connections, and account status.</p>
      </div>
      <div class="row gap8">
        <button class="btn ghost" @click="loadAccounts">Refresh</button>
        <button class="btn primary" @click="connectWhatsApp">Connect</button>
      </div>
    </div>

    <div v-if="error" class="alert error">{{ error }}</div>
    <div v-if="success" class="alert success">{{ success }}</div>

    <div class="account-summary mb">
      <div class="acct-stat-card">
        <span class="eyebrow">Total</span>
        <strong>{{ stats.total }}</strong>
      </div>
      <div class="acct-stat-card success">
        <span class="eyebrow">Connected</span>
        <strong>{{ stats.connected }}</strong>
      </div>
      <div class="acct-stat-card warning">
        <span class="eyebrow">Available</span>
        <strong>{{ stats.disconnected }}</strong>
      </div>
    </div>

    <div class="account-shell">
      <div class="card account-form-card">
        <div class="section-title">Connect new account</div>

        <label class="field">
          <span class="lb">Company</span>
          <select v-model="selectedCompanyId" @change="loadAccounts; clearStatus()">
            <option v-for="company in companies" :key="company.id" :value="String(company.id)">
              {{ company.name }}
            </option>
          </select>
        </label>

        <label class="field">
          <span class="lb">Label</span>
          <input v-model="form.label" type="text" placeholder="Primary WhatsApp Number" />
        </label>

        <label class="field">
          <span class="lb">Session name</span>
          <input v-model="form.session_name" type="text" placeholder="company-1-primary" />
        </label>

        <label class="field">
          <span class="lb">Phone number</span>
          <input v-model="form.phone_number" type="text" placeholder="Optional if already linked" />
        </label>

        <div class="button-row">
          <button class="btn primary" :disabled="loading || !selectedCompanyId" @click="connectWhatsApp">
            {{ loading ? 'Connecting…' : 'Connect WhatsApp' }}
          </button>
          <button class="btn ghost danger" :disabled="loading || !selectedCompanyId" @click="disconnectWhatsApp">
            Disconnect
          </button>
        </div>
      </div>

      <div class="card qr-card">
        <div class="section-title">Live status</div>

        <div class="status-badge-wrap">
          <span class="badge" :class="whatsapp.connected ? 'ready' : whatsapp.status === 'qr' ? 'qr' : 'failed'">
            {{ whatsapp.connected ? 'Connected' : statusLabels[whatsapp.status] || 'Offline' }}
          </span>
        </div>

        <div v-if="whatsapp.status === 'qr' && whatsapp.qr" class="qr-box mt">
          <img :src="whatsapp.qr" alt="WhatsApp QR code" />
        </div>

        <div v-else-if="!whatsapp.connected" class="empty-state mt">
          <div class="empty-illustration">📱</div>
          <p>QR will appear here when a new session is started.</p>
        </div>

        <div v-else class="account-live-box mt">
          <div><strong>Number</strong><span>{{ whatsapp.phone || 'Not available' }}</span></div>
          <div><strong>Status</strong><span>{{ whatsapp.status }}</span></div>
          <div><strong>Session</strong><span>{{ form.session_name || 'company-primary' }}</span></div>
        </div>
      </div>
    </div>

    <div class="card mt">
      <div class="section-title">Saved accounts</div>

      <div v-if="accounts.length === 0" class="empty">No WhatsApp accounts saved yet.</div>

      <div v-else class="account-grid">
        <div v-for="account in accounts" :key="account.id" class="account-card">
          <div class="account-header">
            <div>
              <span class="mini-label">Account</span>
              <h4>{{ account.label }}</h4>
            </div>
            <span class="badge" :class="account.status === 'connected' ? 'ready' : account.status === 'disconnected' ? 'failed' : 'qr'">
              {{ account.status }}
            </span>
          </div>

          <div class="account-meta">
            <div><span>Phone</span><strong>{{ account.phone_number || 'Not linked' }}</strong></div>
            <div><span>Session</span><strong>{{ account.session_name || '—' }}</strong></div>
            <div><span>Last connected</span><strong>{{ account.last_connected_at ? new Date(account.last_connected_at).toLocaleString() : 'Not yet' }}</strong></div>
          </div>

          <div class="account-actions">
            <button class="btn ghost sm" @click="() => { form.label = account.label; form.session_name = account.session_name || form.session_name; form.phone_number = account.phone_number || ''; }">Edit</button>
            <button class="btn danger sm" @click="disconnectWhatsApp">Remove</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
