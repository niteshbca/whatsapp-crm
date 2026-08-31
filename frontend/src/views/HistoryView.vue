<script setup>
import { onMounted, reactive, ref } from 'vue'
import api from '../api'

const campaigns = ref([])
const page = ref(1)
const last = ref(1)
const error = ref('')
const expanded = ref(null)
const details = ref(null)
const detailLoading = ref(false)

const statusTitle = {
  draft: 'Draft', running: 'Running', paused: 'Paused',
  completed: 'Completed', stopped: 'Stopped', failed: 'Failed',
}

async function load() {
  error.value = ''
  try {
    const { data } = await api.get('/campaigns', { params: { page: page.value } })
    campaigns.value = data.data
    last.value = data.last_page
  } catch (e) {
    error.value = e.message
  }
}

async function toggle(c) {
  if (expanded.value === c.id) {
    expanded.value = null
    details.value = null
    return
  }
  expanded.value = c.id
  detailLoading.value = true
  details.value = null
  try {
    const { data } = await api.get(`/campaigns/${c.id}`)
    details.value = data
  } catch (e) {
    error.value = e.message
  } finally {
    detailLoading.value = false
  }
}

async function remove(c) {
  if (!confirm(`Delete campaign "${c.name}"? Its message history will be removed.`)) return
  try {
    await api.delete(`/campaigns/${c.id}`)
    await load()
  } catch (e) {
    error.value = e.message
  }
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
      <h1>Campaign History</h1>
      <p>All campaigns and their sending results.</p>
    </div>

    <div v-if="error" class="alert error">{{ error }}</div>

    <div class="card">
      <div v-if="campaigns.length === 0" class="empty">
        No campaigns yet. <RouterLink to="/bulk" class="link">Create your first campaign</RouterLink>.
      </div>

      <div v-else class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Name</th><th>Status</th><th>Success / Total</th><th>Failed</th><th>Started</th><th></th>
            </tr>
          </thead>
          <tbody>
            <template v-for="c in campaigns" :key="c.id">
              <tr @click="toggle(c)" style="cursor:pointer">
                <td>
                  {{ c.name }}
                  <span v-if="c.media_name" class="hint"> · 📎 {{ c.media_name }}</span>
                </td>
                <td><span class="badge" :class="c.status">{{ statusTitle[c.status] }}</span></td>
                <td>{{ c.success }} / {{ c.total }}</td>
                <td>{{ c.failed }}</td>
                <td class="hint">{{ c.started_at ? new Date(c.started_at).toLocaleString() : '—' }}</td>
                <td class="hint">{{ expanded === c.id ? '▲' : '▼' }}</td>
              </tr>
              <tr v-if="expanded === c.id">
                <td colspan="6" style="background:#fafbfc">
                  <div v-if="detailLoading" class="empty">Loading details…</div>
                  <div v-else-if="details">
                    <div class="row gap8 mb">
                      <span class="badge pending">Pending {{ details.counts.pending + details.counts.sending }}</span>
                      <span class="badge sent">Sent {{ details.counts.sent }}</span>
                      <span class="badge failed">Failed {{ details.counts.failed }}</span>
                      <span class="badge cancelled">Cancelled {{ details.counts.cancelled }}</span>
                    </div>

                    <div class="alert info" style="white-space:pre-wrap">{{ details.campaign.message }}</div>

                    <button class="btn ghost sm mb" @click.stop="details.expandMsgs = !details.expandMsgs">
                      {{ details.expandMsgs ? 'Hide messages' : 'Show messages' }}
                    </button>

                    <div v-if="details.expandMsgs" class="table-wrap mt">
                      <table>
                        <thead><tr><th>#</th><th>Name</th><th>Number</th><th>Status</th><th>Error</th><th>Sent at</th></tr></thead>
                        <tbody>
                          <tr v-for="m in details.messages.data" :key="m.id">
                            <td class="hint">{{ m.id }}</td>
                            <td>{{ m.name || '—' }}</td>
                            <td>+{{ m.number }}</td>
                            <td><span class="badge" :class="m.status">{{ m.status }}</span></td>
                            <td class="hint" style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ m.error || '—' }}</td>
                            <td class="hint">{{ m.sent_at ? new Date(m.sent_at).toLocaleString() : '—' }}</td>
                          </tr>
                        </tbody>
                      </table>
                    </div>

                    <div class="mt">
                      <button class="btn danger sm" @click.stop="remove(c)">Delete campaign</button>
                    </div>
                  </div>
                </td>
              </tr>
            </template>
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