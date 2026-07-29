import api from './api'

export const getPostComments = (postId, page = 1) => {
  return api.get(`/posts/${postId}/comments`, {
    params: { page },
  })
}

export const createPostComment = (postId, content) => {
  return api.post(`/posts/${postId}/comments`, { content })
}

export const getCommentReplies = (commentId, page = 1) => {
  return api.get(`/comments/${commentId}/replies`, {
    params: { page },
  })
}

export const createCommentReply = (commentId, content) => {
  return api.post(`/comments/${commentId}/replies`, { content })
}

export const updateComment = (commentId, content) => {
  return api.put(`/comments/${commentId}`, { content })
}

export const deleteComment = (commentId) => {
  return api.delete(`/comments/${commentId}`)
}

export const likeComment = (commentId) => {
  return api.post(`/comments/${commentId}/like`)
}

export const unlikeComment = (commentId) => {
  return api.delete(`/comments/${commentId}/like`)
}
