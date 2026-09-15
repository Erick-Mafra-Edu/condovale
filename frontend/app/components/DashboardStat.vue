<script setup lang="ts">
defineProps<{ label: string; value: string; note: string; icon: 'users' | 'alert' | 'calendar' | 'message'; tone: string }>()
</script>

<template>
  <article class="stat-card">
    <div class="stat-head"><span>{{ label }}</span><i :class="tone"><SvgIcon :name="icon" /></i></div>
    <strong>{{ value }}</strong>
    <small :class="tone">{{ note }}</small>
  </article>
</template>
