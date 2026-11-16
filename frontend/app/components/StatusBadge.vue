<script setup lang="ts">
withDefaults(defineProps<{
  label: string
  tone?: 'green' | 'gray' | 'yellow' | 'red' | 'blue'
}>(), { tone: 'gray' })
</script>

<template>
  <span class="status-badge" :class="tone" role="status">{{ label }}</span>
</template>

<style scoped>
.status-badge{display:inline-flex;align-items:center;white-space:nowrap;border-radius:999px;padding:4px 8px;font-size:9px;font-weight:700;line-height:1.2}
.status-badge.green{background:var(--success-bg);color:var(--success-text)}
.status-badge.gray{background:var(--surface-raised);color:var(--neutral)}
.status-badge.yellow{background:var(--warning-bg);color:var(--warning-text)}
.status-badge.red{background:var(--danger-bg);color:var(--danger-text)}
.status-badge.blue{background:var(--selected);color:var(--deep)}
</style>
