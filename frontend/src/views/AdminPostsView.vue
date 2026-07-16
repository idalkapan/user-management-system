<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import api from '../services/api'

const router = useRouter()

const posts = ref([])
const isLoading = ref(true)
const errorMessage = ref('')
const successMessage = ref('')

const selectedPostId = ref(null)
const rejectionReason = ref('')
const isRejectModalOpen = ref(false)

const loadPendingPosts = async () => {
  isLoading.value = true
  errorMessage.value = ''

  try {
    const response = await api.get('/admin/posts/pending')

    const allPosts =
      response.data.data ??
      response.data.posts ??
      response.data ??
      []

    console.log('Tüm yazılar:', allPosts)

    posts.value = allPosts.filter(
      (post) => post.status === 'pending',
    )
  } catch (error) {
    console.error('Bekleyen yazılar alınamadı:', error)

    errorMessage.value =
      error.response?.data?.message ??
      'Bekleyen yazılar yüklenirken bir hata oluştu.'
  } finally {
    isLoading.value = false
  }
}

const formatDate = (date) => {
  if (!date) {
    return 'Tarih belirtilmemiş'
  }

  return new Intl.DateTimeFormat('tr-TR', {
    day: '2-digit',
    month: 'long',
    year: 'numeric',
  }).format(new Date(date))
}

const getAuthorName = (post) => {
  return post.user?.name ?? post.author?.name ?? 'Bilinmeyen kullanıcı'
}

const openRejectModal = (postId) => {
  selectedPostId.value = postId
  rejectionReason.value = ''
  isRejectModalOpen.value = true
}

const rejectPost = async () => {
  errorMessage.value = ''
  successMessage.value = ''

  if (!rejectionReason.value.trim()) {
    errorMessage.value = 'Red sebebi boş bırakılamaz.'
    return
  }

  try {
    await api.patch(
      `/admin/posts/${selectedPostId.value}/reject`,
      {
        rejection_reason: rejectionReason.value,
      },
    )

    posts.value = posts.value.filter(
      (post) => post.id !== selectedPostId.value,
    )

    isRejectModalOpen.value = false
    selectedPostId.value = null
    rejectionReason.value = ''

    successMessage.value = 'Yazı başarıyla reddedildi.'
  } catch (error) {
    console.error('Yazı reddedilemedi:', error)

    errorMessage.value =
      error.response?.data?.message ??
      'Yazı reddedilirken bir hata oluştu.'
  }
}


const approvePost = async (postId) => {
  errorMessage.value = ''
  successMessage.value = ''

  try {
    await api.patch(`/admin/posts/${postId}/approve`)

    posts.value = posts.value.filter(
      (post) => post.id !== postId,
    )
    successMessage.value = 'Yazı başarıyla onaylandı.'
  
  } catch (error) {
    console.error('Yazı onaylanamadı:', error)

    errorMessage.value =
      error.response?.data?.message ??
      'Yazı onaylanırken bir hata oluştu.'
  }
}

const goToProfile = () => {
  router.push('/profile')
}

onMounted(() => {
  loadPendingPosts()
})
</script>


<template>
  <div class="admin-posts-page">
    <div class="admin-posts-container">
      <header class="page-header">
        <div>
          <button
            type="button"
            class="back-button"
            @click="goToProfile"
          >
            ← Profile Dön
          </button>

          <h1>Admin Paneli</h1>
          <p>Onay bekleyen blog yazılarını inceleyin ve yönetin.</p>
        </div>

        <button
          type="button"
          class="refresh-button"
          :disabled="isLoading"
          @click="loadPendingPosts"
        >
          {{ isLoading ? 'Yenileniyor...' : 'Yenile' }}
        </button>
      </header>

      <div
         v-if="successMessage"
         class="alert alert-success"
      >
        {{ successMessage }}
      </div>

      <div
        v-if="errorMessage"
        class="alert alert-error"
      >
        {{ errorMessage }}
      </div>



      <div
        v-if="isLoading"
        class="loading-state"
      >
        Bekleyen yazılar yükleniyor...
      </div>

      <div
        v-else-if="posts.length === 0"
        class="empty-state"
      >
        <h2>Onay bekleyen yazı bulunamadı</h2>
        <p>Yeni bir yazı gönderildiğinde burada görüntülenecek.</p>
      </div>

      <div
        v-else
        class="posts-list"
      >
        <article
          v-for="post in posts"
          :key="post.id"
          class="post-card"
        >
          <div class="post-info">
            <span class="status-badge">Onay Bekliyor</span>

            <h2>{{ post.title }}</h2>

            <p class="post-content">
              {{ post.content }}
            </p>

            <div class="post-meta">
              <span>Yazar: {{ getAuthorName(post) }}</span>
              <span>{{ formatDate(post.created_at) }}</span>
            </div>
          </div>

          <div class="post-actions">
            <button
              type="button"
              class="view-button"
              @click="router.push(`/posts/${post.id}?from=admin`)"
            >
              Görüntüle
            </button>

            <button
              type="button"
              class="approve-button"
              @click="approvePost(post.id)"
            >
              Onayla
            </button>

            <button
               type="button"
               class="reject-button"
               @click="openRejectModal(post.id)"
            >
              Reddet
            </button>
          </div>
        </article>
      </div>
    </div>
  </div>
  <div
  v-if="isRejectModalOpen"
  class="modal-overlay"
>
  <div class="modal">
    <h2>Yazıyı Reddet</h2>

    <p>Lütfen red sebebini yazın.</p>

    <textarea
      v-model="rejectionReason"
      rows="5"
      placeholder="Red sebebini yazınız..."
    ></textarea>

    <div class="modal-actions">
      <button
        type="button"
        class="cancel-button"
        @click="isRejectModalOpen = false"
      >
        İptal
      </button>

      <button
         type="button"
         class="reject-button"
         @click="rejectPost"
      >
        Reddet
      </button>
    </div>
  </div>
</div>
</template>

<style scoped>
.admin-posts-page {
  min-height: 100vh;
  padding: 2rem 1.5rem;
  background-color: #f0f4f8;
}

.admin-posts-container {
  max-width: 1050px;
  margin: 0 auto;
}

.page-header {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.page-header h1 {
  margin: 1rem 0 0.4rem;
  color: #1a1a2e;
}

.page-header p {
  margin: 0;
  color: #718096;
}

.back-button {
  padding: 0;
  color: #4f6ef7;
  background: transparent;
  border: none;
  font-weight: 600;
  cursor: pointer;
}

.refresh-button {
  padding: 0.7rem 1rem;
  color: #4f6ef7;
  background-color: #ffffff;
  border: 1px solid #c7d2fe;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
}

.refresh-button:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.alert {
  padding: 1rem;
  margin-bottom: 1rem;
  border-radius: 8px;
}

.alert-error {
  color: #991b1b;
  background-color: #fef2f2;
  border: 1px solid #fecaca;
}

.alert-success {
  color: #166534;
  background-color: #f0fdf4;
  border: 1px solid #bbf7d0;
}

.loading-state,
.empty-state {
  padding: 3rem;
  text-align: center;
  background-color: #ffffff;
  border-radius: 12px;
}

.posts-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.post-card {
  display: flex;
  justify-content: space-between;
  gap: 1.5rem;
  padding: 1.5rem;
  background-color: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
}

.post-info {
  min-width: 0;
  flex: 1;
}

.status-badge {
  display: inline-block;
  padding: 0.3rem 0.65rem;
  color: #92400e;
  background-color: #fef3c7;
  border-radius: 999px;
  font-size: 0.75rem;
  font-weight: 700;
}

.post-info h2 {
  margin: 0.8rem 0 0.5rem;
  color: #1a1a2e;
}

.post-content {
  display: -webkit-box;
  overflow: hidden;
  color: #718096;
  line-height: 1.6;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 3;
}

.post-meta {
  display: flex;
  gap: 1rem;
  margin-top: 1rem;
  color: #718096;
  font-size: 0.85rem;
}

.post-actions {
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 0.6rem;
  width: 110px;
}

.post-actions button {
  padding: 0.6rem;
  background-color: #ffffff;
  border-radius: 7px;
  font-weight: 600;
  cursor: pointer;
}

.view-button {
  color: #4a5568;
  border: 1px solid #cbd5e0;
}

.approve-button {
  color: #166534;
  border: 1px solid #86efac;
}

.reject-button {
  color: #991b1b;
  border: 1px solid #fecaca;
}
.modal-overlay {
  position: fixed;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
  background-color: rgba(15, 23, 42, 0.55);
  z-index: 1000;
}

.modal {
  width: 100%;
  max-width: 480px;
  padding: 1.5rem;
  background-color: #ffffff;
  border-radius: 12px;
  box-shadow: 0 20px 50px rgba(0, 0, 0, 0.2);
}

.modal h2 {
  margin: 0 0 0.5rem;
  color: #1a1a2e;
}

.modal p {
  margin: 0 0 1rem;
  color: #718096;
}

.modal textarea {
  width: 100%;
  padding: 0.85rem;
  border: 1px solid #cbd5e0;
  border-radius: 8px;
  font: inherit;
  resize: vertical;
  box-sizing: border-box;
}

.modal textarea:focus {
  outline: none;
  border-color: #4f6ef7;
}

.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
  margin-top: 1rem;
}

.modal-actions button {
  padding: 0.7rem 1.1rem;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
}

.cancel-button {
  color: #4a5568;
  background-color: #ffffff;
  border: 1px solid #cbd5e0;
}

.modal-actions .reject-button {
  color: #ffffff;
  background-color: #dc2626;
  border: 1px solid #dc2626;
}

@media (max-width: 700px) {
  .page-header,
  .post-card {
    flex-direction: column;
    align-items: stretch;
  }

  .post-actions {
    flex-direction: row;
    width: 100%;
  }

  .post-actions button {
    flex: 1;
  }
}
</style>