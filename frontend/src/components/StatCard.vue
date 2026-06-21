<script setup>
import AppIcon from './AppIcon.vue'

/** Stat card used on the organizer & admin dashboards. */
defineProps({
  label: { type: String, required: true },
  value: { type: [String, Number], required: true },
  icon:  { type: String, default: 'calendar' },
  tint:  { type: String, default: 'maroon' },   // maroon | green | blue | purple
})

const tints = {
  maroon: { bg: 'var(--maroon-tint)', fg: 'var(--maroon)' },
  green:  { bg: 'var(--green-bg)',     fg: 'var(--green)' },
  blue:   { bg: 'var(--blue-bg)',      fg: 'var(--blue)' },
  purple: { bg: 'var(--purple-bg)',    fg: 'var(--purple)' },
  orange: { bg: '#fff7ed',             fg: '#c2410c' },
}
</script>

<template>
  <div class="card card-pad stat">
    <div>
      <div class="stat-label">{{ label }}</div>
      <div class="stat-value">{{ value }}</div>
    </div>
    <div class="stat-icon" :style="{ background: tints[tint].bg, color: tints[tint].fg }">
      <AppIcon :name="icon" :size="22" />
    </div>
  </div>
</template>
