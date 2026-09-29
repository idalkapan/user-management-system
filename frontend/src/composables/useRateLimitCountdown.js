import { computed, onUnmounted, ref } from 'vue'

export function useRateLimitCountdown() {
  const retryAfterSeconds = ref(0)
  const message = ref('')
  const isRateLimited = computed(() => retryAfterSeconds.value > 0)

  let timer = null
  let formatMessage = () => ''

  const clearRateLimitTimer = () => {
    if (timer !== null) {
      clearInterval(timer)
      timer = null
    }
  }

  const startRateLimitCountdown = (seconds, format) => {
    clearRateLimitTimer()
    formatMessage = format
    retryAfterSeconds.value = seconds
    message.value = format(seconds)

    timer = setInterval(() => {
      retryAfterSeconds.value -= 1

      if (retryAfterSeconds.value <= 0) {
        retryAfterSeconds.value = 0
        message.value = ''
        clearRateLimitTimer()
        return
      }

      message.value = formatMessage(retryAfterSeconds.value)
    }, 1000)
  }

  const handleRateLimit = (error, format, fallback) => {
    const retryAfter = Number(error.response?.headers?.['retry-after'])

    if (Number.isFinite(retryAfter) && retryAfter > 0) {
      startRateLimitCountdown(Math.ceil(retryAfter), format)
      return
    }

    clearRateLimitTimer()
    retryAfterSeconds.value = 0
    message.value = fallback
  }

  onUnmounted(() => {
    clearRateLimitTimer()
  })

  return {
    isRateLimited,
    message,
    handleRateLimit,
  }
}
