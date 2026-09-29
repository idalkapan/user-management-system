<template>
  <div class="auth-page">
    <div class="auth-card">
      <div class="auth-header">
        <h1 class="auth-title">Şifreyi Sıfırla</h1>
        <p class="auth-subtitle">Yeni şifrenizi belirleyin.</p>
      </div>

      <p v-if="!hasResetLink" class="general-error">
        Bu şifre sıfırlama bağlantısı geçersiz veya süresi dolmuş.
      </p>

      <template v-else>
        <p v-if="rateLimitMessage" class="general-error">
          {{ rateLimitMessage }}
        </p>
        <p v-else-if="errorMessage" class="general-error">
          {{ errorMessage }}
        </p>

        <form class="auth-form" @submit.prevent="submit">
          <div class="form-group">
            <label for="password">Yeni Şifre</label>
            <PasswordField
              id="password"
              v-model="password"
              autocomplete="new-password"
              placeholder="En az 8 karakter"
              :invalid="Boolean(fieldErrors.password)"
            />
            <PasswordStrength :password="password" />
            <p v-if="fieldErrors.password" class="field-error">
              {{ fieldErrors.password[0] }}
            </p>
          </div>

          <div class="form-group">
            <label for="password_confirmation">Yeni Şifre Tekrar</label>
            <PasswordField
              id="password_confirmation"
              v-model="passwordConfirmation"
              autocomplete="new-password"
              placeholder="Şifrenizi tekrar girin"
              :invalid="Boolean(fieldErrors.password_confirmation)"
            />
            <p v-if="fieldErrors.password_confirmation" class="field-error">
              {{ fieldErrors.password_confirmation[0] }}
            </p>
          </div>

          <button
            type="submit"
            class="auth-button"
            :disabled="isLoading || isRateLimited"
          >
            {{ isLoading ? 'Kaydediliyor...' : 'Şifreyi sıfırla' }}
          </button>
        </form>
      </template>

      <p class="auth-link">
        <router-link to="/forgot-password">Yeni bağlantı iste</router-link>
        <router-link to="/login">Giriş yap</router-link>
      </p>
    </div>
  </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import PasswordField from '../components/PasswordField.vue'
import PasswordStrength from '../components/PasswordStrength.vue'
import { useRateLimitCountdown } from '../composables/useRateLimitCountdown'
import authService from '../services/authService'
import { useAuthStore } from '../stores/auth'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

const readQuery = (value) => {
  if (typeof value !== 'string' || value.trim() === '') {
    return ''
  }

  return value
}

const token = computed(() => readQuery(route.query.token))
const email = computed(() => readQuery(route.query.email))
const hasResetLink = computed(() => token.value !== '' && email.value !== '')

const password = ref('')
const passwordConfirmation = ref('')
const isLoading = ref(false)
const errorMessage = ref('')
const fieldErrors = reactive({})

const {
  isRateLimited,
  message: rateLimitMessage,
  handleRateLimit,
} = useRateLimitCountdown()

const resetRateLimitMessage = (seconds) =>
  `Çok fazla şifre sıfırlama denemesi yaptınız. Lütfen ${seconds} saniye sonra tekrar deneyin.`

const clearFieldErrors = () => {
  Object.keys(fieldErrors).forEach((key) => {
    delete fieldErrors[key]
  })
}

const submit = async () => {
  if (!hasResetLink.value || isRateLimited.value) {
    return
  }

  errorMessage.value = ''
  clearFieldErrors()
  isLoading.value = true

  try {
    const response = await authService.resetPassword({
      email: email.value,
      token: token.value,
      password: password.value,
      password_confirmation: passwordConfirmation.value,
    })

    authStore.clearLocalSession()
    sessionStorage.removeItem('sessionExpired')
    sessionStorage.setItem('passwordResetMessage', response.data.message)
    await router.push('/login')
  } catch (error) {
    if (error.response?.status === 429) {
      handleRateLimit(
        error,
        resetRateLimitMessage,
        'Çok fazla şifre sıfırlama denemesi yaptınız. Lütfen kısa bir süre bekleyip tekrar deneyin.',
      )
      return
    }

    if (error.response?.status === 422) {
      const validationErrors = error.response.data?.errors
      const hasFieldErrors =
        validationErrors &&
        typeof validationErrors === 'object' &&
        Object.keys(validationErrors).length > 0

      if (hasFieldErrors) {
        Object.assign(fieldErrors, validationErrors)
        return
      }
    }

    errorMessage.value =
      error.response?.data?.message ||
      'Şifre sıfırlanırken bir hata oluştu.'
  } finally {
    isLoading.value = false
  }
}
</script>

<style scoped>
.auth-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background-color: #f0f4f8;
  padding: 1.5rem;
  box-sizing: border-box;
}

.auth-card {
  width: 100%;
  max-width: 460px;
  background-color: #ffffff;
  border-radius: 12px;
  box-shadow: 0 4px 24px rgba(0, 0, 0, 0.08);
  padding: 2.5rem 2rem;
  box-sizing: border-box;
}

.auth-header {
  margin-bottom: 2rem;
  text-align: center;
}

.auth-title {
  margin: 0;
  font-size: 1.75rem;
  font-weight: 600;
  color: #1a1a2e;
  letter-spacing: -0.02em;
}

.auth-subtitle {
  margin: 0.75rem 0 0;
  font-size: 0.9rem;
  line-height: 1.5;
  color: #718096;
}

.auth-form {
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

.field-error {
  margin: 0;
  font-size: 0.8rem;
  line-height: 1.4;
  color: #c53030;
}

.general-error {
  margin: 0 0 1.25rem;
  padding: 0.75rem 1rem;
  font-size: 0.875rem;
  line-height: 1.5;
  color: #c53030;
  background-color: #fff5f5;
  border: 1px solid #feb2b2;
  border-radius: 8px;
}

.auth-button {
  width: 100%;
  margin-top: 0.5rem;
  padding: 0.875rem 1rem;
  font-size: 1rem;
  font-weight: 600;
  color: #ffffff;
  background-color: #4f6ef7;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  transition:
    background-color 0.2s ease,
    transform 0.1s ease,
    opacity 0.2s ease;
}

.auth-button:hover:not(:disabled) {
  background-color: #3b5de7;
}

.auth-button:active:not(:disabled) {
  transform: scale(0.98);
}

.auth-button:disabled {
  cursor: not-allowed;
  opacity: 0.65;
}

.auth-link {
  display: flex;
  justify-content: center;
  gap: 1rem;
  margin: 1.75rem 0 0;
  font-size: 0.875rem;
}

.auth-link a {
  color: #4f6ef7;
  font-weight: 500;
  text-decoration: none;
  transition: color 0.2s ease;
}

.auth-link a:hover {
  color: #3b5de7;
  text-decoration: underline;
}

@media (max-width: 480px) {
  .auth-card {
    padding: 2rem 1.5rem;
    border-radius: 10px;
  }

  .auth-title {
    font-size: 1.5rem;
  }

  .auth-subtitle {
    font-size: 0.85rem;
  }
}
</style>
