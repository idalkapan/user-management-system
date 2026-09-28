import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import authService from '../services/authService'

export const useAuthStore = defineStore('auth', () => {
  const token = ref(localStorage.getItem('token'))
  const user = ref(null)
  const sessionValidated = ref(false)

  const isAuthenticated = computed(() => Boolean(token.value))
  const isAdmin = computed(() => user.value?.role === 'admin')

  const clearLocalSession = () => {
    token.value = null
    user.value = null
    sessionValidated.value = false
    localStorage.removeItem('token')
  }

  const login = async (credentials) => {
    const response = await authService.login(credentials)
  
    token.value = response.data.data.token
    localStorage.setItem('token', token.value)
    await fetchUser()
    sessionValidated.value = true
  
    return response
  }
  const fetchUser = async () => {
    const response = await authService.getProfile()
  
    user.value = response.data.data
  
    return response
  }

  const logout = async () => {
    try {
      await authService.logout()
    } finally {
      clearLocalSession()
    }
  }

  const getHomeRoute = () => {
    if (isAdmin.value) {
      return '/admin/dashboard'
    }

    return '/posts'
  }

  return {
    token,
    user,
    isAuthenticated,
    isAdmin,
    sessionValidated,
    login,
    fetchUser,
    logout,
    clearLocalSession,
    getHomeRoute,
  }
})