import { useAuthStore } from '../stores/auth'
import { createRouter, createWebHistory } from 'vue-router'

import AppLayout from '../layouts/AppLayout.vue'

import LoginView from '../views/LoginView.vue'
import RegisterView from '../views/RegisterView.vue'
import ProfileView from '../views/ProfileView.vue'
import NotFoundView from '../views/NotFoundView.vue'
import PostListView from '../views/PostListView.vue'
import PostCreateView from '../views/PostCreateView.vue'
import PostDetailView from '../views/PostDetailView.vue'
import MyPostsView from '../views/MyPostsView.vue'
import AdminPostsView from '../views/AdminPostsView.vue'
import PostEditView from '../views/PostEditView.vue'
import AdminCategoriesView from '../views/AdminCategoriesView.vue'
import AdminDashboardView from '../views/AdminDashboardView.vue'
import StatisticsView from '../views/StatisticsView.vue'

const router = createRouter({
  history: createWebHistory(),

  routes: [
    {
      path: '/login',
      component: LoginView,
      meta: { guestOnly: true },
    },
    {
      path: '/register',
      component: RegisterView,
      meta: { guestOnly: true },
    },
    {
      path: '/',
      component: AppLayout,
      children: [
        {
          path: '',
          redirect: '/login',
        },
        {
          path: 'profile',
          component: ProfileView,
          meta: { requiresAuth: true },
        },
        {
          path: 'posts',
          name: 'posts',
          component: PostListView,
        },
        {
          path: 'my-posts',
          name: 'my-posts',
          component: MyPostsView,
          meta: { requiresAuth: true },
        },
        {
          path: 'statistics',
          name: 'statistics',
          component: StatisticsView,
          meta: { requiresAuth: true },
        },
        {
          path: 'admin/dashboard',
          name: 'admin-dashboard',
          component: AdminDashboardView,
          meta: {
            requiresAuth: true,
            requiresAdmin: true,
          },
        },
        {
          path: 'admin/posts',
          name: 'admin-posts',
          component: AdminPostsView,
          meta: {
            requiresAuth: true,
            requiresAdmin: true,
          },
        },
        {
          path: 'admin/categories',
          name: 'admin-categories',
          component: AdminCategoriesView,
          meta: {
            requiresAuth: true,
            requiresAdmin: true,
          },
        },
        {
          path: 'posts/create',
          name: 'post-create',
          component: PostCreateView,
          meta: { requiresAuth: true },
        },
        {
          path: 'posts/:id/edit',
          name: 'post-edit',
          component: PostEditView,
          meta: { requiresAuth: true },
        },
        {
          path: 'posts/:id',
          name: 'post-detail',
          component: PostDetailView,
        },
      ],
    },
    {
      path: '/:pathMatch(.*)*',
      component: NotFoundView,
    },
  ],
})

router.beforeEach(async (to) => {
  const authStore = useAuthStore()

  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    return '/login'
  }

  if (to.meta.requiresAdmin) {
    if (!authStore.user) {
      try {
        await authStore.fetchUser()
      } catch {
        return '/login'
      }
    }

    if (!authStore.isAdmin) {
      return '/posts'
    }
  }

  if (to.meta.guestOnly && authStore.isAuthenticated) {
    if (!authStore.user) {
      try {
        await authStore.fetchUser()
      } catch {
        return '/login'
      }
    }

    return authStore.getHomeRoute()
  }

  return true
})

export default router
