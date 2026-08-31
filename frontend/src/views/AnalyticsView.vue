<script setup>
import { onMounted, ref } from 'vue'
import api from '../api'

const analytics = ref(null)
const error = ref('')

async function load() {
  error.value = ''
  try {
    const { data } = await api.get('/campaigns/analytics')
    analytics.value = data.data
  } catch (e) {
    error.value = e.message
  }
}

onMounted(load)

const statusMeta = {
  draft: { label: 'Draft', color: 'draft' },
  running: { label: 'Running', color: 'running' },
  paused: { label: 'Paused', color: 'paused' },
  completed: { label: 'Completed', color: 'sent' },
  stopped: { label: 'Stopped', color: 'cancelled' },
  failed: { label: 'Failed', color: 'failed' },
}
</script>

<template>
  <div class="page">
    <div class="page-head">
      <h1>Campaign analytics</h1>
      <p>Overview of campaign performance, delivery quality, and team efficiency.</p>
    </div>

    <div v-if="error" class="alert error">{{ error }}</div>

    <div v-if="!analytics" class="card empty">Loading analytics…</div>

    <template v-else>
      <div class="report-grid mb">
        <div class="report-card success">
          <span class="eyebrow">Campaigns</span>
          <strong>{{ analytics.total_campaigns }}</strong>
          <small>Total launched</small>
        </div>
        <div class="report-card info">
          <span class="eyebrow">Recipients</span>
          <strong>{{ analytics.total_recipients }}</strong>
          <small>People contacted</small>
        </div>
        <div class="report-card warn">
          <span class="eyebrow">Successful</span>
          <strong>{{ analytics.total_success }}</strong>
          <small>Confirmed delivery</small>
        </div>
        <div class="report-card danger">
          <span class="eyebrow">Success rate</span>
          <strong>{{ analytics.success_rate }}%</strong>
          <small>Delivery quality</small>
        </div>
      </div>

      <div class="grid two mb">
        <div class="card">
          <div class="section-title">Status breakdown</div>
          <div class="stat-list">
            <div v-for="(value, key) in analytics.status_breakdown" :key="key" class="stat-row">
              <div class="stat-left">
                <span class="badge" :class="statusMeta[key]?.color || 'draft'">{{ statusMeta[key]?.label || key }}</span>
              </div>
              <div class="stat-right">
                <strong>{{ value }}</strong>
                <div class="mini-track">
                  <span :style="{ width: Math.max((value / Math.max(analytics.total_campaigns, 1)) * 100, 6) + '%' }"></span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="section-title">Delivery summary</div>
          <div class="summary-stack">
            <div class="summary-row">
              <span>Total success</span>
              <strong>{{ analytics.total_success }}</strong>
            </div>
            <div class="summary-row">
              <span>Total failed</span>
              <strong>{{ analytics.total_failed }}</strong>
            </div>
            <div class="summary-row">
              <span>Success rate</span>
              <strong>{{ analytics.success_rate }}%</strong>
            </div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="section-title">Recent activity</div>
        <div v-if="analytics.recent_message_volume.length === 0" class="empty">No recent activity yet.</div>
        <div v-else class="table-wrap">
          <table>
            <thead>
              <tr><th>Day</th><th>Messages</th></tr>
            </thead>
            <tbody>
              <tr v-for="item in analytics.recent_message_volume" :key="item.day">
                <td>{{ item.day }}</td>
                <td>{{ item.total }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>
  </div>
</template>
