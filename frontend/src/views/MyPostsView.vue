<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { getMyPosts } from '../services/postService'
import api from '../services/api'

const router = useRouter()

const posts = ref([])
const activeFilter = ref('all')
const isLoading = ref(true)
const errorMessage = ref('')

const postToDelete = ref(null)
const isDeleteModalOpen = ref(false)
const isDeleting = ref(false)

const currentUserRole = ref('')

const isAdmin = computed(() => {
  return currentUserRole.value === 'admin'
})

const loadCurrentUser = async () => {
  try {
    const response = await api.get('/profile')
    const user = response.data.data ?? response.data

    currentUserRole.value = user.role ?? ''
  } catch (error) {
    console.error('Kullanıcı rolü alınamadı:', error)
    currentUserRole.value = ''
  }
}

const loadPosts = async () => {
  isLoading.value = true
  errorMessage.value = ''

  try {
    const response = await getMyPosts()

    posts.value =
      response.data.posts ??
      response.data.data ??
      response.data ??
      []
  } catch (error) {
    console.error('Yazılar alınamadı:', error)

    errorMessage.value =
      error.response?.data?.message ||
      'Blog yazıları yüklenirken bir hata oluştu.'
  } finally {
    isLoading.value = false
  }
}

const filteredPosts = computed(() => {
  if (activeFilter.value === 'all') {
    return posts.value
  }

  return posts.value.filter(
    (post) => post.status === activeFilter.value,
  )
})

const publishedPostCount = computed(() => {
  return posts.value.filter(
    (post) => post.status === 'published',
  ).length
})

const pendingPostCount = computed(() => {
  return posts.value.filter(
    (post) => post.status === 'pending',
  ).length
})

const draftPostCount = computed(() => {
  return posts.value.filter(
    (post) => post.status === 'draft',
  ).length
})

const setFilter = (filter) => {
  activeFilter.value = filter
}

const goToProfile = () => {
  router.push('/profile')
}

const goToCreatePost = () => {
  router.push('/posts/create')
}

const goToEditPost = (postId) => {
  router.push(`/posts/${postId}/edit`)
}
const openDeleteModal = (post) => {
  postToDelete.value = post
  isDeleteModalOpen.value = true
}
const closeDeleteModal = () => {
  if (isDeleting.value) {
    return
  }

  isDeleteModalOpen.value = false
  postToDelete.value = null
}

const confirmDeletePost = async () => {
  if (!postToDelete.value) {
    return
  }

  isDeleting.value = true
  errorMessage.value = ''

  try {
    await api.delete(`/posts/${postToDelete.value.id}`)

    posts.value = posts.value.filter(
      (post) => post.id !== postToDelete.value.id,
    )

    isDeleteModalOpen.value = false
    postToDelete.value = null
  } catch (error) {
    console.error('Yazı silinemedi:', error)

    errorMessage.value =
      error.response?.data?.message ||
      'Yazı silinirken bir hata oluştu.'
  } finally {
    isDeleting.value = false
  }
}

const getStatusLabel = (status) => {
  if (status === 'published') {
    return 'Yayında'
  }
  if (status === 'draft') {
    return 'Taslak'
  }

  if (status === 'pending') {
    return 'Onay Bekliyor'
  }

  if (status === 'rejected') {
    return 'Reddedildi'
  }

  return 'Durum belirtilmemiş'
}

const getAuthorName = (post) => {
  return post.author?.name || post.user?.name || 'Bilinmeyen yazar'
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

onMounted(async () => {
  await loadCurrentUser()
  await loadPosts()
})
</script>

<template>
  <div class="posts-page">
    <div class="posts-container">
      <header class="page-header">
        <div class="header-content">
          <button
            type="button"
            class="back-button"
            @click="goToProfile"
          >
            ← Profile Dön
          </button>

          <div class="header-text">
            <h1> Yazılarım </h1>

            <p>
              Blog yazılarınızı görüntüleyin, düzenleyin ve yönetin.
            </p>
          </div>
        </div>

        <button
          type="button"
          class="create-button"
          @click="goToCreatePost"
        >
          <span class="button-icon">+</span>
          Yeni Yazı Ekle
        </button>
      </header>

      <section class="statistics-grid">
        <div class="statistic-card">
          <div class="statistic-icon">📝</div>

          <div>
            <span class="statistic-label">Tüm Yazılar</span>
            <strong class="statistic-value">
              {{ posts.length }}
            </strong>
          </div>
        </div>

        <div class="statistic-card">
          <div class="statistic-icon">✓</div>

          <div>
            <span class="statistic-label">Yayınlananlar</span>
            <strong class="statistic-value">
              {{ publishedPostCount }}
            </strong>
          </div>
        </div>

        <div 
             v-if="isAdmin" 
             class="statistic-card">
             
          <div class="statistic-icon">📄</div>

          <div>
            <span class="statistic-label">Onay Bekleyenler</span>
            <strong class="statistic-value">
              {{ pendingPostCount }}
            </strong>
          </div>
        </div>
      </section>

      <section class="management-panel">
        <div class="panel-header">
          <div>
            <h2>Yazı Yönetimi</h2>

            <p>
              Yazılarınızı filtreleyebilir, düzenleyebilir veya silebilirsiniz.
            </p>
          </div>

          <button
            type="button"
            class="refresh-button"
            :disabled="isLoading"
            @click="loadPosts"
          >
            {{ isLoading ? 'Yenileniyor...' : 'Yenile' }}
          </button>
        </div>

        <div class="filter-tabs">
          <button
            type="button"
            class="filter-button"
            :class="{ active: activeFilter === 'all' }"
            @click="setFilter('all')"
          >
            Tümü
            <span>{{ posts.length }}</span>
          </button>

          <button
            type="button"
            class="filter-button"
            :class="{ active: activeFilter === 'published' }"
            @click="setFilter('published')"
          >
            Yayınlananlar
            <span>{{ publishedPostCount }}</span>
          </button>
         
          <button
            type="button"
            class="filter-button"
            :class="{ active: activeFilter === 'draft' }"
            @click="setFilter('draft')"
         >
            Taslaklar
            
            <span>{{ draftPostCount }}</span>
        </button>

          <button
            type="button"
            class="filter-button"
            :class="{ active: activeFilter === 'pending' }"
            @click="setFilter('pending')"
          >
          
            Onay Bekleyenler
          
            <span>{{ pendingPostCount }}</span>
        </button>
        </div>

        <div
          v-if="errorMessage"
          class="alert alert-error"
        >
          <span class="alert-icon">!</span>

          <span>{{ errorMessage }}</span>
        </div>

        <div
          v-if="isLoading"
          class="loading-state"
        >
          <div class="loading-spinner"></div>
          <p>Blog yazıları yükleniyor...</p>
        </div>

        <div
          v-else-if="filteredPosts.length === 0"
          class="empty-state"
        >
          <div class="empty-icon">🗒️</div>

          <h3>
            {{
              activeFilter === 'pending'
                ? 'Onay bekleyen yazı bulunamadı'
                : activeFilter === 'published'
                  ? 'Yayınlanmış yazı bulunamadı'
                  : 'Henüz blog yazısı bulunmuyor'
            }}
          </h3>

          <p>
            Yeni bir blog yazısı oluşturarak içeriklerinizi paylaşmaya
            başlayabilirsiniz.
          </p>

          <button
            type="button"
            class="empty-create-button"
            @click="goToCreatePost"
          >
            Yeni Yazı Oluştur
          </button>
        </div>

        <div
          v-else
          class="posts-list"
        >
          <article
            v-for="post in filteredPosts"
            :key="post.id"
            class="post-card"
          >
            <div class="post-main">
              <div class="post-top">
                <span
                  class="status-badge"
                  :class="{
                    draft: post.status === 'draft',
                    published: post.status === 'published',
                    pending: post.status === 'pending',
                    rejected: post.status === 'rejected',
                    }"
                >
                  {{ getStatusLabel(post.status) }}
                </span>

                <span class="post-date">
                  {{ formatDate(post.created_at) }}
                </span>
              </div>

              <h3 class="post-title">
                {{ post.title }}
              </h3>

              <p class="post-content">
                {{ post.content }}
              </p>
              <div
                 v-if="post.status === 'rejected' && post.rejection_reason"
                 class="rejection-box"
              >
                 <strong>Red Sebebi:</strong>
                 <span>{{ post.rejection_reason }}</span>
              </div>

              <div class="post-meta">
                <span class="author-avatar">
                  {{
                    getAuthorName(post)
                      .charAt(0)
                      .toUpperCase()
                  }}
                </span>

                <div>
                  <span class="meta-label">Yazar</span>
                  <strong>{{ getAuthorName(post) }}</strong>
                </div>
              </div>
            </div>

            <div class="post-actions">
                <button
                  type="button"
                  class="action-button view-button"
                  @click="router.push(`/posts/${post.id}`)"
                >
                  Görüntüle
                </button>

              <button
                type="button"
                class="action-button edit-button"
                @click="goToEditPost(post.id)"
              >
                Düzenle
              </button>

              <button
                type="button"
                class="action-button delete-button"
                @click="openDeleteModal(post)"
              >
                Sil
              </button>
            </div>
          </article>
        </div>
      </section>
    </div>
  </div>
  <div
  v-if="isDeleteModalOpen"
  class="modal-overlay"
  @click.self="closeDeleteModal"
>
  <div class="delete-modal">
    <div class="delete-modal-icon">!</div>

    <h3>Yazıyı Sil</h3>

    <p>
      <strong>{{ postToDelete?.title }}</strong>
      başlıklı yazıyı silmek istediğinize emin misiniz?
    </p>

    <p class="delete-warning">
      Bu işlem geri alınamaz.
    </p>

    <div class="delete-modal-actions">
      <button
        type="button"
        class="modal-cancel-button"
        :disabled="isDeleting"
        @click="closeDeleteModal"
      >
        İptal
      </button>

      <button
        type="button"
        class="modal-delete-button"
        :disabled="isDeleting"
        @click="confirmDeletePost"
      >
        {{ isDeleting ? 'Siliniyor...' : 'Yazıyı Sil' }}
      </button>
    </div>
  </div>
</div>
</template>

<style scoped>
.posts-page {
  min-height: 100vh;
  padding: 2rem 1.5rem 3rem;
  background-color: #f0f4f8;
  box-sizing: border-box;
}

.posts-container {
  width: 100%;
  max-width: 1080px;
  margin: 0 auto;
}

.page-header {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 1.5rem;
  margin-bottom: 1.5rem;
}

.header-content {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 1rem;
}

.header-text h1 {
  margin: 0;
  color: #1a1a2e;
  font-size: 1.9rem;
  font-weight: 700;
  letter-spacing: -0.03em;
}

.header-text p {
  margin: 0.5rem 0 0;
  color: #718096;
  font-size: 0.9375rem;
  line-height: 1.5;
}

.back-button {
  padding: 0;
  color: #4f6ef7;
  background: transparent;
  border: none;
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
}

.back-button:hover {
  color: #3b5de7;
  text-decoration: underline;
}

.create-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.55rem;
  padding: 0.8rem 1.3rem;
  color: #ffffff;
  background-color: #4f6ef7;
  border: none;
  border-radius: 8px;
  font-size: 0.9375rem;
  font-weight: 600;
  cursor: pointer;
  box-shadow: 0 4px 12px rgba(79, 110, 247, 0.2);
  transition:
    background-color 0.2s ease,
    transform 0.1s ease,
    box-shadow 0.2s ease;
}

.create-button:hover {
  background-color: #3b5de7;
  box-shadow: 0 6px 16px rgba(79, 110, 247, 0.25);
}

.create-button:active {
  transform: scale(0.98);
}

.button-icon {
  font-size: 1.2rem;
  line-height: 1;
}

.statistics-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.statistic-card {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1.25rem;
  background-color: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.05);
}

.statistic-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 46px;
  height: 46px;
  flex-shrink: 0;
  background-color: #eef2ff;
  border-radius: 10px;
  color: #4f6ef7;
  font-size: 1.2rem;
  font-weight: 700;
}

.statistic-label {
  display: block;
  margin-bottom: 0.25rem;
  color: #718096;
  font-size: 0.8125rem;
}

.statistic-value {
  display: block;
  color: #1a1a2e;
  font-size: 1.4rem;
  font-weight: 700;
}

.management-panel {
  padding: 1.75rem;
  background-color: #ffffff;
  border-radius: 12px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
}

.panel-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.panel-header h2 {
  margin: 0;
  color: #1a1a2e;
  font-size: 1.25rem;
  font-weight: 700;
}

.panel-header p {
  margin: 0.4rem 0 0;
  color: #718096;
  font-size: 0.875rem;
}

.refresh-button {
  padding: 0.6rem 1rem;
  color: #4f6ef7;
  background-color: #ffffff;
  border: 1.5px solid #c7d2fe;
  border-radius: 8px;
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
}

.refresh-button:hover:not(:disabled) {
  background-color: #f8faff;
}

.refresh-button:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.filter-tabs {
  display: flex;
  gap: 0.5rem;
  padding-bottom: 1.25rem;
  margin-bottom: 1.25rem;
  border-bottom: 1px solid #e2e8f0;
  overflow-x: auto;
}

.filter-button {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.625rem 0.875rem;
  color: #718096;
  background-color: transparent;
  border: 1.5px solid transparent;
  border-radius: 8px;
  font-size: 0.875rem;
  font-weight: 600;
  white-space: nowrap;
  cursor: pointer;
}

.filter-button span {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 22px;
  height: 22px;
  padding: 0 0.35rem;
  background-color: #edf2f7;
  border-radius: 999px;
  color: #4a5568;
  font-size: 0.75rem;
}

.filter-button:hover {
  color: #4f6ef7;
  background-color: #f8faff;
}

.filter-button.active {
  color: #4f6ef7;
  background-color: #eef2ff;
  border-color: #c7d2fe;
}

.filter-button.active span {
  color: #ffffff;
  background-color: #4f6ef7;
}

.alert {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 1rem 1.25rem;
  margin-bottom: 1.25rem;
  border-radius: 10px;
  font-size: 0.9rem;
}

.alert-error {
  color: #991b1b;
  background-color: #fef2f2;
  border: 1px solid #fecaca;
}

.alert-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 22px;
  height: 22px;
  flex-shrink: 0;
  color: #dc2626;
  background-color: #fee2e2;
  border-radius: 50%;
  font-size: 0.75rem;
  font-weight: 700;
}

.loading-state,
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 260px;
  padding: 2rem;
  text-align: center;
}

.loading-state {
  color: #718096;
}

.loading-spinner {
  width: 38px;
  height: 38px;
  margin-bottom: 1rem;
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

.empty-icon {
  margin-bottom: 1rem;
  font-size: 2.5rem;
}

.empty-state h3 {
  margin: 0;
  color: #1a1a2e;
  font-size: 1.125rem;
}

.empty-state p {
  max-width: 440px;
  margin: 0.65rem 0 1.25rem;
  color: #718096;
  font-size: 0.875rem;
  line-height: 1.6;
}

.empty-create-button {
  padding: 0.7rem 1.2rem;
  color: #ffffff;
  background-color: #4f6ef7;
  border: none;
  border-radius: 8px;
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
}

.posts-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.post-card {
  display: flex;
  align-items: stretch;
  justify-content: space-between;
  gap: 1.5rem;
  padding: 1.4rem;
  background-color: #ffffff;
  border: 1.5px solid #e2e8f0;
  border-radius: 12px;
  transition:
    border-color 0.2s ease,
    box-shadow 0.2s ease,
    transform 0.2s ease;
}

.post-card:hover {
  border-color: #c7d2fe;
  box-shadow: 0 6px 18px rgba(79, 110, 247, 0.08);
  transform: translateY(-1px);
}

.post-main {
  min-width: 0;
  flex: 1;
}

.post-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 0.8rem;
}

.status-badge {
  display: inline-flex;
  align-items: center;
  padding: 0.3rem 0.65rem;
  color: #4a5568;
  background-color: #edf2f7;
  border-radius: 999px;
  font-size: 0.75rem;
  font-weight: 700;
}
.status-badge.draft {
  color: #1d4ed8;
  background-color: #dbeafe;
}

.status-badge.published {
  color: #166534;
  background-color: #dcfce7;
}

.status-badge.pending {
  color: #92400e;
  background-color: #fef3c7;
}

.status-badge.rejected {
  color: #991b1b;
  background-color: #fee2e2;
}

.post-date {
  color: #a0aec0;
  font-size: 0.75rem;
}

.post-title {
  margin: 0;
  color: #1a1a2e;
  font-size: 1.15rem;
  font-weight: 700;
  line-height: 1.4;
}

.post-content {
  display: -webkit-box;
  margin: 0.6rem 0 1rem;
  overflow: hidden;
  color: #718096;
  font-size: 0.875rem;
  line-height: 1.6;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
}
.rejection-box {
  margin: 0 0 1rem;
  padding: 0.9rem 1rem;
  background: #fef2f2;
  border: 1px solid #fecaca;
  border-left: 4px solid #dc2626;
  border-radius: 8px;
}

.rejection-box strong {
  display: block;
  margin-bottom: 0.35rem;
  color: #991b1b;
  font-size: 0.85rem;
}

.rejection-box span {
  color: #7f1d1d;
  font-size: 0.85rem;
  line-height: 1.5;
}

.post-meta {
  display: flex;
  align-items: center;
  gap: 0.7rem;
}

.author-avatar {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 34px;
  height: 34px;
  flex-shrink: 0;
  color: #ffffff;
  background-color: #4f6ef7;
  border-radius: 50%;
  font-size: 0.8rem;
  font-weight: 700;
}

.meta-label {
  display: block;
  margin-bottom: 0.1rem;
  color: #a0aec0;
  font-size: 0.7rem;
}

.post-meta strong {
  color: #4a5568;
  font-size: 0.8rem;
  font-weight: 600;
}

.post-actions {
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 0.55rem;
  width: 110px;
  flex-shrink: 0;
  padding-left: 1.25rem;
  border-left: 1px solid #edf2f7;
}

.action-button {
  width: 100%;
  padding: 0.55rem 0.75rem;
  background-color: #ffffff;
  border-radius: 7px;
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
  transition:
    background-color 0.2s ease,
    border-color 0.2s ease;
}

.view-button {
  color: #4a5568;
  border: 1px solid #cbd5e0;
}

.view-button:hover {
  background-color: #f8fafc;
}

.edit-button {
  color: #4f6ef7;
  border: 1px solid #c7d2fe;
}

.edit-button:hover {
  background-color: #eef2ff;
}

.delete-button {
  color: #dc2626;
  border: 1px solid #fecaca;
}

.delete-button:hover {
  background-color: #fef2f2;
}

@media (max-width: 760px) {
  .posts-page {
    padding: 1.25rem 1rem 2rem;
  }

  .page-header {
    align-items: stretch;
    flex-direction: column;
  }

  .create-button {
    width: 100%;
  }

  .statistics-grid {
    grid-template-columns: 1fr;
  }

  .management-panel {
    padding: 1.25rem;
  }

  .panel-header {
    align-items: flex-start;
    flex-direction: column;
  }

  .post-card {
    flex-direction: column;
  }

  .post-actions {
    flex-direction: row;
    width: 100%;
    padding-top: 1rem;
    padding-left: 0;
    border-top: 1px solid #edf2f7;
    border-left: none;
  }

  .post-top {
    align-items: flex-start;
    flex-direction: column;
    gap: 0.5rem;
  }
}

@media (max-width: 480px) {
  .header-text h1 {
    font-size: 1.55rem;
  }

  .filter-tabs {
    padding-bottom: 1rem;
  }

  .post-actions {
    flex-direction: column;
  }
}
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
}

.delete-modal {
  width: 100%;
  max-width: 430px;
  padding: 2rem;
  background: #ffffff;
  border-radius: 14px;
  text-align: center;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.18);
}

.delete-modal-icon {
  width: 70px;
  height: 70px;
  margin: 0 auto 1rem;
  border-radius: 50%;
  background: #fee2e2;
  color: #dc2626;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 2rem;
}

.delete-modal h3 {
  margin-bottom: 0.75rem;
  color: #1f2937;
}

.delete-modal p {
  color: #6b7280;
  line-height: 1.6;
}

.delete-warning {
  margin-top: 0.75rem;
  color: #dc2626 !important;
  font-weight: 600;
}

.delete-modal-actions {
  display: flex;
  gap: 1rem;
  margin-top: 2rem;
}

.modal-cancel-button,
.modal-delete-button {
  flex: 1;
  padding: 0.85rem;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
}

.modal-cancel-button {
  background: white;
  border: 1px solid #d1d5db;
}

.modal-cancel-button:hover {
  background: #f9fafb;
}

.modal-delete-button {
  background: #dc2626;
  color: white;
  border: none;
}

.modal-delete-button:hover {
  background: #b91c1c;
}

.modal-delete-button:disabled,
.modal-cancel-button:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>