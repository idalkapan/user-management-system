import api from './api'

export const getCategories = () => {
  return api.get('/categories')
}

export const getAdminCategories = () => {
  return api.get('/admin/categories')
}

export const createCategory = (data) => {
  return api.post('/admin/categories', data)
}

export const updateCategory = (id, data) => {
  return api.put(`/admin/categories/${id}`, data)
}

export const updateCategoryStatus = (id, isActive) => {
  return api.patch(`/admin/categories/${id}/status`, {
    is_active: isActive,
  })
}

export const deleteCategory = (id) => {
  return api.delete(`/admin/categories/${id}`)
}
