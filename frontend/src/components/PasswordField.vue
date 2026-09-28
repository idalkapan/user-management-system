<template>
  <div class="password-field">
    <input
      :id="id"
      v-model="model"
      :type="isVisible ? 'text' : 'password'"
      :autocomplete="autocomplete"
      :placeholder="placeholder"
      :aria-invalid="invalid ? 'true' : undefined"
      :class="{ 'input-error': invalid }"
    />

    <button
      type="button"
      class="password-toggle"
      :aria-label="isVisible ? 'Şifreyi gizle' : 'Şifreyi göster'"
      :aria-pressed="isVisible"
      @click="isVisible = !isVisible"
    >
      <svg
        v-if="isVisible"
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.8"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
      >
        <path d="M3 3l18 18" />
        <path d="M10.6 10.6a3 3 0 0 0 4.2 4.2" />
        <path
          d="M9.9 5.2A10.8 10.8 0 0 1 12 5c6.5 0 10 7 10 7a18.5 18.5 0 0 1-3.2 4.3"
        />
        <path
          d="M6.1 6.1C3.8 7.9 2 12 2 12s3.5 7 10 7a10.8 10.8 0 0 0 3.9-.8"
        />
      </svg>

      <svg
        v-else
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.8"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
      >
        <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z" />
        <circle cx="12" cy="12" r="3" />
      </svg>
    </button>
  </div>
</template>

<script setup>
import { ref } from 'vue'

const model = defineModel({
  type: String,
  default: '',
})

defineProps({
  id: {
    type: String,
    required: true,
  },
  autocomplete: {
    type: String,
    default: 'current-password',
  },
  placeholder: {
    type: String,
    default: '',
  },
  invalid: {
    type: Boolean,
    default: false,
  },
})

const isVisible = ref(false)
</script>

<style scoped>
.password-field {
  position: relative;
  width: 100%;
}

.password-field input {
  width: 100%;
  padding: 0.75rem 2.75rem 0.75rem 1rem;
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

.password-field input::placeholder {
  color: #a0aec0;
}

.password-field input:focus {
  border-color: #4f6ef7;
  box-shadow: 0 0 0 3px rgba(79, 110, 247, 0.15);
  background-color: #ffffff;
}

.password-field input.input-error {
  border-color: #e53e3e;
  background-color: #fffafa;
}

.password-field input.input-error:focus {
  border-color: #e53e3e;
  box-shadow: 0 0 0 3px rgba(229, 62, 62, 0.12);
}

.password-toggle {
  position: absolute;
  top: 50%;
  right: 0.35rem;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 2.25rem;
  height: 2.25rem;
  padding: 0;
  color: #718096;
  background: transparent;
  border: none;
  border-radius: 6px;
  transform: translateY(-50%);
  cursor: pointer;
}

.password-toggle:hover {
  color: #1a1a2e;
}

.password-toggle:focus-visible {
  outline: 2px solid #4f6ef7;
  outline-offset: 2px;
}

.password-toggle svg {
  width: 1.25rem;
  height: 1.25rem;
}
</style>
