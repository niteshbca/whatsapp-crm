import axios from 'axios'

const rawBase = import.meta.env.VITE_API_URL || 'http://127.0.0.1:8000/api'
const baseURL = /\/api\/?$/.test(rawBase) ? rawBase : rawBase.replace(/\/+$/, '') + '/api'

const api = axios.create({
  baseURL,
  timeout: 60000,
  withCredentials: true,
})

api.interceptors.response.use(
  (response) => response,
  (error) => {
    const message =
      error.response?.data?.error ||
      error.response?.data?.message ||
      error.message ||
      'Something went wrong'
    return Promise.reject(new Error(message))
  },
)

export default api