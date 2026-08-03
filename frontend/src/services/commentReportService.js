import api from './api'

export const reportComment = (commentId, payload) => {
  return api.post(`/comments/${commentId}/report`, payload)
}

export const getAdminCommentReports = (params = {}) => {
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

  if (params.reason) {
    queryParams.reason = params.reason
  }

  if (params.search) {
    queryParams.search = params.search
  }

  if (params.sort) {
    queryParams.sort = params.sort
  }

  return api.get('/admin/comment-reports', {
    params: queryParams,
  })
}

export const getAdminCommentReport = (reportId) => {
  return api.get(`/admin/comment-reports/${reportId}`)
}

export const resolveCommentReport = (reportId, payload) => {
  return api.patch(`/admin/comment-reports/${reportId}/resolve`, payload)
}
