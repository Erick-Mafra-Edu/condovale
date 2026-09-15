<script setup lang="ts">
defineProps<{ name: string; initials: string; compact?: boolean }>()
defineEmits<{ notifications: [] }>()
</script>

<template>
  <header :class="['topbar', 'glass', { compact }]">
    <CondoBrand compact />
    <div class="search">⌕ <span>Buscar no CondoVale...</span></div>
    <div class="top-actions"><button class="icon-button" aria-label="Notificações" @click="$emit('notifications')"><SvgIcon name="message" /><b>2</b></button><div class="avatar">{{ initials }}</div><div class="greeting"><small>Bom dia,</small><strong>{{ name }}</strong></div></div>
  </header>
</template>
