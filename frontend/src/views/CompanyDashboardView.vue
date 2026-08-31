<script setup>
import { computed, onMounted, ref } from 'vue'
import api from '../api'
import { refreshWhatsapp, whatsapp } from '../store'

const companies = ref([])
const selectedCompanyId = ref(null)
const loading = ref(false)
const error = ref('')
const connectBusy = ref(false)

const selectedCompany = computed(() => {
  return companies.value.find((company) => String(company.id) === String(selectedCompanyId.value)) || companies.value[0] || null
})

async function load() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await api.get('/companies')
    companies.value = data.data || []
    if (!selectedCompanyId.value && companies.value.length) {
      selectedCompanyId.value = String(companies.value[0].id)
    }
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

async function createCompany() {
  const name = window.prompt('Company name')
  if (!name) return

  try {
    await api.post('/companies', { name, slug: name.toLowerCase().replace(/\s+/g, '-') })
    await load()
  } catch (e) {
    error.value = e.message
  }
}

async function editCompany() {
  if (!selectedCompany.value) return

  const name = window.prompt('Edit company name', selectedCompany.value.name)
  if (!name) return

  try {
    await api.put(`/companies/${selectedCompany.value.id}`, {
      name,
      slug: name.toLowerCase().replace(/\s+/g, '-'),
      status: 'active',
    })
    await load()
  } catch (e) {
    error.value = e.message
  }
}

async function deleteCompany() {
  if (!selectedCompany.value) return
  if (!window.confirm(`Delete company "${selectedCompany.value.name}"?`)) return

  try {
    await api.delete(`/companies/${selectedCompany.value.id}`)
    companies.value = companies.value.filter((company) => String(company.id) !== String(selectedCompany.value.id))
    selectedCompanyId.value = companies.value[0] ? String(companies.value[0].id) : null
  } catch (e) {
    error.value = e.message
  }
}

async function connectCompanyNumber() {
  if (!selectedCompanyId.value) return

  try {
    await api.post(`/companies/${selectedCompanyId.value}/connect-number`, {
      label: 'Primary WhatsApp number',
      session_name: `company-${selectedCompanyId.value}-primary`,
      phone_number: whatsapp.phone || null,
    })
    await load()
    await refreshWhatsapp()
  } catch (e) {
    error.value = e.message
  }
}

async function connectCompanyWhatsApp() {
  if (!selectedCompanyId.value) return

  connectBusy.value = true
  error.value = ''

  try {
    await api.post('/whatsapp/connect', {
      company_id: Number(selectedCompanyId.value),
      session_name: `company-${selectedCompanyId.value}-primary`,
    })

    for (let i = 0; i < 12; i++) {
      await refreshWhatsapp(Number(selectedCompanyId.value))
      if (whatsapp.status === 'qr' || whatsapp.connected) {
        break
      }
      await new Promise((resolve) => setTimeout(resolve, 800))
    }

    if (whatsapp.status === 'qr') {
      return
    }

    if (whatsapp.status === 'service_down') {
      error.value = 'WhatsApp bridge is offline. Start the WhatsApp service on port 3001 and try again.'
      return
    }

    if (whatsapp.status === 'connecting' || whatsapp.status === 'authenticated' || whatsapp.status === 'unlinked') {
      error.value = 'WhatsApp is still starting. Please wait a moment while the QR code is generated.'
      return
    }

    if (!whatsapp.connected || !whatsapp.phone) {
      error.value = whatsapp.error || 'QR not generated yet. Please wait a moment and try again.'
      return
    }

    await api.post(`/companies/${selectedCompanyId.value}/connect-number`, {
      label: 'Primary WhatsApp number',
      session_name: `company-${selectedCompanyId.value}-primary`,
      phone_number: whatsapp.phone,
    })

    await load()
  } catch (e) {
    error.value = e.message
  } finally {
    connectBusy.value = false
    setTimeout(() => refreshWhatsapp(Number(selectedCompanyId.value)), 1200)
  }
}

async function logoutCompanyNumber() {
  if (!selectedCompany.value) return

  try {
    await api.post(`/companies/${selectedCompany.value.id}/logout-number`)
    await load()
    await refreshWhatsapp(Number(selectedCompany.value.id))
  } catch (e) {
    error.value = e.message
  }
}

async function createApiKey() {
  if (!selectedCompanyId.value) return

  const name = window.prompt('API key name')
  if (!name) return

  try {
    await api.post('/api-keys', {
      company_id: Number(selectedCompanyId.value),
      name,
      permissions: ['send_messages', 'campaigns:read'],
      status: 'active',
    })
    await load()
  } catch (e) {
    error.value = e.message
  }
}

onMounted(async () => {
  await load()
  if (selectedCompanyId.value) {
    await refreshWhatsapp(Number(selectedCompanyId.value))
  }
})
</script>

<template>
  <div class="page">
    <div class="page-head row between">
      <div>
        <h1>Multi-company dashboard</h1>
        <p>Manage companies, WhatsApp numbers, and API access in one place.</p>
      </div>
      <button class="btn green" @click="createCompany">+ Add company</button>
    </div>

    <div v-if="error" class="alert error">{{ error }}</div>
    <div v-if="loading" class="alert info">Loading companies…</div>

    <div v-if="selectedCompany" class="card mb">
      <div class="row between">
        <h3 style="margin:0">{{ selectedCompany.name }}</h3>
        <div class="row gap8">
          <button class="btn green" @click="connectCompanyWhatsApp" :disabled="connectBusy">
            <span v-if="connectBusy" class="spinner"></span>
            {{ whatsapp.status === 'qr' ? 'Refresh QR' : 'Connect WhatsApp' }}
          </button>
          <button class="btn ghost" @click="logoutCompanyNumber">Logout number</button>
          <button class="btn ghost" @click="editCompany">Edit</button>
          <button class="btn danger" @click="deleteCompany">Delete</button>
        </div>
      </div>

      <div class="row between mt">
        <div class="hint">Selected WhatsApp status: {{ whatsapp.connected ? `Connected · ${whatsapp.phone || 'unknown'}` : whatsapp.status === 'qr' ? 'Scan QR code' : whatsapp.status === 'service_down' ? 'WhatsApp bridge offline' : whatsapp.status === 'connecting' ? 'Starting WhatsApp…' : 'Not connected' }}</div>
        <select v-model="selectedCompanyId" style="max-width:260px">
          <option v-for="company in companies" :key="company.id" :value="String(company.id)">{{ company.name }}</option>
        </select>
      </div>

      <div v-if="whatsapp.status === 'qr'" class="mt card" style="padding:18px; background:#f8fafc; border:1px solid #e2e8f0; max-width:320px;">
        <div class="qr-box" style="width:260px; height:260px; display:flex; align-items:center; justify-content:center; border-radius:12px; background:white;">
          <img :src="whatsapp.qr" alt="WhatsApp QR code" style="max-width:220px; max-height:220px; display:block;" />
        </div>
        <p class="hint mt" style="margin-bottom:0">Scan this QR code with your phone to link the WhatsApp number to this company.</p>
      </div>
    </div>

    <div v-if="selectedCompany" class="grid two">
      <div class="card">
        <h3>WhatsApp numbers</h3>
        <div v-if="selectedCompany.whatsapp_accounts?.length">
          <div v-for="account in selectedCompany.whatsapp_accounts" :key="account.id" class="mini-card">
            <div class="row between">
              <strong>{{ account.label }}</strong>
              <span class="badge" :class="account.status === 'connected' ? 'ready' : 'failed'">{{ account.status }}</span>
            </div>
            <div class="hint mt-8">{{ account.phone_number || 'Not connected yet' }}</div>
          </div>
        </div>
        <div v-else class="empty">No WhatsApp numbers connected yet.</div>
      </div>

      <div class="card">
        <h3>API keys</h3>
        <div v-if="selectedCompany.api_keys?.length">
          <div v-for="key in selectedCompany.api_keys" :key="key.id" class="mini-card">
            <div class="row between">
              <strong>{{ key.name }}</strong>
              <span class="badge" :class="key.status === 'active' ? 'ready' : 'failed'">{{ key.status }}</span>
            </div>
            <div class="hint mt-8">{{ key.key }}</div>
          </div>
        </div>
        <div v-else class="empty">No API keys created.</div>
      </div>
    </div>
  </div>
</template>
