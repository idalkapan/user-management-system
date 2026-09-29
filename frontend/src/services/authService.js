import api from './api'

const login = (credentials) => {
  return api.post('/login', credentials)
}

const logout = () => {
  return api.post('/logout')
}

const getProfile = () => {
  return api.get('/profile')
}

const forgotPassword = (payload) => {
  return api.post('/forgot-password', payload)
}

const resetPassword = (payload) => {
  return api.post('/reset-password', payload)
}

export default {
  login,
  logout,
  getProfile,
  forgotPassword,
  resetPassword,
}