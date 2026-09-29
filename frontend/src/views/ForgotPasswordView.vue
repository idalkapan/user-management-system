<template>
  <div class="auth-page">
    <div class="auth-card">
      <div class="auth-header">
        <h1 class="auth-title">Şifremi Unuttum</h1>
        <p class="auth-subtitle">
          E-posta adresinizi girin, size şifre sıfırlama bağlantısı gönderelim.
        </p>
      </div>

      <p v-if="rateLimitMessage" class="general-error">
        {{ rateLimitMessage }}
      </p>
      <p v-else-if="errorMessage" class="general-error">
        {{ errorMessage }}
      </p>
      <p v-else-if="successMessage" class="general-success">
        {{ successMessage }}
      </p>

      <form class="auth-form" @submit.prevent="submit">
        <div class="form-group">
          <label for="email">E-posta</label>
          <input
            id="email"
            v-model.trim="email"
            type="email"
            placeholder="ornek@email.com"
            autocomplete="email"
            :class="{ 'input-error': fieldErrors.email }"
          />
          <p v-if="fieldErrors.email" class="field-error">
            {{ fieldErrors.email[0] }}
          </p>
        </div>

        <button
          type="submit"
          class="auth-button"
          :disabled="isLoading || isRateLimited"
        >
          {{ isLoading ? 'Gönderiliyor...' : 'Sıfırlama bağlantısı gönder' }}
        </button>
      </form>

      <p class="auth-link">
        <router-link to="/login">Giriş yap</router-link>
      </p>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { useRateLimitCountdown } from '../composables/useRateLimitCountdown'
import authService from '../services/authService'

const email = ref('')
const isLoading = ref(false)
const errorMessage = ref('')
const successMessage = ref('')
const fieldErrors = reactive({})

const {
  isRateLimited,
  message: rateLimitMessage,
  handleRateLimit,
} = useRateLimitCountdown()

const forgotRateLimitMessage = (seconds) =>
  `Çok fazla şifre sıfırlama isteği gönderdiniz. Lütfen ${seconds} saniye sonra tekrar deneyin.`

const clearFieldErrors = () => {
  Object.keys(fieldErrors).forEach((key) => {
    delete fieldErrors[key]
  })
}

const submit = async () => {
  if (isRateLimited.value) {
    return
  }

  errorMessage.value = ''
  successMessage.value = ''
  clearFieldErrors()
  isLoading.value = true

  try {
    const response = await authService.forgotPassword({
      email: email.value,
    })

    successMessage.value = response.data.message
  } catch (error) {
    if (error.response?.status === 429) {
      handleRateLimit(
        error,
        forgotRateLimitMessage,
        'Çok fazla şifre sıfırlama isteği gönderdiniz. Lütfen kısa bir süre bekleyip tekrar deneyin.',
      )
      return
    }

    if (error.response?.status === 422) {
      Object.assign(fieldErrors, error.response.data?.errors || {})
    }

    errorMessage.value =
      error.response?.data?.message ||
      'Şifre sıfırlama bağlantısı gönderilirken bir hata oluştu.'
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
  max-width: 420px;
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

.form-group input {
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
  font-size: 0.8rem;
  line-height: 1.4;
  color: #c53030;
}

.general-error,
.general-success {
  margin: 0 0 1.25rem;
  padding: 0.75rem 1rem;
  font-size: 0.875rem;
  line-height: 1.5;
  border-radius: 8px;
}

.general-error {
  color: #c53030;
  background-color: #fff5f5;
  border: 1px solid #feb2b2;
}

.general-success {
  color: #276749;
  background-color: #f0fff4;
  border: 1px solid #9ae6b4;
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
  margin: 1.75rem 0 0;
  text-align: center;
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
