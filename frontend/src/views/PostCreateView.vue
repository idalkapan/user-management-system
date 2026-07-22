<script setup>
import { reactive, ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '../services/api'
import { getCategories } from '../services/categoryService'

const router = useRouter()

const form = reactive({
  title: '',
  content: '',
  category_id: '',
  status: 'draft',
})

const selectedImage = ref(null)
const imagePreviewUrl = ref('')
const imageInput = ref(null)

const categories = ref([])

const errors = reactive({})

const isSubmitting = ref(false)
const errorMessage = ref('')

const clearErrors = () => {
  Object.keys(errors).forEach((key) => {
    delete errors[key]
  })

  errorMessage.value = ''
}

const handleImageChange = (event) => {
  errorMessage.value = ''

  const file = event.target.files?.[0] ?? null

  if (imagePreviewUrl.value) {
    URL.revokeObjectURL(imagePreviewUrl.value)
    imagePreviewUrl.value = ''
  }

  if (!file) {
    selectedImage.value = null
    return
  }

  const allowedTypes = [
    'image/jpeg',
    'image/png',
    'image/webp',
  ]

  const maximumFileSize = 2 * 1024 * 1024

  if (!allowedTypes.includes(file.type)) {
    selectedImage.value = null
    imageInput.value.value = ''

    errorMessage.value =
      'Kapak görseli JPG, PNG veya WEBP formatında olmalıdır.'

    return
  }

  if (file.size > maximumFileSize) {
    selectedImage.value = null
    imageInput.value.value = ''

    errorMessage.value =
      'Kapak görselinin boyutu en fazla 2 MB olabilir.'

    return
  }

  selectedImage.value = file
  imagePreviewUrl.value = URL.createObjectURL(file)
}

const removeImage = () => {
  selectedImage.value = null

  if (imagePreviewUrl.value) {
    URL.revokeObjectURL(imagePreviewUrl.value)
    imagePreviewUrl.value = ''
  }

  if (imageInput.value) {
    imageInput.value.value = ''
  }
}

const createPost = async () => {
  clearErrors()
  isSubmitting.value = true

  try {
    const formData = new FormData()

    formData.append('title', form.title)
    formData.append('content', form.content)
    formData.append('category_id', form.category_id)
    formData.append('status', form.status)

    if (selectedImage.value) {
      formData.append(
        'featured_image',
        selectedImage.value,
      )
    }

    await api.post('/posts', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    })

    router.push('/my-posts')
  } catch (error) {
    const validationErrors =
      error.response?.data?.errors ?? {}

    if (Object.keys(validationErrors).length > 0) {
      Object.assign(errors, validationErrors)

      errorMessage.value =
        error.response?.data?.message ||
        Object.values(validationErrors).flat()[0] ||
        'Lütfen form alanlarını kontrol edin.'
    } else {
      errorMessage.value =
        error.response?.data?.message ||
        'Blog yazısı oluşturulurken bir hata oluştu.'
    }
  } finally {
    isSubmitting.value = false
  }
}
const loadCategories = async () => {
  try {
    const response = await getCategories()
    categories.value = response.data.categories
  } catch (error) {
    console.error('Kategoriler yüklenemedi:', error)
  }
}

onMounted(() => {
  loadCategories()
})

const goBack = () => {
  router.push('/my-posts')
}
</script>

<template>
  <div class="create-post-page">
    <div class="create-post-container">
      <header class="page-header">
        <div class="header-content">
          <div>
            <h1>Yeni Blog Yazısı</h1>

            <p>
              Yeni bir içerik oluşturun ve taslak olarak
              kaydedin veya onaya gönderin.
            </p>
          </div>
        </div>
      </header>

      <div
        v-if="errorMessage"
        class="alert alert-error"
        role="alert"
      >
        <span class="alert-icon">!</span>
        <span>{{ errorMessage }}</span>
      </div>

      <form
        class="post-form"
        novalidate
        @submit.prevent="createPost"
      >
        <section class="form-card">
          <div class="card-header">
            <div>
              <h2>Yazı Bilgileri</h2>

              <p>
                Blog yazınızın başlığını ve içeriğini girin.
              </p>
            </div>

            <span class="required-info">
              * Zorunlu alanlar
            </span>
          </div>

          <div class="form-group">
            <label for="title">
              Yazı Başlığı
              <span class="required-mark">*</span>
            </label>

            <input
              id="title"
              v-model.trim="form.title"
              type="text"
              placeholder="Blog yazınız için bir başlık girin"
              maxlength="255"
              :class="{ 'input-error': errors.title }"
            />

            <div class="field-bottom">
              <p
                v-if="errors.title"
                class="field-error"
              >
                {{ errors.title[0] }}
              </p>

              <span class="character-count">
                {{ form.title.length }}/255
              </span>
            </div>
          </div>

          <div class="form-group">
            <label for="content">
              Yazı İçeriği
              <span class="required-mark">*</span>
            </label>

            <textarea
              id="content"
              v-model.trim="form.content"
              rows="13"
              placeholder="Blog yazınızın içeriğini buraya yazın..."
              :class="{ 'input-error': errors.content }"
            ></textarea>

            <p
              v-if="errors.content"
              class="field-error"
            >
              {{ errors.content[0] }}
            </p>
          </div>

          <div class="form-group">
            <label for="category">
               Kategori
               <span class="required-mark">*</span>
            </label>
          
          <select
             id="category"
             v-model="form.category_id"
             :class="{ 'input-error': errors.category_id }"
          >
          <option value="">Kategori Seçiniz</option>

          <option
              v-for="category in categories"
              :key="category.id"
              :value="category.id"
          >
              {{ category.name }}
          </option>
          </select>

        <p
           v-if="errors.category_id"
           class="field-error"
        >
    
           {{ errors.category_id[0] }}
        </p>
        </div>
        </section>

        <section class="form-card">
          <div class="card-header">
            <div>
              <h2>Yayın Ayarları</h2>

              <p>
                Yazınızın durumunu ve kapak görselini belirleyin.
              </p>
            </div>
          </div>

          <div class="form-group">
            <label>Yayın Durumu</label>

            <div class="status-options">
              <label
                class="status-option"
                :class="{ selected: form.status === 'draft' }"
              >
                <input
                  v-model="form.status"
                  type="radio"
                  value="draft"
                />

                <span class="status-icon">📄</span>

                <span class="status-content">
                  <strong>Taslak Olarak Kaydet</strong>

                  <small>
                    Yazı yalnızca yönetim panelinde görünür.
                  </small>
                </span>
              </label>

              <label
                class="status-option"
                :class="{
                  selected: form.status === 'pending',
                  }"
              >
                <input
                  v-model="form.status"
                  type="radio"
                  value="pending"
                />

                <span class="status-icon">✓</span>

                <span class="status-content">
                  <strong>Onaya Gönder</strong>

                  <small>
                    Yazınız admin onayına gönderilir. Onaylandıktan sonra yayınlanır.
                  </small>
                </span>
              </label>
            </div>

            <p
              v-if="errors.status"
              class="field-error"
            >
              {{ errors.status[0] }}
            </p>
          </div>

          <div class="form-group">
            <label for="featured-image">
              Kapak Görseli
              <span class="optional-text">
                İsteğe bağlı
              </span>
            </label>

            <div
              v-if="imagePreviewUrl"
              class="image-preview"
            >
              <img
                :src="imagePreviewUrl"
                alt="Kapak görseli önizleme"
              />

              <button
                type="button"
                class="remove-image-button"
                @click="removeImage"
              >
                Görseli Kaldır
              </button>
            </div>

            <label
              v-else
              for="featured-image"
              class="image-upload-area"
            >
              <span class="upload-icon">🖼️</span>

              <strong>Kapak görseli seçin</strong>

              <span>
                JPG, PNG veya WEBP · En fazla 2 MB
              </span>
            </label>

            <input
              id="featured-image"
              ref="imageInput"
              type="file"
              class="hidden-file-input"
              accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
              @change="handleImageChange"
            />

            <p
              v-if="errors.featured_image"
              class="field-error"
            >
              {{ errors.featured_image[0] }}
            </p>
          </div>
        </section>

        <div class="form-actions">
          <button
            type="button"
            class="cancel-button"
            :disabled="isSubmitting"
            @click="goBack"
          >
            İptal
          </button>

          <button
            type="submit"
            class="submit-button"
            :disabled="isSubmitting"
          >
          {{
          isSubmitting
          ? 'Yazı kaydediliyor...'
          : form.status === 'pending'
             ? 'Admin Onayına Gönder'
             : 'Taslak Olarak Kaydet'
             }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<style scoped>
.create-post-page {
  padding: 2rem;
  box-sizing: border-box;
}

.create-post-container {
  width: 100%;
  max-width: 1100px;
  margin: 0 auto;
}

.page-header {
  margin-bottom: 1.5rem;
}

.header-content {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 1.5rem;
}

.header-content h1 {
  margin: 0;
  color: #1a1a2e;
  font-size: 1.9rem;
  font-weight: 700;
  letter-spacing: -0.03em;
}

.header-content p {
  max-width: 650px;
  margin: 0.5rem 0 0;
  color: #718096;
  font-size: 0.9375rem;
  line-height: 1.6;
}

.alert {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  padding: 1rem 1.25rem;
  margin-bottom: 1.25rem;
  border-radius: 10px;
  font-size: 0.9rem;
  line-height: 1.5;
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

.post-form {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.form-card {
  padding: 1.75rem;
  background-color: #ffffff;
  border-radius: 12px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
}

.card-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  padding-bottom: 1.25rem;
  margin-bottom: 1.5rem;
  border-bottom: 1px solid #e2e8f0;
}

.card-header h2 {
  margin: 0;
  color: #1a1a2e;
  font-size: 1.15rem;
  font-weight: 700;
}

.card-header p {
  margin: 0.4rem 0 0;
  color: #718096;
  font-size: 0.85rem;
  line-height: 1.5;
}

.required-info {
  color: #a0aec0;
  font-size: 0.75rem;
  white-space: nowrap;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 0.55rem;
}

.form-group + .form-group {
  margin-top: 1.5rem;
}

.form-group label {
  color: #4a5568;
  font-size: 0.875rem;
  font-weight: 600;
}

.required-mark {
  color: #dc2626;
}

.optional-text {
  margin-left: 0.35rem;
  color: #a0aec0;
  font-size: 0.75rem;
  font-weight: 400;
}

.form-group input[type='text'],
.form-group select,
.form-group textarea {
  width: 100%;
  padding: 0.8rem 1rem;
  color: #1a1a2e;
  background-color: #f8fafc;
  border: 1.5px solid #e2e8f0;
  border-radius: 8px;
  box-sizing: border-box;
  outline: none;
  font-family: inherit;
  font-size: 0.95rem;
  transition:
    border-color 0.2s ease,
    box-shadow 0.2s ease,
    background-color 0.2s ease;
}

.form-group textarea {
  min-height: 260px;
  resize: vertical;
  line-height: 1.65;
}

.form-group input::placeholder,
.form-group textarea::placeholder {
  color: #a0aec0;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
  background-color: #ffffff;
  border-color: #4f6ef7;
  box-shadow: 0 0 0 3px rgba(79, 110, 247, 0.15);
}

.form-group input.input-error,
.form-group select.input-error,
.form-group textarea.input-error {
  background-color: #fffafa;
  border-color: #e53e3e;
}

.field-bottom {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
}

.field-error {
  margin: 0;
  color: #c53030;
  font-size: 0.8rem;
  line-height: 1.4;
}

.character-count {
  margin-left: auto;
  color: #a0aec0;
  font-size: 0.75rem;
}

.status-options {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1rem;
}

.status-option {
  display: flex;
  align-items: flex-start;
  gap: 0.85rem;
  padding: 1.15rem;
  background-color: #ffffff;
  border: 2px solid #e2e8f0;
  border-radius: 10px;
  cursor: pointer;
  transition:
    border-color 0.2s ease,
    background-color 0.2s ease,
    box-shadow 0.2s ease;
}

.status-option:hover {
  border-color: #c7d2fe;
  background-color: #f8faff;
}

.status-option.selected {
  border-color: #4f6ef7;
  background-color: #f8faff;
  box-shadow: 0 3px 12px rgba(79, 110, 247, 0.1);
}

.status-option input {
  margin-top: 0.25rem;
  accent-color: #4f6ef7;
}

.status-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  flex-shrink: 0;
  color: #4f6ef7;
  background-color: #eef2ff;
  border-radius: 8px;
  font-size: 1rem;
  font-weight: 700;
}

.status-content {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
}

.status-content strong {
  color: #1a1a2e;
  font-size: 0.875rem;
}

.status-content small {
  color: #718096;
  font-size: 0.78rem;
  font-weight: 400;
  line-height: 1.5;
}

.hidden-file-input {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  opacity: 0;
  pointer-events: none;
}

.image-upload-area {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 170px;
  padding: 1.5rem;
  color: #718096;
  background-color: #f8fafc;
  border: 2px dashed #cbd5e0;
  border-radius: 10px;
  text-align: center;
  cursor: pointer;
  box-sizing: border-box;
  transition:
    border-color 0.2s ease,
    background-color 0.2s ease;
}

.image-upload-area:hover {
  background-color: #f8faff;
  border-color: #4f6ef7;
}

.image-upload-area strong {
  margin: 0.65rem 0 0.25rem;
  color: #4f6ef7;
  font-size: 0.9rem;
}

.image-upload-area span:last-child {
  color: #a0aec0;
  font-size: 0.78rem;
}

.upload-icon {
  font-size: 2rem;
}

.image-preview {
  position: relative;
  overflow: hidden;
  border: 1.5px solid #e2e8f0;
  border-radius: 10px;
}

.image-preview img {
  display: block;
  width: 100%;
  max-height: 360px;
  object-fit: cover;
}

.remove-image-button {
  position: absolute;
  right: 1rem;
  bottom: 1rem;
  padding: 0.6rem 0.9rem;
  color: #ffffff;
  background-color: rgba(220, 38, 38, 0.92);
  border: none;
  border-radius: 7px;
  font-size: 0.78rem;
  font-weight: 600;
  cursor: pointer;
}

.remove-image-button:hover {
  background-color: #b91c1c;
}

.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
  padding: 1.25rem 1.5rem;
  background-color: #ffffff;
  border-radius: 12px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
}

.cancel-button,
.submit-button {
  padding: 0.8rem 1.4rem;
  border-radius: 8px;
  font-size: 0.9rem;
  font-weight: 600;
  cursor: pointer;
  transition:
    background-color 0.2s ease,
    border-color 0.2s ease,
    opacity 0.2s ease;
}

.cancel-button {
  color: #4a5568;
  background-color: #ffffff;
  border: 1.5px solid #e2e8f0;
}

.cancel-button:hover:not(:disabled) {
  background-color: #f8fafc;
  border-color: #cbd5e0;
}

.submit-button {
  color: #ffffff;
  background-color: #4f6ef7;
  border: none;
  box-shadow: 0 4px 12px rgba(79, 110, 247, 0.2);
}

.submit-button:hover:not(:disabled) {
  background-color: #3b5de7;
}

.cancel-button:disabled,
.submit-button:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}

@media (max-width: 700px) {
  .create-post-page {
    padding: 1.25rem 1rem;
  }

  .header-content h1 {
    font-size: 1.55rem;
  }

  .form-card {
    padding: 1.25rem;
  }

  .card-header {
    flex-direction: column;
  }

  .status-options {
    grid-template-columns: 1fr;
  }

  .form-actions {
    flex-direction: column-reverse;
    padding: 1.25rem;
  }

  .cancel-button,
  .submit-button {
    width: 100%;
  }
}
</style>