<script setup>
import { computed, onMounted, ref } from 'vue'
import { getMyStatistics } from '../services/postService'

const statistics = ref(null)
const isLoading = ref(true)
const errorMessage = ref('')

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

const summary = computed(() => ({
  total_views: statistics.value?.summary?.total_views ?? 0,
  average_views: statistics.value?.summary?.average_views ?? 0,
  published_posts_count: statistics.value?.summary?.published_posts_count ?? 0,
  categories_count: statistics.value?.summary?.categories_count ?? 0,
}))

const dailyViews = computed(
  () => statistics.value?.chart?.daily_views ?? [],
)

const performance = computed(() => ({
  most_viewed_post: statistics.value?.performance?.most_viewed_post ?? null,
  least_viewed_post: statistics.value?.performance?.least_viewed_post ?? null,
  latest_published_post:
    statistics.value?.performance?.latest_published_post ?? null,
  most_used_category: statistics.value?.performance?.most_used_category ?? null,
}))

const posts = computed(() => statistics.value?.posts ?? [])

const maxDailyViews = computed(() => {
  if (dailyViews.value.length === 0) {
    return 0
  }

  return Math.max(...dailyViews.value.map((day) => day.views ?? 0))
})

const hasChartData = computed(() => maxDailyViews.value > 0)

const metrics = computed(() => [
  { key: 'total_views', label: 'Toplam Görüntülenme' },
  { key: 'average_views', label: 'Ortalama Görüntülenme' },
  { key: 'published_posts_count', label: 'Yayındaki Yazı' },
  { key: 'categories_count', label: 'Kullanılan Kategori' },
])

const getBarHeight = (views) => {
  if (!hasChartData.value || maxDailyViews.value === 0) {
    return '0%'
  }

  const heightPercent = (views / maxDailyViews.value) * 100

  return `${Math.max(heightPercent, views > 0 ? 4 : 0)}%`
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
    const response = await getMyStatistics()
    statistics.value = response.data.statistics ?? null
  } catch (error) {
    errorMessage.value =
      error.response?.data?.message ||
      'İstatistikler yüklenirken bir hata oluştu.'
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  loadStatistics()
})
</script>

<template>
  <div class="statistics-page">
    <div class="statistics-container">
      <header class="page-header">
        <div class="page-header-content">
          <div>
            <h1>İstatistikler</h1>
            <p>Yazılarınızın performansını buradan takip edebilirsiniz.</p>
          </div>

          <span class="period-selector">Son 30 Gün</span>
        </div>
      </header>

      <div
        v-if="isLoading"
        class="state-card loading-state"
      >
        İstatistikler yükleniyor...
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
        <section class="metrics-row">
          <article
            v-for="metric in metrics"
            :key="metric.key"
            class="metric-item"
          >
            <span class="metric-label">{{ metric.label }}</span>
            <strong class="metric-value">{{ summary[metric.key] ?? 0 }}</strong>
          </article>
        </section>

        <section class="panel chart-panel">
          <div class="panel-header">
            <h2>Günlük Görüntülenmeler</h2>
          </div>

          <p
            v-if="!hasChartData"
            class="empty-message chart-empty-message"
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
            <h2>Performans</h2>
          </div>

          <div class="performance-grid">
            <article class="performance-item">
              <span class="performance-label">En Çok Görüntülenen Yazı</span>
              <template v-if="performance.most_viewed_post">
                <strong class="performance-title">
                  {{ performance.most_viewed_post.title }}
                </strong>
                <span class="performance-meta">
                  {{ performance.most_viewed_post.views_count ?? 0 }} görüntülenme
                </span>
              </template>
              <span
                v-else
                class="performance-empty"
              >
                Henüz veri yok
              </span>
            </article>

            <article class="performance-item">
              <span class="performance-label">En Az Görüntülenen Yazı</span>
              <template v-if="performance.least_viewed_post">
                <strong class="performance-title">
                  {{ performance.least_viewed_post.title }}
                </strong>
                <span class="performance-meta">
                  {{ performance.least_viewed_post.views_count ?? 0 }} görüntülenme
                </span>
              </template>
              <span
                v-else
                class="performance-empty"
              >
                Henüz veri yok
              </span>
            </article>

            <article class="performance-item">
              <span class="performance-label">Son Yayınlanan Yazı</span>
              <template v-if="performance.latest_published_post">
                <strong class="performance-title">
                  {{ performance.latest_published_post.title }}
                </strong>
                <span class="performance-meta">
                  {{ formatDate(performance.latest_published_post.published_at) }}
                </span>
              </template>
              <span
                v-else
                class="performance-empty"
              >
                Henüz veri yok
              </span>
            </article>

            <article class="performance-item">
              <span class="performance-label">En Çok Yazı Yazılan Kategori</span>
              <template v-if="performance.most_used_category">
                <strong class="performance-title">
                  {{ performance.most_used_category.name }}
                </strong>
                <span class="performance-meta">
                  {{ performance.most_used_category.posts_count ?? 0 }} yazı
                </span>
              </template>
              <span
                v-else
                class="performance-empty"
              >
                Henüz veri yok
              </span>
            </article>
          </div>
        </section>

        <section class="panel">
          <div class="panel-header">
            <h2>Yazı Performansı</h2>
          </div>

          <p
            v-if="posts.length === 0"
            class="empty-message"
          >
            Henüz yayınlanmış yazınız bulunmuyor.
          </p>

          <div
            v-else
            class="table-wrapper"
          >
            <table class="posts-table">
              <thead>
                <tr>
                  <th>Yazı</th>
                  <th>Kategori</th>
                  <th class="col-views">Görüntülenme</th>
                  <th>Yayın Tarihi</th>
                </tr>
              </thead>

              <tbody>
                <tr
                  v-for="post in posts"
                  :key="post.id"
                >
                  <td data-label="Yazı">{{ post.title }}</td>
                  <td data-label="Kategori">
                    {{ post.category?.name ?? 'Kategorisiz' }}
                  </td>
                  <td
                    class="col-views"
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
      </template>
    </div>
  </div>
</template>

<style scoped>
.statistics-page {
  padding: 2rem;
  box-sizing: border-box;
}

.statistics-container {
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

.period-selector {
  flex-shrink: 0;
  padding: 0.5rem 0.9rem;
  color: #64748b;
  background-color: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 999px;
  font-size: 0.8125rem;
  font-weight: 500;
  white-space: nowrap;
  cursor: default;
  user-select: none;
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

.metrics-row {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 0;
  margin-bottom: 1.5rem;
  background-color: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  overflow: hidden;
}

.metric-item {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  padding: 1.5rem 1.25rem;
  border-right: 1px solid #e2e8f0;
}

.metric-item:last-child {
  border-right: none;
}

.metric-label {
  color: #718096;
  font-size: 0.8125rem;
  font-weight: 500;
}

.metric-value {
  color: #1a1a2e;
  font-size: 1.75rem;
  font-weight: 700;
  line-height: 1.2;
  letter-spacing: -0.02em;
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

.chart-panel {
  padding-bottom: 1.25rem;
}

.chart-panel .panel-header {
  margin-bottom: 0.85rem;
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

.chart-empty-message {
  padding: 0.25rem 0 0.5rem;
}

.chart-wrapper {
  overflow-x: auto;
  padding: 0 1.25rem 0.15rem;
}

.chart-bars {
  display: flex;
  align-items: flex-end;
  gap: 0.3rem;
  min-width: 100%;
  padding: 0 0.15rem;
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
  transition: height 0.2s ease;
}

.chart-label {
  min-height: 1rem;
  color: #94a3b8;
  font-size: 0.6875rem;
  line-height: 1.2;
  text-align: center;
  white-space: nowrap;
}

.performance-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.75rem;
}

.performance-item {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  padding: 0.85rem 1rem;
  background-color: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
}

.performance-label {
  color: #718096;
  font-size: 0.8125rem;
  font-weight: 500;
}

.performance-title {
  color: #1a1a2e;
  font-size: 0.95rem;
  font-weight: 600;
  line-height: 1.45;
}

.performance-meta {
  color: #64748b;
  font-size: 0.8125rem;
}

.performance-empty {
  color: #94a3b8;
  font-size: 0.875rem;
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

.posts-table .col-views {
  text-align: right;
  font-variant-numeric: tabular-nums;
}

@media (max-width: 900px) {
  .metrics-row {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .metric-item {
    border-right: none;
    border-bottom: 1px solid #e2e8f0;
  }

  .metric-item:nth-child(odd) {
    border-right: 1px solid #e2e8f0;
  }

  .metric-item:nth-last-child(-n + 2) {
    border-bottom: none;
  }
}

@media (max-width: 768px) {
  .statistics-page {
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

  .period-selector {
    align-self: flex-start;
  }

  .metrics-row {
    grid-template-columns: 1fr;
  }

  .metric-item,
  .metric-item:nth-child(odd) {
    border-right: none;
    border-bottom: 1px solid #e2e8f0;
  }

  .metric-item:last-child {
    border-bottom: none;
  }

  .metric-value {
    font-size: 1.5rem;
  }

  .performance-grid {
    grid-template-columns: 1fr;
  }

  .chart-bars {
    min-width: 560px;
  }

  .chart-wrapper {
    padding: 0 0.75rem 0.15rem;
  }
}
</style>
