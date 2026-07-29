<script setup>
import { computed } from 'vue'

const props = defineProps({
  variant: {
    type: String,
    default: 'list',
    validator: (value) => ['list', 'detail'].includes(value),
  },
  viewsCount: {
    type: Number,
    default: 0,
  },
  likesCount: {
    type: Number,
    default: 0,
  },
  commentsCount: {
    type: Number,
    default: 0,
  },
  isLiked: {
    type: Boolean,
    default: false,
  },
  canLike: {
    type: Boolean,
    default: false,
  },
  isLiking: {
    type: Boolean,
    default: false,
  },
  likeAriaLabel: {
    type: String,
    default: 'Beğeni ekle',
  },
  readOnly: {
    type: Boolean,
    default: false,
  },
})

defineEmits(['toggle-like', 'open-comments'])

const showLikeButton = computed(
  () => !props.readOnly && props.canLike,
)

const commentsClickable = computed(
  () => !props.readOnly && props.variant === 'list',
)

const commentsAriaLabel = computed(
  () => `${props.commentsCount} yorum, yorumları görüntüle`,
)
</script>

<template>
  <div
    class="post-interaction-bar"
    :class="`post-interaction-bar--${variant}`"
  >
    <span
      class="interaction-stat"
      :class="{ 'interaction-stat--detail': variant === 'detail' }"
    >
      <svg
        v-if="variant === 'detail'"
        class="interaction-icon"
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 24 24"
        aria-hidden="true"
      >
        <path
          d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
        />
        <circle
          cx="12"
          cy="12"
          r="3"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
        />
      </svg>

      <span
        v-else
        class="interaction-emoji"
        aria-hidden="true"
      >👁️</span>

      <span class="interaction-value">{{ viewsCount }}</span>

      <span
        v-if="variant === 'detail'"
        class="interaction-label"
      >görüntülenme</span>
    </span>

    <button
      v-if="showLikeButton"
      type="button"
      class="interaction-like"
      :class="{
        'interaction-like--detail': variant === 'detail',
        'is-liked': isLiked,
      }"
      :disabled="isLiking"
      :aria-label="likeAriaLabel"
      :aria-pressed="isLiked"
      :title="likeAriaLabel"
      @click="$emit('toggle-like')"
    >
      <svg
        v-if="isLiked"
        class="interaction-icon heart-icon"
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 24 24"
        aria-hidden="true"
      >
        <path
          d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"
          fill="currentColor"
        />
      </svg>

      <svg
        v-else
        class="interaction-icon heart-icon"
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 24 24"
        aria-hidden="true"
      >
        <path
          d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
        />
      </svg>

      <span class="interaction-value">{{ likesCount }}</span>

      <span
        v-if="variant === 'detail'"
        class="interaction-label"
      >beğeni</span>
    </button>

    <span
      v-else
      class="interaction-stat interaction-stat-like"
      :class="{ 'interaction-stat--detail': variant === 'detail' }"
      :title="`${likesCount} beğeni`"
    >
      <svg
        class="interaction-icon heart-icon"
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 24 24"
        aria-hidden="true"
      >
        <path
          d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
        />
      </svg>

      <span class="interaction-value">{{ likesCount }}</span>

      <span
        v-if="variant === 'detail'"
        class="interaction-label"
      >beğeni</span>
    </span>

    <button
      v-if="commentsClickable"
      type="button"
      class="interaction-stat interaction-stat-comments interaction-comments-button"
      :aria-label="commentsAriaLabel"
      :title="commentsAriaLabel"
      @click="$emit('open-comments')"
    >
      <svg
        class="interaction-icon comment-icon"
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 24 24"
        aria-hidden="true"
      >
        <path
          d="M21 15a2 2 0 0 1-2 2H8l-5 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
        />
      </svg>

      <span class="interaction-value">{{ commentsCount }}</span>
    </button>

    <span
      v-else
      class="interaction-stat interaction-stat-comments"
      :class="{ 'interaction-stat--detail': variant === 'detail' }"
      :title="`${commentsCount} yorum`"
    >
      <svg
        class="interaction-icon comment-icon"
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 24 24"
        aria-hidden="true"
      >
        <path
          d="M21 15a2 2 0 0 1-2 2H8l-5 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
        />
      </svg>

      <span class="interaction-value">{{ commentsCount }}</span>

      <span
        v-if="variant === 'detail'"
        class="interaction-label"
      >yorum</span>
    </span>
  </div>
</template>

<style scoped>
.post-interaction-bar {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.65rem;
}

.post-interaction-bar--detail {
  gap: 0.75rem;
  margin-top: 1.25rem;
  padding: 0.65rem 0.85rem;
  background-color: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
}

.interaction-stat,
.interaction-like {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  color: #64748b;
  font-size: 0.8rem;
  font-weight: 600;
  line-height: 1;
}

.interaction-stat--detail,
.interaction-like--detail {
  gap: 0.4rem;
  font-size: 0.875rem;
}

.interaction-stat-like,
.interaction-stat-comments {
  color: #94a3b8;
}

.interaction-emoji {
  font-size: 0.85rem;
  line-height: 1;
}

.interaction-icon {
  width: 15px;
  height: 15px;
  flex-shrink: 0;
}

.interaction-stat--detail .interaction-icon,
.interaction-like--detail .interaction-icon {
  width: 17px;
  height: 17px;
}

.interaction-value {
  color: #334155;
  font-variant-numeric: tabular-nums;
}

.interaction-stat-like .interaction-value,
.interaction-stat-comments .interaction-value {
  color: inherit;
}

.interaction-label {
  color: #94a3b8;
  font-size: 0.8125rem;
  font-weight: 500;
}

.interaction-like {
  padding: 0.28rem 0.55rem;
  background-color: transparent;
  border: 1px solid transparent;
  border-radius: 999px;
  cursor: pointer;
  transition:
    background-color 0.15s ease,
    border-color 0.15s ease,
    color 0.15s ease;
}

.interaction-like--detail {
  padding: 0.35rem 0.65rem;
  background-color: #ffffff;
  border-color: #e2e8f0;
}

.interaction-like:hover:not(:disabled) {
  color: #475569;
  background-color: #f8fafc;
  border-color: #e2e8f0;
}

.interaction-like.is-liked {
  color: #e11d48;
  background-color: #fff1f2;
  border-color: #fecdd3;
}

.interaction-like.is-liked:hover:not(:disabled) {
  background-color: #ffe4e6;
  border-color: #fda4af;
}

.interaction-like:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.interaction-like:focus-visible {
  outline: 2px solid #4f6ef7;
  outline-offset: 2px;
}

.interaction-comments-button {
  padding: 0.28rem 0.55rem;
  background-color: transparent;
  border: 1px solid transparent;
  border-radius: 999px;
  cursor: pointer;
  transition:
    background-color 0.15s ease,
    border-color 0.15s ease,
    color 0.15s ease;
}

.interaction-comments-button:hover {
  color: #475569;
  background-color: #f8fafc;
  border-color: #e2e8f0;
}

.interaction-comments-button:focus-visible {
  outline: 2px solid #4f6ef7;
  outline-offset: 2px;
}

.post-interaction-bar--list {
  margin-top: 0.65rem;
}
</style>
