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
    },
    {
      path: '/register',
      component: RegisterView,
    },
    {
      path: '/profile',
      component: ProfileView,
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
    },
    {
      path: '/admin/posts',
      name: 'admin-posts',
      component: AdminPostsView,
    },
    {
      path: '/posts/create',
      name: 'post-create',
      component: PostCreateView,
    },
    {
      path: '/posts/:id/edit',
      name: 'post-edit',
      component: PostEditView,
    },
    {
      path: '/posts/:id',
      name: 'post-detail',
      component: PostDetailView,
    },
    {
      path: '/:pathMatch(.*)*',
      component: NotFoundView,
    }
    
  ],
})

export default router