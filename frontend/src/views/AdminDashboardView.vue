<script setup>
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { getAdminDashboard } from '../services/postService'

const dashboardData = ref({
  summary: {
    users_total: 0,
    posts_total: 0,
    posts_published: 0,
    posts_pending: 0,
    posts_rejected: 0,
    posts_draft: 0,
    categories_total: 0,
    categories_active: 0,
    views_total: 0,
  },
  recent_pending_posts: [],
})

const isLoading = ref(true)
const errorMessage = ref('')

const summaryCards = [
  { key: 'users_total', label: 'Toplam Kullanıcı' },
  { key: 'posts_total', label: 'Toplam Yazı' },
  { key: 'posts_published', label: 'Yayınlanan' },
  { key: 'posts_pending', label: 'Onay Bekleyen' },
  { key: 'posts_rejected', label: 'Reddedilen' },
  { key: 'posts_draft', label: 'Taslak' },
  { key: 'categories_total', label: 'Toplam Kategori' },
  { key: 'categories_active', label: 'Aktif Kategori' },
  { key: 'views_total', label: 'Toplam Görüntülenme' },
]

const formatDate = (date) => {
  if (!date) {
    return '-'
  }

  const parsedDate = new Date(date)

  if (Number.isNaN(parsedDate.getTime())) {
    return '-'
  }

  return parsedDate.toLocaleDateString('tr-TR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  })
}

const loadDashboard = async () => {
  isLoading.value = true
  errorMessage.value = ''

  try {
    const response = await getAdminDashboard()

    dashboardData.value = {
      summary: response.data.summary ?? dashboardData.value.summary,
      recent_pending_posts:
        response.data.recent_pending_posts ?? [],
    }
  } catch (error) {
    console.error('Dashboard verileri alınamadı:', error)

    errorMessage.value =
      error.response?.data?.message ||
      'Dashboard verileri yüklenirken bir hata oluştu.'
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  loadDashboard()
})
</script>

<template>
  <div class="admin-dashboard-page">
    <div class="admin-dashboard-container">
      <header class="page-header">
        <h1>Dashboard</h1>
        <p>
          Blog yönetimiyle ilgili genel durumu buradan takip edebilirsiniz.
        </p>
      </header>

      <div
        v-if="isLoading"
        class="state-card loading-state"
      >
        Dashboard verileri yükleniyor...
      </div>

      <div
        v-else-if="errorMessage"
        class="state-card error-state"
      >
        <p>{{ errorMessage }}</p>

        <button
          type="button"
          class="retry-button"
          @click="loadDashboard"
        >
          Tekrar Dene
        </button>
      </div>

      <template v-else>
        <section class="summary-grid">
          <article
            v-for="card in summaryCards"
            :key="card.key"
            class="summary-card"
          >
            <span class="summary-label">{{ card.label }}</span>
            <strong class="summary-value">
              {{ dashboardData.summary[card.key] ?? 0 }}
            </strong>
          </article>
        </section>

        <section class="panel">
          <div class="panel-header">
            <h2>Son Onay Bekleyen Yazılar</h2>
          </div>

          <p
            v-if="dashboardData.recent_pending_posts.length === 0"
            class="empty-message"
          >
            Onay bekleyen yazı bulunmuyor.
          </p>

          <div
            v-else
            class="table-wrapper"
          >
            <table class="posts-table">
              <thead>
                <tr>
                  <th>Başlık</th>
                  <th>Yazar</th>
                  <th>Kategori</th>
                  <th>Görüntülenme</th>
                  <th>Tarih</th>
                  <th>İşlem</th>
                </tr>
              </thead>

              <tbody>
                <tr
                  v-for="post in dashboardData.recent_pending_posts"
                  :key="post.id"
                >
                  <td data-label="Başlık">{{ post.title }}</td>
                  <td data-label="Yazar">
                    {{ post.author?.name ?? 'Bilinmiyor' }}
                  </td>
                  <td data-label="Kategori">
                    {{ post.category?.name ?? 'Kategorisiz' }}
                  </td>
                  <td data-label="Görüntülenme">
                    {{ post.views_count ?? 0 }}
                  </td>
                  <td data-label="Tarih">
                    {{ formatDate(post.created_at) }}
                  </td>
                  <td data-label="İşlem">
                    <RouterLink
                      to="/admin/posts"
                      class="action-link"
                    >
                      İncele
                    </RouterLink>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <section class="panel">
          <div class="panel-header">
            <h2>Hızlı İşlemler</h2>
          </div>

          <div class="quick-actions">
            <RouterLink
              to="/admin/posts"
              class="quick-action-link"
            >
              Onay Bekleyenler
            </RouterLink>

            <RouterLink
              to="/admin/categories"
              class="quick-action-link"
            >
              Kategoriler
            </RouterLink>

            <RouterLink
              to="/posts"
              class="quick-action-link"
            >
              Blog Yazıları
            </RouterLink>
          </div>
        </section>
      </template>
    </div>
  </div>
</template>

<style scoped>
.admin-dashboard-page {
  padding: 2rem;
  box-sizing: border-box;
}

.admin-dashboard-container {
  max-width: 1100px;
  margin: 0 auto;
}

.page-header {
  padding: 2rem;
  margin-bottom: 1.5rem;
  background-color: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
}

.page-header h1 {
  margin: 0 0 0.5rem;
  color: #1a1a2e;
  font-size: 2rem;
  font-weight: 700;
  letter-spacing: -0.03em;
}

.page-header p {
  margin: 0;
  color: #718096;
  font-size: 0.95rem;
  line-height: 1.6;
}

.state-card {
  padding: 2rem;
  margin-bottom: 1.5rem;
  background-color: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
  text-align: center;
  color: #718096;
}

.error-state p {
  margin: 0 0 1rem;
  color: #991b1b;
}

.retry-button {
  padding: 0.65rem 1.1rem;
  color: #ffffff;
  background-color: #4f6ef7;
  border: none;
  border-radius: 8px;
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
}

.retry-button:hover {
  background-color: #3b5de7;
}

.summary-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.summary-card {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  padding: 1.25rem;
  background-color: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
}

.summary-label {
  color: #718096;
  font-size: 0.875rem;
  font-weight: 500;
}

.summary-value {
  color: #1a1a2e;
  font-size: 1.75rem;
  font-weight: 700;
  line-height: 1.2;
}

.panel {
  padding: 1.75rem;
  margin-bottom: 1.5rem;
  background-color: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
}

.panel-header {
  margin-bottom: 1.25rem;
}

.panel-header h2 {
  margin: 0;
  color: #1a1a2e;
  font-size: 1.15rem;
  font-weight: 700;
}

.empty-message {
  margin: 0;
  color: #718096;
  font-size: 0.9375rem;
}

.table-wrapper {
  overflow-x: auto;
}

.posts-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.9rem;
}

.posts-table th,
.posts-table td {
  padding: 0.85rem 1rem;
  border-bottom: 1px solid #e2e8f0;
  text-align: left;
  vertical-align: top;
}

.posts-table th {
  color: #4a5568;
  background-color: #f8fafc;
  font-size: 0.8rem;
  font-weight: 600;
}

.posts-table td {
  color: #1a1a2e;
}

.action-link {
  color: #4f6ef7;
  font-size: 0.875rem;
  font-weight: 600;
  text-decoration: none;
}

.action-link:hover {
  color: #3b5de7;
  text-decoration: underline;
}

.quick-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
}

.quick-action-link {
  display: inline-flex;
  align-items: center;
  padding: 0.75rem 1.1rem;
  color: #4338ca;
  background-color: #eef2ff;
  border: 1px solid #c7d2fe;
  border-radius: 8px;
  font-size: 0.875rem;
  font-weight: 600;
  text-decoration: none;
  transition: background-color 0.15s ease;
}

.quick-action-link:hover {
  background-color: #e0e7ff;
}

@media (max-width: 768px) {
  .admin-dashboard-page {
    padding: 1.25rem 1rem;
  }

  .page-header,
  .panel {
    padding: 1.25rem;
  }

  .summary-value {
    font-size: 1.5rem;
  }
}
</style>
