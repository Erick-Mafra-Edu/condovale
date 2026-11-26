<script setup lang="ts">
defineProps<{ label: string; value: string; note: string; icon: 'users' | 'alert' | 'calendar' | 'message'; tone: string; target?: string }>()
const emit = defineEmits<{ navigate: [target: string] }>()
</script>

<template>
  <article class="stat-card" :class="{ 'stat-interactive': target }" :tabindex="target ? 0 : undefined" :role="target ? 'button' : undefined" @click="target && emit('navigate', target)" @keydown.enter="target && emit('navigate', target)" @keydown.space.prevent="target && emit('navigate', target)">
    <div class="stat-head"><span>{{ label }}</span><i :class="tone"><SvgIcon :name="icon" /></i></div>
    <strong>{{ value }}</strong>
    <small :class="tone">{{ note }}</small>
  </article>
</template>
<style scoped>.stat-interactive{cursor:pointer}.stat-interactive:focus-visible{outline:3px solid var(--focus);outline-offset:3px}</style>
