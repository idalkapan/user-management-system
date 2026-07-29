import api from './api'

export const getPosts = () => {
  return api.get('/posts')
}

export const getPost = (id) => {
  return api.get(`/posts/${id}`)
}

export const recordView = (postId) => {
  return api.post(`/posts/${postId}/views`)
}

export const likePost = (postId) => {
  return api.post(`/posts/${postId}/like`)
}

export const unlikePost = (postId) => {
  return api.delete(`/posts/${postId}/like`)
}

export const getMyPosts = () => {
  return api.get('/my-posts')
}
export const createPost = (postData) => {
  return api.post('/posts', postData)
}

export const updatePost = (id, postData) => {
  return api.put(`/posts/${id}`, postData)
}

export const deletePost = (id) => {
  return api.delete(`/posts/${id}`)
}

export const getAdminDashboard = () => {
  return api.get('/admin/dashboard')
}

export const getMyStatistics = () => {
  return api.get('/my-statistics')
}

export const getAdminStatistics = () => {
  return api.get('/admin/statistics')
}
