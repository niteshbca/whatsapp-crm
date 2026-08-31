<script setup>
import { onMounted, reactive, ref } from 'vue'
import api from '../api'

const contacts = ref([])
const page = ref(1)
const last = ref(1)
const search = ref('')
const error = ref('')
const info = ref('')
const busy = ref(false)

const add = reactive({ name: '', number: '' })
const importText = ref('')
const importFile = ref(null)
const importCount = ref(0)
const showImport = ref(false)

async function load() {
  error.value = ''
  try {
    const { data } = await api.get('/contacts', {
      params: { page: page.value, search: search.value || undefined },
    })
    contacts.value = data.data
    last.value = data.last_page
  } catch (e) {
    error.value = e.message
  }
}

async function createOne() {
  error.value = ''
  if (!add.number.trim()) return (error.value = 'Number is required')
  busy.value = true
  try {
    await api.post('/contacts', { name: add.name, number: add.number })
    add.name = ''
    add.number = ''
    info.value = 'Contact added'
    await load()
  } catch (e) {
    error.value = e.message
  } finally {
    busy.value = false
  }
}

async function remove(c) {
  if (!confirm(`Remove contact +${c.number}?`)) return
  try {
    await api.delete(`/contacts/${c.id}`)
    await load()
  } catch (e) {
    error.value = e.message
  }
}

function onImportFile(e) {
  importFile.value = e.target.files?.[0] || null
}

async function runImport() {
  error.value = ''
  info.value = ''
  const numbers = importText.value.split(/\r?\n/).map((l) => l.trim() || l.split(/[\t;,]/).pop().trim()).filter(Boolean)
  if (numbers.length === 0 && !importFile.value) return (error.value = 'Nothing to import')

  busy.value = true
  try {
    const fd = new FormData()
    fd.append('numbers', JSON.stringify(numbers))
    if (importFile.value) fd.append('csv', importFile.value)
    const { data } = await api.post('/contacts/import', fd)
    info.value = `Import finished: ${data.created} created, ${data.existing} already existed, ${data.total} total.`
    await load()
  } catch (e) {
    error.value = e.message
  } finally {
    busy.value = false
  }
}

function onSearch() {
  page.value = 1
  load()
}

function reload(p) {
  page.value = p
  load()
}

onMounted(load)
</script>

<template>
  <div class="page">
    <div class="page-head">
      <h1>Contacts</h1>
      <p>Your address book. These numbers are also auto-created when you run campaigns.</p>
    </div>

    <div v-if="error" class="alert error">{{ error }}</div>
    <div v-if="info" class="alert success">{{ info }}</div>

    <div class="grid two mb">
      <div class="card">
        <h3 style="margin:0 0 14px">Add contact</h3>
        <div class="row">
          <input type="text" placeholder="Name (optional)" v-model="add.name" style="flex:1" />
          <input type="text" placeholder="Phone number" v-model="add.number" style="flex:1" />
          <button class="btn sm" @click="createOne" :disabled="busy">Add</button>
        </div>
      </div>

      <div class="card">
        <button class="btn ghost sm" @click="showImport = !showImport">
          {{ showImport ? 'Hide import' : 'Import CSV / bulk numbers' }}
        </button>

        <div v-if="showImport" class="mt">
          <label class="field">
            <span class="lb">Paste numbers <span class="hint">(one per line)</span></span>
            <textarea v-model="importText" placeholder="15551234567&#10;15559876543"></textarea>
          </label>

          <label class="field">
            <span class="lb">Or upload CSV</span>
            <label class="file-drop">
              <input type="file" class="hidden" accept=".csv,.txt" @change="onImportFile" />
              <span v-if="importFile">{{ importFile.name }}</span>
              <span v-else>Click to choose a CSV file</span>
            </label>
          </label>

          <button class="btn green" @click="runImport" :disabled="busy">
            <span v-if="busy" class="spinner"></span>
            Import
          </button>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="row mb">
        <input type="text" placeholder="Search name or number…" v-model="search" @keyup.enter="onSearch" style="max-width:260px" />
        <button class="btn ghost sm" @click="onSearch">Search</button>
        <span class="hint">{{ contacts.length }} shown</span>
      </div>

      <div v-if="contacts.length === 0" class="empty">
        No contacts yet. Add one above or run your first campaign.
      </div>

      <div v-else class="table-wrap">
        <table>
          <thead><tr><th>#</th><th>Name</th><th>Number</th><th>Added</th><th></th></tr></thead>
          <tbody>
            <tr v-for="c in contacts" :key="c.id">
              <td class="hint">{{ c.id }}</td>
              <td>{{ c.name || '—' }}</td>
              <td>+{{ c.number }}</td>
              <td class="hint">{{ new Date(c.created_at).toLocaleDateString() }}</td>
              <td><button class="btn danger sm" @click="remove(c)">Delete</button></td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="row between mt">
        <button v-if="page > 1" class="btn ghost sm" @click="reload(page - 1)">← Prev</button>
        <span class="hint">Page {{ page }} of {{ last }}</span>
        <button v-if="page < last" class="btn ghost sm" @click="reload(page + 1)">Next →</button>
      </div>
    </div>
  </div>
</template>