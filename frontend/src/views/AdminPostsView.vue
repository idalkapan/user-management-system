<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import api from '../services/api'

const router = useRouter()

const posts = ref([])
const isLoading = ref(true)
const errorMessage = ref('')

const loadPendingPosts = async () => {
  isLoading.value = true
  errorMessage.value = ''

  try {
    const response = await api.get('/posts')

    const allPosts =
      response.data.data ??
      response.data.posts ??
      response.data ??
      []

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
              @click="router.push(`/posts/${post.id}`)"
            >
              Görüntüle
            </button>

            <button
              type="button"
              class="approve-button"
            >
              Onayla
            </button>

            <button
              type="button"
              class="reject-button"
            >
              Reddet
            </button>
          </div>
        </article>
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