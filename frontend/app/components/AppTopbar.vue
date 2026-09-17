<script setup lang="ts">
defineProps<{ name: string; initials: string; compact?: boolean; canOpenProfile?: boolean }>()
defineEmits<{ notifications: []; profile: []; logout: [] }>()
</script>

<template>
  <header :class="['topbar', 'glass', { compact }]">
    <CondoBrand compact />
    <div class="search">⌕ <span>Buscar no CondoVale...</span></div>
    <div class="top-actions"><button class="icon-button" aria-label="Notificações" @click="$emit('notifications')"><SvgIcon name="message" /><b>2</b></button><button v-if="canOpenProfile" class="profile-trigger" aria-label="Abrir meu cadastro" title="Meu cadastro" @click="$emit('profile')"><span class="avatar">{{ initials }}</span><span class="greeting"><small>Bom dia,</small><strong>{{ name }}</strong></span></button><template v-else><div class="avatar">{{ initials }}</div><div class="greeting"><small>Bom dia,</small><strong>{{ name }}</strong></div></template><button class="logout-button" aria-label="Sair" title="Sair" @click="$emit('logout')">↗</button></div>
  </header>
</template>
