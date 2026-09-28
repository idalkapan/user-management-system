<template>
  <div
    v-if="level"
    class="password-strength"
    :data-level="level"
  >
    <div class="strength-bars" aria-hidden="true">
      <span></span>
      <span></span>
      <span></span>
    </div>

    <p class="strength-label" aria-live="polite">
      {{ strengthLabels[level] }}
    </p>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import {
  getPasswordStrength,
  strengthLabels,
} from '../utils/passwordStrength'

const props = defineProps({
  password: {
    type: String,
    default: '',
  },
})

const level = computed(() => getPasswordStrength(props.password))
</script>

<style scoped>
.password-strength {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.strength-bars {
  display: flex;
  flex: 1;
  gap: 0.35rem;
}

.strength-bars span {
  height: 0.35rem;
  flex: 1;
  border-radius: 999px;
  background-color: #e2e8f0;
}

.password-strength[data-level='weak'] .strength-bars span:nth-child(1) {
  background-color: #e53e3e;
}

.password-strength[data-level='medium'] .strength-bars span:nth-child(-n + 2) {
  background-color: #d69e2e;
}

.password-strength[data-level='strong'] .strength-bars span {
  background-color: #38a169;
}

.strength-label {
  margin: 0;
  min-width: 3.25rem;
  font-size: 0.8rem;
  font-weight: 600;
  text-align: right;
}

.password-strength[data-level='weak'] .strength-label {
  color: #c53030;
}

.password-strength[data-level='medium'] .strength-label {
  color: #b7791f;
}

.password-strength[data-level='strong'] .strength-label {
  color: #2f855a;
}
</style>
