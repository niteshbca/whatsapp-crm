<script setup>
import { computed, onMounted, ref } from 'vue'
import api from '../api'

const leads = ref([])
const stats = ref(null)
const error = ref('')
const search = ref('')
const selectedStage = ref('all')
const form = ref({
  name: '',
  phone: '',
  email: '',
  source: 'manual',
  stage: 'new',
  notes: '',
  value: 0,
  owner_name: '',
})

const stages = ['new', 'contacted', 'qualified', 'proposal', 'won', 'lost']

const stageMeta = {
  new: { label: 'New', color: 'draft' },
  contacted: { label: 'Contacted', color: 'pending' },
  qualified: { label: 'Qualified', color: 'sending' },
  proposal: { label: 'Proposal', color: 'qr' },
  won: { label: 'Won', color: 'ready' },
  lost: { label: 'Lost', color: 'failed' },
}

const filteredLeads = computed(() => {
  return leads.value.filter((lead) => {
    const stageOk = selectedStage.value === 'all' || lead.stage === selectedStage.value
    const query = search.value.trim().toLowerCase()
    const searchOk = !query || [lead.name, lead.phone, lead.email, lead.source].join(' ').toLowerCase().includes(query)
    return stageOk && searchOk
  })
})

const groupedLeads = computed(() => {
  return stages.reduce((acc, stage) => {
    acc[stage] = filteredLeads.value.filter((lead) => lead.stage === stage)
    return acc
  }, {})
})

async function load() {
  error.value = ''
  try {
    const { data } = await api.get('/leads', {
      params: { search: search.value || undefined, stage: selectedStage.value !== 'all' ? selectedStage.value : undefined },
    })
    leads.value = data.data || []

    const { data: statsData } = await api.get('/leads/stats')
    stats.value = statsData.data
  } catch (e) {
    error.value = e.message
  }
}

async function submit() {
  if (!form.value.name.trim()) {
    error.value = 'Lead name is required.'
    return
  }

  try {
    await api.post('/leads', {
      name: form.value.name,
      phone: form.value.phone,
      email: form.value.email,
      source: form.value.source,
      stage: form.value.stage,
      notes: form.value.notes,
      value: Number(form.value.value || 0),
      owner_name: form.value.owner_name,
    })

    form.value = {
      name: '',
      phone: '',
      email: '',
      source: 'manual',
      stage: 'new',
      notes: '',
      value: 0,
      owner_name: '',
    }

    await load()
  } catch (e) {
    error.value = e.message
  }
}

async function updateStage(leadId, stage) {
  try {
    await api.patch(`/leads/${leadId}`, { stage })
    await load()
  } catch (e) {
    error.value = e.message
  }
}

async function remove(leadId) {
  if (!confirm('Delete this lead?')) return
  try {
    await api.delete(`/leads/${leadId}`)
    await load()
  } catch (e) {
    error.value = e.message
  }
}

onMounted(load)
</script>

<template>
  <div class="page">
    <div class="page-head row between">
      <div>
        <h1>Lead pipeline</h1>
        <p>Track opportunities from first contact to won business.</p>
      </div>
      <button class="btn ghost" @click="load">Refresh board</button>
    </div>

    <div v-if="error" class="alert error">{{ error }}</div>

    <div v-if="stats" class="grid cards mb">
      <div class="card stat soft-green">
        <div class="stat-icon">👥</div>
        <div>
          <div class="stat-value">{{ stats.total }}</div>
          <div class="stat-label">Total leads</div>
        </div>
      </div>
      <div class="card stat soft-blue">
        <div class="stat-icon">🆕</div>
        <div>
          <div class="stat-value">{{ stats.by_stage.new }}</div>
          <div class="stat-label">New</div>
        </div>
      </div>
      <div class="card stat soft-gold">
        <div class="stat-icon">✅</div>
        <div>
          <div class="stat-value">{{ stats.by_stage.won }}</div>
          <div class="stat-label">Won</div>
        </div>
      </div>
      <div class="card stat soft-violet">
        <div class="stat-icon">💰</div>
        <div>
          <div class="stat-value">₹{{ Number(stats.total_value || 0).toLocaleString() }}</div>
          <div class="stat-label">Pipeline value</div>
        </div>
      </div>
    </div>

    <div class="lead-stage-layout mb">
      <div class="lead-form-panel card team-card">
        <div class="panel-head mb">
          <div>
            <span class="eyebrow">Capture</span>
            <h3>Add lead</h3>
          </div>
        </div>

        <div class="lead-form-grid">
          <label class="field full-wide">
            <span class="lb">Name</span>
            <input v-model="form.name" type="text" placeholder="Aditi Sharma" />
          </label>

          <label class="field">
            <span class="lb">Phone</span>
            <input v-model="form.phone" type="text" placeholder="15551234567" />
          </label>

          <label class="field">
            <span class="lb">Email</span>
            <input v-model="form.email" type="text" placeholder="name@example.com" />
          </label>

          <label class="field">
            <span class="lb">Source</span>
            <select v-model="form.source">
              <option value="manual">Manual</option>
              <option value="website">Website</option>
              <option value="campaign">Campaign</option>
              <option value="referral">Referral</option>
              <option value="social">Social</option>
            </select>
          </label>

          <label class="field">
            <span class="lb">Stage</span>
            <select v-model="form.stage">
              <option v-for="stage in stages" :key="stage" :value="stage">{{ stageMeta[stage].label }}</option>
            </select>
          </label>

          <label class="field">
            <span class="lb">Value</span>
            <input v-model.number="form.value" type="number" min="0" step="100" placeholder="0" />
          </label>

          <label class="field">
            <span class="lb">Owner</span>
            <input v-model="form.owner_name" type="text" placeholder="Sales owner" />
          </label>

          <label class="field full-wide">
            <span class="lb">Notes</span>
            <textarea v-model="form.notes" placeholder="Interested in demo and pricing details"></textarea>
          </label>
        </div>

        <div class="lead-form-actions">
          <button class="btn green" @click="submit">Save lead</button>
        </div>
      </div>

      <div class="card team-card lead-board-panel">
        <div class="panel-head mb">
          <div>
            <span class="eyebrow">Overview</span>
            <h3>Pipeline overview</h3>
          </div>
        </div>

        <div class="lead-toolbar mb">
          <input v-model="search" type="text" placeholder="Search leads" />
          <select v-model="selectedStage">
            <option value="all">All stages</option>
            <option v-for="stage in stages" :key="stage" :value="stage">{{ stageMeta[stage].label }}</option>
          </select>
        </div>

        <div class="pipeline-board">
          <div v-for="stage in stages" :key="stage" class="pipeline-column">
            <div class="column-header">
              <span>{{ stageMeta[stage].label }}</span>
              <b>{{ groupedLeads[stage]?.length || 0 }}</b>
            </div>

            <div v-if="(groupedLeads[stage] || []).length === 0" class="lead-empty">No leads</div>

            <div v-for="lead in groupedLeads[stage] || []" :key="lead.id" class="lead-card">
              <div class="lead-head">
                <strong>{{ lead.name }}</strong>
                <span class="badge" :class="stageMeta[lead.stage].color">{{ stageMeta[lead.stage].label }}</span>
              </div>

              <div class="lead-meta">
                <span>{{ lead.source }}</span>
                <strong>₹{{ Number(lead.value || 0).toLocaleString() }}</strong>
              </div>

              <div class="lead-contact">
                <span>{{ lead.email || 'No email' }}</span>
                <span>{{ lead.phone || 'No phone' }}</span>
              </div>

              <div class="lead-actions">
                <select :value="lead.stage" @change="updateStage(lead.id, $event.target.value)">
                  <option v-for="option in stages" :key="option" :value="option">{{ stageMeta[option].label }}</option>
                </select>
                <button class="btn danger sm" @click="remove(lead.id)">Delete</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
