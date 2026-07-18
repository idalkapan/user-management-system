import { useAuthStore } from '../stores/auth'
import { createRouter, createWebHistory } from 'vue-router'


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

const router = createRouter({
  history: createWebHistory(),

  routes: [
    {
      path: '/',
      redirect: '/login',
    },
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
      path: '/profile',
      component: ProfileView,
      meta: { requiresAuth: true },
    },
    {
      path: '/posts',
      name: 'posts',
      component: PostListView,
    },
    {
      path: '/my-posts',
      name: 'my-posts',
      component: MyPostsView,
      meta: { requiresAuth: true },
    },
    {
      path: '/admin/posts',
      name: 'admin-posts',
      component: AdminPostsView,
      meta: {
        requiresAuth: true,
        requiresAdmin: true,
      },
    },
    {
      path: '/posts/create',
      name: 'post-create',
      component: PostCreateView,
      meta: { requiresAuth: true },
    },
    {
      path: '/posts/:id/edit',
      name: 'post-edit',
      component: PostEditView,
      meta: { requiresAuth: true },
    },
    {
      path: '/posts/:id',
      name: 'post-detail',
      component: PostDetailView,
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
    return '/profile'
  }

  return true
})

export default router