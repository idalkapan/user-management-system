import api from './api'

export const reportComment = (commentId, payload) => {
  return api.post(`/comments/${commentId}/report`, payload)
}
