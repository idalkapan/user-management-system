<template>
  <div class="login-page">
    <div class="login-card">
      <h1 class="login-title">Giriş Yap</h1>

      <form class="login-form" @submit.prevent="login">
        <div class="form-group">
          <label for="email">E-posta</label>
          <input
          id="email"
          v-model="email"
          type="email"
          placeholder="ornek@email.com"
          />
        </div>

        <div class="form-group">
          <label for="password">Şifre</label>
          <input id="password" v-model="password" type="password" placeholder="••••••••" />
        </div>

        <button 
          type="submit"
          class="login-button"
          :disabled="isLoading"
        > 
          {{ isLoading ? 'Giriş yapılıyor...' : 'Giriş Yap' }}
        </button>
        <p v-if="errorMessage" class="error-message">
            {{ errorMessage }}
        </p>
      </form>

      <p class="register-link">
        Hesabın yok mu?
        <router-link to="/register">Kayıt ol</router-link>
      </p>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const email = ref('')
const password = ref('')
const errorMessage = ref('')
const isLoading = ref(false)

const router = useRouter()
const authStore = useAuthStore()

const login = async () => {
  errorMessage.value = ''
  isLoading.value = true

  try {
    await authStore.login({
      email: email.value,
      password: password.value,
    })

    router.push('/profile')
  } catch (error) {
    errorMessage.value =
      error.response?.data?.message || 'Giriş yapılırken bir hata oluştu.'
  } finally {
    isLoading.value = false
  }
}
</script>

<style scoped>
.login-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background-color: #f0f4f8;
  padding: 1.5rem;
  box-sizing: border-box;
}

.login-card {
  width: 100%;
  max-width: 420px;
  background-color: #ffffff;
  border-radius: 12px;
  box-shadow: 0 4px 24px rgba(0, 0, 0, 0.08);
  padding: 2.5rem 2rem;
  box-sizing: border-box;
}

.login-title {
  margin: 0 0 2rem;
  font-size: 1.75rem;
  font-weight: 600;
  color: #1a1a2e;
  text-align: center;
  letter-spacing: -0.02em;
}

.login-form {
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
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
  outline: none;
}

.form-group input::placeholder {
  color: #a0aec0;
}

.form-group input:focus {
  border-color: #4f6ef7;
  box-shadow: 0 0 0 3px rgba(79, 110, 247, 0.15);
  background-color: #ffffff;
}

.login-button {
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
  transition: background-color 0.2s ease, transform 0.1s ease;
}

.login-button:hover {
  background-color: #3b5de7;
}

.login-button:active {
  transform: scale(0.98);
}

.register-link {
  margin: 1.75rem 0 0;
  text-align: center;
  font-size: 0.875rem;
  color: #718096;
}

.register-link a {
  color: #4f6ef7;
  font-weight: 500;
  text-decoration: none;
  margin-left: 0.25rem;
  transition: color 0.2s ease;
}

.register-link a:hover {
  color: #3b5de7;
  text-decoration: underline;
}

@media (max-width: 480px) {
  .login-card {
    padding: 2rem 1.5rem;
    border-radius: 10px;
  }

  .login-title {
    font-size: 1.5rem;
    margin-bottom: 1.5rem;
  }
}
</style>
