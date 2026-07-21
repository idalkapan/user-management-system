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