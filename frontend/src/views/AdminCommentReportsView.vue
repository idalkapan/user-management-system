<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import Pagination from '../components/Pagination.vue'
import {
  getAdminCommentReport,
  getAdminCommentReports,
  resolveCommentReport,
} from '../services/commentReportService'

const router = useRouter()

const reports = ref([])
const summary = ref({
  all: 0,
  pending: 0,
  resolved_removed: 0,
  resolved_kept: 0,
})
const currentPage = ref(1)
const lastPage = ref(1)
const perPage = ref(9)
const total = ref(0)
const isInitialLoading = ref(true)
const isRefreshing = ref(false)
const errorMessage = ref('')
const successMessage = ref('')
const listAnchor = ref(null)

const searchQuery = ref('')
const debouncedSearch = ref('')
const statusFilter = ref('')
const reasonFilter = ref('')
const sortBy = ref('latest')

const selectedReport = ref(null)
const isDetailModalOpen = ref(false)
const isDetailLoading = ref(false)
const detailError = ref('')
const adminNote = ref('')
const isResolving = ref(false)
const resolveError = ref('')
const pendingAction = ref('')

let searchDebounceTimer = null

const REASON_OPTIONS = [
  { value: '', label: 'Tüm nedenler' },
  { value: 'spam', label: 'Spam / Reklam' },
  { value: 'harassment', label: 'Taciz veya Zorbalık' },
  { value: 'hate_speech', label: 'Nefret Söylemi' },
  { value: 'inappropriate', label: 'Uygunsuz İçerik' },
  { value: 'misinformation', label: 'Yanıltıcı Bilgi' },
  { value: 'other', label: 'Diğer' },
]

const STATUS_OPTIONS = [
  { value: '', label: 'Tüm durumlar' },
  { value: 'pending', label: 'Bekliyor' },
  { value: 'resolved_removed', label: 'Yorum Kaldırıldı' },
  { value: 'resolved_kept', label: 'Yorum Bırakıldı' },
]

const SORT_OPTIONS = [
  { value: 'latest', label: 'En yeni' },
  { value: 'oldest', label: 'En eski' },
]

const summaryCards = computed(() => [
  { key: 'all', label: 'Tüm Şikâyetler' },
  { key: 'pending', label: 'Bekleyenler' },
  { key: 'resolved_removed', label: 'Yorum Kaldırılanlar' },
  { key: 'resolved_kept', label: 'Yorum Bırakılanlar' },
])

const emptyStateMessage = computed(() => {
  if (debouncedSearch.value) {
    return 'Arama kriterlerinize uygun şikâyet bulunamadı.'
  }

  if (statusFilter.value === 'pending') {
    return 'Bekleyen şikâyet bulunmuyor.'
  }

  if (statusFilter.value === 'resolved_removed') {
    return 'Yorum kaldırılarak sonuçlandırılmış şikâyet bulunmuyor.'
  }

  if (statusFilter.value === 'resolved_kept') {
    return 'Yorum bırakılarak sonuçlandırılmış şikâyet bulunmuyor.'
  }

  return 'Henüz yorum şikâyeti bulunmuyor.'
})

const isPendingReport = computed(
  () => selectedReport.value?.status === 'pending',
)

const parseReportsResponse = (response) => {
  const raw = response.data?.reports

  if (Array.isArray(raw)) {
    return raw
  }

  if (Array.isArray(raw?.data)) {
    return raw.data
  }

  return []
}

const loadReports = async ({ scrollToList = false } = {}) => {
  if (isInitialLoading.value) {
    errorMessage.value = ''
  } else {
    isRefreshing.value = true
  }

  try {
    const params = {
      page: currentPage.value,
      per_page: perPage.value,
      sort: sortBy.value,
    }

    if (statusFilter.value) {
      params.status = statusFilter.value
    }

    if (reasonFilter.value) {
      params.reason = reasonFilter.value
    }

    if (debouncedSearch.value.trim()) {
      params.search = debouncedSearch.value.trim()
    }

    const response = await getAdminCommentReports(params)

    reports.value = parseReportsResponse(response)

    const meta = response.data?.meta ?? {}

    currentPage.value = meta.current_page ?? currentPage.value
    lastPage.value = meta.last_page ?? 1
    perPage.value = meta.per_page ?? perPage.value
    total.value = meta.total ?? 0

    summary.value = {
      all: response.data?.summary?.all ?? 0,
      pending: response.data?.summary?.pending ?? 0,
      resolved_removed: response.data?.summary?.resolved_removed ?? 0,
      resolved_kept: response.data?.summary?.resolved_kept ?? 0,
    }

    errorMessage.value = ''

    if (scrollToList) {
      listAnchor.value?.scrollIntoView({
        behavior: 'smooth',
        block: 'start',
      })
    }
  } catch (error) {
    errorMessage.value =
      error.response?.data?.message ??
      'Yorum şikâyetleri yüklenirken bir hata oluştu.'
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
  loadReports({ scrollToList: true })
}

const formatDate = (date) => {
  if (!date) {
    return '—'
  }

  return new Intl.DateTimeFormat('tr-TR', {
    day: '2-digit',
    month: 'long',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(date))
}

const getCommentPreview = (report) => {
  if (report.comment?.content) {
    return report.comment.content
  }

  return report.comment_content_snapshot ?? '—'
}

const getCommentAuthorName = (report) =>
  report.comment_author?.name ??
  report.comment?.author?.name ??
  'Bilinmiyor'

const getPostTitle = (report) =>
  report.post?.title ??
  (report.post_id_snapshot ? `Yazı #${report.post_id_snapshot}` : '—')

const openDetailModal = async (report) => {
  selectedReport.value = report
  adminNote.value = ''
  resolveError.value = ''
  pendingAction.value = ''
  detailError.value = ''
  isDetailModalOpen.value = true
  isDetailLoading.value = true

  try {
    const response = await getAdminCommentReport(report.id)
    selectedReport.value = response.data.report ?? report
  } catch (error) {
    detailError.value =
      error.response?.data?.message ??
      'Şikâyet detayı yüklenirken bir hata oluştu.'
  } finally {
    isDetailLoading.value = false
  }
}

const closeDetailModal = () => {
  if (isResolving.value) {
    return
  }

  isDetailModalOpen.value = false
  selectedReport.value = null
  adminNote.value = ''
  resolveError.value = ''
  pendingAction.value = ''
  detailError.value = ''
}

const requestResolve = (action) => {
  if (!isPendingReport.value || isResolving.value) {
    return
  }

  pendingAction.value = action
  resolveError.value = ''
}

const cancelResolve = () => {
  if (isResolving.value) {
    return
  }

  pendingAction.value = ''
  resolveError.value = ''
}

const confirmResolve = async () => {
  if (!selectedReport.value || !pendingAction.value || isResolving.value) {
    return
  }

  if (adminNote.value.length > 500) {
    resolveError.value = 'Admin notu en fazla 500 karakter olabilir.'
    return
  }

  resolveError.value = ''
  isResolving.value = true

  try {
    const payload = {
      action: pendingAction.value,
    }

    const trimmedNote = adminNote.value.trim()

    if (trimmedNote) {
      payload.admin_note = trimmedNote
    }

    const response = await resolveCommentReport(
      selectedReport.value.id,
      payload,
    )

    successMessage.value =
      response.data?.message ?? 'Moderasyon işlemi tamamlandı.'

    isDetailModalOpen.value = false
    selectedReport.value = null
    adminNote.value = ''
    pendingAction.value = ''

    const wasLastItemOnPage = reports.value.length === 1

    if (wasLastItemOnPage && currentPage.value > 1) {
      currentPage.value -= 1
    }

    await loadReports()
  } catch (error) {
    resolveError.value =
      error.response?.data?.message ??
      'Moderasyon işlemi sırasında bir hata oluştu.'

    if (error.response?.status === 409) {
      await loadReports()
    }
  } finally {
    isResolving.value = false
  }
}

const goToPost = (report) => {
  const postId = report.post?.id ?? report.post_id_snapshot

  if (!postId) {
    return
  }

  router.push(`/posts/${postId}?from=admin`)
}

watch(searchQuery, (value) => {
  clearTimeout(searchDebounceTimer)

  searchDebounceTimer = setTimeout(() => {
    debouncedSearch.value = value
  }, 400)
})

watch([debouncedSearch, statusFilter, reasonFilter, sortBy], () => {
  if (isInitialLoading.value) {
    return
  }

  currentPage.value = 1
  loadReports()
})

onMounted(() => {
  loadReports()
})
</script>

<template>
  <div class="admin-reports-page">
    <div class="admin-reports-container">
      <header class="page-header">
        <div>
          <h1>Yorum Şikâyetleri</h1>
          <p>
            Kullanıcılar tarafından bildirilen yorumları inceleyin ve moderasyon
            kararlarını yönetin.
          </p>
        </div>
      </header>

      <div
        v-if="successMessage"
        class="alert alert-success"
      >
        {{ successMessage }}
      </div>

      <div
        v-if="errorMessage"
        class="alert alert-error"
      >
        {{ errorMessage }}
      </div>

      <section
        v-if="!isInitialLoading"
        class="summary-strip"
      >
        <article
          v-for="card in summaryCards"
          :key="card.key"
          class="summary-card"
        >
          <span class="summary-card-label">{{ card.label }}</span>
          <strong class="summary-card-value">{{ summary[card.key] ?? 0 }}</strong>
        </article>
      </section>

      <section class="panel">
        <div class="filters-bar">
          <label class="filter-field filter-search">
            <span class="visually-hidden">Şikâyet ara</span>
            <input
              v-model="searchQuery"
              type="search"
              placeholder="Yorum, kullanıcı, yazı veya açıklama ara..."
              aria-label="Şikâyet ara"
            >
          </label>

          <label class="filter-field">
            <span class="filter-label">Durum</span>
            <select
              v-model="statusFilter"
              aria-label="Durum filtresi"
            >
              <option
                v-for="option in STATUS_OPTIONS"
                :key="option.value || 'all-status'"
                :value="option.value"
              >
                {{ option.label }}
              </option>
            </select>
          </label>

          <label class="filter-field">
            <span class="filter-label">Neden</span>
            <select
              v-model="reasonFilter"
              aria-label="Neden filtresi"
            >
              <option
                v-for="option in REASON_OPTIONS"
                :key="option.value || 'all-reason'"
                :value="option.value"
              >
                {{ option.label }}
              </option>
            </select>
          </label>

          <label class="filter-field">
            <span class="filter-label">Sıralama</span>
            <select
              v-model="sortBy"
              aria-label="Sıralama"
            >
              <option
                v-for="option in SORT_OPTIONS"
                :key="option.value"
                :value="option.value"
              >
                {{ option.label }}
              </option>
            </select>
          </label>
        </div>

        <div
          v-if="isInitialLoading"
          class="loading-state"
        >
          Yorum şikâyetleri yükleniyor...
        </div>

        <template v-else>
          <div
            v-if="isRefreshing"
            class="refresh-banner"
            role="status"
            aria-live="polite"
          >
            Liste güncelleniyor...
          </div>

          <div
            v-if="!isRefreshing && reports.length === 0"
            class="empty-state"
          >
            <h2>Şikâyet bulunamadı</h2>
            <p>{{ emptyStateMessage }}</p>
          </div>

          <div
            v-else
            ref="listAnchor"
            class="reports-table-wrapper"
            :class="{ 'reports-table-wrapper--refreshing': isRefreshing }"
          >
            <table class="reports-table">
              <thead>
                <tr>
                  <th>Neden</th>
                  <th>Durum</th>
                  <th>Şikâyet Eden</th>
                  <th>Yorum Sahibi</th>
                  <th>Yorum</th>
                  <th>Yazı</th>
                  <th>Tarih</th>
                  <th>Bekleyen</th>
                  <th>İşlem</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="report in reports"
                  :key="report.id"
                >
                  <td>{{ report.reason_label }}</td>
                  <td>
                    <span
                      class="status-badge"
                      :class="`status-badge--${report.status}`"
                    >
                      {{ report.status_label }}
                    </span>
                  </td>
                  <td>{{ report.reporter?.name ?? '—' }}</td>
                  <td>{{ getCommentAuthorName(report) }}</td>
                  <td class="comment-cell">
                    <span
                      v-if="report.comment_missing"
                      class="comment-missing-label"
                    >
                      Yorum artık mevcut değil
                    </span>
                    <span class="comment-preview">
                      {{ getCommentPreview(report) }}
                    </span>
                  </td>
                  <td>{{ getPostTitle(report) }}</td>
                  <td>{{ formatDate(report.created_at) }}</td>
                  <td>{{ report.pending_reports_count ?? 0 }}</td>
                  <td>
                    <button
                      type="button"
                      class="inspect-button"
                      @click="openDetailModal(report)"
                    >
                      İncele
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>

            <div class="reports-cards">
              <article
                v-for="report in reports"
                :key="`card-${report.id}`"
                class="report-card"
              >
                <div class="report-card-header">
                  <span
                    class="status-badge"
                    :class="`status-badge--${report.status}`"
                  >
                    {{ report.status_label }}
                  </span>
                  <span class="report-card-reason">{{ report.reason_label }}</span>
                </div>

                <p class="report-card-meta">
                  <strong>Şikâyet eden:</strong>
                  {{ report.reporter?.name ?? '—' }}
                </p>

                <p class="report-card-meta">
                  <strong>Yorum sahibi:</strong>
                  {{ getCommentAuthorName(report) }}
                </p>

                <p
                  v-if="report.comment_missing"
                  class="comment-missing-label"
                >
                  Yorum artık mevcut değil
                </p>

                <p class="report-card-content">
                  {{ getCommentPreview(report) }}
                </p>

                <p class="report-card-meta">
                  <strong>Yazı:</strong>
                  {{ getPostTitle(report) }}
                </p>

                <p class="report-card-meta">
                  <strong>Tarih:</strong>
                  {{ formatDate(report.created_at) }}
                </p>

                <p class="report-card-meta">
                  <strong>Bekleyen şikâyet:</strong>
                  {{ report.pending_reports_count ?? 0 }}
                </p>

                <button
                  type="button"
                  class="inspect-button"
                  @click="openDetailModal(report)"
                >
                  İncele
                </button>
              </article>
            </div>
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

  <div
    v-if="isDetailModalOpen"
    class="modal-overlay"
    @click.self="closeDetailModal"
  >
    <div class="detail-modal">
      <div class="detail-modal-header">
        <h2>Şikâyet Detayı</h2>
        <button
          type="button"
          class="modal-close-button"
          :disabled="isResolving"
          @click="closeDetailModal"
        >
          ×
        </button>
      </div>

      <div
        v-if="isDetailLoading"
        class="detail-loading"
      >
        Detay yükleniyor...
      </div>

      <template v-else-if="selectedReport">
        <p
          v-if="detailError"
          class="modal-error"
        >
          {{ detailError }}
        </p>

        <div class="detail-grid">
          <div>
            <span class="detail-label">Şikâyet nedeni</span>
            <strong>{{ selectedReport.reason_label }}</strong>
          </div>

          <div>
            <span class="detail-label">Durum</span>
            <span
              class="status-badge"
              :class="`status-badge--${selectedReport.status}`"
            >
              {{ selectedReport.status_label }}
            </span>
          </div>

          <div>
            <span class="detail-label">Şikâyet eden</span>
            <strong>{{ selectedReport.reporter?.name ?? '—' }}</strong>
          </div>

          <div>
            <span class="detail-label">Yorum sahibi</span>
            <strong>{{ getCommentAuthorName(selectedReport) }}</strong>
          </div>

          <div>
            <span class="detail-label">Şikâyet tarihi</span>
            <strong>{{ formatDate(selectedReport.created_at) }}</strong>
          </div>

          <div>
            <span class="detail-label">Bekleyen şikâyet sayısı</span>
            <strong>{{ selectedReport.pending_reports_count ?? 0 }}</strong>
          </div>
        </div>

        <div class="detail-block">
          <span class="detail-label">Kullanıcı açıklaması</span>
          <p>{{ selectedReport.description || 'Açıklama girilmemiş.' }}</p>
        </div>

        <div class="detail-block">
          <span class="detail-label">Yorum içeriği</span>
          <p
            v-if="selectedReport.comment_missing"
            class="comment-missing-label"
          >
            Yorum artık mevcut değil
          </p>
          <p class="detail-comment-content">
            {{ getCommentPreview(selectedReport) }}
          </p>
        </div>

        <div class="detail-block">
          <span class="detail-label">Yazı</span>
          <button
            v-if="selectedReport.post?.id || selectedReport.post_id_snapshot"
            type="button"
            class="link-button"
            @click="goToPost(selectedReport)"
          >
            {{ getPostTitle(selectedReport) }}
          </button>
          <p v-else>—</p>
        </div>

        <div
          v-if="selectedReport.reviewer"
          class="detail-block"
        >
          <span class="detail-label">İnceleyen admin</span>
          <p>{{ selectedReport.reviewer.name }}</p>
        </div>

        <div
          v-if="selectedReport.reviewed_at"
          class="detail-block"
        >
          <span class="detail-label">Sonuçlandırma tarihi</span>
          <p>{{ formatDate(selectedReport.reviewed_at) }}</p>
        </div>

        <div
          v-if="selectedReport.admin_note"
          class="detail-block"
        >
          <span class="detail-label">Admin notu</span>
          <p>{{ selectedReport.admin_note }}</p>
        </div>

        <template v-if="isPendingReport">
          <label
            class="detail-label"
            for="admin-note"
          >
            Admin notu (isteğe bağlı)
          </label>

          <textarea
            id="admin-note"
            v-model="adminNote"
            class="admin-note-textarea"
            rows="4"
            maxlength="500"
            placeholder="Moderasyon notu ekleyebilirsiniz..."
            :disabled="isResolving"
          />

          <div class="admin-note-meta">
            {{ adminNote.length }}/500
          </div>

          <div
            v-if="!pendingAction"
            class="resolve-actions"
          >
            <div class="resolve-action-card">
              <h3>Yorumu Bırak</h3>
              <p>
                Yorum yayında kalacak ve bu yoruma ait bekleyen şikâyetler
                kapatılacaktır.
              </p>
              <button
                type="button"
                class="keep-button"
                :disabled="isResolving"
                @click="requestResolve('keep')"
              >
                Yorumu Bırak
              </button>
            </div>

            <div class="resolve-action-card resolve-action-card--danger">
              <h3>Yorumu Kaldır</h3>
              <p>
                Yorum ve varsa yanıtları silinecek, bu yoruma ait bekleyen
                şikâyetler sonuçlandırılacaktır.
              </p>
              <button
                type="button"
                class="remove-button"
                :disabled="isResolving"
                @click="requestResolve('remove')"
              >
                Yorumu Kaldır
              </button>
            </div>
          </div>

          <div
            v-else
            class="resolve-confirmation"
          >
            <p class="resolve-confirmation-text">
              {{
                pendingAction === 'keep'
                  ? 'Yorum yayında kalacak ve bekleyen şikâyetler kapatılacak. Devam etmek istiyor musunuz?'
                  : 'Yorum ve varsa yanıtları kalıcı olarak silinecek. Devam etmek istiyor musunuz?'
              }}
            </p>

            <p
              v-if="resolveError"
              class="modal-error"
            >
              {{ resolveError }}
            </p>

            <div class="modal-actions">
              <button
                type="button"
                class="secondary-button"
                :disabled="isResolving"
                @click="cancelResolve"
              >
                Vazgeç
              </button>

              <button
                type="button"
                class="confirm-button"
                :class="{ 'confirm-button--danger': pendingAction === 'remove' }"
                :disabled="isResolving"
                @click="confirmResolve"
              >
                {{
                  isResolving
                    ? 'İşleniyor...'
                    : pendingAction === 'keep'
                      ? 'Yorumu Bırak'
                      : 'Yorumu Kaldır'
                }}
              </button>
            </div>
          </div>

          <p
            v-if="resolveError && !pendingAction"
            class="modal-error"
          >
            {{ resolveError }}
          </p>
        </template>
      </template>
    </div>
  </div>
</template>

<style scoped>
.admin-reports-page {
  padding: 2rem;
  box-sizing: border-box;
}

.admin-reports-container {
  max-width: 1200px;
  margin: 0 auto;
}

.page-header {
  margin-bottom: 1.5rem;
}

.page-header h1 {
  margin: 0 0 0.4rem;
  color: #1a1a2e;
}

.page-header p {
  margin: 0;
  color: #718096;
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

.alert-success {
  color: #166534;
  background-color: #f0fdf4;
  border: 1px solid #bbf7d0;
}

.summary-strip {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.summary-card {
  padding: 1rem 1.1rem;
  background-color: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
}

.summary-card-label {
  display: block;
  color: #64748b;
  font-size: 0.8125rem;
}

.summary-card-value {
  display: block;
  margin-top: 0.35rem;
  color: #1a1a2e;
  font-size: 1.35rem;
}

.panel {
  padding: 1.25rem;
  background-color: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
}

.filters-bar {
  display: grid;
  grid-template-columns: 2fr repeat(3, minmax(140px, 1fr));
  gap: 0.85rem;
  margin-bottom: 1.25rem;
}

.filter-field {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.filter-search input,
.filter-field select {
  width: 100%;
  padding: 0.7rem 0.85rem;
  color: #1a1a2e;
  background-color: #ffffff;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  font: inherit;
  box-sizing: border-box;
}

.filter-label {
  color: #64748b;
  font-size: 0.8125rem;
  font-weight: 600;
}

.loading-state,
.empty-state,
.detail-loading {
  padding: 2.5rem 1rem;
  text-align: center;
  color: #718096;
}

.empty-state h2 {
  margin: 0 0 0.5rem;
  color: #1a1a2e;
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

.reports-table-wrapper {
  overflow-x: auto;
  transition: opacity 0.15s ease;
}

.reports-table-wrapper--refreshing {
  opacity: 0.72;
}

.reports-table {
  width: 100%;
  border-collapse: collapse;
}

.reports-table th,
.reports-table td {
  padding: 0.85rem 0.75rem;
  border-bottom: 1px solid #e2e8f0;
  text-align: left;
  vertical-align: top;
}

.reports-table th {
  color: #475569;
  background-color: #f8fafc;
  font-size: 0.8125rem;
  font-weight: 700;
}

.comment-cell {
  max-width: 260px;
}

.comment-preview {
  display: -webkit-box;
  overflow: hidden;
  color: #475569;
  line-height: 1.5;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
}

.comment-missing-label {
  display: block;
  margin-bottom: 0.25rem;
  color: #92400e;
  font-size: 0.75rem;
  font-weight: 600;
}

.status-badge {
  display: inline-flex;
  align-items: center;
  padding: 0.25rem 0.55rem;
  border-radius: 999px;
  font-size: 0.75rem;
  font-weight: 700;
}

.status-badge--pending {
  color: #92400e;
  background-color: #fef3c7;
}

.status-badge--resolved_kept {
  color: #166534;
  background-color: #dcfce7;
}

.status-badge--resolved_removed {
  color: #991b1b;
  background-color: #fee2e2;
}

.inspect-button,
.link-button,
.secondary-button,
.confirm-button,
.keep-button,
.remove-button {
  padding: 0.55rem 0.85rem;
  border-radius: 8px;
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
}

.inspect-button,
.link-button {
  color: #4f6ef7;
  background-color: #ffffff;
  border: 1px solid #c7d2fe;
}

.link-button {
  padding: 0;
  border: none;
  text-align: left;
}

.keep-button {
  color: #166534;
  background-color: #f0fdf4;
  border: 1px solid #86efac;
}

.remove-button,
.confirm-button--danger {
  color: #ffffff;
  background-color: #dc2626;
  border: 1px solid #dc2626;
}

.confirm-button {
  color: #ffffff;
  background-color: #4f6ef7;
  border: 1px solid #4f6ef7;
}

.secondary-button {
  color: #475569;
  background-color: #ffffff;
  border: 1px solid #cbd5e0;
}

.reports-cards {
  display: none;
  flex-direction: column;
  gap: 1rem;
}

.report-card {
  padding: 1rem;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
}

.report-card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  margin-bottom: 0.75rem;
}

.report-card-reason {
  color: #475569;
  font-size: 0.875rem;
  font-weight: 600;
}

.report-card-meta {
  margin: 0 0 0.45rem;
  color: #64748b;
  font-size: 0.8125rem;
}

.report-card-content {
  margin: 0 0 0.75rem;
  color: #475569;
  line-height: 1.55;
}

.modal-overlay {
  position: fixed;
  inset: 0;
  z-index: 1000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
  background-color: rgba(15, 23, 42, 0.55);
}

.detail-modal {
  width: 100%;
  max-width: 720px;
  max-height: 90vh;
  overflow-y: auto;
  padding: 1.5rem;
  background-color: #ffffff;
  border-radius: 12px;
  box-shadow: 0 20px 50px rgba(0, 0, 0, 0.2);
}

.detail-modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1rem;
}

.detail-modal-header h2 {
  margin: 0;
  color: #1a1a2e;
}

.modal-close-button {
  width: 2rem;
  height: 2rem;
  color: #64748b;
  background: transparent;
  border: none;
  font-size: 1.5rem;
  cursor: pointer;
}

.detail-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 1rem;
  margin-bottom: 1rem;
}

.detail-label {
  display: block;
  margin-bottom: 0.25rem;
  color: #64748b;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.detail-block {
  margin-bottom: 1rem;
}

.detail-block p,
.detail-comment-content {
  margin: 0.35rem 0 0;
  color: #475569;
  line-height: 1.6;
  white-space: pre-wrap;
}

.admin-note-textarea {
  width: 100%;
  padding: 0.85rem;
  border: 1px solid #cbd5e0;
  border-radius: 8px;
  font: inherit;
  resize: vertical;
  box-sizing: border-box;
}

.admin-note-meta {
  margin: 0.35rem 0 1rem;
  color: #94a3b8;
  font-size: 0.75rem;
  text-align: right;
}

.resolve-actions {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 1rem;
}

.resolve-action-card {
  padding: 1rem;
  background-color: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
}

.resolve-action-card--danger {
  background-color: #fff7f7;
  border-color: #fecaca;
}

.resolve-action-card h3 {
  margin: 0 0 0.5rem;
  color: #1a1a2e;
  font-size: 0.95rem;
}

.resolve-action-card p {
  margin: 0 0 0.85rem;
  color: #64748b;
  font-size: 0.8125rem;
  line-height: 1.55;
}

.resolve-confirmation {
  margin-top: 1rem;
  padding: 1rem;
  background-color: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
}

.resolve-confirmation-text {
  margin: 0 0 1rem;
  color: #475569;
  line-height: 1.55;
}

.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
}

.modal-error {
  margin: 0.75rem 0 0;
  color: #991b1b;
  font-size: 0.875rem;
}

.visually-hidden {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}

@media (max-width: 960px) {
  .summary-strip {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .filters-bar {
    grid-template-columns: 1fr;
  }

  .reports-table {
    display: none;
  }

  .reports-cards {
    display: flex;
  }

  .resolve-actions,
  .detail-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 640px) {
  .admin-reports-page {
    padding: 1rem;
  }

  .summary-strip {
    grid-template-columns: 1fr;
  }
}
</style>
