<script setup>
import { computed, onMounted, ref } from 'vue'
import { getAdminStatistics } from '../services/postService'

const statistics = ref(null)
const isLoading = ref(true)
const errorMessage = ref('')

const summaryCards = [
  { key: 'views_today', label: 'Bugünkü Görüntülenme' },
  { key: 'views_last_7_days', label: 'Son 7 Günlük Görüntülenme' },
  { key: 'users_added_this_month', label: 'Bu Ay Eklenen Kullanıcı' },
  { key: 'posts_published_this_month', label: 'Bu Ay Yayımlanan Yazı' },
  { key: 'posts_pending', label: 'Onay Bekleyen İçerik' },
  { key: 'posts_rejected', label: 'Reddedilen İçerik' },
]

const formatDate = (date) => {
  if (!date) {
    return '—'
  }

  const parsedDate = new Date(date)

  if (Number.isNaN(parsedDate.getTime())) {
    return '—'
  }

  return parsedDate.toLocaleDateString('tr-TR', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  })
}

const formatShortDate = (date) => {
  if (!date) {
    return ''
  }

  const parsedDate = new Date(`${date}T00:00:00`)

  if (Number.isNaN(parsedDate.getTime())) {
    return ''
  }

  return parsedDate.toLocaleDateString('tr-TR', {
    day: 'numeric',
    month: 'short',
  })
}

const summary = computed(
  () => statistics.value?.summary ?? {},
)

const dailyViews = computed(
  () => statistics.value?.chart?.daily_views ?? [],
)

const statusDistribution = computed(
  () => statistics.value?.status_distribution ?? [],
)

const topPosts = computed(
  () => statistics.value?.top_posts ?? [],
)

const topAuthors = computed(
  () => statistics.value?.top_authors ?? [],
)

const categoryPerformance = computed(
  () => statistics.value?.category_performance ?? [],
)

const maxDailyViews = computed(() => {
  if (dailyViews.value.length === 0) {
    return 0
  }

  return Math.max(...dailyViews.value.map((day) => day.views ?? 0))
})

const hasChartData = computed(() => maxDailyViews.value > 0)

const totalStatusCount = computed(() =>
  statusDistribution.value.reduce(
    (total, item) => total + (item.count ?? 0),
    0,
  ),
)

const getBarHeight = (views) => {
  if (!hasChartData.value || maxDailyViews.value === 0) {
    return '0%'
  }

  const heightPercent = (views / maxDailyViews.value) * 100

  return `${Math.max(heightPercent, views > 0 ? 4 : 0)}%`
}

const getStatusBarWidth = (count) => {
  if (totalStatusCount.value === 0) {
    return '0%'
  }

  return `${(count / totalStatusCount.value) * 100}%`
}

const shouldShowChartLabel = (index) => {
  const total = dailyViews.value.length

  if (total === 0) {
    return false
  }

  if (index === 0 || index === total - 1) {
    return true
  }

  return index % 5 === 0
}

const loadStatistics = async () => {
  isLoading.value = true
  errorMessage.value = ''

  try {
    const response = await getAdminStatistics()
    statistics.value = response.data.statistics ?? null
  } catch {
    errorMessage.value =
      'Sistem istatistikleri yüklenirken bir hata oluştu.'
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  loadStatistics()
})
</script>

<template>
  <div class="admin-statistics-page">
    <div class="admin-statistics-container">
      <header class="page-header">
        <div class="page-header-content">
          <div>
            <h1>Sistem İstatistikleri</h1>
            <p>Blog platformunun genel performansını buradan takip edebilirsiniz.</p>
          </div>

          <span class="period-badge">Son 30 Gün</span>
        </div>
      </header>

      <div
        v-if="isLoading"
        class="state-card loading-state"
      >
        Sistem istatistikleri yükleniyor...
      </div>

      <div
        v-else-if="errorMessage"
        class="state-card error-state"
      >
        <p>{{ errorMessage }}</p>

        <button
          type="button"
          class="retry-button"
          @click="loadStatistics"
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
              {{ summary[card.key] ?? 0 }}
            </strong>
          </article>
        </section>

        <div class="analytics-grid">
          <section class="panel chart-panel">
            <div class="panel-header">
              <h2>Son 30 Günlük Görüntülenmeler</h2>
            </div>

            <p
              v-if="!hasChartData"
              class="empty-message"
            >
              Bu dönem için görüntülenme verisi bulunmuyor.
            </p>

            <div
              v-else
              class="chart-wrapper"
            >
              <div class="chart-bars">
                <div
                  v-for="(day, index) in dailyViews"
                  :key="day.date"
                  class="chart-bar-column"
                >
                  <div class="chart-bar-stack">
                    <div
                      class="chart-bar-reference"
                      aria-hidden="true"
                    />

                    <div
                      v-if="(day.views ?? 0) > 0"
                      class="chart-bar"
                      :style="{ height: getBarHeight(day.views ?? 0) }"
                      :title="`${formatShortDate(day.date)}: ${day.views ?? 0} görüntülenme`"
                    />
                  </div>

                  <span
                    v-if="shouldShowChartLabel(index)"
                    class="chart-label"
                  >
                    {{ formatShortDate(day.date) }}
                  </span>
                </div>
              </div>
            </div>
          </section>

          <section class="panel">
            <div class="panel-header">
              <h2>Yazı Durum Dağılımı</h2>
            </div>

            <p
              v-if="totalStatusCount === 0"
              class="empty-message"
            >
              Henüz yazı bulunmuyor.
            </p>

            <ul
              v-else
              class="status-list"
            >
              <li
                v-for="item in statusDistribution"
                :key="item.status"
                class="status-item"
              >
                <div class="status-item-header">
                  <span class="status-label">{{ item.label }}</span>
                  <strong class="status-count">{{ item.count ?? 0 }}</strong>
                </div>

                <div class="status-bar-track">
                  <div
                    class="status-bar-fill"
                    :style="{ width: getStatusBarWidth(item.count ?? 0) }"
                  />
                </div>
              </li>
            </ul>
          </section>
        </div>

        <section class="panel">
          <div class="panel-header">
            <h2>En Çok Görüntülenen Yazılar</h2>
          </div>

          <p
            v-if="topPosts.length === 0"
            class="empty-message"
          >
            Yayınlanmış yazı bulunmuyor.
          </p>

          <div
            v-else
            class="table-wrapper"
          >
            <table class="data-table">
              <thead>
                <tr>
                  <th>Başlık</th>
                  <th>Yazar</th>
                  <th>Kategori</th>
                  <th class="col-number">Görüntülenme</th>
                  <th>Yayın Tarihi</th>
                </tr>
              </thead>

              <tbody>
                <tr
                  v-for="post in topPosts"
                  :key="post.id"
                >
                  <td data-label="Başlık">{{ post.title }}</td>
                  <td data-label="Yazar">
                    {{ post.author?.name ?? 'Bilinmiyor' }}
                  </td>
                  <td data-label="Kategori">
                    {{ post.category?.name ?? 'Kategorisiz' }}
                  </td>
                  <td
                    class="col-number"
                    data-label="Görüntülenme"
                  >
                    {{ post.views_count ?? 0 }}
                  </td>
                  <td data-label="Yayın Tarihi">
                    {{ formatDate(post.published_at) }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <section class="panel">
          <div class="panel-header">
            <h2>En Aktif Yazarlar</h2>
          </div>

          <p
            v-if="topAuthors.length === 0"
            class="empty-message"
          >
            Yayınlanmış yazısı olan yazar bulunmuyor.
          </p>

          <div
            v-else
            class="table-wrapper"
          >
            <table class="data-table">
              <thead>
                <tr>
                  <th>Yazar</th>
                  <th class="col-number">Yayınlanan Yazı</th>
                  <th class="col-number">Toplam Görüntülenme</th>
                </tr>
              </thead>

              <tbody>
                <tr
                  v-for="author in topAuthors"
                  :key="author.id"
                >
                  <td data-label="Yazar">{{ author.name }}</td>
                  <td
                    class="col-number"
                    data-label="Yayınlanan Yazı"
                  >
                    {{ author.published_posts_count ?? 0 }}
                  </td>
                  <td
                    class="col-number"
                    data-label="Toplam Görüntülenme"
                  >
                    {{ author.total_views ?? 0 }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <section class="panel">
          <div class="panel-header">
            <h2>Kategori Performansı</h2>
          </div>

          <p
            v-if="categoryPerformance.length === 0"
            class="empty-message"
          >
            Yayınlanmış yazısı olan kategori bulunmuyor.
          </p>

          <div
            v-else
            class="table-wrapper"
          >
            <table class="data-table">
              <thead>
                <tr>
                  <th>Kategori</th>
                  <th class="col-number">Yayınlanan Yazı</th>
                  <th class="col-number">Toplam Görüntülenme</th>
                </tr>
              </thead>

              <tbody>
                <tr
                  v-for="category in categoryPerformance"
                  :key="category.id"
                >
                  <td data-label="Kategori">{{ category.name }}</td>
                  <td
                    class="col-number"
                    data-label="Yayınlanan Yazı"
                  >
                    {{ category.published_posts_count ?? 0 }}
                  </td>
                  <td
                    class="col-number"
                    data-label="Toplam Görüntülenme"
                  >
                    {{ category.total_views ?? 0 }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>
      </template>
    </div>
  </div>
</template>

<style scoped>
.admin-statistics-page {
  padding: 2rem;
  box-sizing: border-box;
}

.admin-statistics-container {
  max-width: 1100px;
  margin: 0 auto;
}

.page-header,
.state-card,
.summary-card,
.panel {
  background-color: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
}

.page-header {
  padding: 2rem;
  margin-bottom: 1.5rem;
}

.page-header-content {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
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

.period-badge {
  flex-shrink: 0;
  padding: 0.5rem 0.9rem;
  color: #64748b;
  background-color: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 999px;
  font-size: 0.8125rem;
  font-weight: 500;
  white-space: nowrap;
}

.state-card {
  padding: 2rem;
  margin-bottom: 1.5rem;
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
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.summary-card {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  padding: 1.25rem;
}

.summary-label {
  color: #718096;
  font-size: 0.8125rem;
  font-weight: 500;
}

.summary-value {
  color: #1a1a2e;
  font-size: 1.75rem;
  font-weight: 700;
  line-height: 1.2;
}

.analytics-grid {
  display: grid;
  grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr);
  gap: 1.5rem;
  margin-bottom: 1.5rem;
}

.panel {
  padding: 1.75rem;
  margin-bottom: 1.5rem;
}

.analytics-grid .panel {
  margin-bottom: 0;
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

.chart-wrapper {
  overflow-x: auto;
}

.chart-bars {
  display: flex;
  align-items: flex-end;
  gap: 0.3rem;
  min-width: 100%;
  padding-bottom: 0.15rem;
  border-bottom: 1px solid #e2e8f0;
}

.chart-bar-column {
  display: flex;
  flex: 1;
  flex-direction: column;
  align-items: center;
  justify-content: flex-end;
  min-width: 0;
}

.chart-bar-stack {
  position: relative;
  display: flex;
  align-items: flex-end;
  justify-content: center;
  width: 100%;
  max-width: 16px;
  height: 168px;
  margin-bottom: 0.45rem;
}

.chart-bar-reference {
  position: absolute;
  bottom: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background-color: #f1f5f9;
  border-radius: 3px 3px 0 0;
}

.chart-bar {
  position: relative;
  z-index: 1;
  width: 100%;
  background: linear-gradient(180deg, #6366f1 0%, #4f6ef7 100%);
  border-radius: 3px 3px 0 0;
}

.chart-label {
  min-height: 1rem;
  color: #94a3b8;
  font-size: 0.6875rem;
  line-height: 1.2;
  text-align: center;
  white-space: nowrap;
}

.status-list {
  display: flex;
  flex-direction: column;
  gap: 0.85rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.status-item-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  margin-bottom: 0.35rem;
}

.status-label {
  color: #475569;
  font-size: 0.875rem;
  font-weight: 500;
}

.status-count {
  color: #1a1a2e;
  font-size: 0.875rem;
  font-weight: 700;
}

.status-bar-track {
  height: 8px;
  background-color: #f1f5f9;
  border-radius: 999px;
  overflow: hidden;
}

.status-bar-fill {
  height: 100%;
  background: linear-gradient(90deg, #6366f1 0%, #4f6ef7 100%);
  border-radius: 999px;
}

.table-wrapper {
  overflow-x: auto;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.9rem;
}

.data-table th,
.data-table td {
  padding: 0.85rem 1rem;
  border-bottom: 1px solid #e2e8f0;
  text-align: left;
  vertical-align: top;
}

.data-table th {
  color: #4a5568;
  background-color: #f8fafc;
  font-size: 0.8rem;
  font-weight: 600;
}

.data-table td {
  color: #1a1a2e;
}

.col-number {
  text-align: right;
  font-variant-numeric: tabular-nums;
}

@media (max-width: 900px) {
  .analytics-grid {
    grid-template-columns: 1fr;
  }

  .analytics-grid .panel {
    margin-bottom: 0;
  }

  .analytics-grid .panel:last-child {
    margin-bottom: 1.5rem;
  }
}

@media (max-width: 768px) {
  .admin-statistics-page {
    padding: 1.25rem 1rem;
  }

  .page-header,
  .panel {
    padding: 1.25rem;
  }

  .page-header-content {
    flex-direction: column;
    align-items: stretch;
  }

  .period-badge {
    align-self: flex-start;
  }

  .summary-value {
    font-size: 1.5rem;
  }

  .chart-bars {
    min-width: 560px;
  }
}
</style>
