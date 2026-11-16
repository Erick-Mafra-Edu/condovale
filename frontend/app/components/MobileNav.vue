<script setup lang="ts">
defineProps<{ active: string; items: ReadonlyArray<{ label: string; icon: 'home' | 'alert' | 'calendar' | 'message' | 'building' | 'users' | 'profile' | 'occurrences' | 'documents' }> }>()
defineEmits<{ select: [label: string] }>()
</script>

<template>
  <nav class="mobile-nav glass" :style="{ '--mobile-nav-count': items.length }">
    <button v-for="item in items" :key="item.label" type="button" :class="{ selected: active === item.label }" :aria-label="item.label" :title="item.label" @click="$emit('select', item.label)"><span><SvgIcon :name="item.icon" /></span><span class="mobile-nav-label">{{ item.label }}</span></button>
  </nav>
</template>
