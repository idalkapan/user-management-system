<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { getPosts } from '../services/postService'


const router = useRouter()

const posts = ref([])
const searchQuery = ref('')
const selectedCategory = ref('')
const isLoading = ref(true)
const errorMessage = ref('')



const loadPosts = async () => {
  isLoading.value = true
  errorMessage.value = ''

  try {
    const response = await getPosts()

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

const categories = computed(() => {
  const categoryMap = new Map()

  posts.value.forEach((post) => {
    if (post.category?.id && post.category?.name) {
      categoryMap.set(post.category.id, post.category)
    }
  })

  return Array.from(categoryMap.values())
})

const filteredPosts = computed(() => {
  const searchText = searchQuery.value.trim().toLocaleLowerCase('tr-TR')

  return posts.value.filter((post) => {
    const isPublished = post.status === 'published'

    const matchesSearch =
       post.title?.toLocaleLowerCase('tr-TR').includes(searchText) ||
       post.content?.toLocaleLowerCase('tr-TR').includes(searchText)

    const matchesCategory =
       selectedCategory.value === '' ||
       String(post.category?.id) === String(selectedCategory.value)

    return isPublished && matchesSearch && matchesCategory
  })
})

const getStatusLabel = (status) => {
  if (status === 'published') {
    return 'Yayında'
  }

  if (status === 'pending') {
    return 'Onay Bekliyor'
  }

  if (status === 'rejected') {
    return 'Reddedildi'
  }

  return 'Durum belirtilmemiş'
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

onMounted(() => {
  loadPosts()
})
</script>

<template>
  <div class="posts-page">
    <div class="posts-container">
      <header class="page-header">
        <div class="header-text">
          <h1>Blog Yazıları</h1>

          <p>
            Yayınlanmış blog yazılarını okuyabilir ve içeriklerde arama yapabilirsiniz.
          </p>
        </div>
      </header>

      <section class="management-panel">
        <div class="panel-header">
          <div>
            <h2>Yayınlanan Yazılar</h2>
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

        <div class="filter-section">
  <div class="search-box">
    <input
      v-model="searchQuery"
      type="text"
      placeholder="Başlık veya içerikte ara..."
      class="search-input"
    />
  </div>

  <div class="category-filter">
    <select
      v-model="selectedCategory"
      class="category-select"
    >
      <option value="">
        Tüm Kategoriler
      </option>

      <option
        v-for="category in categories"
        :key="category.id"
        :value="category.id"
      >
        {{ category.name }}
      </option>
    </select>
  </div>
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
          searchQuery.trim()
          ? 'Aramanızla eşleşen blog yazısı bulunamadı.'
          : 'Henüz yayınlanmış blog yazısı bulunmuyor.'
          }}

        </h3>

        <p>
          {{
          searchQuery.trim()
          ? 'Farklı bir başlık veya içerik kelimesiyle tekrar arama yapabilirsiniz.'
          : 'Yayınlanmış bir blog yazısı olduğunda burada görüntülenecektir.'
          }}
        </p>
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
              
              <span
                v-if="post.category"
                class="category-badge"
              >
                {{ post.category.name }}
              </span>

              <p class="post-content">
                {{ post.content }}
              </p>

              <div class="post-meta">
                <span class="meta-item">
                  👤 {{ post.user?.name }}
                </span>

                <span class="meta-item">
                  👁️ {{ post.views_count ?? 0 }}
                </span>

                <span class="meta-item">
                  📅 {{ formatDate(post.created_at) }}
                </span>
              </div>
            </div>

            <div class="post-actions">
                <button
                  type="button"
                  class="action-button view-button"
                  @click="router.push(`/posts/${post.id}?from=blog`)"
                >
                  Görüntüle
                </button>

            </div>
          </article>
        </div>
      </section>
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
.filter-section {
  display: flex;
  gap: 1rem;
  margin-bottom: 1.5rem;
  align-items: center;
}

.category-filter {
  min-width: 220px;
}

.category-select {
  width: 100%;
  padding: 0.8rem 1rem;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  font-size: 0.95rem;
  background: white;
  outline: none;
  transition: border-color 0.2s;
}

.category-select:focus {
  border-color: #4f6ef7;
}

.search-box {
  flex: 1;
}

.search-input {
  width: 100%;
  padding: 0.8rem 1rem;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  font-size: 0.95rem;
  outline: none;
  transition: border-color 0.2s;
}

.search-input:focus {
  border-color: #4f6ef7;
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

.category-badge {
  display: inline-flex;
  align-items: center;
  margin-top: 0.55rem;
  padding: 0.3rem 0.65rem;
  color: #4338ca;
  background-color: #eef2ff;
  border-radius: 999px;
  font-size: 0.75rem;
  font-weight: 700;
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

.post-meta {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 1.25rem;
}

.meta-item {
  color: #4a5568;
  font-size: 0.8rem;
  font-weight: 600;
  white-space: nowrap;
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

@media (max-width: 760px) {
  .posts-page {
    padding: 1.25rem 1rem 2rem;
  }

  .page-header {
    align-items: stretch;
    flex-direction: column;
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
  
  .post-actions {
    flex-direction: column;
  }
}
</style>