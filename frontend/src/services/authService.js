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

export default {
  login,
  logout,
  getProfile,
}