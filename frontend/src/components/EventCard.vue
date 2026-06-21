<script setup>
import AppIcon from './AppIcon.vue'
import { formatDate } from '../utils/format'

/**
 * One event card on the browse grid. The action area switches based on
 * whether the current student is already registered:
 *   not registered -> "Register" button
 *   registered     -> green "Registered" badge + "View Details"
 */
defineProps({
  event: { type: Object, required: true },
  registered: { type: Boolean, default: false },
})
defineEmits(['register', 'view'])
</script>

<template>
  <div class="card card-pad ev">
    <div class="ev-head">
      <h3 class="ev-title">{{ event.title }}</h3>
      <span class="badge badge-cat">{{ event.category }}</span>
    </div>

    <div class="ev-meta">
      <div class="cell-icon"><AppIcon name="calendar" :size="16" /> {{ formatDate(event.event_datetime) }}</div>
      <div class="cell-icon"><AppIcon name="pin" :size="16" /> {{ event.venue }}</div>
      <div class="cell-icon"><AppIcon name="users" :size="16" /> {{ event.registered_count }}/{{ event.capacity }} registered</div>
    </div>

    <div class="ev-action">
      <template v-if="registered">
        <span class="badge badge-green"><AppIcon name="check" :size="14" /> Registered</span>
        <button class="btn btn-muted btn-block" @click="$emit('view', event.id)">View Details</button>
      </template>
      <template v-else>
        <button class="btn btn-primary btn-block" @click="$emit('register', event.id)">Register</button>
      </template>
    </div>
  </div>
</template>

<style scoped>
.ev { display: flex; flex-direction: column; gap: 14px; }
.ev-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; }
.ev-title { font-size: 17px; font-weight: 700; }
.ev-meta { display: flex; flex-direction: column; gap: 8px; font-size: 14px; }
.ev-action { margin-top: auto; display: flex; flex-direction: column; gap: 10px; }
.ev-action .badge-green { align-self: flex-start; }
</style>
