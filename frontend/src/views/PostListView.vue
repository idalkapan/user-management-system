<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { getPosts, likePost, unlikePost } from '../services/postService'
import { getCategories } from '../services/categoryService'
import { useAuthStore } from '../stores/auth'
import PostInteractionBar from '../components/PostInteractionBar.vue'
import Pagination from '../components/Pagination.vue'

const router = useRouter()
const authStore = useAuthStore()

const posts = ref([])
const categories = ref([])
const searchQuery = ref('')
const debouncedSearch = ref('')
const selectedCategory = ref('')
const currentPage = ref(1)
const lastPage = ref(1)
const perPage = ref(9)
const total = ref(0)
const isInitialLoading = ref(true)
const isRefreshing = ref(false)
const errorMessage = ref('')
const likeActionError = ref('')
const likingPostIds = ref(new Set())
const postsListAnchor = ref(null)

let searchDebounceTimer = null

const hasActiveFilters = computed(
  () => debouncedSearch.value !== '' || selectedCategory.value !== '',
)

const parsePostsResponse = (response) => {
  const raw = response.data?.posts

  if (Array.isArray(raw)) {
    return raw
  }

  if (Array.isArray(raw?.data)) {
    return raw.data
  }

  if (Array.isArray(response.data?.data)) {
    return response.data.data
  }

  return []
}

const parseCategoriesResponse = (response) => {
  const raw = response.data?.categories

  if (Array.isArray(raw)) {
    return raw
  }

  if (Array.isArray(raw?.data)) {
    return raw.data
  }

  return []
}

const loadCategories = async () => {
  try {
    const response = await getCategories()
    categories.value = parseCategoriesResponse(response)
  } catch {
    categories.value = []
  }
}

const loadPosts = async ({ scrollToList = false } = {}) => {
  if (isInitialLoading.value) {
    errorMessage.value = ''
  } else {
    isRefreshing.value = true
  }

  try {
    const params = {
      page: currentPage.value,
      per_page: perPage.value,
    }

    if (debouncedSearch.value) {
      params.search = debouncedSearch.value
    }

    if (selectedCategory.value) {
      params.category = selectedCategory.value
    }

    const response = await getPosts(params)

    posts.value = parsePostsResponse(response)

    const meta = response.data?.meta ?? {}

    currentPage.value = meta.current_page ?? currentPage.value
    lastPage.value = meta.last_page ?? 1
    perPage.value = meta.per_page ?? perPage.value
    total.value = meta.total ?? 0
    errorMessage.value = ''

    if (scrollToList) {
      postsListAnchor.value?.scrollIntoView({
        behavior: 'smooth',
        block: 'start',
      })
    }
  } catch (error) {
    errorMessage.value =
      error.response?.data?.message ||
      'Blog yazıları yüklenirken bir hata oluştu.'
  } finally {
    isInitialLoading.value = false
    isRefreshing.value = false
  }
}

const handlePageChange = (page) => {
  if (page === currentPage.value || isRefreshing.value) {
    return
  }

  currentPage.value = page
  loadPosts({ scrollToList: true })
}

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

const getAuthorId = (post) => post.author?.id ?? post.user?.id ?? null

const canLikePost = (post) => {
  if (!authStore.isAuthenticated || authStore.isAdmin) {
    return false
  }

  if (post.status !== 'published') {
    return false
  }

  const authorId = getAuthorId(post)

  return authorId !== null && authorId !== authStore.user?.id
}

const isLikingPost = (postId) => likingPostIds.value.has(postId)

const getLikeAriaLabel = (post) =>
  post.is_liked_by_current_user ? 'Beğeniyi kaldır' : 'Beğeni ekle'

const openPostComments = (post) => {
  router.push({
    name: 'post-detail',
    params: { id: post.id },
    query: {
      from: 'blog',
      focus: 'comments',
    },
  })
}

const toggleLike = async (post) => {
  if (!canLikePost(post) || isLikingPost(post.id)) {
    return
  }

  likeActionError.value = ''

  const postIndex = posts.value.findIndex((item) => item.id === post.id)

  if (postIndex === -1) {
    return
  }

  const currentPost = posts.value[postIndex]
  const wasLiked = Boolean(currentPost.is_liked_by_current_user)
  const previousLikesCount = currentPost.likes_count ?? 0

  likingPostIds.value = new Set(likingPostIds.value).add(post.id)

  posts.value[postIndex] = {
    ...currentPost,
    is_liked_by_current_user: !wasLiked,
    likes_count: Math.max(0, previousLikesCount + (wasLiked ? -1 : 1)),
  }

  try {
    const response = wasLiked
      ? await unlikePost(post.id)
      : await likePost(post.id)

    posts.value[postIndex] = {
      ...posts.value[postIndex],
      likes_count: response.data.likes_count ?? posts.value[postIndex].likes_count,
      is_liked_by_current_user:
        response.data.is_liked_by_current_user ??
        posts.value[postIndex].is_liked_by_current_user,
    }
  } catch (error) {
    posts.value[postIndex] = {
      ...posts.value[postIndex],
      is_liked_by_current_user: wasLiked,
      likes_count: previousLikesCount,
    }

    likeActionError.value =
      error.response?.data?.message ||
      'Beğeni işlemi sırasında bir hata oluştu.'
  } finally {
    const nextSet = new Set(likingPostIds.value)
    nextSet.delete(post.id)
    likingPostIds.value = nextSet
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

  await loadCategories()
  await loadPosts()
})

watch(searchQuery, () => {
  clearTimeout(searchDebounceTimer)

  searchDebounceTimer = setTimeout(() => {
    debouncedSearch.value = searchQuery.value.trim()
  }, 400)
})

watch(debouncedSearch, () => {
  if (isInitialLoading.value) {
    return
  }

  currentPage.value = 1
  loadPosts()
})

watch(selectedCategory, () => {
  if (isInitialLoading.value) {
    return
  }

  currentPage.value = 1
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
            :disabled="isInitialLoading || isRefreshing"
            @click="loadPosts()"
          >
            {{ isInitialLoading || isRefreshing ? 'Yenileniyor...' : 'Yenile' }}
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
        :value="category.slug"
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
          v-if="likeActionError"
          class="alert alert-error"
        >
          <span class="alert-icon">!</span>

          <span>{{ likeActionError }}</span>
        </div>

        <div
          v-if="isInitialLoading"
          class="loading-state"
        >
          <div class="loading-spinner"></div>
          <p>Blog yazıları yükleniyor...</p>
        </div>

        <template v-else>
          <div
            v-if="isRefreshing"
            class="refresh-banner"
            role="status"
            aria-live="polite"
          >
            Yazılar güncelleniyor...
          </div>

          <div
            v-if="!isRefreshing && posts.length === 0"
            class="empty-state"
          >
            <div class="empty-icon">🗒️</div>
            <h3>
              {{
                hasActiveFilters
                  ? 'Aramanızla eşleşen blog yazısı bulunamadı.'
                  : 'Henüz yayınlanmış blog yazısı bulunmuyor.'
              }}
            </h3>

            <p>
              {{
                hasActiveFilters
                  ? 'Farklı bir başlık, içerik veya kategori ile tekrar deneyebilirsiniz.'
                  : 'Yayınlanmış bir blog yazısı olduğunda burada görüntülenecektir.'
              }}
            </p>
          </div>

          <div
            v-else
            ref="postsListAnchor"
            class="posts-list"
            :class="{ 'posts-list--refreshing': isRefreshing }"
          >
            <article
              v-for="post in posts"
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
                  👤 {{ post.author?.name ?? post.user?.name ?? 'Bilinmiyor' }}
                </span>

                <span class="meta-item">
                  📅 {{ formatDate(post.created_at) }}
                </span>
              </div>

              <PostInteractionBar
                variant="list"
                :views-count="post.views_count ?? 0"
                :likes-count="post.likes_count ?? 0"
                :comments-count="post.comments_count ?? 0"
                :is-liked="Boolean(post.is_liked_by_current_user)"
                :can-like="canLikePost(post)"
                :is-liking="isLikingPost(post.id)"
                :like-aria-label="getLikeAriaLabel(post)"
                @toggle-like="toggleLike(post)"
                @open-comments="openPostComments(post)"
              />
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

          <Pagination
            :current-page="currentPage"
            :last-page="lastPage"
            :loading="isRefreshing"
            @change-page="handlePageChange"
          />
        </template>
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
  transition: opacity 0.15s ease;
}

.posts-list--refreshing {
  opacity: 0.72;
}

.refresh-banner {
  margin-bottom: 1rem;
  padding: 0.75rem 1rem;
  color: #4338ca;
  background-color: #eef2ff;
  border: 1px solid #c7d2fe;
  border-radius: 10px;
  font-size: 0.875rem;
  font-weight: 500;
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