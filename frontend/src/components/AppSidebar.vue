<template>
  <aside class="app-sidebar">
    <div class="sidebar-header">
      BLOG
    </div>

    <nav class="sidebar-nav">
      <template v-if="authStore.isAdmin">
        <RouterLink
          to="/admin/dashboard"
          class="sidebar-link"
        >
          Dashboard
        </RouterLink>

        <RouterLink
          to="/posts"
          class="sidebar-link"
        >
          Ana Sayfa
        </RouterLink>

        <RouterLink
          to="/admin/posts"
          class="sidebar-link"
        >
          Onay Bekleyenler
        </RouterLink>

        <RouterLink
          to="/admin/categories"
          class="sidebar-link"
        >
          Kategoriler
        </RouterLink>

        <RouterLink
          to="/statistics"
          class="sidebar-link"
        >
          İstatistikler
        </RouterLink>

        <RouterLink
          to="/profile"
          class="sidebar-link"
        >
          Hesabım
        </RouterLink>
      </template>

      <template v-else>
        <RouterLink
          to="/posts"
          class="sidebar-link"
        >
          Ana Sayfa
        </RouterLink>

        <RouterLink
          to="/my-posts"
          class="sidebar-link"
        >
          Yazılarım
        </RouterLink>

        <RouterLink
          to="/statistics"
          class="sidebar-link"
        >
          İstatistikler
        </RouterLink>

        <RouterLink
          to="/profile"
          class="sidebar-link"
        >
          Hesabım
        </RouterLink>
      </template>
    </nav>

    <div class="sidebar-footer">
      <button
        type="button"
        class="logout-button"
        @click="logout"
      >
        Çıkış
      </button>
    </div>
  </aside>
</template>

<script setup>
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const router = useRouter()
const authStore = useAuthStore()

onMounted(async () => {
  if (authStore.isAuthenticated && !authStore.user) {
    try {
      await authStore.fetchUser()
    } catch {
      // Sidebar menüsü guard akışına bırakılır.
    }
  }
})

const logout = async () => {
  await authStore.logout()
  router.push('/login')
}
</script>

<style scoped>
.app-sidebar {
  display: flex;
  flex-direction: column;
  width: 250px;
  min-width: 250px;
  height: 100vh;
  min-height: 100vh;
  position: sticky;
  top: 0;
  flex-shrink: 0;
  background-color: #ffffff;
  border-right: 1px solid #e2e8f0;
  box-sizing: border-box;
}

.sidebar-header {
  padding: 1.5rem 1.25rem 1.25rem;
  color: #1a1a2e;
  font-size: 1.125rem;
  font-weight: 700;
  letter-spacing: 0.08em;
}

.sidebar-nav {
  display: flex;
  flex: 1;
  flex-direction: column;
  gap: 0.35rem;
  padding: 0.5rem 0.75rem;
  overflow-y: auto;
}

.sidebar-link {
  display: block;
  padding: 0.7rem 0.9rem;
  color: #475569;
  border-radius: 8px;
  font-size: 0.925rem;
  font-weight: 500;
  line-height: 1.4;
  text-decoration: none;
  transition: background-color 0.15s ease, color 0.15s ease;
}

.sidebar-link:hover {
  color: #1e293b;
  background-color: #f1f5f9;
}

.sidebar-link.router-link-active {
  color: #4338ca;
  background-color: #eef2ff;
  font-weight: 600;
}

.sidebar-link:focus-visible {
  outline: 2px solid #4f6ef7;
  outline-offset: 2px;
}

.sidebar-footer {
  padding: 1rem 0.75rem 1.25rem;
  border-top: 1px solid #e2e8f0;
}

.logout-button {
  width: 100%;
  padding: 0.7rem 0.9rem;
  color: #475569;
  background-color: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-size: 0.925rem;
  font-weight: 500;
  line-height: 1.4;
  text-align: left;
  cursor: pointer;
  transition: background-color 0.15s ease, color 0.15s ease, border-color 0.15s ease;
}

.logout-button:hover {
  color: #1e293b;
  background-color: #f8fafc;
  border-color: #cbd5e0;
}

.logout-button:focus-visible {
  outline: 2px solid #4f6ef7;
  outline-offset: 2px;
}

@media (max-width: 768px) {
  .app-sidebar {
    width: 200px;
    min-width: 200px;
  }

  .sidebar-header {
    padding: 1.25rem 1rem 1rem;
    font-size: 1rem;
  }

  .sidebar-link,
  .logout-button {
    padding: 0.65rem 0.75rem;
    font-size: 0.875rem;
  }
}
</style>
