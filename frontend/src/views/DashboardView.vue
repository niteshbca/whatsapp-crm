<script setup>
import { onMounted, ref } from 'vue'
import api from '../api'
import { whatsapp, statusLabels } from '../store'

const data = ref(null)
const error = ref('')

const statusTitle = {
  draft: 'Draft', running: 'Running', paused: 'Paused',
  completed: 'Completed', stopped: 'Stopped', failed: 'Failed',
}

const kpis = [
  { key: 'contacts', label: 'Contacts', accent: 'emerald', icon: '👥' },
  { key: 'leads', label: 'Leads', accent: 'violet', icon: '🎯' },
  { key: 'sent', label: 'Messages sent', accent: 'blue', icon: '✓' },
  { key: 'campaigns', label: 'Campaigns', accent: 'amber', icon: '📦' },
]

function getKpiValue(key) {
  return data.value?.totals?.[key] ?? 0
}

async function load() {
  error.value = ''
  try {
    const { data: res } = await api.get('/dashboard')
    data.value = res
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
        <span class="eyebrow">Executive summary</span>
        <h1>Growth dashboard</h1>
      </div>
      <div class="header-actions">
        <RouterLink to="/bulk" class="btn green">New campaign</RouterLink>
      </div>
    </div>

    <div v-if="error" class="alert error">{{ error }}</div>
    <div v-else-if="!data" class="alert info">Loading…</div>

    <template v-else>
      <div class="dashboard-kpis mb">
        <div v-for="kpi in kpis" :key="kpi.key" class="kpi-card" :class="kpi.accent">
          <div class="kpi-icon">{{ kpi.icon }}</div>
          <div>
            <div class="kpi-value">{{ getKpiValue(kpi.key) }}</div>
            <div class="kpi-label">{{ kpi.label }}</div>
          </div>
        </div>
      </div>

      <div class="ai-grid mb">
        <div v-for="insight in data.ai_insights" :key="insight.type" class="ai-card" :class="insight.type">
          <div class="ai-topline">
            <span class="eyebrow">{{ insight.title }}</span>
            <span class="trend-pill">{{ insight.trend }}</span>
          </div>
          <strong>{{ insight.value }}</strong>
          <p>{{ insight.detail }}</p>
        </div>
      </div>

      <div class="grid two dashboard-panels">
        <div class="card application-card">
          <div class="panel-head">
            <h3>WhatsApp connection</h3>
            <RouterLink to="/connect" class="btn ghost sm">Open</RouterLink>
          </div>

          <div class="row status-row" style="gap:10px">
            <span class="badge" :class="data.whatsapp.connected ? 'ready' : data.whatsapp.status === 'qr' ? 'qr' : 'failed'">
              {{ whatsapp.connected ? 'Connected' : statusLabels[data.whatsapp.status] || 'Unknown' }}
            </span>
            <span v-if="data.whatsapp.phone" class="hint">+{{ data.whatsapp.phone }}</span>
          </div>

          <p v-if="data.whatsapp.error && !data.whatsapp.connected" class="hint mt" style="color:var(--danger)">
            {{ data.whatsapp.error }}
          </p>

          <p v-if="!data.whatsapp.connected" class="hint mt">
            WhatsApp is not connected. <RouterLink to="/connect" class="link">Scan the QR code</RouterLink> to start sending messages.
          </p>
        </div>

        <div class="card application-card">
          <div class="panel-head">
            <h3>Business summary</h3>
          </div>

          <div class="summary-stack">
            <div class="summary-row soft-green">
              <span>Quality score</span>
              <strong>{{ data.business_summary.quality_score }}%</strong>
            </div>
            <div class="summary-row soft-blue">
              <span>Open leads</span>
              <strong>{{ data.totals.leads || 0 }}</strong>
            </div>
            <div class="summary-row soft-gold">
              <span>Next action</span>
              <strong class="small-text">{{ data.business_summary.next_action }}</strong>
            </div>
          </div>
        </div>
      </div>

      <div class="card mt-24">
        <div class="panel-head mb">
          <h3>Recent campaigns</h3>
          <RouterLink to="/history" class="btn ghost sm">See all</RouterLink>
        </div>

        <div v-if="data.recent_campaigns.length === 0" class="empty">
          No campaigns yet. <RouterLink to="/bulk" class="link">Create your first one</RouterLink>.
        </div>

        <div class="table-wrap" v-else>
          <table>
            <thead>
              <tr><th>Name</th><th>Status</th><th>Sent / Total</th><th>Failed</th></tr>
            </thead>
            <tbody>
              <tr v-for="c in data.recent_campaigns" :key="c.id">
                <td>
                  <RouterLink :to="`/history`" class="link">{{ c.name }}</RouterLink>
                </td>
                <td><span class="badge" :class="c.status">{{ statusTitle[c.status] }}</span></td>
                <td>{{ c.success }} / {{ c.total }}</td>
                <td>{{ c.failed }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>
  </div>
</template>