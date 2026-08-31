<script setup>
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue'
import api from '../api'
import { whatsapp } from '../store'
import * as XLSX from 'xlsx'

const form = reactive({ name: '', message: '', delayMin: 2, delayMax: 5, companyId: '', senderNumber: '' })
const companies = ref([])
const senderOptions = ref([])
const manualText = ref('')
const csvFile = ref(null)
const uploadFile = ref(null)
const mediaFiles = ref([])
const error = ref('')
const info = ref('')
const creating = ref(false)
const busy = ref('')
const savedTemplates = ref([])
const templateName = ref('')
const selectedTemplate = ref('')
const templateCategory = ref('general')
const selectedCompanyStatus = ref({ connected: false, status: 'unlinked', phone: null })

const campaign = ref(null) // { info, counts, messages }
const messagesPage = reactive({ page: 1, search: '', status: '', last: 1, loading: false })

let pollTimer = null
let companyStatusTimer = null

async function loadSavedTemplates() {
  try {
    const { data } = await api.get('/message-templates')
    savedTemplates.value = (data.data || []).map((item) => ({
      id: item.id,
      name: item.name,
      content: item.content,
      category: item.category || 'general',
      is_default: Boolean(item.is_default),
    }))
  } catch (e) {
    savedTemplates.value = []
  }
}

async function saveTemplate() {
  const name = templateName.value.trim()
  if (!name || !form.message.trim()) return (error.value = 'Enter a template name and message first')

  const payload = {
    company_id: form.companyId || null,
    name,
    content: form.message.trim(),
    category: templateCategory.value,
    is_default: false,
  }

  try {
    const existing = savedTemplates.value.find((item) => item.name.toLowerCase() === name.toLowerCase())
    let response
    if (existing) {
      response = await api.put(`/message-templates/${existing.id}`, payload)
    } else {
      response = await api.post('/message-templates', payload)
    }

    selectedTemplate.value = response.data.data.name
    templateName.value = ''
    await loadSavedTemplates()
    info.value = 'Message template saved successfully.'
  } catch (e) {
    error.value = e.message
  }
}

function applyTemplate() {
  const template = savedTemplates.value.find((item) => item.name === selectedTemplate.value)
  if (template) form.message = template.content
}

async function deleteTemplate() {
  if (!selectedTemplate.value) return
  const template = savedTemplates.value.find((item) => item.name === selectedTemplate.value)
  if (!template) return

  try {
    await api.delete(`/message-templates/${template.id}`)
    selectedTemplate.value = ''
    await loadSavedTemplates()
    info.value = 'Template deleted.'
  } catch (e) {
    error.value = e.message
  }
}

function downloadExcelTemplate() {
  const recipients = XLSX.utils.aoa_to_sheet([
    ['name', 'number'],
    ['John Doe', '15551234567'],
    ['Jane Smith', '15559876543'],
  ])
  recipients['!cols'] = [{ wch: 24 }, { wch: 20 }]

  const instructions = XLSX.utils.aoa_to_sheet([
    ['WhatsApp recipient upload instructions'],
    ['Column', 'What to enter'],
    ['name', 'Optional contact name. It can be used with {{name}} in the message.'],
    ['number', 'Required WhatsApp number with country code, digits only. Example: 15551234567'],
    ['Important', 'Do not change the column header "number". Remove sample rows before uploading.'],
  ])
  instructions['!cols'] = [{ wch: 18 }, { wch: 95 }]

  const workbook = XLSX.utils.book_new()
  XLSX.utils.book_append_sheet(workbook, recipients, 'Recipients')
  XLSX.utils.book_append_sheet(workbook, instructions, 'Instructions')
  XLSX.writeFile(workbook, 'whatsapp-recipients-template.xlsx')
}

const statusTitle = {
  draft: 'Draft', running: 'Running', paused: 'Paused',
  completed: 'Completed', stopped: 'Stopped', failed: 'Failed',
}

function parseNumbers(text) {
  const out = []
  for (const raw of String(text).split(/\r?\n/)) {
    const line = raw.trim()
    if (!line) continue
    const parts = line.split(/[\t;,]/).map((s) => s.trim()).filter(Boolean)
    const number = parts.length > 0 ? parts[parts.length - 1] : ''
    const name = parts.length > 1 ? parts.slice(0, -1).join(' ') : null
    out.push({ number, name })
  }
  return out
}

const manualRecipients = computed(() => parseNumbers(manualText.value))

const csvPreview = ref(0)
async function updateCsvPreview() {
  if (!uploadFile.value) return (csvPreview.value = 0)
  const text = await uploadFile.value.text()
  const lines = text.split(/\r?\n/).filter((l) => l.trim() !== '')
  if (!lines.length) return (csvPreview.value = 0)
  const first = lines[0].toLowerCase()
  const hasHeader = /number|phone|phone_number|mobile|celular|telefono|whatsapp/.test(first)
  csvPreview.value = hasHeader ? Math.max(0, lines.length - 1) : lines.length
}

watch(csvFile, updateCsvPreview)

async function onCsv(e) {
  csvFile.value = e.target.files?.[0] || null
  uploadFile.value = csvFile.value
  if (!csvFile.value) return

  if (/\.xlsx?$/i.test(csvFile.value.name)) {
    const workbook = XLSX.read(await csvFile.value.arrayBuffer(), { type: 'array' })
    const firstSheet = workbook.Sheets[workbook.SheetNames[0]]
    const csv = XLSX.utils.sheet_to_csv(firstSheet)
    uploadFile.value = new File([csv], `${csvFile.value.name.replace(/\.xlsx?$/i, '')}.csv`, { type: 'text/csv' })
  }
  await updateCsvPreview()
}
function onMedia(e) {
  mediaFiles.value = Array.from(e.target.files || []).slice(0, 10)
}

function removeManual(index) {
  const without = [...manualRecipients.value]
  without.splice(index, 1)
  manualText.value = without.map((r) => (r.name ? `${r.name}, ${r.number}` : r.number)).join('\n')
}

function removeCsv() {
  csvFile.value = null
  uploadFile.value = null
  csvPreview.value = 0
}

function insertVar(v) {
  form.message += v
}

const NAME_VAR = '{{name}}'
const NUMBER_VAR = '{{number}}'

const totalPreviews = computed(() => manualRecipients.value.length + csvPreview.value)
const canStartCampaign = computed(() => Boolean(form.companyId) && selectedCompanyStatus.value.connected)

async function refreshSelectedCompanyStatus() {
  if (!form.companyId) {
    selectedCompanyStatus.value = { connected: false, status: 'unlinked', phone: null }
    return
  }

  try {
    const { data } = await api.get('/whatsapp/status', {
      params: { company_id: Number(form.companyId) },
    })
    selectedCompanyStatus.value = data
  } catch {
    selectedCompanyStatus.value = { connected: false, status: 'service_down', phone: null }
  }
}

async function loadCompanies() {
  try {
    const { data } = await api.get('/companies')
    companies.value = data.data || []
    if (companies.value.length && !form.companyId) {
      form.companyId = String(companies.value[0].id)
      await loadSenderNumbers(form.companyId)
    }
  } catch (e) {
    error.value = e.message
  }
}

async function loadSenderNumbers(companyId) {
  senderOptions.value = []
  if (!companyId) return
  try {
    const { data } = await api.get(`/companies/${companyId}/accounts`)
    senderOptions.value = data.data || []
    if (senderOptions.value.length && !form.senderNumber) {
      form.senderNumber = senderOptions.value[0].phone_number || senderOptions.value[0].label || ''
    }
  } catch (e) {
    error.value = e.message
  }
}

watch(() => form.companyId, async (companyId) => {
  form.senderNumber = ''
  await loadSenderNumbers(companyId)
  await refreshSelectedCompanyStatus()
})

async function createCampaign() {
  error.value = ''
  info.value = ''
  if (!form.message.trim()) return (error.value = 'Message is required')
  if (totalPreviews.value === 0) return (error.value = 'Add at least one phone number or a CSV file')

  creating.value = true
  try {
    const fd = new FormData()
    fd.append('company_id', form.companyId || '')
    fd.append('sender_number', form.senderNumber || '')
    fd.append('name', form.name.trim() || `Campaign ${new Date().toLocaleString()}`)
    fd.append('message', form.message)
    fd.append('recipients', JSON.stringify(manualRecipients.value.map((r) => r.number)))
    fd.append('delay_min', String(form.delayMin))
    fd.append('delay_max', String(form.delayMax))
    if (uploadFile.value) fd.append('csv', uploadFile.value)
    for (const file of mediaFiles.value) fd.append('media[]', file)

    const { data } = await api.post('/campaigns', fd)
    campaign.value = { info: data, counts: null, messages: null }
    info.value = `Campaign created with ${data.total} recipients. Review the list below, then press Start.`
    await loadCampaign()
  } catch (e) {
    error.value = e.message
  } finally {
    creating.value = false
  }
}

async function loadCampaign() {
  if (!campaign.value) return
  messagesPage.loading = true
  try {
    const { data } = await api.get(`/campaigns/${campaign.value.info.id}`, {
      params: {
        page: messagesPage.page,
        search: messagesPage.search || undefined,
        status: messagesPage.status || undefined,
      },
    })
    campaign.value = { info: data.campaign, counts: data.counts, messages: data.messages }
    messagesPage.last = data.messages.last_page
  } catch (e) {
    error.value = e.message
  } finally {
    messagesPage.loading = false
  }
}

function startPolling() {
  stopPolling()
  pollTimer = setInterval(async () => {
    if (!campaign.value) return
    const status = campaign.value.info.status
    if (status !== 'running' && status !== 'paused') {
      stopPolling()
    }
    await loadCampaign()
  }, 1500)
}

function stopPolling() {
  if (pollTimer) {
    clearInterval(pollTimer)
    pollTimer = null
  }
}

watch(
  () => campaign.value?.info.status,
  (s) => {
    if (s === 'running' || s === 'paused') startPolling()
    else stopPolling()
  },
)

async function act(action) {
  busy.value = action
  error.value = ''
  try {
    if (action === 'start') await refreshSelectedCompanyStatus()
    if (action === 'start' && !canStartCampaign.value) {
      throw new Error('Selected company WhatsApp is not connected. Connect it before starting the campaign.')
    }
    await api.post(`/campaigns/${campaign.value.info.id}/${action}`)
    await loadCampaign()
  } catch (e) {
    error.value = e.message
  } finally {
    busy.value = ''
  }
}

async function sendTest() {
  error.value = ''
  info.value = ''
  const to = (testNumber.value || '').trim()
  if (!/^\d{7,15}$/.test(to.replace('+', ''))) return (error.value = 'Enter a valid test number')
  if (!form.message.trim()) return (error.value = 'Write a message first')
  testBusy.value = true
  try {
    const { data } = await api.post('/whatsapp/test-send', {
      to,
      message: form.message,
      company_id: form.companyId || null,
    })
    if (data.ok) info.value = `Test message sent to +${to} ✓`
    else error.value = `Test failed: ${data.error || 'unknown error'}`
  } catch (e) {
    error.value = e.message
  } finally {
    testBusy.value = false
  }
}

const testNumber = ref('')
const testBusy = ref(false)

const progressPct = computed(() => {
  if (!campaign.value?.info?.total) return 0
  const info = campaign.value.info
  return Math.round(((info.success + info.failed) / info.total) * 100)
})

function reloadPage(p) {
  messagesPage.page = p
  loadCampaign()
}

onMounted(() => {
  loadSavedTemplates()
  loadCompanies().then(refreshSelectedCompanyStatus)
  companyStatusTimer = setInterval(refreshSelectedCompanyStatus, 3000)
})

onUnmounted(() => {
  stopPolling()
  if (companyStatusTimer) clearInterval(companyStatusTimer)
})
</script>

<template>
  <div class="page">
    <div class="page-head">
      <h1>Bulk Message</h1>
      <p>Send a personalized message to many contacts at once.</p>
    </div>

    <div v-if="!canStartCampaign" class="alert warn">
      Selected company WhatsApp is not connected. <RouterLink to="/connect" class="link">Connect it first</RouterLink> — campaigns can only be started while connected.
    </div>

    <div v-if="error" class="alert error">{{ error }}</div>
    <div v-if="info" class="alert success">{{ info }}</div>

    <!-- Compose -->
    <div class="grid two bulk-compose">
      <div class="card bulk-card">
        <h3 style="margin:0 0 14px">Campaign details</h3>

        <div class="row two">
          <label class="field">
            <span class="lb">Company</span>
            <select v-model="form.companyId">
              <option value="">Select company</option>
              <option v-for="company in companies" :key="company.id" :value="String(company.id)">{{ company.name }}</option>
            </select>
          </label>

          <label class="field">
            <span class="lb">Sender WhatsApp number</span>
            <select v-model="form.senderNumber">
              <option value="">Select connected number</option>
              <option v-for="account in senderOptions" :key="account.id" :value="account.phone_number || account.label">
                {{ account.label }} {{ account.phone_number ? `· ${account.phone_number}` : '' }}
              </option>
            </select>
          </label>
        </div>

        <label class="field">
          <span class="lb">Campaign name</span>
          <input type="text" v-model="form.name" placeholder="Summer promotion" />
        </label>

        <label class="field">
          <span class="lb">Message text</span>
          <div class="message-tools">
            <button type="button" class="var-btn" @click="insertVar(NAME_VAR)">{{ NAME_VAR }}</button>
            <button type="button" class="var-btn" @click="insertVar(NUMBER_VAR)">{{ NUMBER_VAR }}</button>
            <span class="hint">Variables are replaced per contact.</span>
          </div>
          <textarea v-model="form.message" :placeholder="'Hi {{name}}, here is your offer…'"></textarea>
        </label>

        <div class="template-box">
          <div class="template-head">
            <div><strong>Reusable message templates</strong><span class="hint">Save once, use for every campaign.</span></div>
            <span class="template-count">{{ savedTemplates.length }} saved</span>
          </div>
          <div class="template-actions">
            <select v-model="selectedTemplate" @change="applyTemplate">
              <option value="">Choose saved template</option>
              <option v-for="template in savedTemplates" :key="template.id || template.name" :value="template.name">{{ template.name }}</option>
            </select>
            <button type="button" class="btn ghost sm" @click="deleteTemplate" :disabled="!selectedTemplate">Delete</button>
          </div>
          <div class="template-save">
            <input v-model="templateName" type="text" placeholder="Template name, e.g. Summer offer" @keyup.enter="saveTemplate" />
            <select v-model="templateCategory" style="max-width:160px">
              <option value="general">General</option>
              <option value="promotion">Promotion</option>
              <option value="followup">Follow-up</option>
              <option value="support">Support</option>
            </select>
            <button type="button" class="btn green sm" @click="saveTemplate">Save template</button>
          </div>
        </div>

        <div class="row two">
          <label class="field">
            <span class="lb">Delay between messages (s)</span>
            <input type="number" v-model.number="form.delayMin" min="1" max="120" />
          </label>
          <label class="field">
            <span class="lb">Max delay (s)</span>
            <input type="number" v-model.number="form.delayMax" min="1" max="120" />
          </label>
        </div>

        <label class="field">
          <span class="lb">Optional image / document</span>
          <label class="file-drop">
            <input type="file" class="hidden" multiple accept="image/*,.pdf,.doc,.docx,.xls,.xlsx" @change="onMedia" />
            <span v-if="mediaFiles.length">{{ mediaFiles.length }} files selected: {{ mediaFiles.map((file) => file.name).join(', ') }}</span>
            <span v-else>Click to choose images or documents to attach</span>
          </label>
        </label>

        <div class="row gap8">
          <button class="btn" :disabled="creating || !form.message" @click="createCampaign">
            <span v-if="creating" class="spinner"></span>
            Create campaign ({{ totalPreviews }} recipients)
          </button>
        </div>
      </div>

      <div class="card bulk-card">
        <h3 style="margin:0 0 14px">Recipients</h3>

        <label class="field">
          <span class="lb">Manual numbers <span class="hint">(one per line — name, number or number)</span></span>
          <textarea v-model="manualText" placeholder="John Doe, 15551234567&#10;15559876543&#10;status_message, 14155550123" />
        </label>

        <label class="field">
          <span class="lb">Upload Excel / CSV</span>
          <label class="file-drop">
            <input type="file" class="hidden" accept=".csv,.txt,.xlsx,.xls" @change="onCsv" />
            <span v-if="csvFile">{{ csvFile.name }} — {{ csvPreview }} numbers found</span>
            <span v-else>Click to upload CSV with a <b>number</b> column (name column optional)</span>
          </label>
          <button type="button" class="btn ghost sm download-template" @click="downloadExcelTemplate">Download Excel template (.xlsx)</button>
          <p class="hint">Use the <b>Recipients</b> sheet. Keep the <b>number</b> column and include the country code.</p>
        </label>

        <div class="alert info">
          Include the country code without the leading digit assumptions, e.g. <b>15551234567</b> for a US number.
        </div>

        <div class="chips">
          <div class="chip" v-for="(r, i) in manualRecipients" :key="`${r.number}-${i}`">
            <span v-if="r.name"><b>{{ r.name }}</b> · </span>{{ r.number }}
            <button @click="removeManual(i)">✕</button>
          </div>
          <div v-if="csvPreview > 0" class="chip">CSV: <b>{{ csvPreview }} numbers</b><button @click="removeCsv">✕</button></div>
          <span v-if="manualRecipients.length === 0 && csvPreview === 0" class="hint">No recipients yet</span>
        </div>

        <div class="mt" v-if="form.message">
          <h4 style="margin:0 0 6px">Preview with variables</h4>
          <div class="alert info" style="white-space:pre-wrap">
            {{ form.message.replace(/\{\{\s*name\s*\}\}/gi, 'John Doe').replace(/\{\{\s*number\s*\}\}/gi, '15551234567') }}
          </div>
        </div>
      </div>
    </div>

    <!-- Send test -->
    <div class="card mt">
      <div class="row">
        <label class="field" style="margin:0;flex:1">
          <span class="lb">Send a test message (text only) to verify your connection</span>
          <input type="text" v-model="testNumber" placeholder="15551234567" />
        </label>
        <button class="btn ghost" style="margin-top:22px" @click="sendTest" :disabled="testBusy">
          <span v-if="testBusy" class="spinner" style="border-color:#94a3b8;border-top-color:#2563eb"></span>
          Send test
        </button>
      </div>
    </div>

    <!-- Running campaign -->
    <div v-if="campaign" class="card mt">
      <div class="between mb">
        <div>
          <h3 style="margin:0">{{ campaign.info.name }}</h3>
          <span class="badge" :class="campaign.info.status">{{ statusTitle[campaign.info.status] }}</span>
        </div>
        <div class="row gap8">
          <button v-if="campaign.info.status === 'draft'" class="btn green" :disabled="busy || !form.companyId" @click="act('start')">
            <span v-if="busy === 'start'" class="spinner"></span> Start campaign
          </button>
          <button v-if="campaign.info.status === 'running'" class="btn gray" :disabled="busy" @click="act('pause')">
            <span v-if="busy === 'pause'" class="spinner"></span> Pause
          </button>
          <button v-if="campaign.info.status === 'paused'" class="btn green" :disabled="busy" @click="act('resume')">
            <span v-if="busy === 'resume'" class="spinner"></span> Resume
          </button>
          <button v-if="campaign.info.status === 'running' || campaign.info.status === 'paused'" class="btn danger" :disabled="busy" @click="act('stop')">
            <span v-if="busy === 'stop'" class="spinner"></span> Stop
          </button>
        </div>
      </div>

      <div class="mb">
        <div class="row between mb" style="gap:16px">
          <div class="hint" id="campaignProgress">
            Sent <b>{{ campaign.info.success }}</b> · failed <b>{{ campaign.info.failed }}</b> · pending <b>{{ campaign.info.pending }}</b> of {{ campaign.info.total }}
          </div>
          <div class="hint"><b>{{ progressPct }}%</b></div>
        </div>
        <div class="progress">
          <div :class="{ busy: campaign.info.status === 'running' || campaign.info.status === 'paused' }" :style="{ width: progressPct + '%' }"></div>
        </div>
        <div v-if="campaign.info.error" class="alert error mt">{{ campaign.info.error }}</div>
      </div>

      <div class="row mb">
        <input type="text" placeholder="Search number / name…" v-model="messagesPage.search" @input="messagesPage.page = 1; loadCampaign()" style="max-width:240px" />
        <select v-model="messagesPage.status" @change="messagesPage.page = 1; loadCampaign()" style="max-width:150px">
          <option value="">All statuses</option>
          <option value="pending">Pending</option>
          <option value="sending">Sending</option>
          <option value="sent">Sent</option>
          <option value="failed">Failed</option>
          <option value="cancelled">Cancelled</option>
        </select>
      </div>

      <div class="table-wrap">
        <table>
          <thead>
            <tr><th>#</th><th>Name</th><th>Number</th><th>Status</th><th>Error</th></tr>
          </thead>
          <tbody>
            <tr v-for="m in campaign.messages?.data || []" :key="m.id">
              <td class="hint">{{ m.id }}</td>
              <td>{{ m.name || '—' }}</td>
              <td>+{{ m.number }}</td>
              <td><span class="badge" :class="m.status">{{ m.status }}</span></td>
              <td class="hint" style="max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ m.error || '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="row between mt">
        <button v-if="messagesPage.page > 1" class="btn ghost sm" @click="reloadPage(messagesPage.page - 1)">← Prev</button>
        <span class="hint">Page {{ messagesPage.page }} of {{ messagesPage.last }}</span>
        <button v-if="messagesPage.page < messagesPage.last" class="btn ghost sm" @click="reloadPage(messagesPage.page + 1)">Next →</button>
      </div>
    </div>
  </div>
</template>