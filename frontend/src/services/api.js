import axios from 'axios'

const api = axios.create({
  baseURL: 'http://localhost:8000/api',
  headers: {
    Accept: 'application/json',
  },
})

const isCredentialRequest = (url = '') =>
  url.includes('/login') ||
  url.includes('/register') ||
  url.includes('/forgot-password') ||
  url.includes('/reset-password')

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('token')

  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }

  return config
})

api.interceptors.response.use(
  (response) => response,
  async (error) => {
    const status = error.response?.status
    const url = error.config?.url ?? ''

    if (status === 401 && !isCredentialRequest(url)) {
      const { useAuthStore } = await import('../stores/auth')
      const authStore = useAuthStore()
      const hadToken = Boolean(authStore.token)

      authStore.clearLocalSession()

      if (hadToken && !url.includes('/logout')) {
        sessionStorage.setItem('sessionExpired', '1')
      }

      const { default: router } = await import('../router')

      if (router.currentRoute.value.path !== '/login') {
        await router.push('/login').catch(() => {})
      }
    }

    return Promise.reject(error)
  },
)

export default api