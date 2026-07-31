<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { getAdminStatistics } from '../services/postService'

const statistics = ref(null)
const selectedPeriod = ref('30d')
const isInitialLoading = ref(true)
const isRefreshing = ref(false)
const errorMessage = ref('')

const periodOptions = [
  { value: '7d', label: 'Son 7 Gün' },
  { value: '30d', label: 'Son 30 Gün' },
]

const periodLabelPrefix = computed(() =>
  selectedPeriod.value === '7d' ? 'Son 7 Gün' : 'Son 30 Gün',
)

const summary = computed(() => ({
  period_views: statistics.value?.summary?.period_views ?? 0,
  period_new_users: statistics.value?.summary?.period_new_users ?? 0,
  period_published_posts:
    statistics.value?.summary?.period_published_posts ?? 0,
  period_active_authors:
    statistics.value?.summary?.period_active_authors ?? 0,
  total_likes: statistics.value?.summary?.total_likes ?? 0,
  total_comments: statistics.value?.summary?.total_comments ?? 0,
  total_engagement: statistics.value?.summary?.total_engagement ?? 0,
  average_engagement_rate:
    statistics.value?.summary?.average_engagement_rate ?? 0,
}))

const kpiCards = computed(() => [
  {
    key: 'period_views',
    label: `${periodLabelPrefix.value} Görüntülenme`,
    format: 'number',
  },
  {
    key: 'period_new_users',
    label: `${periodLabelPrefix.value} Yeni Kullanıcı`,
    format: 'number',
  },
  {
    key: 'period_published_posts',
    label: `${periodLabelPrefix.value} Yayınlanan Yazı`,
    format: 'number',
  },
  {
    key: 'period_active_authors',
    label: `${periodLabelPrefix.value} Aktif Yazar`,
    format: 'number',
  },
  {
    key: 'total_engagement',
    label: 'Toplam Etkileşim',
    format: 'number',
  },
  {
    key: 'average_engagement_rate',
    label: 'Ortalama Etkileşim Oranı',
    format: 'percent',
  },
  {
    key: 'total_likes',
    label: 'Toplam Beğeni',
    format: 'number',
  },
  {
    key: 'total_comments',
    label: 'Toplam Yorum',
    format: 'number',
  },
])

const engagementDaily = computed(
  () => statistics.value?.engagement_chart?.daily ?? [],
)

const growthDaily = computed(
  () => statistics.value?.growth_chart?.daily ?? [],
)

const statusItems = computed(() => {
  const distribution = statistics.value?.status_distribution ?? {}

  return [
    { key: 'published', label: 'Yayınlanan', count: distribution.published ?? 0 },
    { key: 'pending', label: 'Onay Bekleyen', count: distribution.pending ?? 0 },
    { key: 'rejected', label: 'Reddedilen', count: distribution.rejected ?? 0 },
    { key: 'draft', label: 'Taslak', count: distribution.draft ?? 0 },
  ]
})

const topAuthors = computed(
  () => statistics.value?.top_authors ?? [],
)

const topPosts = computed(
  () => statistics.value?.top_posts ?? [],
)

const categoryPerformance = computed(
  () => statistics.value?.category_performance ?? [],
)

const engagementLegend = [
  { key: 'views', label: 'Görüntülenme', className: 'chart-bar--views' },
  { key: 'likes', label: 'Beğeni', className: 'chart-bar--likes' },
  { key: 'comments', label: 'Yorum', className: 'chart-bar--comments' },
]

const growthLegend = [
  { key: 'new_users', label: 'Yeni Kullanıcı', className: 'chart-bar--users' },
  { key: 'new_posts', label: 'Yeni Yazı', className: 'chart-bar--posts' },
]

const maxEngagementValue = computed(() => {
  if (engagementDaily.value.length === 0) {
    return 0
  }

  const values = engagementDaily.value.flatMap((day) => [
    day.views ?? 0,
    day.likes ?? 0,
    day.comments ?? 0,
  ])

  return Math.max(...values, 0)
})

const maxGrowthValue = computed(() => {
  if (growthDaily.value.length === 0) {
    return 0
  }

  const values = growthDaily.value.flatMap((day) => [
    day.new_users ?? 0,
    day.new_posts ?? 0,
  ])

  return Math.max(...values, 0)
})

const hasEngagementActivity = computed(() => maxEngagementValue.value > 0)

const hasGrowthActivity = computed(() => maxGrowthValue.value > 0)

const totalStatusCount = computed(() =>
  statusItems.value.reduce((total, item) => total + (item.count ?? 0), 0),
)

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
  const card = kpiCards.value.find((item) => item.key === key)

  if (card?.format === 'percent') {
    return formatPercent(value)
  }

  return formatNumber(value)
}

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

const truncateText = (text, maxLength = 56) => {
  const normalized = String(text ?? '')

  if (normalized.length <= maxLength) {
    return normalized
  }

  return `${normalized.slice(0, maxLength - 1)}…`
}

const getBarHeight = (value, maxValue, hasActivity) => {
  const numericValue = Number(value ?? 0)

  if (!hasActivity || maxValue === 0 || numericValue <= 0) {
    return '0%'
  }

  const heightPercent = (numericValue / maxValue) * 100

  return `${Math.max(heightPercent, 4)}%`
}

const shouldShowChartLabel = (index, totalDays) => {
  if (totalDays === 0) {
    return false
  }

  if (totalDays <= 7) {
    return true
  }

  if (index === 0 || index === totalDays - 1) {
    return true
  }

  return index % 5 === 0
}

const getEngagementTooltip = (day) =>
  `${formatShortDate(day.date)} — Görüntülenme: ${formatNumber(day.views)}, Beğeni: ${formatNumber(day.likes)}, Yorum: ${formatNumber(day.comments)}`

const getGrowthTooltip = (day) =>
  `${formatShortDate(day.date)} — Yeni Kullanıcı: ${formatNumber(day.new_users)}, Yeni Yazı: ${formatNumber(day.new_posts)}`

const getStatusBarWidth = (count) => {
  if (totalStatusCount.value === 0) {
    return '0%'
  }

  return `${(count / totalStatusCount.value) * 100}%`
}

const loadStatistics = async () => {
  if (isInitialLoading.value) {
    errorMessage.value = ''
  } else {
    isRefreshing.value = true
  }

  try {
    const response = await getAdminStatistics(selectedPeriod.value)
    statistics.value = response.data.statistics ?? null
    errorMessage.value = ''
  } catch (error) {
    errorMessage.value =
      error.response?.data?.message ||
      'Sistem istatistikleri yüklenirken bir hata oluştu.'
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
  <div class="admin-statistics-page">
    <div class="admin-statistics-container">
      <header class="page-header">
        <div class="page-header-content">
          <div>
            <h1>Sistem Analitiği</h1>
            <p>
              Platform performansı, büyüme ve içerik etkileşimlerini inceleyin.
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
        Sistem istatistikleri yükleniyor...
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

        <section
          class="kpi-grid"
          :class="{ 'kpi-grid--refreshing': isRefreshing }"
          aria-label="Platform performans özeti"
        >
          <article
            v-for="card in kpiCards"
            :key="card.key"
            class="kpi-card"
          >
            <span class="kpi-label">{{ card.label }}</span>
            <strong class="kpi-value">
              {{ formatMetricValue(card.key, summary[card.key]) }}
            </strong>
          </article>
        </section>

        <div class="charts-grid">
          <section class="panel chart-panel">
            <div class="panel-header chart-panel-header">
              <h2>Platform Etkileşimi</h2>

              <ul
                class="chart-legend"
                aria-label="Platform etkileşim göstergeleri"
              >
                <li
                  v-for="item in engagementLegend"
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
              v-if="!hasEngagementActivity"
              class="empty-message"
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
                :aria-label="`${selectedPeriod === '7d' ? 'Son 7 gün' : 'Son 30 gün'} platform etkileşim grafiği`"
              >
                <div
                  v-for="(day, index) in engagementDaily"
                  :key="day.date"
                  class="chart-bar-column"
                >
                  <div
                    class="chart-bar-stack"
                    :title="getEngagementTooltip(day)"
                  >
                    <div
                      class="chart-bar-reference"
                      aria-hidden="true"
                    />

                    <div
                      class="chart-bar chart-bar--views"
                      :style="{
                        height: getBarHeight(
                          day.views,
                          maxEngagementValue,
                          hasEngagementActivity,
                        ),
                      }"
                      aria-hidden="true"
                    />

                    <div
                      class="chart-bar chart-bar--likes"
                      :style="{
                        height: getBarHeight(
                          day.likes,
                          maxEngagementValue,
                          hasEngagementActivity,
                        ),
                      }"
                      aria-hidden="true"
                    />

                    <div
                      class="chart-bar chart-bar--comments"
                      :style="{
                        height: getBarHeight(
                          day.comments,
                          maxEngagementValue,
                          hasEngagementActivity,
                        ),
                      }"
                      aria-hidden="true"
                    />
                  </div>

                  <span
                    v-if="shouldShowChartLabel(index, engagementDaily.length)"
                    class="chart-label"
                  >
                    {{ formatShortDate(day.date) }}
                  </span>
                </div>
              </div>
            </div>
          </section>

          <section class="panel chart-panel">
            <div class="panel-header chart-panel-header">
              <h2>Platform Büyümesi</h2>

              <ul
                class="chart-legend"
                aria-label="Platform büyüme göstergeleri"
              >
                <li
                  v-for="item in growthLegend"
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
              v-if="!hasGrowthActivity"
              class="empty-message"
            >
              Seçilen dönemde yeni kullanıcı veya yazı verisi bulunmuyor.
            </p>

            <div
              v-else
              class="chart-wrapper"
            >
              <div
                class="chart-bars"
                role="img"
                :aria-label="`${selectedPeriod === '7d' ? 'Son 7 gün' : 'Son 30 gün'} platform büyüme grafiği`"
              >
                <div
                  v-for="(day, index) in growthDaily"
                  :key="day.date"
                  class="chart-bar-column"
                >
                  <div
                    class="chart-bar-stack chart-bar-stack--growth"
                    :title="getGrowthTooltip(day)"
                  >
                    <div
                      class="chart-bar-reference"
                      aria-hidden="true"
                    />

                    <div
                      class="chart-bar chart-bar--users"
                      :style="{
                        height: getBarHeight(
                          day.new_users,
                          maxGrowthValue,
                          hasGrowthActivity,
                        ),
                      }"
                      aria-hidden="true"
                    />

                    <div
                      class="chart-bar chart-bar--posts"
                      :style="{
                        height: getBarHeight(
                          day.new_posts,
                          maxGrowthValue,
                          hasGrowthActivity,
                        ),
                      }"
                      aria-hidden="true"
                    />
                  </div>

                  <span
                    v-if="shouldShowChartLabel(index, growthDaily.length)"
                    class="chart-label"
                  >
                    {{ formatShortDate(day.date) }}
                  </span>
                </div>
              </div>
            </div>
          </section>
        </div>

        <section class="panel status-panel">
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
              v-for="item in statusItems"
              :key="item.key"
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
                  <th scope="col">Yazar</th>
                  <th
                    scope="col"
                    class="col-number"
                  >
                    Yayınlanan Yazı
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
                    Toplam Etkileşim
                  </th>
                  <th
                    scope="col"
                    class="col-number"
                  >
                    Etkileşim Oranı
                  </th>
                  <th
                    scope="col"
                    class="col-number"
                  >
                    Yazı Başına Ort. Etkileşim
                  </th>
                </tr>
              </thead>

              <tbody>
                <tr
                  v-for="author in topAuthors"
                  :key="author.id"
                >
                  <td
                    data-label="Yazar"
                    :title="author.name"
                  >
                    {{ truncateText(author.name, 32) }}
                  </td>
                  <td
                    class="col-number"
                    data-label="Yayınlanan Yazı"
                  >
                    {{ formatNumber(author.published_posts_count) }}
                  </td>
                  <td
                    class="col-number"
                    data-label="Görüntülenme"
                  >
                    {{ formatNumber(author.total_views) }}
                  </td>
                  <td
                    class="col-number"
                    data-label="Beğeni"
                  >
                    {{ formatNumber(author.total_likes) }}
                  </td>
                  <td
                    class="col-number"
                    data-label="Yorum"
                  >
                    {{ formatNumber(author.total_comments) }}
                  </td>
                  <td
                    class="col-number"
                    data-label="Toplam Etkileşim"
                  >
                    {{ formatNumber(author.total_engagement) }}
                  </td>
                  <td
                    class="col-number"
                    data-label="Etkileşim Oranı"
                  >
                    {{ formatPercent(author.engagement_rate) }}
                  </td>
                  <td
                    class="col-number"
                    data-label="Yazı Başına Ort. Etkileşim"
                  >
                    {{ formatNumber(author.average_engagement_per_post) }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <section class="panel">
          <div class="panel-header">
            <h2>En Başarılı Yazılar</h2>
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
                  <th scope="col">Yazı</th>
                  <th scope="col">Yazar</th>
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
                  <th scope="col">Yayın Tarihi</th>
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
                    data-label="Yazar"
                    :title="post.author?.name ?? 'Bilinmiyor'"
                  >
                    {{ truncateText(post.author?.name ?? 'Bilinmiyor', 28) }}
                  </td>
                  <td
                    data-label="Kategori"
                    :title="post.category?.name ?? 'Kategorisiz'"
                  >
                    {{ truncateText(post.category?.name ?? 'Kategorisiz', 24) }}
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
                  <th scope="col">Kategori</th>
                  <th
                    scope="col"
                    class="col-number"
                  >
                    Yayınlanan Yazı
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
                  v-for="category in categoryPerformance"
                  :key="category.id"
                >
                  <td
                    data-label="Kategori"
                    :title="category.name"
                  >
                    {{ truncateText(category.name, 32) }}
                  </td>
                  <td
                    class="col-number"
                    data-label="Yayınlanan Yazı"
                  >
                    {{ formatNumber(category.published_posts_count) }}
                  </td>
                  <td
                    class="col-number"
                    data-label="Görüntülenme"
                  >
                    {{ formatNumber(category.total_views) }}
                  </td>
                  <td
                    class="col-number"
                    data-label="Beğeni"
                  >
                    {{ formatNumber(category.total_likes) }}
                  </td>
                  <td
                    class="col-number"
                    data-label="Yorum"
                  >
                    {{ formatNumber(category.total_comments) }}
                  </td>
                  <td
                    class="col-number"
                    data-label="Toplam Etkileşim"
                  >
                    {{ formatNumber(category.total_engagement) }}
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
.kpi-card,
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

.kpi-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 1rem;
  margin-bottom: 1.5rem;
  transition: opacity 0.15s ease;
}

.kpi-grid--refreshing {
  opacity: 0.72;
}

.kpi-card {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  padding: 1.25rem;
}

.kpi-label {
  color: #718096;
  font-size: 0.8125rem;
  font-weight: 500;
  line-height: 1.4;
}

.kpi-value {
  color: #1a1a2e;
  font-size: 1.55rem;
  font-weight: 700;
  line-height: 1.2;
  letter-spacing: -0.02em;
  font-variant-numeric: tabular-nums;
}

.charts-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 1.5rem;
  margin-bottom: 1.5rem;
}

.panel {
  padding: 1.75rem;
  margin-bottom: 1.5rem;
}

.charts-grid .panel {
  margin-bottom: 0;
}

.status-panel {
  padding: 1.25rem 1.5rem;
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

.status-panel .panel-header {
  margin-bottom: 1rem;
}

.status-panel .panel-header h2 {
  font-size: 1rem;
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

.chart-legend-swatch.chart-bar--users {
  background: linear-gradient(180deg, #38bdf8 0%, #0284c7 100%);
}

.chart-legend-swatch.chart-bar--posts {
  background: linear-gradient(180deg, #fbbf24 0%, #d97706 100%);
}

.empty-message {
  margin: 0;
  color: #718096;
  font-size: 0.9375rem;
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

.chart-bar-stack--growth {
  max-width: 22px;
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

.chart-bar--users {
  width: 8px;
  background: linear-gradient(180deg, #38bdf8 0%, #0284c7 100%);
}

.chart-bar--posts {
  width: 8px;
  background: linear-gradient(180deg, #fbbf24 0%, #d97706 100%);
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
  gap: 0.75rem;
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
  font-size: 0.8125rem;
  font-weight: 500;
}

.status-count {
  color: #1a1a2e;
  font-size: 0.8125rem;
  font-weight: 700;
}

.status-bar-track {
  height: 6px;
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

.data-table .col-number {
  text-align: right;
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}

@media (max-width: 1024px) {
  .kpi-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 900px) {
  .charts-grid {
    grid-template-columns: 1fr;
  }

  .charts-grid .panel:last-child {
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

  .page-header-content,
  .chart-panel-header {
    flex-direction: column;
    align-items: stretch;
  }

  .period-selector {
    align-self: flex-start;
  }

  .kpi-grid {
    grid-template-columns: 1fr;
  }

  .kpi-value {
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
