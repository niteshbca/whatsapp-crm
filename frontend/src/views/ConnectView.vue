<script setup>
import { onMounted, ref } from 'vue'
import api from '../api'
import { whatsapp, refreshWhatsapp, statusLabels } from '../store'

const busy = ref(false)
const error = ref('')
const companies = ref([])
const companyId = ref('')

async function loadCompanies() {
  const { data } = await api.get('/companies')
  companies.value = data.data || []
  if (!companyId.value && companies.value.length) companyId.value = String(companies.value[0].id)
}

async function refreshSelected() {
  await refreshWhatsapp(companyId.value ? Number(companyId.value) : null)
}

async function connect() {
  busy.value = true
  error.value = ''
  try {
    if (!companyId.value) {
      error.value = 'Create a company first, then select it to connect WhatsApp.'
      return
    }
    await api.post('/whatsapp/connect', {
      company_id: Number(companyId.value),
      session_name: `company-${companyId.value}-primary`,
    })
    await refreshSelected()
  } catch (e) {
    error.value = e.message
  } finally {
    busy.value = false
    setTimeout(refreshSelected, 1200)
  }
}

async function logout() {
  if (!confirm('Log out of WhatsApp on this computer? You will need to scan the QR code again.')) return
  busy.value = true
  error.value = ''
  try {
    await api.post('/whatsapp/logout', { company_id: Number(companyId.value) })
    await refreshSelected()
  } catch (e) {
    error.value = e.message
  } finally {
    busy.value = false
  }
}

onMounted(async () => {
  try {
    await loadCompanies()
    await refreshSelected()
  } catch (e) {
    error.value = e.message
  }
})
</script>

<template>
  <div class="page">
    <div class="page-head">
      <h1>Connect WhatsApp</h1>
      <p>Scan the QR code with your phone to link this WhatsApp account.</p>
    </div>

    <div v-if="error" class="alert error">{{ error }}</div>

    <div class="connect-wrap">
      <div class="connect-toolbar">
        <div>
          <span class="eyebrow">Workspace connection</span>
          <strong>Choose the company account</strong>
        </div>
        <select v-model="companyId" @change="refreshSelected" :disabled="busy">
          <option value="" disabled>Select company</option>
          <option v-for="company in companies" :key="company.id" :value="String(company.id)">{{ company.name }}</option>
        </select>
      </div>

      <!-- Not connected -->
      <div v-if="!whatsapp.connected" class="card centered">
        <h3 style="margin:0">{{ whatsapp.status === 'qr' ? 'Scan this QR code' : 'Link your WhatsApp' }}</h3>

        <div v-if="whatsapp.status === 'qr' && whatsapp.qr" class="qr-box">
          <img :src="whatsapp.qr" alt="WhatsApp QR code" />
        </div>

        <div v-else class="qr-box">
          <span class="hint" style="max-width:200px">
            {{ whatsapp.status === 'connecting' ? 'Starting browser…' : 'Click the button below to generate a QR code.' }}
          </span>
        </div>

        <p class="hint" style="max-width:340px">
          Open WhatsApp on your phone, tap <b>Settings → Linked devices → Link a device</b>
          and scan the QR code shown here.
        </p>

        <button class="btn green block" :disabled="busy" @click="connect">
          <span v-if="busy" class="spinner"></span>
          {{ whatsapp.status === 'qr' ? 'Refresh QR code' : 'Connect WhatsApp' }}
        </button>
      </div>

      <!-- Connected -->
      <div v-else class="connected-panel">
        <div class="card centered">
          <div style="font-size:58px">✅</div>
          <h2 style="margin:0">WhatsApp Connected</h2>
          <p class="hint" v-if="whatsapp.phone">Number: <b>+{{ whatsapp.phone }}</b></p>
          <p class="hint" v-if="whatsapp.name">Account name: <b>{{ whatsapp.name }}</b></p>
          <button class="btn danger" :disabled="busy" @click="logout">
            <span v-if="busy" class="spinner"></span>
            Log out
          </button>
        </div>
      </div>

      <!-- Help -->
      <div class="card">
        <h3 style="margin:0 0 12px">How it works</h3>
        <ol class="steps mb" style="padding-left:20px;line-height:1.9">
          <li>Click <b>Connect WhatsApp</b> — a QR code appears.</li>
          <li>On your phone open <b>WhatsApp → Settings → Linked devices</b> and tap <b>Link a device</b>.</li>
          <li>Scan the QR code from your phone.</li>
          <li>Once scanned, the status changes to <b>WhatsApp Connected</b>.</li>
        </ol>
        <div class="alert info">
          Your session is saved locally in the <code>whatsapp/.wwebjs_auth</code> folder,
          so you won't need to scan again on the next start.
        </div>
        <div class="alert warn">
          Unofficial integration based on WhatsApp Web. Use responsibly and only send messages to people who expect them.
        </div>
      </div>
    </div>
  </div>
</template>