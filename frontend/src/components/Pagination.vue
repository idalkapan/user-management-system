<script setup>
import { computed } from 'vue'

const props = defineProps({
  currentPage: {
    type: Number,
    required: true,
  },
  lastPage: {
    type: Number,
    required: true,
  },
  loading: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['change-page'])

const shouldRender = computed(() => props.lastPage > 1)

const visiblePages = computed(() => {
  const total = props.lastPage
  const current = props.currentPage

  if (total <= 7) {
    return Array.from({ length: total }, (_, index) => index + 1)
  }

  const pageSet = new Set([
    1,
    total,
    current,
    current - 1,
    current + 1,
  ])

  const sortedPages = [...pageSet]
    .filter((page) => page >= 1 && page <= total)
    .sort((a, b) => a - b)

  const items = []
  let previousPage = 0

  sortedPages.forEach((page) => {
    if (previousPage && page - previousPage > 1) {
      items.push('ellipsis')
    }

    items.push(page)
    previousPage = page
  })

  return items
})

const goToPage = (page) => {
  if (props.loading || page === props.currentPage) {
    return
  }

  if (page < 1 || page > props.lastPage) {
    return
  }

  emit('change-page', page)
}
</script>

<template>
  <nav
    v-if="shouldRender"
    class="pagination"
    aria-label="Sayfalama"
  >
    <button
      type="button"
      class="pagination-button pagination-button-nav"
      :disabled="loading || currentPage <= 1"
      aria-label="Önceki sayfa"
      @click="goToPage(currentPage - 1)"
    >
      Önceki
    </button>

    <div class="pagination-pages">
      <template
        v-for="(item, index) in visiblePages"
        :key="`${item}-${index}`"
      >
        <span
          v-if="item === 'ellipsis'"
          class="pagination-ellipsis"
          aria-hidden="true"
        >
          …
        </span>

        <button
          v-else
          type="button"
          class="pagination-button pagination-button-page"
          :class="{ 'pagination-button-page--active': item === currentPage }"
          :disabled="loading"
          :aria-label="`${item}. sayfa`"
          :aria-current="item === currentPage ? 'page' : undefined"
          @click="goToPage(item)"
        >
          {{ item }}
        </button>
      </template>
    </div>

    <button
      type="button"
      class="pagination-button pagination-button-nav"
      :disabled="loading || currentPage >= lastPage"
      aria-label="Sonraki sayfa"
      @click="goToPage(currentPage + 1)"
    >
      Sonraki
    </button>
  </nav>
</template>

<style scoped>
.pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin-top: 1.5rem;
  padding-top: 0.5rem;
}

.pagination-pages {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.35rem;
}

.pagination-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 2.25rem;
  padding: 0.45rem 0.85rem;
  color: #475569;
  background-color: #ffffff;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
  transition:
    background-color 0.15s ease,
    border-color 0.15s ease,
    color 0.15s ease;
}

.pagination-button-nav {
  min-width: 5.5rem;
}

.pagination-button-page {
  min-width: 2.25rem;
  padding-inline: 0.65rem;
}

.pagination-button-page--active {
  color: #ffffff;
  background-color: #4f6ef7;
  border-color: #4f6ef7;
}

.pagination-button:hover:not(:disabled):not(.pagination-button-page--active) {
  background-color: #f8fafc;
  border-color: #94a3b8;
}

.pagination-button:focus-visible {
  outline: 2px solid #4f6ef7;
  outline-offset: 2px;
}

.pagination-button:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.pagination-ellipsis {
  min-width: 1.5rem;
  color: #94a3b8;
  font-size: 0.875rem;
  font-weight: 700;
  text-align: center;
}

@media (max-width: 640px) {
  .pagination {
    gap: 0.4rem;
  }

  .pagination-button-nav {
    min-width: auto;
    flex: 1 1 calc(50% - 0.2rem);
  }

  .pagination-pages {
    order: 3;
    justify-content: center;
    width: 100%;
  }
}
</style>
