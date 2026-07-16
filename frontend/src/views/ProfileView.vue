<template>
  <div class="profile-page">
    <div class="profile-container">
      <header class="profile-header">
        <div class="header-top">
          <h1 class="page-title">Profilim</h1>

          <button
            type="button"
            class="logout-button"
            :disabled="isLoggingOut"
            @click="logout"
          >
            {{ isLoggingOut ? 'Çıkış yapılıyor...' : 'Çıkış Yap' }}
          </button>
        </div>

        <p class="page-subtitle">
          Hesap bilgilerinizi ve güvenlik ayarlarınızı yönetin
        </p>
      </header>

      <div v-if="isLoading" class="loading-state">
        <div class="loading-spinner"></div>
        <p>Bilgiler yükleniyor...</p>
      </div>

      <template v-if="!isLoading">
        <div v-if="errorMessage" class="alert alert-error" role="alert">
          <span class="alert-icon">!</span>
          <span>{{ errorMessage }}</span>
        </div>

        <div v-if="successMessage" class="alert alert-success" role="alert">
          <span class="alert-icon">✓</span>
          <span>{{ successMessage }}</span>
        </div>

        <section class="profile-summary">
          <div class="avatar-wrapper">
            <img
              v-if="savedProfilePhotoUrl"
              :src="savedProfilePhotoUrl"
              alt="Profil fotoğrafı"
              class="avatar-image"
            />

            <div v-else class="avatar-placeholder">
              {{
                savedProfile.name
                  ? savedProfile.name.charAt(0).toUpperCase()
                  : '?'
              }}
            </div>
          </div>

          <div class="summary-details">
            <h2 class="summary-name">
              {{ savedProfile.name || '—' }}
            </h2>

            <p class="summary-email">
              {{ savedProfile.email || '—' }}
            </p>
          </div>
        </section>

        <section class="action-cards">
          <button
            type="button"
            class="action-card"
            :class="{ active: activeSection === 'profile' }"
            @click="openProfileSection"
          >
            <span class="action-card-icon">✎</span>
            <span class="action-card-label">Profili Düzenle</span>
            <span class="action-card-desc">
              Ad ve e-posta bilgilerini güncelle
            </span>
          </button>

          <button
            type="button"
            class="action-card"
            :class="{ active: activeSection === 'password' }"
            @click="openPasswordSection"
          >
            <span class="action-card-icon">🔒</span>
            <span class="action-card-label">Şifre Değiştir</span>
            <span class="action-card-desc">
              Hesap şifrenizi yenileyin
            </span>
          </button>

          <button
            type="button"
            class="action-card"
            :class="{ active: activeSection === 'photo' }"
            @click="openPhotoSection"
          >
            <span class="action-card-icon">📷</span>
            <span class="action-card-label">Profil Fotoğrafı</span>
            <span class="action-card-desc">
              Profil görselinizi yükleyin
            </span>
          </button>
          <RouterLink
          v-if="savedProfile.role === 'admin'"
          to="/posts"
          class="action-card"
          >
          <span class="action-card-icon">📝</span>
          <span class="action-card-label">Blog Yönetimi</span>
          <span class="action-card-desc">
            Blog yazılarınızı yönetin
          </span>
        </RouterLink>

        <RouterLink
          v-if="savedProfile.role === 'user'"
          to="/my-posts"
          class="action-card"
        >
        
          <span class="action-card-icon">📰</span>
          <span class="action-card-label">Yazılarım</span>
          <span class="action-card-desc">
            Oluşturduğunuz blog yazılarınızı yönetin.
          </span>

      </RouterLink>

        </section>

        <section
          v-if="activeSection === 'profile'"
          class="section-panel"
        >
          <div class="section-panel-header">
            <h3>Profili Düzenle</h3>

            <button
              type="button"
              class="close-button"
              @click="closeProfileSection"
            >
              Kapat
            </button>
           

          </div>

          <form
            class="section-form"
            novalidate
            @submit.prevent="updateProfile"
          >
            <div class="form-group">
              <label for="name">Ad Soyad</label>

              <input
                id="name"
                v-model.trim="profileForm.name"
                type="text"
                autocomplete="name"
                placeholder="Adınızı ve soyadınızı girin"
                :class="{ 'input-error': profileErrors.name }"
              />

              <p
                v-if="profileErrors.name"
                class="field-error"
              >
                {{ profileErrors.name[0] }}
              </p>
            </div>

            <div class="form-group">
              <label for="email">E-posta</label>

              <input
                id="email"
                v-model.trim="profileForm.email"
                type="email"
                autocomplete="email"
                placeholder="ornek@email.com"
                :class="{ 'input-error': profileErrors.email }"
              />

              <p
                v-if="profileErrors.email"
                class="field-error"
              >
                {{ profileErrors.email[0] }}
              </p>
            </div>

            <div class="form-actions">
              <button
                type="button"
                class="btn-secondary"
                :disabled="isUpdating"
                @click="closeProfileSection"
              >
                İptal
              </button>

              <button
                type="submit"
                class="btn-primary"
                :disabled="isUpdating"
              >
                {{
                  isUpdating
                    ? 'Güncelleniyor...'
                    : 'Profili Güncelle'
                }}
              </button>
            </div>
          </form>
        </section>

        <section
          v-if="activeSection === 'password'"
          class="section-panel"
        >
          <div class="section-panel-header">
            <h3>Şifre Değiştir</h3>

            <button
              type="button"
              class="close-button"
              @click="closePasswordSection"
            >
              Kapat
            </button>
          </div>

          <form
            class="section-form"
            novalidate
            @submit.prevent="changePassword"
          >
            <div class="form-group">
              <label for="current-password">Mevcut Şifre</label>

              <input
                id="current-password"
                v-model="passwordForm.current_password"
                type="password"
                autocomplete="current-password"
                placeholder="Mevcut şifrenizi girin"
                :class="{
                  'input-error': passwordErrors.current_password,
                }"
              />

              <p
                v-if="passwordErrors.current_password"
                class="field-error"
              >
                {{ passwordErrors.current_password[0] }}
              </p>
            </div>

            <div class="form-group">
              <label for="new-password">Yeni Şifre</label>

              <input
                id="new-password"
                v-model="passwordForm.password"
                type="password"
                autocomplete="new-password"
                placeholder="En az 8 karakter"
                :class="{ 'input-error': passwordErrors.password }"
              />

              <p
                v-if="passwordErrors.password"
                class="field-error"
              >
                {{ passwordErrors.password[0] }}
              </p>
            </div>

            <div class="form-group">
              <label for="password-confirmation">
                Yeni Şifre Tekrar
              </label>

              <input
                id="password-confirmation"
                v-model="passwordForm.password_confirmation"
                type="password"
                autocomplete="new-password"
                placeholder="Yeni şifrenizi tekrar girin"
                :class="{
                  'input-error': passwordErrors.password_confirmation,
                }"
              />

              <p
                v-if="passwordErrors.password_confirmation"
                class="field-error"
              >
                {{ passwordErrors.password_confirmation[0] }}
              </p>
            </div>

            <div class="form-actions">
              <button
                type="button"
                class="btn-secondary"
                :disabled="isChangingPassword"
                @click="closePasswordSection"
              >
                İptal
              </button>

              <button
                type="submit"
                class="btn-primary"
                :disabled="isChangingPassword"
              >
                {{
                  isChangingPassword
                    ? 'Şifre değiştiriliyor...'
                    : 'Şifreyi Değiştir'
                }}
              </button>
            </div>
          </form>
        </section>

        <section
          v-if="activeSection === 'photo'"
          class="section-panel"
        >
          <div class="section-panel-header">
            <h3>Profil Fotoğrafı</h3>

            <button
              type="button"
              class="close-button"
              @click="closePhotoSection"
            >
              Kapat
            </button>
          </div>

          <div class="photo-section">
            <div class="photo-preview">
              <img
                v-if="previewPhotoUrl || savedProfilePhotoUrl"
                :src="previewPhotoUrl || savedProfilePhotoUrl"
                alt="Profil fotoğrafı önizleme"
              />

              <div v-else class="photo-preview-placeholder">
                {{
                  savedProfile.name
                    ? savedProfile.name.charAt(0).toUpperCase()
                    : '?'
                }}
              </div>
            </div>

            <form
              class="section-form"
              novalidate
              @submit.prevent="uploadPhoto"
            >
              <div class="form-group">
                <label for="profile-photo">Fotoğraf Seç</label>

                <input
                  id="profile-photo"
                  ref="photoInput"
                  type="file"
                  accept=".jpg,.jpeg,.png,image/jpeg,image/png"
                  @change="handlePhotoChange"
                />
              </div>

              <p class="photo-info">
                JPG, JPEG veya PNG formatında ve en fazla 2 MB olmalıdır.
              </p>

              <div class="form-actions">
                <button
                  type="button"
                  class="btn-secondary"
                  :disabled="isUploadingPhoto"
                  @click="closePhotoSection"
                >
                  İptal
                </button>

                <button
                  type="submit"
                  class="btn-primary"
                  :disabled="isUploadingPhoto || !selectedPhoto"
                >
                  {{
                    isUploadingPhoto
                      ? 'Fotoğraf yükleniyor...'
                      : 'Fotoğrafı Yükle / Güncelle'
                  }}
                </button>
              </div>
            </form>
          </div>
        </section>
      </template>
    </div>
  </div>
</template>

<script setup>
import {
  onBeforeUnmount,
  onMounted,
  reactive,
  ref,
  watch,
} from 'vue'
import { useRouter } from 'vue-router'
import api from '../services/api'

const router = useRouter()

const isLoading = ref(true)
const isUpdating = ref(false)
const isChangingPassword = ref(false)
const isUploadingPhoto = ref(false)
const isLoggingOut = ref(false)

const errorMessage = ref('')
const successMessage = ref('')

const activeSection = ref(null)

const savedProfile = reactive({
  name: '',
  email: '',
  role: '',
})

const profileForm = reactive({
  name: '',
  email: '',
})

const profileErrors = reactive({})

const passwordForm = reactive({
  current_password: '',
  password: '',
  password_confirmation: '',
})

const passwordErrors = reactive({})

const selectedPhoto = ref(null)
const profilePhotoUrl = ref('')
const savedProfilePhotoUrl = ref('')
const previewPhotoUrl = ref('')
const photoInput = ref(null)

const clearReactiveObject = (object) => {
  Object.keys(object).forEach((key) => {
    delete object[key]
  })
}

const clearMessages = () => {
  errorMessage.value = ''
  successMessage.value = ''
}

const revokePreviewPhotoUrl = () => {
  if (
    previewPhotoUrl.value &&
    previewPhotoUrl.value.startsWith('blob:')
  ) {
    URL.revokeObjectURL(previewPhotoUrl.value)
  }

  previewPhotoUrl.value = ''
}

watch(
  profilePhotoUrl,
  (url) => {
    if (!selectedPhoto.value) {
      savedProfilePhotoUrl.value = url
    }
  },
  { immediate: true },
)

watch(selectedPhoto, (file) => {
  revokePreviewPhotoUrl()

  if (file) {
    previewPhotoUrl.value = URL.createObjectURL(file)
  }
})

const resetProfileForm = () => {
  profileForm.name = savedProfile.name
  profileForm.email = savedProfile.email
  clearReactiveObject(profileErrors)
}

const resetPasswordForm = () => {
  passwordForm.current_password = ''
  passwordForm.password = ''
  passwordForm.password_confirmation = ''
  clearReactiveObject(passwordErrors)
}

const resetPhotoSelection = () => {
  selectedPhoto.value = null
  profilePhotoUrl.value = savedProfilePhotoUrl.value
  revokePreviewPhotoUrl()

  if (photoInput.value) {
    photoInput.value.value = ''
  }
}

const openProfileSection = () => {
  clearMessages()
  resetProfileForm()
  activeSection.value = 'profile'
}

const closeProfileSection = () => {
  resetProfileForm()
  errorMessage.value = ''
  activeSection.value = null
}

const openPasswordSection = () => {
  clearMessages()
  resetPasswordForm()
  activeSection.value = 'password'
}

const closePasswordSection = () => {
  resetPasswordForm()
  errorMessage.value = ''
  activeSection.value = null
}

const openPhotoSection = () => {
  clearMessages()
  resetPhotoSelection()
  activeSection.value = 'photo'
}

const closePhotoSection = () => {
  resetPhotoSelection()
  errorMessage.value = ''
  activeSection.value = null
}

const getProfile = async () => {
  errorMessage.value = ''

  try {
    const response = await api.get('/profile')
    const user = response.data.data ?? response.data
    console.log('Profil kullanıcısı:', user)

    savedProfile.name = user.name ?? ''
    savedProfile.role = user.role ?? ''
    savedProfile.email = user.email ?? ''

    resetProfileForm()

    if (user.profile_photo_url) {
      profilePhotoUrl.value = user.profile_photo_url
    } else if (user.profile_photo) {
      profilePhotoUrl.value = user.profile_photo.startsWith('http')
        ? user.profile_photo
        : `http://localhost:8000/storage/${user.profile_photo}`
    } else {
      profilePhotoUrl.value = ''
    }

    savedProfilePhotoUrl.value = profilePhotoUrl.value
  } catch (error) {
    errorMessage.value =
      error.response?.data?.message ||
      'Profil bilgileri alınamadı.'
  } finally {
    isLoading.value = false
  }
}

const updateProfile = async () => {
  clearMessages()
  clearReactiveObject(profileErrors)
  isUpdating.value = true

  try {
    const response = await api.put('/profile', {
      name: profileForm.name,
      email: profileForm.email,
    })

    const user = response.data.data ?? response.data

    savedProfile.name = user.name ?? profileForm.name
    savedProfile.email = user.email ?? profileForm.email

    resetProfileForm()

    successMessage.value =
      response.data.message ||
      'Profil başarıyla güncellendi.'

    activeSection.value = null
  } catch (error) {
    const validationErrors =
      error.response?.data?.errors ?? {}

    if (Object.keys(validationErrors).length > 0) {
      Object.assign(profileErrors, validationErrors)

      errorMessage.value =
        error.response?.data?.message ||
        Object.values(validationErrors).flat()[0] ||
        'Lütfen bilgilerinizi kontrol edin.'
    } else {
      errorMessage.value =
        error.response?.data?.message ||
        'Profil güncellenemedi.'
    }
  } finally {
    isUpdating.value = false
  }
}

const changePassword = async () => {
  clearMessages()
  clearReactiveObject(passwordErrors)
  isChangingPassword.value = true

  try {
    const response = await api.put('/change-password', {
      current_password: passwordForm.current_password,
      password: passwordForm.password,
      password_confirmation:
        passwordForm.password_confirmation,
    })

    successMessage.value =
      response.data.message ||
      'Şifre başarıyla değiştirildi.'

    resetPasswordForm()
    activeSection.value = null
  } catch (error) {
    const validationErrors =
      error.response?.data?.errors ?? {}

    if (Object.keys(validationErrors).length > 0) {
      Object.assign(passwordErrors, validationErrors)

      errorMessage.value =
        error.response?.data?.message ||
        Object.values(validationErrors).flat()[0] ||
        'Lütfen şifre bilgilerinizi kontrol edin.'
    } else {
      errorMessage.value =
        error.response?.data?.message ||
        'Şifre değiştirilemedi.'
    }
  } finally {
    isChangingPassword.value = false
  }
}

const handlePhotoChange = (event) => {
  clearMessages()

  const file = event.target.files?.[0] ?? null

  if (!file) {
    resetPhotoSelection()
    return
  }

  const allowedTypes = ['image/jpeg', 'image/png']
  const maximumFileSize = 2 * 1024 * 1024

  if (!allowedTypes.includes(file.type)) {
    resetPhotoSelection()
    errorMessage.value =
      'Lütfen JPG, JPEG veya PNG formatında bir fotoğraf seçin.'
    return
  }

  if (file.size > maximumFileSize) {
    resetPhotoSelection()
    errorMessage.value =
      'Profil fotoğrafı en fazla 2 MB olabilir.'
    return
  }

  selectedPhoto.value = file
}

const uploadPhoto = async () => {
  clearMessages()

  if (!selectedPhoto.value) {
    errorMessage.value = 'Lütfen bir fotoğraf seçin.'
    return
  }

  isUploadingPhoto.value = true

  try {
    const formData = new FormData()
    formData.append('photo', selectedPhoto.value)

    const response = await api.post(
      '/profile/photo',
      formData,
      {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      },
    )

    successMessage.value =
      response.data.message ||
      'Profil fotoğrafı başarıyla yüklendi.'

    resetPhotoSelection()
    await getProfile()
    activeSection.value = null
  } catch (error) {
    const validationErrors =
      error.response?.data?.errors ?? {}

    if (Object.keys(validationErrors).length > 0) {
      errorMessage.value =
        Object.values(validationErrors).flat()[0]
    } else {
      errorMessage.value =
        error.response?.data?.message ||
        'Profil fotoğrafı yüklenemedi.'
    }
  } finally {
    isUploadingPhoto.value = false
  }
}

const logout = async () => {
  clearMessages()
  isLoggingOut.value = true

  try {
    await api.post('/logout')
  } catch (error) {
    console.error(
      'Çıkış isteği başarısız oldu:',
      error,
    )
  } finally {
    localStorage.removeItem('token')
    isLoggingOut.value = false
    router.push('/login')
  }
}

onMounted(() => {
  getProfile()
})

onBeforeUnmount(() => {
  revokePreviewPhotoUrl()
})
</script>

<style scoped>
.profile-page {
  min-height: 100vh;
  background-color: #f0f4f8;
  padding: 2rem 1.5rem 3rem;
  box-sizing: border-box;
}

.profile-container {
  width: 100%;
  max-width: 720px;
  margin: 0 auto;
}

.profile-header {
  margin-bottom: 1.75rem;
}

.header-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  flex-wrap: wrap;
}

.page-title {
  margin: 0;
  font-size: 1.75rem;
  font-weight: 600;
  color: #1a1a2e;
  letter-spacing: -0.02em;
}

.page-subtitle {
  margin: 0.5rem 0 0;
  font-size: 0.9375rem;
  color: #718096;
}

.logout-button {
  padding: 0.625rem 1.25rem;
  font-size: 0.875rem;
  font-weight: 600;
  color: #ffffff;
  background-color: #dc2626;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  transition:
    background-color 0.2s ease,
    opacity 0.2s ease;
  white-space: nowrap;
}

.logout-button:hover:not(:disabled) {
  background-color: #b91c1c;
}

.logout-button:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}

.loading-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1rem;
  padding: 3rem 1rem;
  background-color: #ffffff;
  border-radius: 12px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
  color: #718096;
}

.loading-spinner {
  width: 36px;
  height: 36px;
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

.alert {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  padding: 1rem 1.25rem;
  border-radius: 10px;
  font-size: 0.9375rem;
  margin-bottom: 1.25rem;
  line-height: 1.5;
}

.alert-icon {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 1.375rem;
  height: 1.375rem;
  border-radius: 50%;
  font-size: 0.75rem;
  font-weight: 700;
}

.alert-error {
  background-color: #fef2f2;
  border: 1px solid #fecaca;
  color: #991b1b;
}

.alert-error .alert-icon {
  background-color: #fee2e2;
  color: #dc2626;
}

.alert-success {
  background-color: #f0fdf4;
  border: 1px solid #bbf7d0;
  color: #166534;
}

.alert-success .alert-icon {
  background-color: #dcfce7;
  color: #16a34a;
}

.profile-summary {
  display: flex;
  align-items: center;
  gap: 1.5rem;
  padding: 2rem;
  background-color: #ffffff;
  border-radius: 12px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
  margin-bottom: 1.5rem;
}

.avatar-wrapper {
  flex-shrink: 0;
}

.avatar-image {
  width: 88px;
  height: 88px;
  border-radius: 50%;
  object-fit: cover;
  border: 3px solid #e2e8f0;
}

.avatar-placeholder {
  width: 88px;
  height: 88px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #4f6ef7, #6b8cff);
  color: #ffffff;
  font-size: 2rem;
  font-weight: 600;
  border: 3px solid #e2e8f0;
}

.summary-details {
  min-width: 0;
}

.summary-name {
  margin: 0 0 0.375rem;
  font-size: 1.375rem;
  font-weight: 600;
  color: #1a1a2e;
  word-break: break-word;
}

.summary-email {
  margin: 0;
  font-size: 0.9375rem;
  color: #718096;
  word-break: break-all;
}

.action-cards {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.action-card {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.375rem;
  padding: 1.25rem;
  background-color: #ffffff;
  border: 2px solid #e2e8f0;
  border-radius: 12px;
  cursor: pointer;
  text-align: left;
  transition:
    border-color 0.2s ease,
    box-shadow 0.2s ease,
    background-color 0.2s ease;
}

.action-card:hover {
  border-color: #c7d2fe;
  box-shadow: 0 4px 16px rgba(79, 110, 247, 0.1);
}

.action-card.active {
  border-color: #4f6ef7;
  background-color: #f8faff;
  box-shadow: 0 4px 16px rgba(79, 110, 247, 0.12);
}

.action-card-icon {
  font-size: 1.25rem;
  line-height: 1;
}

.action-card-label {
  font-size: 0.9375rem;
  font-weight: 600;
  color: #1a1a2e;
}

.action-card-desc {
  font-size: 0.8125rem;
  color: #718096;
  line-height: 1.4;
}

.section-panel {
  background-color: #ffffff;
  border-radius: 12px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
  padding: 1.75rem;
  animation: fade-in 0.2s ease;
}

@keyframes fade-in {
  from {
    opacity: 0;
    transform: translateY(6px);
  }

  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.section-panel-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1.5rem;
  padding-bottom: 1rem;
  border-bottom: 1px solid #e2e8f0;
}

.section-panel-header h3 {
  margin: 0;
  font-size: 1.125rem;
  font-weight: 600;
  color: #1a1a2e;
}

.close-button {
  padding: 0.375rem 0.875rem;
  font-size: 0.8125rem;
  font-weight: 500;
  color: #718096;
  background-color: transparent;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  cursor: pointer;
  transition:
    color 0.2s ease,
    border-color 0.2s ease,
    background-color 0.2s ease;
}

.close-button:hover {
  color: #4a5568;
  border-color: #cbd5e0;
  background-color: #f8fafc;
}

.section-form {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.form-group label {
  font-size: 0.875rem;
  font-weight: 500;
  color: #4a5568;
}

.form-group input[type='text'],
.form-group input[type='email'],
.form-group input[type='password'] {
  width: 100%;
  padding: 0.75rem 1rem;
  font-size: 1rem;
  color: #1a1a2e;
  background-color: #f8fafc;
  border: 1.5px solid #e2e8f0;
  border-radius: 8px;
  box-sizing: border-box;
  outline: none;
  transition:
    border-color 0.2s ease,
    box-shadow 0.2s ease,
    background-color 0.2s ease;
}

.form-group input[type='file'] {
  width: 100%;
  font-size: 0.875rem;
  color: #4a5568;
}

.form-group input::placeholder {
  color: #a0aec0;
}

.form-group input:focus {
  border-color: #4f6ef7;
  box-shadow: 0 0 0 3px rgba(79, 110, 247, 0.15);
  background-color: #ffffff;
}

.form-group input.input-error {
  border-color: #e53e3e;
  background-color: #fffafa;
}

.form-group input.input-error:focus {
  border-color: #e53e3e;
  box-shadow: 0 0 0 3px rgba(229, 62, 62, 0.12);
}

.field-error {
  margin: 0;
  color: #c53030;
  font-size: 0.8rem;
  line-height: 1.4;
}

.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
  margin-top: 0.5rem;
  flex-wrap: wrap;
}

.btn-primary,
.btn-secondary {
  padding: 0.75rem 1.5rem;
  font-size: 0.9375rem;
  font-weight: 600;
  border-radius: 8px;
  cursor: pointer;
  transition:
    background-color 0.2s ease,
    border-color 0.2s ease,
    opacity 0.2s ease;
}

.btn-primary {
  color: #ffffff;
  background-color: #4f6ef7;
  border: none;
}

.btn-primary:hover:not(:disabled) {
  background-color: #3b5de7;
}

.btn-secondary {
  color: #4a5568;
  background-color: #ffffff;
  border: 1.5px solid #e2e8f0;
}

.btn-secondary:hover:not(:disabled) {
  background-color: #f8fafc;
  border-color: #cbd5e0;
}

.btn-primary:disabled,
.btn-secondary:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}

.photo-section {
  display: flex;
  flex-direction: column;
  align-items: center;
}

.photo-preview {
  margin-bottom: 1.5rem;
}

.photo-preview img {
  width: 120px;
  height: 120px;
  border-radius: 50%;
  object-fit: cover;
  border: 3px solid #e2e8f0;
}

.photo-preview-placeholder {
  width: 120px;
  height: 120px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #4f6ef7, #6b8cff);
  color: #ffffff;
  font-size: 2.5rem;
  font-weight: 600;
  border: 3px solid #e2e8f0;
}

.photo-section .section-form {
  width: 100%;
}

.photo-info {
  margin: -0.5rem 0 0;
  font-size: 0.8125rem;
  color: #718096;
}

@media (max-width: 640px) {
  .profile-page {
    padding: 1.25rem 1rem 2rem;
  }

  .page-title {
    font-size: 1.5rem;
  }

  .header-top {
    flex-direction: column;
    align-items: stretch;
  }

  .logout-button {
    width: 100%;
    text-align: center;
  }

  .profile-summary {
    flex-direction: column;
    text-align: center;
    padding: 1.5rem;
    gap: 1rem;
  }

  .action-cards {
    grid-template-columns: 1fr;
  }

  .action-card {
    flex-direction: row;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.5rem 0.75rem;
  }

  .action-card-icon {
    font-size: 1.125rem;
  }

  .action-card-desc {
    width: 100%;
    padding-left: 1.75rem;
  }

  .section-panel {
    padding: 1.25rem;
  }

  .form-actions {
    flex-direction: column-reverse;
  }

  .btn-primary,
  .btn-secondary {
    width: 100%;
    text-align: center;
  }
}
</style>