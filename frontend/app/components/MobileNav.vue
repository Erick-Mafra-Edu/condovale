<script setup lang="ts">
defineProps<{ active: string; items: Array<{ label: string; icon: 'home' | 'alert' | 'calendar' | 'message' }> }>()
defineEmits<{ select: [label: string] }>()
</script>

<template>
  <nav class="mobile-nav glass">
    <span class="mobile-nav-bubble" :style="{ transform: `translateX(${Math.max(items.findIndex(item => item.label === active), 0) * 100}%)` }" aria-hidden="true"></span>
    <button v-for="item in items" :key="item.label" :class="{ selected: active === item.label }" @click="$emit('select', item.label)"><span><SvgIcon :name="item.icon" /></span>{{ item.label }}</button>
  </nav>
</template>
