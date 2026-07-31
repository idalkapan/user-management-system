<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { getMyStatistics } from '../services/postService'

const statistics = ref(null)
const selectedPeriod = ref('30d')
const isInitialLoading = ref(true)
const isRefreshing = ref(false)
const errorMessage = ref('')

const periodOptions = [
  { value: '7d', label: '7 Gün' },
  { value: '30d', label: '30 Gün' },
]

const summary = computed(() => ({
  published_posts_count:
    statistics.value?.summary?.published_posts_count ?? 0,
  total_views: statistics.value?.summary?.total_views ?? 0,
  total_likes: statistics.value?.summary?.total_likes ?? 0,
  total_comments: statistics.value?.summary?.total_comments ?? 0,
  total_engagement: statistics.value?.summary?.total_engagement ?? 0,
  average_views: statistics.value?.summary?.average_views ?? 0,
  engagement_rate: statistics.value?.summary?.engagement_rate ?? 0,
}))

const dailyChart = computed(() => statistics.value?.chart?.daily ?? [])

const topPosts = computed(() => statistics.value?.top_posts ?? [])

const categoryPerformance = computed(
  () => statistics.value?.category_performance ?? [],
)

const hasPublishedPosts = computed(
  () => summary.value.published_posts_count > 0,
)

const metrics = computed(() => [
  { key: 'total_views', label: 'Toplam Görüntülenme', format: 'number' },
  { key: 'total_likes', label: 'Toplam Beğeni', format: 'number' },
  { key: 'total_comments', label: 'Toplam Yorum', format: 'number' },
  { key: 'total_engagement', label: 'Toplam Etkileşim', format: 'number' },
  { key: 'average_views', label: 'Ortalama Görüntülenme', format: 'number' },
  { key: 'engagement_rate', label: 'Etkileşim Oranı', format: 'percent' },
])

const chartLegend = [
  { key: 'views', label: 'Görüntülenme', className: 'chart-bar--views' },
  { key: 'likes', label: 'Beğeni', className: 'chart-bar--likes' },
  { key: 'comments', label: 'Yorum', className: 'chart-bar--comments' },
]

const maxChartValue = computed(() => {
  if (dailyChart.value.length === 0) {
    return 0
  }

  const values = dailyChart.value.flatMap((day) => [
    day.views ?? 0,
    day.likes ?? 0,
    day.comments ?? 0,
  ])

  return Math.max(...values, 0)
})

const hasChartActivity = computed(() => maxChartValue.value > 0)

const formatNumber = (value) => {
  const numericValue = Number(value ?? 0)

  if (Number.isNaN(numericValue)) {
    return '0'
  }

  return new Intl.NumberFormat('tr-TR').format(numericValue)
}

const formatPercent = (value) => {
  const numericValue = Number(value ?? 0)

  if (Number.isNaN(numericValue)) {
    return '0%'
  }

  return `${new Intl.NumberFormat('tr-TR', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
  }).format(numericValue)}%`
}

const formatMetricValue = (key, value) => {
  if (key === 'engagement_rate') {
    return formatPercent(value)
  }

  return formatNumber(value)
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

const truncateText = (text, maxLength = 56) => {
  const normalized = String(text ?? '')

  if (normalized.length <= maxLength) {
    return normalized
  }

  return `${normalized.slice(0, maxLength - 1)}…`
}

const getBarHeight = (value) => {
  const numericValue = Number(value ?? 0)

  if (!hasChartActivity.value || maxChartValue.value === 0 || numericValue <= 0) {
    return '0%'
  }

  const heightPercent = (numericValue / maxChartValue.value) * 100

  return `${Math.max(heightPercent, 4)}%`
}

const shouldShowChartLabel = (index) => {
  const total = dailyChart.value.length

  if (total === 0) {
    return false
  }

  if (total <= 7) {
    return true
  }

  if (index === 0 || index === total - 1) {
    return true
  }

  return index % 5 === 0
}

const getChartTooltip = (day) =>
  `${formatShortDate(day.date)} — Görüntülenme: ${formatNumber(day.views)}, Beğeni: ${formatNumber(day.likes)}, Yorum: ${formatNumber(day.comments)}`

const loadStatistics = async () => {
  if (isInitialLoading.value) {
    errorMessage.value = ''
  } else {
    isRefreshing.value = true
  }

  try {
    const response = await getMyStatistics(selectedPeriod.value)
    statistics.value = response.data.statistics ?? null
    errorMessage.value = ''
  } catch (error) {
    errorMessage.value =
      error.response?.data?.message ||
      'İstatistikler yüklenirken bir hata oluştu.'
  } finally {
    isInitialLoading.value = false
    isRefreshing.value = false
  }
}

const selectPeriod = (period) => {
  if (selectedPeriod.value === period || isRefreshing.value) {
    return
  }

  selectedPeriod.value = period
}

watch(selectedPeriod, () => {
  if (!isInitialLoading.value) {
    loadStatistics()
  }
})

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
            <h1>İstatistiklerim</h1>

            <p
              v-if="hasPublishedPosts"
              class="page-description"
            >
              {{ formatNumber(summary.published_posts_count) }} yayınlanan
              içeriğinizin performansını buradan takip edebilirsiniz.
            </p>

            <p
              v-else
              class="page-description"
            >
              Yayınlanmış içeriklerinizin performansını buradan takip
              edebilirsiniz.
            </p>
          </div>

          <div
            class="period-selector"
            role="group"
            aria-label="İstatistik dönemi"
          >
            <button
              v-for="option in periodOptions"
              :key="option.value"
              type="button"
              class="period-button"
              :class="{ 'period-button--active': selectedPeriod === option.value }"
              :aria-pressed="selectedPeriod === option.value"
              :disabled="isInitialLoading || isRefreshing"
              @click="selectPeriod(option.value)"
            >
              {{ option.label }}
            </button>
          </div>
        </div>
      </header>

      <div
        v-if="isInitialLoading"
        class="state-card loading-state"
        role="status"
        aria-live="polite"
      >
        İstatistikler yükleniyor...
      </div>

      <div
        v-else-if="errorMessage && !statistics"
        class="state-card error-state"
        role="alert"
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
        <div
          v-if="isRefreshing"
          class="refresh-banner"
          role="status"
          aria-live="polite"
        >
          Dönem verileri güncelleniyor...
        </div>

        <div
          v-if="errorMessage"
          class="inline-error"
          role="alert"
        >
          {{ errorMessage }}
        </div>

        <div
          v-if="!hasPublishedPosts"
          class="state-card empty-state"
        >
          <h2>Henüz yayınlanmış yazınız yok</h2>
          <p>
            İstatistikler yalnızca yayınlanmış içerikleriniz için
            gösterilir. Yazılarınız yayımlandığında performans verileri
            burada görünecek.
          </p>
        </div>

        <template v-else>
          <section
            class="metrics-row"
            :class="{ 'metrics-row--refreshing': isRefreshing }"
            aria-label="Performans özeti"
          >
            <article
              v-for="metric in metrics"
              :key="metric.key"
              class="metric-item"
            >
              <span class="metric-label">{{ metric.label }}</span>
              <strong class="metric-value">
                {{ formatMetricValue(metric.key, summary[metric.key]) }}
              </strong>
            </article>
          </section>

          <section class="panel chart-panel">
            <div class="panel-header chart-panel-header">
              <h2>Performans Grafiği</h2>

              <ul
                class="chart-legend"
                aria-label="Grafik göstergeleri"
              >
                <li
                  v-for="item in chartLegend"
                  :key="item.key"
                  class="chart-legend-item"
                >
                  <span
                    class="chart-legend-swatch"
                    :class="item.className"
                    aria-hidden="true"
                  />
                  <span>{{ item.label }}</span>
                </li>
              </ul>
            </div>

            <p
              v-if="!hasChartActivity"
              class="empty-message chart-empty-message"
            >
              Seçilen dönemde görüntülenme, beğeni veya yorum verisi
              bulunmuyor.
            </p>

            <div
              v-else
              class="chart-wrapper"
            >
              <div
                class="chart-bars"
                role="img"
                :aria-label="`${selectedPeriod === '7d' ? 'Son 7 gün' : 'Son 30 gün'} performans grafiği`"
              >
                <div
                  v-for="(day, index) in dailyChart"
                  :key="day.date"
                  class="chart-bar-column"
                >
                  <div
                    class="chart-bar-stack"
                    :title="getChartTooltip(day)"
                  >
                    <div
                      class="chart-bar-reference"
                      aria-hidden="true"
                    />

                    <div
                      class="chart-bar chart-bar--views"
                      :style="{ height: getBarHeight(day.views) }"
                      aria-hidden="true"
                    />

                    <div
                      class="chart-bar chart-bar--likes"
                      :style="{ height: getBarHeight(day.likes) }"
                      aria-hidden="true"
                    />

                    <div
                      class="chart-bar chart-bar--comments"
                      :style="{ height: getBarHeight(day.comments) }"
                      aria-hidden="true"
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
              <h2>En İyi Yazılar</h2>
            </div>

            <p
              v-if="topPosts.length === 0"
              class="empty-message"
            >
              Henüz sıralanacak yayınlanmış yazı bulunmuyor.
            </p>

            <div
              v-else
              class="table-wrapper"
            >
              <table class="data-table">
                <thead>
                  <tr>
                    <th scope="col">Yazı</th>
                    <th scope="col">Kategori</th>
                    <th
                      scope="col"
                      class="col-number"
                    >
                      Görüntülenme
                    </th>
                    <th
                      scope="col"
                      class="col-number"
                    >
                      Beğeni
                    </th>
                    <th
                      scope="col"
                      class="col-number"
                    >
                      Yorum
                    </th>
                    <th
                      scope="col"
                      class="col-number"
                    >
                      Toplam Etkileşim
                    </th>
                    <th
                      scope="col"
                      class="col-number"
                    >
                      Etkileşim Oranı
                    </th>
                  </tr>
                </thead>

                <tbody>
                  <tr
                    v-for="post in topPosts"
                    :key="post.id"
                  >
                    <td
                      data-label="Yazı"
                      :title="post.title"
                    >
                      {{ truncateText(post.title) }}
                    </td>
                    <td
                      data-label="Kategori"
                      :title="post.category?.name ?? 'Kategorisiz'"
                    >
                      {{ truncateText(post.category?.name ?? 'Kategorisiz', 28) }}
                    </td>
                    <td
                      class="col-number"
                      data-label="Görüntülenme"
                    >
                      {{ formatNumber(post.views_count) }}
                    </td>
                    <td
                      class="col-number"
                      data-label="Beğeni"
                    >
                      {{ formatNumber(post.likes_count) }}
                    </td>
                    <td
                      class="col-number"
                      data-label="Yorum"
                    >
                      {{ formatNumber(post.comments_count) }}
                    </td>
                    <td
                      class="col-number"
                      data-label="Toplam Etkileşim"
                    >
                      {{ formatNumber(post.engagement_count) }}
                    </td>
                    <td
                      class="col-number"
                      data-label="Etkileşim Oranı"
                    >
                      {{ formatPercent(post.engagement_rate) }}
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
              Kategori bazlı performans verisi bulunmuyor.
            </p>

            <div
              v-else
              class="table-wrapper"
            >
              <table class="data-table">
                <thead>
                  <tr>
                    <th scope="col">Kategori</th>
                    <th
                      scope="col"
                      class="col-number"
                    >
                      Yazı
                    </th>
                    <th
                      scope="col"
                      class="col-number"
                    >
                      Görüntülenme
                    </th>
                    <th
                      scope="col"
                      class="col-number"
                    >
                      Beğeni
                    </th>
                    <th
                      scope="col"
                      class="col-number"
                    >
                      Yorum
                    </th>
                    <th
                      scope="col"
                      class="col-number"
                    >
                      Etkileşim Oranı
                    </th>
                  </tr>
                </thead>

                <tbody>
                  <tr
                    v-for="category in categoryPerformance"
                    :key="category.category_id ?? category.category_name"
                  >
                    <td
                      data-label="Kategori"
                      :title="category.category_name"
                    >
                      {{ truncateText(category.category_name, 32) }}
                    </td>
                    <td
                      class="col-number"
                      data-label="Yazı"
                    >
                      {{ formatNumber(category.posts_count) }}
                    </td>
                    <td
                      class="col-number"
                      data-label="Görüntülenme"
                    >
                      {{ formatNumber(category.views_count) }}
                    </td>
                    <td
                      class="col-number"
                      data-label="Beğeni"
                    >
                      {{ formatNumber(category.likes_count) }}
                    </td>
                    <td
                      class="col-number"
                      data-label="Yorum"
                    >
                      {{ formatNumber(category.comments_count) }}
                    </td>
                    <td
                      class="col-number"
                      data-label="Etkileşim Oranı"
                    >
                      {{ formatPercent(category.engagement_rate) }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </section>
        </template>
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

.page-description {
  margin: 0;
  color: #718096;
  font-size: 0.95rem;
  line-height: 1.6;
}

.period-selector {
  display: inline-flex;
  gap: 0.35rem;
  padding: 0.25rem;
  background-color: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 999px;
}

.period-button {
  padding: 0.45rem 0.85rem;
  color: #64748b;
  background-color: transparent;
  border: none;
  border-radius: 999px;
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
  transition:
    background-color 0.15s ease,
    color 0.15s ease;
}

.period-button:hover:not(:disabled) {
  color: #334155;
  background-color: #eef2ff;
}

.period-button:focus-visible {
  outline: 2px solid #4f6ef7;
  outline-offset: 2px;
}

.period-button--active {
  color: #4338ca;
  background-color: #ffffff;
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
}

.period-button:disabled {
  opacity: 0.55;
  cursor: not-allowed;
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

.inline-error {
  margin-bottom: 1rem;
  padding: 0.85rem 1rem;
  color: #991b1b;
  background-color: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: 10px;
  font-size: 0.875rem;
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

.empty-state h2 {
  margin: 0 0 0.65rem;
  color: #1a1a2e;
  font-size: 1.125rem;
}

.empty-state p {
  max-width: 520px;
  margin: 0 auto;
  line-height: 1.6;
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
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 0;
  margin-bottom: 1.5rem;
  background-color: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  overflow: hidden;
  transition: opacity 0.15s ease;
}

.metrics-row--refreshing {
  opacity: 0.72;
}

.metric-item {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  padding: 1.35rem 1.15rem;
  border-right: 1px solid #e2e8f0;
  border-bottom: 1px solid #e2e8f0;
}

.metric-item:nth-child(3n) {
  border-right: none;
}

.metric-item:nth-last-child(-n + 3) {
  border-bottom: none;
}

.metric-label {
  color: #718096;
  font-size: 0.8125rem;
  font-weight: 500;
}

.metric-value {
  color: #1a1a2e;
  font-size: 1.55rem;
  font-weight: 700;
  line-height: 1.2;
  letter-spacing: -0.02em;
  font-variant-numeric: tabular-nums;
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

.chart-panel-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
}

.panel-header h2 {
  margin: 0;
  color: #1a1a2e;
  font-size: 1.15rem;
  font-weight: 700;
}

.chart-legend {
  display: flex;
  flex-wrap: wrap;
  gap: 0.65rem 1rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.chart-legend-item {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  color: #64748b;
  font-size: 0.8125rem;
  font-weight: 500;
}

.chart-legend-swatch {
  width: 10px;
  height: 10px;
  border-radius: 2px;
}

.chart-legend-swatch.chart-bar--views {
  background: linear-gradient(180deg, #6366f1 0%, #4f6ef7 100%);
}

.chart-legend-swatch.chart-bar--likes {
  background: linear-gradient(180deg, #fb7185 0%, #e11d48 100%);
}

.chart-legend-swatch.chart-bar--comments {
  background: linear-gradient(180deg, #34d399 0%, #059669 100%);
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
  padding: 0 0.25rem 0.15rem;
}

.chart-bars {
  display: flex;
  align-items: flex-end;
  gap: 0.35rem;
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
  gap: 2px;
  width: 100%;
  max-width: 28px;
  height: 168px;
  margin-bottom: 0.45rem;
}

.chart-bar-reference {
  position: absolute;
  bottom: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background-color: #f8fafc;
  border-radius: 4px 4px 0 0;
}

.chart-bar {
  position: relative;
  z-index: 1;
  width: 7px;
  border-radius: 3px 3px 0 0;
  transition: height 0.2s ease;
}

.chart-bar--views {
  background: linear-gradient(180deg, #6366f1 0%, #4f6ef7 100%);
}

.chart-bar--likes {
  background: linear-gradient(180deg, #fb7185 0%, #e11d48 100%);
}

.chart-bar--comments {
  background: linear-gradient(180deg, #34d399 0%, #059669 100%);
}

.chart-label {
  min-height: 1rem;
  color: #94a3b8;
  font-size: 0.6875rem;
  line-height: 1.2;
  text-align: center;
  white-space: nowrap;
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

.data-table .col-number {
  text-align: right;
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}

@media (max-width: 900px) {
  .metrics-row {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .metric-item {
    border-right: none;
  }

  .metric-item:nth-child(odd) {
    border-right: 1px solid #e2e8f0;
  }

  .metric-item:nth-child(3n) {
    border-right: none;
  }

  .metric-item:nth-last-child(-n + 3) {
    border-bottom: 1px solid #e2e8f0;
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

  .page-header-content,
  .chart-panel-header {
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
    font-size: 1.35rem;
  }

  .chart-bars {
    min-width: 520px;
  }

  .data-table thead {
    display: none;
  }

  .data-table tr {
    display: block;
    margin-bottom: 0.85rem;
    padding: 0.85rem;
    background-color: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
  }

  .data-table td {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.45rem 0;
    border-bottom: 1px solid #e2e8f0;
  }

  .data-table td:last-child {
    border-bottom: none;
  }

  .data-table td::before {
    content: attr(data-label);
    color: #64748b;
    font-size: 0.75rem;
    font-weight: 600;
    flex-shrink: 0;
  }

  .data-table .col-number {
    text-align: right;
  }
}
</style>
