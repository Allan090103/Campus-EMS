/**
 * Small shared formatting helpers used across views.
 */

// "2026-07-15 09:00:00" -> "Jul 15, 2026"
export function formatDate(dt) {
  if (!dt) return ''
  const d = new Date(dt.replace(' ', 'T'))
  return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
}

// "2026-07-15 09:00:00" -> "Jul 15, 2026 · 9:00 AM"
export function formatDateTime(dt) {
  if (!dt) return ''
  const d = new Date(dt.replace(' ', 'T'))
  const date = d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
  const time = d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' })
  return `${date} · ${time}`
}

// Is this datetime in the past?
export function isPast(dt) {
  if (!dt) return false
  return new Date(dt.replace(' ', 'T')) < new Date()
}

// Colour for a utilization bar: green high, amber mid, red low.
export function utilizationColor(pct) {
  if (pct >= 80) return 'var(--green)'
  if (pct >= 50) return 'var(--amber)'
  return 'var(--red)'
}
