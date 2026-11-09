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
:global(html[data-theme='dark']) .status-badge.green{background:#164433;color:#83e5bb}
:global(html[data-theme='dark']) .status-badge.gray{background:#27383d;color:#b9d1d8}
:global(html[data-theme='dark']) .status-badge.yellow{background:#493a1e;color:#f7ca70}
:global(html[data-theme='dark']) .status-badge.red{background:#492b30;color:#ffadb3}
:global(html[data-theme='dark']) .status-badge.blue{background:#123e4a;color:#a9e8ee}
</style>
