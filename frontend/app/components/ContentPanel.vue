<script setup lang="ts">
defineProps<{ title?: string; action?: string }>()
defineEmits<{ action: [] }>()
</script>

<template>
  <article class="panel">
    <div v-if="title" class="panel-head">
      <h2>{{ title }}</h2>
      <button v-if="action" @click="$emit('action')">{{ action }}<span class="action-arrow"></span></button>
    </div>
    <slot />
  </article>
</template>
