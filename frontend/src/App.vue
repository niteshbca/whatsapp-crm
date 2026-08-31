<script setup>
import { onMounted, onUnmounted } from 'vue'
import { useRoute } from 'vue-router'
import { whatsapp, refreshWhatsapp, statusLabels } from './store'

const icons = {
  dashboard: `
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/></svg>`,
  connect: `
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 8V5c0-1.1.9-2 2-2h3"/><path d="M16 3h3c1.1 0 2 .9 2 2v3"/><path d="M21 16v3c0 1.1-.9 2-2 2h-3"/><path d="M8 21H5c-1.1 0-2-.9-2-2v-3"/><rect x="7" y="7" width="10" height="10" rx="2"/></svg>`,
  bulk: `
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8H13"/><path d="M21 12H11"/><path d="M21 16H13"/><path d="M7 8H3"/><path d="M9 6v4"/><rect x="3" y="11" width="4" height="6" rx="1"/></svg>`,
  history: `
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>`,
  contacts: `
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6"/><path d="M22 11h-6"/></svg>`,
  company: `
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21V7l9-4 9 4v14"/><path d="M9 10h6"/><path d="M9 14h6"/><path d="M9 18h6"/></svg>`,
  users: `
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"/><circle cx="9.5" cy="7" r="4"/><path d="M20 8v6"/><path d="M23 11h-6"/></svg>`,
  accounts: `
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/><rect x="3" y="4" width="18" height="16" rx="2"/></svg>`,
  analytics: `
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 18V8"/><path d="M10 18V4"/><path d="M16 18v-6"/><path d="M22 18V10"/></svg>`,
  leads: `
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 18h18"/><path d="M7 14l3-3 3 2 5-7"/><path d="M17 6h2v2"/></svg>`,
  appointments: `
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M8 2v4"/><path d="M16 2v4"/><path d="M3 10h18"/><path d="M8 15h3v3H8z"/></svg>`,
  reminders: `
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v4"/><path d="M12 17v4"/><path d="M4.93 4.93l2.83 2.83"/><path d="M16.24 16.24l2.83 2.83"/><path d="M3 12h4"/><path d="M17 12h4"/><path d="M4.93 19.07l2.83-2.83"/><path d="M16.24 7.76l2.83-2.83"/><circle cx="12" cy="12" r="3.5"/></svg>`,
}

const dotClass =
  whatsapp.status === 'ready' ? 'ok' : whatsapp.status === 'qr' || whatsapp.status === 'connected' ? 'qr' : 'bad'

const route = useRoute()
const refreshGlobalWhatsapp = () => {
  if (route.path !== '/company') refreshWhatsapp()
}

let timer
onMounted(() => {
  refreshGlobalWhatsapp()
  timer = setInterval(refreshGlobalWhatsapp, 3000)
})
onUnmounted(() => clearInterval(timer))
</script>

<template>
  <div class="layout">
    <aside class="sidebar">
      <div class="brand">
        <div class="brand-badge">W</div>
        <span>WhatsApp CRM</span>
      </div>

      <nav class="nav">
        <RouterLink to="/" exact-active-class="active" v-html="icons.dashboard + `<span>Dashboard</span>`"></RouterLink>
        <RouterLink to="/company" exact-active-class="active" v-html="icons.company + '<span>Companies</span>'"></RouterLink>
        <RouterLink to="/connect" exact-active-class="active" v-html="icons.connect + '<span>Connect WhatsApp</span>'"></RouterLink>
        <RouterLink to="/bulk" exact-active-class="active" v-html="icons.bulk + '<span>Bulk Message</span>'"></RouterLink>
        <RouterLink to="/history" exact-active-class="active" v-html="icons.history + '<span>Campaign History</span>'"></RouterLink>
        <RouterLink to="/analytics" exact-active-class="active" v-html="icons.analytics + '<span>Analytics</span>'"></RouterLink>
        <RouterLink to="/leads" exact-active-class="active" v-html="icons.leads + '<span>Leads</span>'"></RouterLink>
        <RouterLink to="/appointments" exact-active-class="active" v-html="icons.appointments + '<span>Appointments</span>'"></RouterLink>
        <RouterLink to="/reminders" exact-active-class="active" v-html="icons.reminders + '<span>Reminders</span>'"></RouterLink>
        <RouterLink to="/contacts" exact-active-class="active" v-html="icons.contacts + '<span>Contacts</span>'"></RouterLink>
        <RouterLink to="/accounts" exact-active-class="active" v-html="icons.accounts + '<span>Accounts</span>'"></RouterLink>
        <RouterLink to="/admin/users" exact-active-class="active" v-html="icons.users + '<span>Team</span>'"></RouterLink>
      </nav>

      <div class="spacer"></div>
      <RouterLink to="/connect" style="text-decoration:none;color:inherit">
        <div class="pill">
          <span class="dot" :class="dotClass"></span>
          <span>{{ whatsapp.connected ? `Connected · ${whatsapp.phone || ''}` : statusLabels[whatsapp.status] || 'Offline' }}</span>
        </div>
      </RouterLink>
    </aside>

    <main class="main">
      <RouterView />
    </main>
  </div>
</template>