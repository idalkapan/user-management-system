const KNOWN_PATTERNS = [
  'password',
  'qwerty',
  '123456',
  '1234',
  'abcd',
  'abc',
  'asdf',
  'zxcv',
]

const SEQUENCES = [
  '0123456789',
  'abcdefghijklmnopqrstuvwxyz',
  'qwertyuiop',
  'asdfghjkl',
  'zxcvbnm',
]

const strengthLabels = {
  weak: 'Zayıf',
  medium: 'Orta',
  strong: 'Güçlü',
}

const containsSequence = (value, sequence, length) => {
  const sources = [sequence, [...sequence].reverse().join('')]

  return sources.some((source) => {
    for (let index = 0; index <= source.length - length; index += 1) {
      if (value.includes(source.slice(index, index + length))) {
        return true
      }
    }

    return false
  })
}

const hasEasyPattern = (value) => {
  const lower = value.toLowerCase()

  if (KNOWN_PATTERNS.some((pattern) => lower.includes(pattern))) {
    return true
  }

  return SEQUENCES.some((sequence) => containsSequence(lower, sequence, 4))
}

const repetitionPenalty = (value) => {
  const lower = value.toLowerCase()
  const unique = new Set(lower).size
  let penalty = 0

  if (/(.)\1{3,}/u.test(lower)) {
    penalty += 2
  }

  if (unique <= 2) {
    penalty += 2
  } else if (unique / value.length < 0.45) {
    penalty += 1
  }

  return penalty
}

const getPasswordStrength = (password) => {
  if (typeof password !== 'string' || password.length === 0) {
    return null
  }

  if (password.length < 8) {
    return 'weak'
  }

  const hasLetter = /\p{L}/u.test(password)
  const hasNumber = /\p{N}/u.test(password)
  const hasSymbol = /[^\p{L}\p{N}]/u.test(password)
  const unique = new Set(password.toLowerCase()).size
  const classes = [hasLetter, hasNumber, hasSymbol].filter(Boolean).length

  let score = 1

  if (password.length >= 12) {
    score += 1
  }

  if (password.length >= 16) {
    score += 1
  }

  if (hasLetter) {
    score += 1
  }

  if (hasNumber) {
    score += 1
  }

  if (hasSymbol) {
    score += 1
  }

  if (unique >= 6) {
    score += 1
  }

  if (unique >= 10) {
    score += 1
  }

  if (classes >= 2) {
    score += 1
  }

  if (classes >= 3) {
    score += 1
  }

  score -= repetitionPenalty(password)

  if (hasEasyPattern(password)) {
    score -= 4
  }

  if (classes < 2) {
    score = Math.min(score, 4)
  }

  if (score <= 2) {
    return 'weak'
  }

  if (score <= 5) {
    return 'medium'
  }

  return 'strong'
}

export { getPasswordStrength, strengthLabels }
