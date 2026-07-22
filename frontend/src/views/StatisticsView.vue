<template>
  <div class="statistics-page">
    <div class="statistics-container">
      <header class="page-header">
        <h1>İstatistikler</h1>
        <p>Yazılarınızın görüntülenme ve etkileşim istatistikleri burada gösterilecek.</p>
      </header>
    </div>
  </div>
</template>

<style scoped>
.statistics-page {
  padding: 2rem;
  box-sizing: border-box;
}

.statistics-container {
  max-width: 1100px;
  margin: 0 auto;
}

.page-header {
  padding: 2rem;
  background-color: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
}

.page-header h1 {
  margin: 0 0 0.5rem;
  color: #1a1a2e;
  font-size: 2rem;
  font-weight: 700;
  letter-spacing: -0.03em;
}

.page-header p {
  margin: 0;
  color: #718096;
  font-size: 0.95rem;
  line-height: 1.6;
}
</style>
