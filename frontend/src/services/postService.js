import api from './api'

export const getPosts = (params = {}) => {
  const queryParams = {}

  if (params.page) {
    queryParams.page = params.page
  }

  if (params.per_page) {
    queryParams.per_page = params.per_page
  }

  if (params.search) {
    queryParams.search = params.search
  }

  if (params.category) {
    queryParams.category = params.category
  }

  return api.get('/posts', {
    params: queryParams,
  })
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

export const getMyPosts = (params = {}) => {
  const queryParams = {}

  if (params.page) {
    queryParams.page = params.page
  }

  if (params.per_page) {
    queryParams.per_page = params.per_page
  }

  if (params.status) {
    queryParams.status = params.status
  }

  return api.get('/my-posts', {
    params: queryParams,
  })
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

export const getAdminPendingPosts = (params = {}) => {
  const queryParams = {}

  if (params.page) {
    queryParams.page = params.page
  }

  if (params.per_page) {
    queryParams.per_page = params.per_page
  }

  return api.get('/admin/posts/pending', {
    params: queryParams,
  })
}

export const getMyStatistics = (period = '30d') => {
  return api.get('/my-statistics', {
    params: { period },
  })
}

export const getAdminStatistics = (period = '30d') => {
  return api.get('/admin/statistics', {
    params: { period },
  })
}
