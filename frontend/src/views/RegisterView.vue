<template>
  <div class="register-page">
    <div class="register-card">
      <div class="register-header">
        <h1 class="register-title">Kayıt Ol</h1>
        <p class="register-subtitle">
          Yeni hesabını oluşturarak sisteme giriş yapabilirsin.
        </p>
      </div>

      <p v-if="errorMessage" class="general-error">
        {{ errorMessage }}
      </p>

      <form class="register-form" @submit.prevent="register">
        <div class="form-group">
          <label for="name">Ad Soyad</label>

          <input
            id="name"
            v-model.trim="name"
            type="text"
            placeholder="Adınızı ve soyadınızı girin"
            autocomplete="name"
            :class="{ 'input-error': fieldErrors.name }"
          />

          <p v-if="fieldErrors.name" class="field-error">
            {{ fieldErrors.name[0] }}
          </p>
        </div>

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

        <div class="form-group">
          <label for="password">Şifre</label>

          <input
            id="password"
            v-model="password"
            type="password"
            placeholder="En az 8 karakter"
            autocomplete="new-password"
            :class="{ 'input-error': fieldErrors.password }"
          />

          <p v-if="fieldErrors.password" class="field-error">
            {{ fieldErrors.password[0] }}
          </p>
        </div>

        <div class="form-group">
          <label for="password_confirmation">Şifre Tekrarı</label>

          <input
            id="password_confirmation"
            v-model="passwordConfirmation"
            type="password"
            placeholder="Şifrenizi tekrar girin"
            autocomplete="new-password"
            :class="{ 'input-error': fieldErrors.password_confirmation }"
          />

          <p
            v-if="fieldErrors.password_confirmation"
            class="field-error"
          >
            {{ fieldErrors.password_confirmation[0] }}
          </p>
        </div>

        <button
          type="submit"
          class="register-button"
          :disabled="isLoading"
        >
          {{ isLoading ? 'Hesap oluşturuluyor...' : 'Kayıt Ol' }}
        </button>
      </form>

      <p class="login-link">
        Zaten hesabın var mı?
        <router-link to="/login">Giriş yap</router-link>
      </p>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import api from '../services/api'

const name = ref('')
const email = ref('')
const password = ref('')
const passwordConfirmation = ref('')

const isLoading = ref(false)
const errorMessage = ref('')
const fieldErrors = reactive({})

const router = useRouter()

const clearErrors = () => {
  errorMessage.value = ''

  Object.keys(fieldErrors).forEach((key) => {
    delete fieldErrors[key]
  })
}

const register = async () => {
  clearErrors()
  isLoading.value = true

  try {
    await api.post('/register', {
      name: name.value,
      email: email.value,
      password: password.value,
      password_confirmation: passwordConfirmation.value,
    })

    router.push('/login')
  } catch (error) {
    if (error.response?.status === 422) {
      Object.assign(
        fieldErrors,
        error.response?.data?.errors || {}
      )
    }

    errorMessage.value =
      error.response?.data?.message ||
      'Kayıt oluşturulurken bir hata oluştu.'
  } finally {
    isLoading.value = false
  }
}
</script>

<style scoped>
.register-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background-color: #f0f4f8;
  padding: 1.5rem;
  box-sizing: border-box;
}

.register-card {
  width: 100%;
  max-width: 460px;
  background-color: #ffffff;
  border-radius: 12px;
  box-shadow: 0 4px 24px rgba(0, 0, 0, 0.08);
  padding: 2.5rem 2rem;
  box-sizing: border-box;
}

.register-header {
  margin-bottom: 2rem;
  text-align: center;
}

.register-title {
  margin: 0;
  font-size: 1.75rem;
  font-weight: 600;
  color: #1a1a2e;
  letter-spacing: -0.02em;
}

.register-subtitle {
  margin: 0.75rem 0 0;
  font-size: 0.9rem;
  line-height: 1.5;
  color: #718096;
}

.register-form {
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

.register-button {
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

.register-button:hover:not(:disabled) {
  background-color: #3b5de7;
}

.register-button:active:not(:disabled) {
  transform: scale(0.98);
}

.register-button:disabled {
  cursor: not-allowed;
  opacity: 0.65;
}

.login-link {
  margin: 1.75rem 0 0;
  text-align: center;
  font-size: 0.875rem;
  color: #718096;
}

.login-link a {
  margin-left: 0.25rem;
  color: #4f6ef7;
  font-weight: 500;
  text-decoration: none;
  transition: color 0.2s ease;
}

.login-link a:hover {
  color: #3b5de7;
  text-decoration: underline;
}

@media (max-width: 480px) {
  .register-card {
    padding: 2rem 1.5rem;
    border-radius: 10px;
  }

  .register-title {
    font-size: 1.5rem;
  }

  .register-subtitle {
    font-size: 0.85rem;
  }
}
</style>