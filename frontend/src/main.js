import { createApp } from 'vue'
import { createRouter, createWebHistory } from 'vue-router'
import App from './App.vue'
import './styles.css'

import DashboardView from './views/DashboardView.vue'
import ConnectView from './views/ConnectView.vue'
import BulkMessageView from './views/BulkMessageView.vue'
import HistoryView from './views/HistoryView.vue'
import ContactsView from './views/ContactsView.vue'
import CompanyDashboardView from './views/CompanyDashboardView.vue'
import AdminUsersView from './views/AdminUsersView.vue'
import WhatsAppAccountsView from './views/WhatsAppAccountsView.vue'
import AnalyticsView from './views/AnalyticsView.vue'
import LeadsView from './views/LeadsView.vue'
import AppointmentsView from './views/AppointmentsView.vue'
import RemindersView from './views/RemindersView.vue'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', name: 'dashboard', component: DashboardView },
    { path: '/company', name: 'company-dashboard', component: CompanyDashboardView },
    { path: '/connect', name: 'connect', component: ConnectView },
    { path: '/bulk', name: 'bulk', component: BulkMessageView },
    { path: '/history', name: 'history', component: HistoryView },
    { path: '/analytics', name: 'analytics', component: AnalyticsView },
    { path: '/leads', name: 'leads', component: LeadsView },
    { path: '/appointments', name: 'appointments', component: AppointmentsView },
    { path: '/reminders', name: 'reminders', component: RemindersView },
    { path: '/contacts', name: 'contacts', component: ContactsView },
    { path: '/admin/users', name: 'admin-users', component: AdminUsersView },
    { path: '/accounts', name: 'whatsapp-accounts', component: WhatsAppAccountsView },
  ],
})

createApp(App).use(router).mount('#app')