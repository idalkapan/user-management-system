<script setup>
import { computed, nextTick, ref, watch } from 'vue'
import { useAuthStore } from '../stores/auth'
import {
  createCommentReply,
  deleteComment,
  getCommentReplies,
  likeComment,
  unlikeComment,
  updateComment,
} from '../services/commentService'
import { reportComment } from '../services/commentReportService'

const REPORT_REASONS = [
  { value: 'spam', label: 'Spam / Reklam' },
  { value: 'harassment', label: 'Taciz veya Zorbalık' },
  { value: 'hate_speech', label: 'Nefret Söylemi' },
  { value: 'inappropriate', label: 'Uygunsuz İçerik' },
  { value: 'misinformation', label: 'Yanıltıcı Bilgi' },
  { value: 'other', label: 'Diğer' },
]

const MAX_REPORT_DESCRIPTION_LENGTH = 500

const normalizeComment = (comment) => ({
  ...comment,
  is_reported_by_current_user: Boolean(comment?.is_reported_by_current_user),
})

const props = defineProps({
  comment: {
    type: Object,
    required: true,
  },
  isReply: {
    type: Boolean,
    default: false,
  },
  isBusy: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits([
  'updated',
  'deleted',
  'busy-change',
  'update:comments-count',
  'reply-to',
])

const authStore = useAuthStore()

const localComment = ref(normalizeComment(props.comment))
const isEditing = ref(false)
const editContent = ref('')
const actionError = ref('')
const likeError = ref('')
const isSubmitting = ref(false)
const isLiking = ref(false)
const isDeleteModalOpen = ref(false)
const isReportModalOpen = ref(false)
const reportReason = ref('')
const reportDescription = ref('')
const reportError = ref('')
const reportSuccessMessage = ref('')
const isSubmittingReport = ref(false)

const activeReplyTarget = ref(null)
const replyContent = ref('')
const replyError = ref('')
const isSubmittingReply = ref(false)
const replyTextareaRef = ref(null)

const repliesExpanded = ref(false)
const repliesLoaded = ref(false)
const repliesLoading = ref(false)
const repliesLoadError = ref('')
const replies = ref([])
const repliesPagination = ref({
  currentPage: 1,
  lastPage: 1,
  perPage: 20,
  total: 0,
})

watch(
  () => props.comment,
  (nextComment) => {
    localComment.value = normalizeComment(nextComment)
  },
  { deep: true },
)

const authorInitial = computed(() =>
  (localComment.value.author?.name ?? '?').charAt(0).toUpperCase(),
)

const canReply = computed(
  () =>
    authStore.isAuthenticated &&
    !authStore.isAdmin &&
    authStore.user?.role === 'user',
)

const canLike = computed(
  () =>
    authStore.isAuthenticated &&
    !authStore.isAdmin &&
    authStore.user?.role === 'user',
)

const canUpdate = computed(
  () =>
    authStore.isAuthenticated &&
    !authStore.isAdmin &&
    authStore.user?.id === localComment.value.author?.id,
)

const canDelete = computed(() => {
  if (!authStore.isAuthenticated) {
    return false
  }

  if (authStore.isAdmin) {
    return true
  }

  return authStore.user?.id === localComment.value.author?.id
})

const isReportedByCurrentUser = computed(
  () => Boolean(localComment.value.is_reported_by_current_user),
)

const canReport = computed(
  () =>
    authStore.isAuthenticated &&
    !authStore.isAdmin &&
    authStore.user?.role === 'user' &&
    authStore.user?.id !== localComment.value.author?.id,
)

const showReportButton = computed(
  () => canReport.value && !isReportedByCurrentUser.value,
)

const showReportedBadge = computed(
  () => canReport.value && isReportedByCurrentUser.value,
)

const reportDescriptionLength = computed(
  () => reportDescription.value.length,
)

const likeAriaLabel = computed(() =>
  localComment.value.is_liked_by_current_user
    ? 'Yorum beğenisini kaldır'
    : 'Yorumu beğen',
)

const repliesCount = computed(
  () => localComment.value.replies_count ?? 0,
)

const hasMoreReplies = computed(
  () => repliesPagination.value.currentPage < repliesPagination.value.lastPage,
)

const repliedToDisplayName = computed(() => {
  if (!props.isReply) {
    return ''
  }

  return localComment.value.replied_to_user?.name ?? 'Silinmiş kullanıcı'
})

const deleteModalMessage = computed(() => {
  if (props.isReply || repliesCount.value <= 0) {
    return 'Bu yorumu silmek istediğinize emin misiniz?'
  }

  return `Bu yorumu silmek istediğinize emin misiniz? Bu yorumla birlikte ${repliesCount.value} yanıt da silinecek.`
})

const formatDate = (date) => {
  if (!date) {
    return '—'
  }

  return new Intl.DateTimeFormat('tr-TR', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(date))
}

const setBusy = (value) => {
  emit('busy-change', value)
}

const getReportErrorMessage = (error) => {
  const data = error.response?.data

  if (data?.message) {
    return data.message
  }

  if (data?.errors?.reason?.[0]) {
    return data.errors.reason[0]
  }

  if (data?.errors?.description?.[0]) {
    return data.errors.description[0]
  }

  if (!error.response) {
    return 'Bağlantı hatası oluştu. Lütfen tekrar deneyin.'
  }

  return 'Şikâyet gönderilirken bir hata oluştu.'
}

const resetReportForm = () => {
  reportReason.value = ''
  reportDescription.value = ''
  reportError.value = ''
}

const openReportModal = () => {
  if (!showReportButton.value || isSubmittingReport.value) {
    return
  }

  reportSuccessMessage.value = ''
  reportError.value = ''
  isReportModalOpen.value = true
}

const closeReportModal = () => {
  if (isSubmittingReport.value) {
    return
  }

  isReportModalOpen.value = false
  resetReportForm()
}

const markCommentAsReported = () => {
  localComment.value = {
    ...localComment.value,
    is_reported_by_current_user: true,
  }
}

const submitReport = async () => {
  if (!showReportButton.value || isSubmittingReport.value) {
    return
  }

  if (!reportReason.value) {
    reportError.value = 'Şikâyet nedeni seçilmelidir.'
    return
  }

  if (reportDescription.value.length > MAX_REPORT_DESCRIPTION_LENGTH) {
    reportError.value = 'Açıklama en fazla 500 karakter olabilir.'
    return
  }

  reportError.value = ''
  reportSuccessMessage.value = ''
  isSubmittingReport.value = true
  setBusy(true)

  try {
    const payload = {
      reason: reportReason.value,
    }

    const trimmedDescription = reportDescription.value.trim()

    if (trimmedDescription) {
      payload.description = trimmedDescription
    }

    const response = await reportComment(localComment.value.id, payload)

    markCommentAsReported()
    isReportModalOpen.value = false
    resetReportForm()
    reportSuccessMessage.value =
      response.data?.message || 'Şikâyetiniz başarıyla alındı.'
  } catch (error) {
    const status = error.response?.status
    const message = getReportErrorMessage(error)

    reportError.value = message

    if (status === 422 && message.includes('zaten şikâyet')) {
      markCommentAsReported()
      isReportModalOpen.value = false
      resetReportForm()
      reportSuccessMessage.value = message
    }
  } finally {
    isSubmittingReport.value = false
    setBusy(false)
  }
}

const startEdit = () => {
  actionError.value = ''
  editContent.value = localComment.value.content ?? ''
  isEditing.value = true
}

const cancelEdit = () => {
  actionError.value = ''
  isEditing.value = false
  editContent.value = ''
}

const saveEdit = async () => {
  const trimmedContent = editContent.value.trim()

  if (trimmedContent.length < 2) {
    actionError.value = 'Yorum en az 2 karakter olmalıdır.'
    return
  }

  actionError.value = ''
  isSubmitting.value = true
  setBusy(true)

  try {
    const response = await updateComment(localComment.value.id, trimmedContent)
    localComment.value = { ...localComment.value, ...response.data.comment }
    emit('updated', response.data.comment)
    isEditing.value = false
  } catch (error) {
    actionError.value =
      error.response?.data?.message ||
      'Yorum güncellenirken bir hata oluştu.'
  } finally {
    isSubmitting.value = false
    setBusy(false)
  }
}

const openDeleteModal = () => {
  actionError.value = ''
  isDeleteModalOpen.value = true
}

const closeDeleteModal = () => {
  if (isSubmitting.value) {
    return
  }

  isDeleteModalOpen.value = false
}

const confirmDelete = async () => {
  actionError.value = ''
  isSubmitting.value = true
  setBusy(true)

  try {
    const response = await deleteComment(localComment.value.id)
    emit('deleted', {
      commentId: localComment.value.id,
      commentsCount: response.data.comments_count,
      isReply: props.isReply,
    })
    isDeleteModalOpen.value = false
  } catch (error) {
    actionError.value =
      error.response?.data?.message ||
      'Yorum silinirken bir hata oluştu.'
  } finally {
    isSubmitting.value = false
    setBusy(false)
  }
}

const toggleLike = async () => {
  if (!canLike.value || isLiking.value) {
    return
  }

  likeError.value = ''

  const wasLiked = Boolean(localComment.value.is_liked_by_current_user)
  const previousLikesCount = localComment.value.likes_count ?? 0

  isLiking.value = true

  localComment.value = {
    ...localComment.value,
    is_liked_by_current_user: !wasLiked,
    likes_count: Math.max(0, previousLikesCount + (wasLiked ? -1 : 1)),
  }

  try {
    const response = wasLiked
      ? await unlikeComment(localComment.value.id)
      : await likeComment(localComment.value.id)

    localComment.value = {
      ...localComment.value,
      likes_count: response.data.likes_count ?? localComment.value.likes_count,
      is_liked_by_current_user:
        response.data.is_liked_by_current_user ??
        localComment.value.is_liked_by_current_user,
    }
  } catch (error) {
    localComment.value = {
      ...localComment.value,
      is_liked_by_current_user: wasLiked,
      likes_count: previousLikesCount,
    }

    likeError.value =
      error.response?.data?.message ||
      'Beğeni işlemi sırasında bir hata oluştu.'
  } finally {
    isLiking.value = false
  }
}

const buildReplyTarget = (comment) => ({
  id: comment.id,
  name: comment.author?.name ?? 'Kullanıcı',
})

const openReplyForm = async (targetComment) => {
  if (!canReply.value) {
    return
  }

  if (props.isReply) {
    emit('reply-to', buildReplyTarget(targetComment))
    return
  }

  activeReplyTarget.value = buildReplyTarget(targetComment)
  replyContent.value = ''
  replyError.value = ''

  await nextTick()
  replyTextareaRef.value?.focus()
}

const handleReplyTo = async (target) => {
  activeReplyTarget.value = target
  replyContent.value = ''
  replyError.value = ''

  if (!repliesExpanded.value) {
    repliesExpanded.value = true
  }

  await nextTick()
  replyTextareaRef.value?.focus()
}

const cancelReply = () => {
  activeReplyTarget.value = null
  replyContent.value = ''
  replyError.value = ''
}

const submitReply = async () => {
  if (!activeReplyTarget.value) {
    return
  }

  const trimmedContent = replyContent.value.trim()

  if (trimmedContent.length < 2) {
    replyError.value = 'Yanıt en az 2 karakter olmalıdır.'
    return
  }

  replyError.value = ''
  isSubmittingReply.value = true
  setBusy(true)

  try {
    const response = await createCommentReply(
      activeReplyTarget.value.id,
      trimmedContent,
    )
    const newReply = response.data.reply

    localComment.value = {
      ...localComment.value,
      replies_count: response.data.replies_count ?? repliesCount.value + 1,
    }

    emit('update:comments-count', response.data.comments_count)

    if (!repliesExpanded.value) {
      repliesExpanded.value = true
    }

    if (newReply) {
      if (!repliesLoaded.value) {
        replies.value = [newReply]
        repliesLoaded.value = true
        repliesPagination.value = {
          currentPage: 1,
          lastPage: 1,
          perPage: 20,
          total: response.data.replies_count ?? 1,
        }
      } else {
        replies.value = [...replies.value, newReply]
        repliesPagination.value = {
          ...repliesPagination.value,
          total: response.data.replies_count ?? replies.value.length,
        }
      }
    }

    activeReplyTarget.value = null
    replyContent.value = ''
  } catch (error) {
    replyError.value =
      error.response?.data?.message ||
      'Yanıt eklenirken bir hata oluştu.'
  } finally {
    isSubmittingReply.value = false
    setBusy(false)
  }
}

const loadReplies = async (page = 1, append = false) => {
  repliesLoading.value = true
  repliesLoadError.value = ''

  try {
    const response = await getCommentReplies(localComment.value.id, page)
    const fetchedReplies = response.data.replies ?? []

    replies.value = append
      ? [...replies.value, ...fetchedReplies]
      : fetchedReplies

    repliesPagination.value = {
      currentPage: response.data.meta?.current_page ?? page,
      lastPage: response.data.meta?.last_page ?? 1,
      perPage: response.data.meta?.per_page ?? 20,
      total: response.data.meta?.total ?? fetchedReplies.length,
    }
    repliesLoaded.value = true
  } catch (error) {
    repliesLoadError.value =
      error.response?.data?.message ||
      'Yanıtlar yüklenirken bir hata oluştu.'
  } finally {
    repliesLoading.value = false
  }
}

const toggleReplies = async () => {
  if (repliesExpanded.value) {
    repliesExpanded.value = false
    return
  }

  repliesExpanded.value = true

  if (!repliesLoaded.value) {
    await loadReplies(1)
  }
}

const loadMoreReplies = async () => {
  if (repliesLoading.value || !hasMoreReplies.value) {
    return
  }

  await loadReplies(repliesPagination.value.currentPage + 1, true)
}

const handleReplyUpdated = (updatedReply) => {
  const index = replies.value.findIndex(
    (reply) => reply.id === updatedReply.id,
  )

  if (index !== -1) {
    replies.value[index] = updatedReply
  }
}

const handleReplyDeleted = ({ commentId, commentsCount }) => {
  replies.value = replies.value.filter((reply) => reply.id !== commentId)

  localComment.value = {
    ...localComment.value,
    replies_count: Math.max(0, (localComment.value.replies_count ?? 1) - 1),
  }

  repliesPagination.value = {
    ...repliesPagination.value,
    total: Math.max(0, (repliesPagination.value.total ?? 1) - 1),
  }

  emit('update:comments-count', commentsCount)
}
</script>

<template>
  <article
    class="comment-item"
    :class="{ 'comment-item--reply': isReply }"
  >
    <div class="comment-header">
      <div class="comment-author">
        <img
          v-if="localComment.author?.profile_photo"
          :src="localComment.author.profile_photo"
          :alt="localComment.author.name"
          class="comment-avatar comment-avatar-image"
        />

        <span
          v-else
          class="comment-avatar"
        >
          {{ authorInitial }}
        </span>

        <div>
          <strong class="comment-author-name">
            {{ localComment.author?.name ?? 'Bilinmiyor' }}
          </strong>

          <div class="comment-meta">
            <span>{{ formatDate(localComment.created_at) }}</span>
            <span
              v-if="localComment.is_edited"
              class="comment-edited"
            >
              · düzenlendi
            </span>
          </div>
        </div>
      </div>

      <div
        v-if="!isEditing && (canUpdate || canDelete)"
        class="comment-actions"
      >
        <button
          v-if="canUpdate"
          type="button"
          class="comment-action-button"
          :disabled="isBusy || isSubmitting || isSubmittingReply"
          @click="startEdit"
        >
          Düzenle
        </button>

        <button
          v-if="canDelete"
          type="button"
          class="comment-action-button comment-action-delete"
          :disabled="isBusy || isSubmitting || isSubmittingReply"
          @click="openDeleteModal"
        >
          Sil
        </button>
      </div>
    </div>

    <div
      v-if="isEditing"
      class="comment-edit-form"
    >
      <textarea
        v-model="editContent"
        class="comment-textarea"
        rows="3"
        maxlength="1000"
        :disabled="isSubmitting"
      />

      <div class="comment-edit-actions">
        <button
          type="button"
          class="comment-secondary-button"
          :disabled="isSubmitting"
          @click="cancelEdit"
        >
          İptal
        </button>

        <button
          type="button"
          class="comment-primary-button"
          :disabled="isSubmitting"
          @click="saveEdit"
        >
          {{ isSubmitting ? 'Kaydediliyor...' : 'Kaydet' }}
        </button>
      </div>
    </div>

    <template v-else>
      <p
        v-if="isReply"
        class="comment-content comment-content-reply"
      >
        <span class="comment-mention">@{{ repliedToDisplayName }}</span>
        <span>{{ localComment.content }}</span>
      </p>

      <p
        v-else
        class="comment-content"
      >
        {{ localComment.content }}
      </p>
    </template>

    <div class="comment-footer">
      <button
        v-if="canLike"
        type="button"
        class="comment-like-button"
        :class="{ 'is-liked': localComment.is_liked_by_current_user }"
        :disabled="isLiking || isBusy || isSubmitting || isSubmittingReply"
        :aria-label="likeAriaLabel"
        :aria-pressed="Boolean(localComment.is_liked_by_current_user)"
        @click="toggleLike"
      >
        <svg
          v-if="localComment.is_liked_by_current_user"
          class="comment-like-icon"
          xmlns="http://www.w3.org/2000/svg"
          viewBox="0 0 24 24"
          aria-hidden="true"
        >
          <path
            d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"
            fill="currentColor"
          />
        </svg>

        <svg
          v-else
          class="comment-like-icon"
          xmlns="http://www.w3.org/2000/svg"
          viewBox="0 0 24 24"
          aria-hidden="true"
        >
          <path
            d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
          />
        </svg>

        <span class="comment-like-count">
          {{ localComment.likes_count ?? 0 }}
        </span>
      </button>

      <span
        v-else-if="(localComment.likes_count ?? 0) > 0"
        class="comment-like-readonly"
      >
        <svg
          class="comment-like-icon"
          xmlns="http://www.w3.org/2000/svg"
          viewBox="0 0 24 24"
          aria-hidden="true"
        >
          <path
            d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
          />
        </svg>
        <span>{{ localComment.likes_count ?? 0 }}</span>
      </span>

      <button
        v-if="canReply"
        type="button"
        class="comment-reply-button"
        :disabled="isBusy || isSubmitting || isSubmittingReply || isSubmittingReport"
        @click="openReplyForm(localComment)"
      >
        Yanıtla
      </button>

      <button
        v-if="showReportButton"
        type="button"
        class="comment-report-button"
        :disabled="isBusy || isSubmitting || isSubmittingReply || isSubmittingReport"
        @click="openReportModal"
      >
        Şikâyet Et
      </button>

      <span
        v-if="showReportedBadge"
        class="comment-reported-badge"
      >
        Şikâyet Edildi
      </span>
    </div>

    <p
      v-if="reportSuccessMessage"
      class="comment-success"
    >
      {{ reportSuccessMessage }}
    </p>

    <p
      v-if="likeError"
      class="comment-error"
    >
      {{ likeError }}
    </p>

    <p
      v-if="actionError"
      class="comment-error"
    >
      {{ actionError }}
    </p>

    <div
      v-if="!isReply && activeReplyTarget"
      class="reply-form"
    >
      <p class="reply-form-label">
        {{ activeReplyTarget.name }} adlı kullanıcıya yanıt veriyorsun
      </p>

      <textarea
        ref="replyTextareaRef"
        v-model="replyContent"
        class="comment-textarea"
        rows="3"
        maxlength="1000"
        placeholder="Yanıtınızı yazın..."
        :disabled="isSubmittingReply"
      />

      <div class="comment-edit-actions">
        <p
          v-if="replyError"
          class="comment-error reply-form-error"
        >
          {{ replyError }}
        </p>

        <button
          type="button"
          class="comment-secondary-button"
          :disabled="isSubmittingReply"
          @click="cancelReply"
        >
          İptal
        </button>

        <button
          type="button"
          class="comment-primary-button"
          :disabled="isSubmittingReply"
          @click="submitReply"
        >
          {{ isSubmittingReply ? 'Gönderiliyor...' : 'Gönder' }}
        </button>
      </div>
    </div>

    <div
      v-if="!isReply && repliesCount > 0"
      class="replies-controls"
    >
      <button
        type="button"
        class="replies-toggle-button"
        :disabled="repliesLoading"
        @click="toggleReplies"
      >
        {{
          repliesExpanded
            ? 'Yanıtları gizle'
            : `${repliesCount} yanıtı göster`
        }}
      </button>
    </div>

    <div
      v-if="!isReply && repliesExpanded"
      class="replies-list"
    >
      <div
        v-if="repliesLoading && !repliesLoaded"
        class="replies-state"
      >
        Yanıtlar yükleniyor...
      </div>

      <div
        v-else-if="repliesLoadError"
        class="replies-state replies-state-error"
      >
        <p>{{ repliesLoadError }}</p>

        <button
          type="button"
          class="comment-secondary-button"
          @click="loadReplies(repliesPagination.currentPage)"
        >
          Tekrar Dene
        </button>
      </div>

      <template v-else>
        <CommentItem
          v-for="reply in replies"
          :key="reply.id"
          :comment="reply"
          :is-reply="true"
          :is-busy="isBusy"
          @updated="handleReplyUpdated"
          @deleted="handleReplyDeleted"
          @busy-change="setBusy($event)"
          @reply-to="handleReplyTo"
          @update:comments-count="emit('update:comments-count', $event)"
        />

        <button
          v-if="hasMoreReplies"
          type="button"
          class="replies-load-more-button"
          :disabled="repliesLoading"
          @click="loadMoreReplies"
        >
          {{ repliesLoading ? 'Yükleniyor...' : 'Daha fazla yanıt göster' }}
        </button>
      </template>
    </div>

    <div
      v-if="isReportModalOpen"
      class="comment-modal-overlay"
      @click.self="closeReportModal"
    >
      <div class="comment-modal comment-report-modal">
        <h3>Yorumu Şikâyet Et</h3>

        <p class="comment-report-modal-description">
          Bu yorumu neden şikâyet ettiğinizi belirtin.
        </p>

        <fieldset class="comment-report-reasons">
          <legend class="visually-hidden">Şikâyet nedeni</legend>

          <label
            v-for="reason in REPORT_REASONS"
            :key="reason.value"
            class="comment-report-reason-option"
          >
            <input
              v-model="reportReason"
              type="radio"
              name="report-reason"
              :value="reason.value"
              :disabled="isSubmittingReport"
            >
            <span>{{ reason.label }}</span>
          </label>
        </fieldset>

        <label
          class="comment-report-description-label"
          for="report-description"
        >
          Açıklama (isteğe bağlı)
        </label>

        <textarea
          id="report-description"
          v-model="reportDescription"
          class="comment-textarea"
          rows="4"
          maxlength="500"
          placeholder="Ek açıklama yazabilirsiniz..."
          :disabled="isSubmittingReport"
        />

        <div class="comment-report-description-meta">
          <span>{{ reportDescriptionLength }}/500</span>
        </div>

        <p
          v-if="reportError"
          class="comment-error comment-report-error"
        >
          {{ reportError }}
        </p>

        <div class="comment-modal-actions">
          <button
            type="button"
            class="comment-secondary-button"
            :disabled="isSubmittingReport"
            @click="closeReportModal"
          >
            İptal
          </button>

          <button
            type="button"
            class="comment-primary-button"
            :disabled="isSubmittingReport || !reportReason"
            @click="submitReport"
          >
            {{ isSubmittingReport ? 'Gönderiliyor...' : 'Şikâyeti Gönder' }}
          </button>
        </div>
      </div>
    </div>

    <div
      v-if="isDeleteModalOpen"
      class="comment-modal-overlay"
      @click.self="closeDeleteModal"
    >
      <div class="comment-modal">
        <div class="comment-modal-icon">!</div>

        <h3>{{ isReply ? 'Yanıtı Sil' : 'Yorumu Sil' }}</h3>

        <p>{{ deleteModalMessage }}</p>

        <p class="comment-modal-warning">
          Bu işlem geri alınamaz.
        </p>

        <div class="comment-modal-actions">
          <button
            type="button"
            class="comment-secondary-button"
            :disabled="isSubmitting"
            @click="closeDeleteModal"
          >
            İptal
          </button>

          <button
            type="button"
            class="comment-danger-button"
            :disabled="isSubmitting"
            @click="confirmDelete"
          >
            {{ isSubmitting ? 'Siliniyor...' : (isReply ? 'Yanıtı Sil' : 'Yorumu Sil') }}
          </button>
        </div>
      </div>
    </div>
  </article>
</template>

<style scoped>
.comment-item {
  padding: 1rem 0;
  border-bottom: 1px solid #e2e8f0;
}

.comment-item:last-child {
  border-bottom: none;
}

.comment-item--reply {
  padding: 0.85rem 0 0.85rem 1rem;
  border-bottom: 1px solid #edf2f7;
}

.comment-item--reply:last-child {
  border-bottom: none;
}

.comment-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 0.65rem;
}

.comment-author {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  min-width: 0;
}

.comment-avatar {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 38px;
  height: 38px;
  flex-shrink: 0;
  color: #ffffff;
  background-color: #4f6ef7;
  border-radius: 50%;
  font-size: 0.85rem;
  font-weight: 700;
}

.comment-item--reply .comment-avatar {
  width: 32px;
  height: 32px;
  font-size: 0.75rem;
}

.comment-avatar-image {
  object-fit: cover;
}

.comment-author-name {
  color: #1a1a2e;
  font-size: 0.875rem;
  font-weight: 600;
}

.comment-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 0.25rem;
  margin-top: 0.15rem;
  color: #94a3b8;
  font-size: 0.75rem;
}

.comment-edited {
  color: #64748b;
}

.comment-actions {
  display: flex;
  flex-shrink: 0;
  gap: 0.45rem;
}

.comment-action-button {
  padding: 0.35rem 0.65rem;
  color: #475569;
  background-color: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
}

.comment-action-button:hover:not(:disabled) {
  background-color: #f8fafc;
}

.comment-action-delete {
  color: #991b1b;
  border-color: #fecaca;
}

.comment-action-delete:hover:not(:disabled) {
  background-color: #fef2f2;
}

.comment-action-button:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.comment-content {
  margin: 0;
  color: #4a5568;
  font-size: 0.9375rem;
  line-height: 1.65;
  white-space: pre-wrap;
  overflow-wrap: break-word;
}

.comment-content-reply {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
}

.comment-mention {
  color: #4f6ef7;
  font-weight: 600;
}

.comment-footer {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-top: 0.65rem;
}

.comment-like-button,
.comment-like-readonly {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  color: #94a3b8;
  font-size: 0.8125rem;
  font-weight: 600;
}

.comment-like-button {
  padding: 0.25rem 0.45rem;
  background-color: transparent;
  border: 1px solid transparent;
  border-radius: 999px;
  cursor: pointer;
  transition:
    background-color 0.15s ease,
    border-color 0.15s ease,
    color 0.15s ease;
}

.comment-like-button:hover:not(:disabled) {
  color: #64748b;
  background-color: #f8fafc;
  border-color: #e2e8f0;
}

.comment-like-button.is-liked {
  color: #e11d48;
  background-color: #fff1f2;
  border-color: #fecdd3;
}

.comment-like-button:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.comment-like-icon {
  width: 14px;
  height: 14px;
  flex-shrink: 0;
}

.comment-like-count {
  font-variant-numeric: tabular-nums;
}

.comment-reply-button,
.comment-report-button,
.replies-toggle-button,
.replies-load-more-button {
  padding: 0.25rem 0.45rem;
  color: #64748b;
  background-color: transparent;
  border: none;
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
}

.comment-reply-button:hover:not(:disabled),
.comment-report-button:hover:not(:disabled),
.replies-toggle-button:hover:not(:disabled),
.replies-load-more-button:hover:not(:disabled) {
  color: #4f6ef7;
}

.comment-report-button:hover:not(:disabled) {
  color: #475569;
}

.comment-reply-button:disabled,
.comment-report-button:disabled,
.replies-toggle-button:disabled,
.replies-load-more-button:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.comment-reported-badge {
  display: inline-flex;
  align-items: center;
  padding: 0.2rem 0.55rem;
  color: #64748b;
  background-color: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 999px;
  font-size: 0.75rem;
  font-weight: 600;
}

.comment-success {
  margin: 0.65rem 0 0;
  color: #166534;
  font-size: 0.8125rem;
}

.comment-report-modal h3 {
  margin: 0 0 0.5rem;
  color: #1a1a2e;
  font-size: 1.05rem;
}

.comment-report-modal-description {
  margin: 0 0 1rem;
  color: #64748b;
  font-size: 0.875rem;
  line-height: 1.55;
}

.comment-report-reasons {
  margin: 0 0 1rem;
  padding: 0;
  border: none;
}

.comment-report-reason-option {
  display: flex;
  align-items: center;
  gap: 0.55rem;
  margin-bottom: 0.55rem;
  color: #475569;
  font-size: 0.875rem;
  cursor: pointer;
}

.comment-report-reason-option:last-child {
  margin-bottom: 0;
}

.comment-report-reason-option input {
  margin: 0;
}

.comment-report-description-label {
  display: block;
  margin-bottom: 0.45rem;
  color: #475569;
  font-size: 0.8125rem;
  font-weight: 600;
}

.comment-report-description-meta {
  display: flex;
  justify-content: flex-end;
  margin-top: 0.35rem;
  color: #94a3b8;
  font-size: 0.75rem;
}

.comment-report-error {
  margin-top: 0.85rem;
}

.visually-hidden {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}

.reply-form {
  display: flex;
  flex-direction: column;
  gap: 0.65rem;
  margin-top: 0.85rem;
  padding-left: 0.25rem;
}

.reply-form-label {
  margin: 0;
  color: #475569;
  font-size: 0.8125rem;
  font-weight: 600;
}

.reply-form-error {
  margin: 0;
  flex: 1;
}

.replies-controls {
  margin-top: 0.45rem;
}

.replies-list {
  margin-top: 0.35rem;
  padding-left: 0.75rem;
  border-left: 2px solid #e2e8f0;
}

.replies-state {
  padding: 0.75rem 0;
  color: #718096;
  font-size: 0.875rem;
}

.replies-state-error p {
  margin: 0 0 0.65rem;
  color: #991b1b;
}

.replies-load-more-button {
  margin-top: 0.35rem;
  padding-left: 1rem;
}

.comment-edit-form {
  display: flex;
  flex-direction: column;
  gap: 0.65rem;
}

.comment-textarea {
  width: 100%;
  padding: 0.75rem 0.85rem;
  color: #1a1a2e;
  background-color: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-size: 0.9375rem;
  line-height: 1.5;
  resize: vertical;
  box-sizing: border-box;
}

.comment-textarea:focus {
  outline: none;
  border-color: #4f6ef7;
}

.comment-edit-actions {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 0.5rem;
}

.comment-primary-button,
.comment-secondary-button,
.comment-danger-button {
  padding: 0.55rem 0.9rem;
  border-radius: 7px;
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
}

.comment-primary-button {
  color: #ffffff;
  background-color: #4f6ef7;
  border: none;
}

.comment-secondary-button {
  color: #475569;
  background-color: #ffffff;
  border: 1px solid #e2e8f0;
}

.comment-danger-button {
  color: #ffffff;
  background-color: #dc2626;
  border: none;
}

.comment-primary-button:disabled,
.comment-secondary-button:disabled,
.comment-danger-button:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.comment-error {
  margin: 0.65rem 0 0;
  color: #991b1b;
  font-size: 0.8125rem;
}

.comment-modal-overlay {
  position: fixed;
  inset: 0;
  z-index: 50;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
  background-color: rgba(15, 23, 42, 0.45);
}

.comment-modal {
  width: 100%;
  max-width: 420px;
  padding: 1.5rem;
  background-color: #ffffff;
  border-radius: 12px;
  box-shadow: 0 12px 32px rgba(15, 23, 42, 0.18);
}

.comment-modal-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 42px;
  height: 42px;
  margin-bottom: 0.85rem;
  color: #dc2626;
  background-color: #fee2e2;
  border-radius: 50%;
  font-size: 1.1rem;
  font-weight: 700;
}

.comment-modal h3 {
  margin: 0 0 0.5rem;
  color: #1a1a2e;
  font-size: 1.05rem;
}

.comment-modal p {
  margin: 0;
  color: #64748b;
  font-size: 0.875rem;
  line-height: 1.55;
}

.comment-modal-warning {
  margin-top: 0.5rem !important;
  color: #991b1b !important;
}

.comment-modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
  margin-top: 1.25rem;
}
</style>
