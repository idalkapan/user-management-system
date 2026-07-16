<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../services/api'

const route = useRoute()
const router = useRouter()

const post = ref(null)
const isLoading = ref(true)
const errorMessage = ref('')

const loadPost = async () => {
  isLoading.value = true
  errorMessage.value = ''

  try {
    const response = await api.get(`/posts/${route.params.id}`)

    post.value =
      response.data.data ??
      response.data.post ??
      response.data
    console.log('Detay yazısı:', post.value)
    console.log('Resim yolu:', post.value.featured_image)

  } catch (error) {
    console.error('Yazı alınamadı:', error)

    errorMessage.value =
      error.response?.data?.message ||
      'Blog yazısı görüntülenirken bir hata oluştu.'
  } finally {
    isLoading.value = false
  }
}

const authorName = computed(() => {
  return (
    post.value?.author?.name ||
    post.value?.user?.name ||
    'Bilinmeyen yazar'
  )
})

const authorInitial = computed(() => {
  return authorName.value.charAt(0).toUpperCase()
})

const featuredImageUrl = computed(() => {
    const image = post.value?.featured_image

    if (!image) {
        return ''
    }

    return image
})

const statusLabel = computed(() => {
  if (post.value?.status === 'published') {
    return 'Yayında'
  }

  if (post.value?.status === 'draft') {
    return 'Taslak'
  }

  if (post.value?.status === 'pending') {
    return 'Onay Bekliyor'
  }

  if (post.value?.status === 'rejected') {
    return 'Reddedildi'
  }

  return 'Durum belirtilmemiş'
})

const formatDate = (date) => {
  if (!date) {
    return 'Tarih belirtilmemiş'
  }

  return new Intl.DateTimeFormat('tr-TR', {
    day: '2-digit',
    month: 'long',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(date))
}

const goBack = () => {
  if (route.query.from === 'admin') {
    router.push('/admin/posts')
    return
  }

  if (route.query.from === 'blog') {
    router.push('/posts')
    return
  }

  router.push('/my-posts')
}

const goToEdit = () => {
  router.push(`/posts/${post.value.id}/edit`)
}

onMounted(() => {
  loadPost()
})
</script>

<template>
  <div class="post-detail-page">
    <div class="post-detail-container">
      <header class="page-header">
        <button
          type="button"
          class="back-button"
          @click="goBack"
        >
        {{
        route.query.from === 'admin'
        ? '← Admin Paneline Dön'
        : route.query.from === 'blog'
           ? '← Blog Yazılarına Dön'
           : '← Yazılarıma Dön'
           }}

        </button>

      </header>

      <div
        v-if="isLoading"
        class="loading-state"
      >
        <div class="loading-spinner"></div>
        <p>Blog yazısı yükleniyor...</p>
      </div>

      <div
        v-else-if="errorMessage"
        class="error-state"
      >
        <div class="error-icon">!</div>

        <h2>Yazı görüntülenemedi</h2>

        <p>{{ errorMessage }}</p>

        <button
          type="button"
          class="back-to-list-button"
          @click="goBack"
        >
          Blog Yönetimine Dön
        </button>
      </div>

      <article
        v-else-if="post"
        class="post-detail-card"
      >
        <div class="post-header">
          <div class="post-header-top">
            <span
              class="status-badge"
              :class="{
                published: post.status === 'published',
                draft: post.status === 'draft',
              }"
            >
              {{ statusLabel }}
            </span>

            <button
               v-if="route.query.from === 'my-posts'"
               type="button"
               class="edit-button"
               @click="goToEdit"
            >
               Düzenle
            </button>
          </div>

          <h1 class="post-title">
            {{ post.title }}
          </h1>

          <div class="post-information">
            <div class="author-information">
              <span class="author-avatar">
                {{ authorInitial }}
              </span>

              <div>
                <span class="information-label">Yazar</span>
                <strong>{{ authorName }}</strong>
              </div>
            </div>

            <div class="date-information">
              <span class="information-label">
                Oluşturulma tarihi
              </span>

              <strong>
                {{ formatDate(post.created_at) }}
              </strong>
            </div>
          </div>
        </div>

        <div
          v-if="featuredImageUrl"
          class="featured-image-wrapper"
        >
          <img
            :src="featuredImageUrl"
            :alt="post.title"
            class="featured-image"
          />
        </div>

        <div class="post-content-section">
          <div class="content-heading">
            <h2>Yazı İçeriği</h2>
          </div>

          <div class="post-content">
            {{ post.content }}
          </div>
        </div>

        
      </article>
    </div>
  </div>
</template>

<style scoped>
.post-detail-page {
  min-height: 100vh;
  padding: 2rem 1.5rem 3rem;
  background-color: #f0f4f8;
  box-sizing: border-box;
}

.post-detail-container {
  width: 100%;
  max-width: 960px;
  margin: 0 auto;
}

.page-header {
  margin-bottom: 1.25rem;
}

.back-button {
  padding: 0;
  color: #4f6ef7;
  background-color: transparent;
  border: none;
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
}

.back-button:hover {
  color: #3b5de7;
  text-decoration: underline;
}

.loading-state,
.error-state {
  display: flex;
  min-height: 360px;
  padding: 2rem;
  background-color: #ffffff;
  border-radius: 12px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
  box-sizing: border-box;
}

.loading-state {
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 1rem;
  color: #718096;
}

.loading-spinner {
  width: 40px;
  height: 40px;
  border: 3px solid #e2e8f0;
  border-top-color: #4f6ef7;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.error-state {
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
}

.error-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 54px;
  height: 54px;
  margin-bottom: 1rem;
  color: #dc2626;
  background-color: #fee2e2;
  border-radius: 50%;
  font-size: 1.4rem;
  font-weight: 700;
}

.error-state h2 {
  margin: 0;
  color: #1a1a2e;
  font-size: 1.25rem;
}

.error-state p {
  max-width: 500px;
  margin: 0.65rem 0 1.25rem;
  color: #718096;
  font-size: 0.9rem;
  line-height: 1.6;
}

.back-to-list-button {
  padding: 0.75rem 1.2rem;
  color: #ffffff;
  background-color: #4f6ef7;
  border: none;
  border-radius: 8px;
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
}

.post-detail-card {
  overflow: hidden;
  background-color: #ffffff;
  border-radius: 14px;
  box-shadow: 0 3px 18px rgba(0, 0, 0, 0.07);
}

.post-header {
  padding: 2rem;
}

.post-header-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1.25rem;
}

.status-badge {
  display: inline-flex;
  align-items: center;
  padding: 0.35rem 0.75rem;
  color: #4a5568;
  background-color: #edf2f7;
  border-radius: 999px;
  font-size: 0.75rem;
  font-weight: 700;
}

.status-badge.published {
  color: #166534;
  background-color: #dcfce7;
}

.status-badge.draft {
  color: #92400e;
  background-color: #fef3c7;
}

.edit-button {
  padding: 0.65rem 1.1rem;
  color: #4f6ef7;
  background-color: #ffffff;
  border: 1.5px solid #c7d2fe;
  border-radius: 8px;
  font-size: 0.825rem;
  font-weight: 600;
  cursor: pointer;
}

.edit-button:hover {
  background-color: #eef2ff;
}

.post-title {
  margin: 0;
  color: #1a1a2e;
  font-size: 2rem;
  font-weight: 700;
  line-height: 1.3;
  letter-spacing: -0.03em;
  word-break: break-word;
}

.post-information {
  display: flex;
  align-items: center;
  gap: 2.5rem;
  padding-top: 1.5rem;
  margin-top: 1.5rem;
  border-top: 1px solid #e2e8f0;
}

.author-information {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.author-avatar {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 42px;
  height: 42px;
  flex-shrink: 0;
  color: #ffffff;
  background-color: #4f6ef7;
  border-radius: 50%;
  font-size: 0.9rem;
  font-weight: 700;
}

.author-information div,
.date-information {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
}

.information-label {
  color: #a0aec0;
  font-size: 0.72rem;
}

.author-information strong,
.date-information strong {
  color: #4a5568;
  font-size: 0.825rem;
  font-weight: 600;
}

.featured-image-wrapper {
  overflow: hidden;
  border-radius: 16px;
  margin-top: 24px;
}

.featured-image {
  width: 100%;
  height: 420px;
  object-fit: cover;
  display: block;
}

.post-content-section {
  padding: 2rem;
}

.content-heading {
  padding-bottom: 1rem;
  margin-bottom: 1.5rem;
  border-bottom: 1px solid #e2e8f0;
}

.content-heading h2 {
  margin: 0;
  color: #1a1a2e;
  font-size: 1.1rem;
  font-weight: 700;
}

.post-content {
  color: #4a5568;
  font-size: 1rem;
  line-height: 1.9;
  white-space: pre-wrap;
  overflow-wrap: break-word;
}

.post-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 1.25rem 2rem;
  background-color: #f8fafc;
  border-top: 1px solid #e2e8f0;
}

.footer-back-button,
.footer-edit-button {
  padding: 0.75rem 1.2rem;
  border-radius: 8px;
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
}

.footer-back-button {
  color: #4a5568;
  background-color: #ffffff;
  border: 1.5px solid #e2e8f0;
}

.footer-back-button:hover {
  background-color: #f1f5f9;
  border-color: #cbd5e0;
}

.footer-edit-button {
  color: #ffffff;
  background-color: #4f6ef7;
  border: none;
}

.footer-edit-button:hover {
  background-color: #3b5de7;
}

@media (max-width: 700px) {
  .post-detail-page {
    padding: 1.25rem 1rem 2rem;
  }

  .post-header,
  .post-content-section {
    padding: 1.35rem;
  }

  .post-title {
    font-size: 1.55rem;
  }

  .post-information {
    align-items: flex-start;
    flex-direction: column;
    gap: 1rem;
  }

  .featured-image-wrapper,
  .featured-image {
    max-height: 340px;
  }

  .post-footer {
    align-items: stretch;
    flex-direction: column-reverse;
    padding: 1.25rem;
  }

  .footer-back-button,
  .footer-edit-button {
    width: 100%;
  }
}
</style>