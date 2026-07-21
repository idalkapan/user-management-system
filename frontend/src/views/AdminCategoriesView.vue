<template>
  <div class="admin-categories-page">
    <div class="admin-categories-container">
      <header class="page-header">
        <div class="page-header-text">
          <h1>Kategori Yönetimi</h1>
          <p>Blog yazılarınız için kategorileri buradan oluşturabilir ve yönetebilirsiniz.</p>
        </div>

        <button
          type="button"
          class="primary-button"
          :disabled="isSubmitting"
          @click="openCreateModal"
        >
          Yeni Kategori Ekle
        </button>
      </header>

      <div
        v-if="successMessage"
        class="alert alert-success"
        role="alert"
      >
        {{ successMessage }}
      </div>

      <div
        v-if="errorMessage"
        class="alert alert-error"
        role="alert"
      >
        {{ errorMessage }}
      </div>

      <section class="panel">
        <div
          v-if="isLoading"
          class="loading-state"
        >
          Kategoriler yükleniyor...
        </div>

        <div
          v-else-if="categories.length === 0"
          class="empty-state"
        >
          <h2>Henüz kategori bulunmuyor.</h2>
          <p>İlk kategorinizi eklemek için sağ üstteki butonu kullanın.</p>
        </div>

        <div
          v-else
          class="table-wrapper"
        >
          <table class="categories-table">
            <thead>
              <tr>
                <th>Kategori</th>
                <th>Açıklama</th>
                <th>Yazı Sayısı</th>
                <th>Durum</th>
                <th>İşlemler</th>
              </tr>
            </thead>

            <tbody>
              <tr
                v-for="category in categories"
                :key="category.id"
              >
                <td
                  data-label="Kategori"
                  class="category-name"
                >
                  {{ category.name }}
                </td>

                <td data-label="Açıklama">
                  {{ category.description || '—' }}
                </td>

                <td data-label="Yazı Sayısı">
                  {{ category.posts_count ?? 0 }}
                </td>

                <td data-label="Durum">
                  <span
                    :class="[
                      'status-badge',
                      category.is_active
                        ? 'status-badge-active'
                        : 'status-badge-inactive',
                    ]"
                  >
                    {{ category.is_active ? 'Aktif' : 'Pasif' }}
                  </span>
                </td>

                <td data-label="İşlemler">
                  <div class="row-actions">
                    <button
                      type="button"
                      class="action-button action-edit"
                      :disabled="isSubmitting || isDeleting || statusUpdatingId !== null"
                      @click="openEditModal(category)"
                    >
                      Düzenle
                    </button>

                    <button
                      type="button"
                      class="action-button action-status"
                      :disabled="
                        statusUpdatingId === category.id
                          || isSubmitting
                          || isDeleting
                      "
                      @click="toggleCategoryStatus(category)"
                    >
                      {{
                        statusUpdatingId === category.id
                          ? 'Güncelleniyor...'
                          : category.is_active
                            ? 'Pasif Yap'
                            : 'Aktif Yap'
                      }}
                    </button>

                    <button
                      type="button"
                      class="action-button action-delete"
                      :disabled="
                        isSubmitting
                          || isDeleting
                          || statusUpdatingId !== null
                      "
                      @click="openDeleteModal(category)"
                    >
                      Sil
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>

    <!-- Oluşturma / Düzenleme Modal -->
    <div
      v-if="isFormModalOpen"
      class="modal-overlay"
      @click.self="closeFormModal"
    >
      <div
        class="modal"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="editingCategoryId ? 'edit-category-title' : 'create-category-title'"
      >
        <div class="modal-header">
          <h2 :id="editingCategoryId ? 'edit-category-title' : 'create-category-title'">
            {{ editingCategoryId ? 'Kategori Düzenle' : 'Yeni Kategori' }}
          </h2>
          <button
            type="button"
            class="modal-close"
            :disabled="isSubmitting"
            aria-label="Kapat"
            @click="closeFormModal"
          >
            ×
          </button>
        </div>

        <form
          class="modal-body"
          @submit.prevent="submitForm"
        >
          <label class="form-field">
            <span>Kategori adı</span>
            <input
              v-model="form.name"
              type="text"
              placeholder="Örn. Web Geliştirme"
              :disabled="isSubmitting"
            >
          </label>

          <label class="form-field">
            <span>Açıklama</span>
            <textarea
              v-model="form.description"
              rows="4"
              placeholder="Kategori hakkında kısa bir açıklama yazın"
              :disabled="isSubmitting"
            ></textarea>
          </label>

          <div class="modal-actions">
            <button
              type="button"
              class="secondary-button"
              :disabled="isSubmitting"
              @click="closeFormModal"
            >
              İptal
            </button>

            <button
              type="submit"
              class="primary-button"
              :disabled="isSubmitting"
            >
              {{
                isSubmitting
                  ? editingCategoryId
                    ? 'Güncelleniyor...'
                    : 'Kaydediliyor...'
                  : editingCategoryId
                    ? 'Güncelle'
                    : 'Kaydet'
              }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Silme Onay Modal -->
    <div
      v-if="isDeleteModalOpen"
      class="modal-overlay"
      @click.self="closeDeleteModal"
    >
      <div
        class="modal modal-delete"
        role="dialog"
        aria-modal="true"
        aria-labelledby="delete-category-title"
      >
        <div class="modal-header">
          <h2 id="delete-category-title">
            Kategoriyi Sil
          </h2>
          <button
            type="button"
            class="modal-close"
            :disabled="isDeleting"
            aria-label="Kapat"
            @click="closeDeleteModal"
          >
            ×
          </button>
        </div>

        <div class="modal-body">
          <p class="delete-message">
            <strong>{{ categoryToDelete?.name }}</strong>
            kategorisini silmek istediğinize emin misiniz?
          </p>
          <p class="delete-warning">
            Bu işlem geri alınamaz.
          </p>
          <p
            v-if="errorMessage"
            class="delete-error"
          >
          {{ errorMessage }}
          </p>

          <div class="modal-actions">
            <button
              type="button"
              class="secondary-button"
              :disabled="isDeleting"
              @click="closeDeleteModal"
            >
              Vazgeç
            </button>

            <button
              type="button"
              class="danger-button"
              :disabled="isDeleting"
              @click="confirmDelete"
            >
              {{ isDeleting ? 'Siliniyor...' : 'Sil' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import {
  createCategory,
  deleteCategory,
  getAdminCategories,
  updateCategory,
  updateCategoryStatus,
} from '../services/categoryService'

const categories = ref([])
const isLoading = ref(true)
const errorMessage = ref('')
const successMessage = ref('')

const isFormModalOpen = ref(false)
const isDeleteModalOpen = ref(false)
const editingCategoryId = ref(null)
const categoryToDelete = ref(null)

const isSubmitting = ref(false)
const isDeleting = ref(false)
const statusUpdatingId = ref(null)

const defaultForm = () => ({
  name: '',
  description: '',
  sort_order: 0,
  is_active: true,
})

const form = ref(defaultForm())

const resetForm = () => {
  form.value = defaultForm()
}

const loadCategories = async () => {
  isLoading.value = true

  try {
    const response = await getAdminCategories()

    categories.value =
      response.data.data ??
      response.data.categories ??
      response.data ??
      []
  } catch (error) {
    console.error('Kategoriler alınamadı:', error)

    errorMessage.value =
      error.response?.data?.message ??
      'Kategoriler yüklenemedi.'
  } finally {
    isLoading.value = false
  }
}

const openCreateModal = () => {
  errorMessage.value = ''
  successMessage.value = ''
  editingCategoryId.value = null
  resetForm()
  isFormModalOpen.value = true
}

const openEditModal = (category) => {
  errorMessage.value = ''
  successMessage.value = ''
  editingCategoryId.value = category.id

  form.value = {
    name: category.name,
    description: category.description ?? '',
    sort_order: category.sort_order ?? 0,
    is_active: category.is_active,
  }

  isFormModalOpen.value = true
}

const closeFormModal = () => {
  if (isSubmitting.value) {
    return
  }

  isFormModalOpen.value = false
  editingCategoryId.value = null
  resetForm()
}

const submitForm = async () => {
  if (isSubmitting.value) {
    return
  }

  errorMessage.value = ''
  successMessage.value = ''

  if (!form.value.name.trim()) {
    errorMessage.value = 'Kategori adı zorunludur.'
    return
  }

  const payload = {
    name: form.value.name.trim(),
    description: form.value.description.trim() || null,
    sort_order: editingCategoryId.value ? form.value.sort_order : 0,
    is_active: form.value.is_active,
  }

  isSubmitting.value = true

  try {
    if (editingCategoryId.value) {
      const response = await updateCategory(
        editingCategoryId.value,
        payload,
      )

      successMessage.value =
        response.data.message ?? 'Kategori başarıyla güncellendi.'
    } else {
      const response = await createCategory(payload)

      successMessage.value =
        response.data.message ?? 'Kategori başarıyla oluşturuldu.'
    }

    isFormModalOpen.value = false
    editingCategoryId.value = null
    resetForm()
    await loadCategories()
  } catch (error) {
    console.error('Kategori kaydedilemedi:', error)

    errorMessage.value =
      error.response?.data?.message ??
      (editingCategoryId.value
        ? 'Kategori güncellenemedi.'
        : 'Kategori oluşturulamadı.')
  } finally {
    isSubmitting.value = false
  }
}

const toggleCategoryStatus = async (category) => {
  if (statusUpdatingId.value !== null) {
    return
  }

  errorMessage.value = ''
  successMessage.value = ''
  statusUpdatingId.value = category.id

  try {
    const response = await updateCategoryStatus(
      category.id,
      !category.is_active,
    )

    successMessage.value =
      response.data.message ?? 'Kategori durumu güncellendi.'

    await loadCategories()
  } catch (error) {
    console.error('Kategori durumu güncellenemedi:', error)

    errorMessage.value =
      error.response?.data?.message ??
      'Kategori durumu güncellenemedi.'
  } finally {
    statusUpdatingId.value = null
  }
}

const openDeleteModal = (category) => {
  errorMessage.value = ''
  successMessage.value = ''
  categoryToDelete.value = category
  isDeleteModalOpen.value = true
}

const closeDeleteModal = () => {
  if (isDeleting.value) {
    return
  }

  isDeleteModalOpen.value = false
  categoryToDelete.value = null
}

const confirmDelete = async () => {
  if (!categoryToDelete.value || isDeleting.value) {
    return
  }

  errorMessage.value = ''
  successMessage.value = ''
  isDeleting.value = true

  try {
    const response = await deleteCategory(categoryToDelete.value.id)

    successMessage.value =
      response.data.message ?? 'Kategori başarıyla silindi.'

    if (editingCategoryId.value === categoryToDelete.value.id) {
      closeFormModal()
    }

    isDeleteModalOpen.value = false
    categoryToDelete.value = null
    await loadCategories()
  } catch (error) {
    console.error('Kategori silinemedi:', error)

    errorMessage.value =
      error.response?.data?.message ??
      'Kategori silinemedi.'

   
  } finally {
    isDeleting.value = false
  }
}

onMounted(() => {
  loadCategories()
})
</script>

<style scoped>
.admin-categories-page {
  min-height: 100vh;
  padding: 2rem 1.5rem;
  background-color: #f0f4f8;
}

.admin-categories-container {
  max-width: 1150px;
  margin: 0 auto;
}

.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1.5rem;
  margin-bottom: 1.5rem;
}

.page-header h1 {
  margin: 0;
  font-size: 2rem;
  color: #1a1a2e;
}

.page-header p {
  margin: 0.5rem 0 0;
  color: #718096;
  max-width: 560px;
}

.alert {
  padding: 0.95rem 1.1rem;
  margin-bottom: 1rem;
  border-radius: 10px;
  font-weight: 500;
}

.alert-success {
  color: #166534;
  background-color: #f0fdf4;
  border: 1px solid #bbf7d0;
}

.alert-error {
  color: #991b1b;
  background-color: #fef2f2;
  border: 1px solid #fecaca;
}

.panel {
  background-color: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  box-shadow: 0 10px 30px rgba(15, 23, 42, 0.05);
  overflow: hidden;
}

.loading-state,
.empty-state {
  padding: 3rem 2rem;
  text-align: center;
  color: #64748b;
}

.empty-state h2 {
  margin: 0 0 0.5rem;
  color: #1e293b;
  font-size: 1.25rem;
}

.empty-state p {
  margin: 0;
}

.table-wrapper {
  overflow-x: auto;
}

.categories-table {
  width: 100%;
  border-collapse: collapse;
}

.categories-table thead {
  background-color: #f8fafc;
}

.categories-table th,
.categories-table td {
  padding: 1rem 1.25rem;
  text-align: left;
  border-bottom: 1px solid #e2e8f0;
  vertical-align: middle;
}

.categories-table th {
  font-size: 0.85rem;
  font-weight: 700;
  letter-spacing: 0.02em;
  text-transform: uppercase;
  color: #64748b;
}

.categories-table tbody tr:hover {
  background-color: #f8fafc;
}

.category-name {
  font-weight: 600;
  color: #1e293b;
}

.status-badge {
  display: inline-flex;
  align-items: center;
  padding: 0.28rem 0.7rem;
  border-radius: 999px;
  font-size: 0.8rem;
  font-weight: 700;
  white-space: nowrap;
}

.status-badge-active {
  color: #166534;
  background-color: #dcfce7;
}

.status-badge-inactive {
  color: #64748b;
  background-color: #e2e8f0;
}

.row-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.primary-button,
.secondary-button,
.danger-button,
.action-button {
  border: none;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  transition: background-color 0.2s ease, opacity 0.2s ease;
}

.primary-button {
  padding: 0.75rem 1.15rem;
  color: #ffffff;
  background-color: #4f46e5;
  white-space: nowrap;
}

.primary-button:hover:not(:disabled) {
  background-color: #4338ca;
}

.secondary-button {
  padding: 0.75rem 1.15rem;
  color: #334155;
  background-color: #e2e8f0;
}

.secondary-button:hover:not(:disabled) {
  background-color: #cbd5e1;
}

.danger-button {
  padding: 0.75rem 1.15rem;
  color: #ffffff;
  background-color: #dc2626;
}

.danger-button:hover:not(:disabled) {
  background-color: #b91c1c;
}

.action-button {
  padding: 0.5rem 0.85rem;
  font-size: 0.875rem;
}

.action-edit {
  color: #ffffff;
  background-color: #4f46e5;
}

.action-edit:hover:not(:disabled) {
  background-color: #4338ca;
}

.action-status {
  color: #334155;
  background-color: #e2e8f0;
}

.action-status:hover:not(:disabled) {
  background-color: #cbd5e1;
}

.action-delete {
  color: #ffffff;
  background-color: #ef4444;
}

.action-delete:hover:not(:disabled) {
  background-color: #dc2626;
}

.primary-button:disabled,
.secondary-button:disabled,
.danger-button:disabled,
.action-button:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}

.modal-overlay {
  position: fixed;
  inset: 0;
  z-index: 1000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1.5rem;
  background-color: rgba(15, 23, 42, 0.55);
}

.modal {
  width: 100%;
  max-width: 520px;
  background-color: #ffffff;
  border-radius: 14px;
  box-shadow: 0 25px 50px rgba(15, 23, 42, 0.2);
}

.modal-delete {
  max-width: 460px;
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid #e2e8f0;
}

.modal-header h2 {
  margin: 0;
  font-size: 1.25rem;
  color: #1e293b;
}

.modal-close {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  border: none;
  border-radius: 8px;
  background-color: #f1f5f9;
  color: #475569;
  font-size: 1.4rem;
  line-height: 1;
  cursor: pointer;
}

.modal-close:hover:not(:disabled) {
  background-color: #e2e8f0;
}

.modal-body {
  padding: 1.5rem;
}

.form-field {
  display: flex;
  flex-direction: column;
  gap: 0.45rem;
  margin-bottom: 1rem;
}

.form-field span {
  font-size: 0.9rem;
  font-weight: 600;
  color: #334155;
}

.form-field input,
.form-field textarea {
  width: 100%;
  padding: 0.85rem;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  font: inherit;
  box-sizing: border-box;
}

.form-field input:focus,
.form-field textarea:focus {
  outline: none;
  border-color: #4f46e5;
  box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.12);
}

.modal-actions {
  display: flex;
  justify-content: flex-end;
  flex-wrap: wrap;
  gap: 0.75rem;
  margin-top: 1.25rem;
}

.delete-message {
  margin: 0 0 0.75rem;
  color: #334155;
  line-height: 1.6;
}

.delete-warning {
  margin: 0;
  color: #64748b;
  font-size: 0.95rem;
}
.delete-error {
  margin-top: 1rem;
  padding: 0.85rem;
  color: #991b1b;
  background-color: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: 8px;
}

@media (max-width: 768px) {
  .page-header {
    flex-direction: column;
    align-items: stretch;
  }

  .primary-button {
    width: 100%;
  }

  .categories-table thead {
    display: none;
  }

  .categories-table tbody,
  .categories-table tr,
  .categories-table td {
    display: block;
    width: 100%;
  }

  .categories-table tr {
    margin-bottom: 1rem;
    padding: 0.25rem 0;
    border-bottom: 1px solid #e2e8f0;
  }

  .categories-table td {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.75rem 1.25rem;
    border-bottom: none;
    text-align: right;
  }

  .categories-table td::before {
    content: attr(data-label);
    font-weight: 700;
    color: #64748b;
    text-align: left;
  }

  .categories-table td[data-label='İşlemler'] {
    flex-direction: column;
    align-items: stretch;
  }

  .categories-table td[data-label='İşlemler']::before {
    margin-bottom: 0.25rem;
  }

  .row-actions {
    flex-direction: column;
  }

  .action-button {
    width: 100%;
  }

}
</style>
