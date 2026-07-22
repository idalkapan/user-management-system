<template>
  <div class="app-layout">
    <AppSidebar />

    <main class="app-main">
      <RouterView />
    </main>
  </div>
</template>

<script setup>
import { RouterView } from 'vue-router'
import AppSidebar from '../components/AppSidebar.vue'
</script>

<style scoped>
.app-layout {
  display: flex;
  min-height: 100vh;
  overflow-x: hidden;
}

.app-main {
  flex: 1;
  min-width: 0;
  min-height: 100vh;
  background-color: #f0f4f8;
}
</style>
