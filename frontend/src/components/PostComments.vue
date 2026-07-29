<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useAuthStore } from '../stores/auth'
import {
  createPostComment,
  getPostComments,
} from '../services/commentService'
import CommentItem from './CommentItem.vue'

const props = defineProps({
  postId: {
    type: [Number, String],
    required: true,
  },
  postStatus: {
    type: String,
    default: 'published',
  },
  initialCommentsCount: {
    type: Number,
    default: 0,
  },
})

const emit = defineEmits(['update:comments-count', 'ready'])

const authStore = useAuthStore()

const comments = ref([])
const commentTextareaRef = ref(null)
const hasEmittedReady = ref(false)
const pagination = ref({
  currentPage: 1,
  lastPage: 1,
  perPage: 20,
  total: 0,
})
const isLoading = ref(true)
const loadError = ref('')
const formContent = ref('')
const formError = ref('')
const isSubmitting = ref(false)
const isCommentBusy = ref(false)

const canComment = computed(
  () =>
    authStore.isAuthenticated &&
    !authStore.isAdmin &&
    props.postStatus === 'published',
)

const commentsCountLabel = computed(() => {
  if (!isLoading.value) {
    return pagination.value.total
  }

  return props.initialCommentsCount || 0
})

const hasComments = computed(() => comments.value.length > 0)

const loadComments = async (page = 1, append = false) => {
  if (!props.postId) {
    return
  }

  if (props.postStatus !== 'published') {
    comments.value = []
    isLoading.value = false
    return
  }

  isLoading.value = true
  loadError.value = ''

  try {
    const response = await getPostComments(props.postId, page)
    const fetchedComments = response.data.comments ?? []

    comments.value = append
      ? [...comments.value, ...fetchedComments]
      : fetchedComments

    pagination.value = {
      currentPage: response.data.meta?.current_page ?? page,
      lastPage: response.data.meta?.last_page ?? 1,
      perPage: response.data.meta?.per_page ?? 20,
      total: response.data.meta?.total ?? fetchedComments.length,
    }
  } catch (error) {
    loadError.value =
      error.response?.data?.message ||
      'Yorumlar yüklenirken bir hata oluştu.'
  } finally {
    isLoading.value = false

    if (!hasEmittedReady.value) {
      hasEmittedReady.value = true
      emit('ready')
    }
  }
}

const focusCommentInput = () => {
  commentTextareaRef.value?.focus()
}

defineExpose({
  focusCommentInput,
})

const submitComment = async () => {
  const trimmedContent = formContent.value.trim()

  if (trimmedContent.length < 2) {
    formError.value = 'Yorum en az 2 karakter olmalıdır.'
    return
  }

  formError.value = ''
  isSubmitting.value = true

  try {
    const response = await createPostComment(props.postId, trimmedContent)
    const newComment = response.data.comment

    if (newComment) {
      comments.value = [...comments.value, newComment]
    }

    pagination.value = {
      ...pagination.value,
      total: response.data.comments_count ?? pagination.value.total + 1,
    }

    emit('update:comments-count', response.data.comments_count)
    formContent.value = ''
  } catch (error) {
    formError.value =
      error.response?.data?.message ||
      'Yorum eklenirken bir hata oluştu.'
  } finally {
    isSubmitting.value = false
  }
}

const handleCommentUpdated = (updatedComment) => {
  const index = comments.value.findIndex(
    (comment) => comment.id === updatedComment.id,
  )

  if (index !== -1) {
    comments.value[index] = updatedComment
  }
}

const handleCommentsCountUpdate = (count) => {
  if (typeof count !== 'number') {
    return
  }

  emit('update:comments-count', count)
}

const handleCommentDeleted = ({ commentId, commentsCount }) => {
  comments.value = comments.value.filter(
    (comment) => comment.id !== commentId,
  )

  if (typeof commentsCount === 'number') {
    pagination.value = {
      ...pagination.value,
      total: commentsCount,
    }
    emit('update:comments-count', commentsCount)
  }
}

onMounted(async () => {
  if (authStore.isAuthenticated && !authStore.user) {
    try {
      await authStore.fetchUser()
    } catch {
      // Kullanıcı bilgisi guard akışına bırakılır.
    }
  }

  await loadComments(1)
})

watch(
  () => props.postId,
  async () => {
    await loadComments(1)
  },
)
</script>

<template>
  <section
    id="post-comments-section"
    class="post-comments"
  >
    <div class="post-comments-header">
      <h2>Yorumlar</h2>

      <span class="post-comments-count">
        {{ commentsCountLabel }}
      </span>
    </div>

    <form
      v-if="canComment"
      class="comment-create-form"
      @submit.prevent="submitComment"
    >
      <label
        class="comment-create-label"
        for="comment-content"
      >
        Yorum yaz
      </label>

      <textarea
        id="comment-content"
        ref="commentTextareaRef"
        v-model="formContent"
        class="comment-create-textarea"
        rows="4"
        maxlength="1000"
        placeholder="Düşüncelerinizi paylaşın..."
        :disabled="isSubmitting || isCommentBusy"
      />

      <div class="comment-create-footer">
        <p
          v-if="formError"
          class="comment-form-error"
        >
          {{ formError }}
        </p>

        <button
          type="submit"
          class="comment-submit-button"
          :disabled="isSubmitting || isCommentBusy"
        >
          {{ isSubmitting ? 'Gönderiliyor...' : 'Yorum Yap' }}
        </button>
      </div>
    </form>

    <div
      v-if="isLoading"
      class="comments-state"
    >
      Yorumlar yükleniyor...
    </div>

    <div
      v-else-if="loadError"
      class="comments-state comments-state-error"
    >
      <p>{{ loadError }}</p>

      <button
        type="button"
        class="comment-retry-button"
        @click="loadComments(pagination.currentPage)"
      >
        Tekrar Dene
      </button>
    </div>

    <p
      v-else-if="!hasComments"
      class="comments-empty"
    >
      Henüz yorum yapılmamış.
    </p>

    <div
      v-else
      class="comments-list"
    >
      <CommentItem
        v-for="comment in comments"
        :key="comment.id"
        :comment="comment"
        :is-busy="isCommentBusy"
        @updated="handleCommentUpdated"
        @deleted="handleCommentDeleted"
        @busy-change="isCommentBusy = $event"
        @update:comments-count="handleCommentsCountUpdate"
      />
    </div>
  </section>
</template>

<style scoped>
.post-comments {
  padding: 1.75rem 2rem 2rem;
  border-top: 1px solid #e2e8f0;
}

.post-comments-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1.25rem;
}

.post-comments-header h2 {
  margin: 0;
  color: #1a1a2e;
  font-size: 1.15rem;
  font-weight: 700;
}

.post-comments-count {
  padding: 0.3rem 0.65rem;
  color: #64748b;
  background-color: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 999px;
  font-size: 0.8125rem;
  font-weight: 600;
}

.comment-create-form {
  display: flex;
  flex-direction: column;
  gap: 0.65rem;
  margin-bottom: 1.5rem;
}

.comment-create-label {
  color: #475569;
  font-size: 0.8125rem;
  font-weight: 600;
}

.comment-create-textarea {
  width: 100%;
  padding: 0.85rem 1rem;
  color: #1a1a2e;
  background-color: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  font-size: 0.9375rem;
  line-height: 1.55;
  resize: vertical;
  box-sizing: border-box;
}

.comment-create-textarea:focus {
  outline: none;
  border-color: #4f6ef7;
}

.comment-create-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
}

.comment-form-error {
  margin: 0;
  color: #991b1b;
  font-size: 0.8125rem;
}

.comment-submit-button {
  margin-left: auto;
  padding: 0.65rem 1.1rem;
  color: #ffffff;
  background-color: #4f6ef7;
  border: none;
  border-radius: 8px;
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
}

.comment-submit-button:hover:not(:disabled) {
  background-color: #3b5de7;
}

.comment-submit-button:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.comments-state {
  padding: 1.5rem 0;
  color: #718096;
  font-size: 0.9375rem;
  text-align: center;
}

.comments-state-error p {
  margin: 0 0 0.85rem;
  color: #991b1b;
}

.comment-retry-button {
  padding: 0.55rem 0.9rem;
  color: #ffffff;
  background-color: #4f6ef7;
  border: none;
  border-radius: 8px;
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
}

.comments-empty {
  margin: 0;
  padding: 0.5rem 0 0.25rem;
  color: #718096;
  font-size: 0.9375rem;
}

@media (max-width: 700px) {
  .post-comments {
    padding: 1.35rem;
  }

  .comment-create-footer {
    align-items: stretch;
    flex-direction: column;
  }

  .comment-submit-button {
    width: 100%;
    margin-left: 0;
  }
}
</style>
