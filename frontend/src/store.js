import { reactive } from 'vue'
import api from './api'

export const whatsapp = reactive({
  connected: false,
  status: 'unlinked',
  qr: null,
  phone: null,
  name: null,
  error: null,
  loading: true,
})

export async function refreshWhatsapp(companyId = null) {
  try {
    const { data } = await api.get('/whatsapp/status', {
      params: companyId ? { company_id: companyId } : {},
    })
    whatsapp.connected = Boolean(data.connected)
    whatsapp.status = data.status || 'unlinked'
    whatsapp.qr = data.qr || null
    whatsapp.phone = data.phone || null
    whatsapp.name = data.name || null
    whatsapp.error = data.error || null
    whatsapp.loading = false
  } catch (e) {
    whatsapp.status = 'service_down'
    whatsapp.error = e.message
    whatsapp.loading = false
  }
}

export const statusLabels = {
  ready: 'Connected',
  qr: 'Scan QR code',
  connecting: 'Connecting…',
  authenticated: 'Authenticating…',
  unlinked: 'Not connected',
  disconnected: 'Disconnected',
  auth_failure: 'Authentication failed',
  error: 'Error',
  service_down: 'Service offline',
}