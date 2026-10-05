<script setup lang="ts">
import { nextTick, ref } from 'vue'

defineProps<{ name: string; initials: string; compact?: boolean; canOpenProfile?: boolean; notificationCount: number }>()
defineEmits<{ notifications: []; profile: []; logout: [] }>()

const searchOpen = ref(false)
const searchInput = ref<HTMLInputElement | null>(null)
const searchTrigger = ref<HTMLElement | null>(null)

const openSearch = async () => {
  searchOpen.value = true
  await nextTick()
  searchInput.value?.focus()
}

const closeSearch = async (restoreFocus = false) => {
  searchOpen.value = false
  if (restoreFocus) {
    await nextTick()
    searchTrigger.value?.focus()
  }
}

</script>

<template>
  <header :class="['topbar', 'glass', { compact, 'search-open': searchOpen }]">
    <CondoBrand compact />
    <div class="search" :class="{ open: searchOpen }" role="search">
      <button ref="searchTrigger" class="search-trigger" type="button" :aria-expanded="searchOpen" aria-label="Abrir busca" @click="openSearch"><span aria-hidden="true">⌕</span></button>
      <input ref="searchInput" type="search" placeholder="Buscar no CondoVale..." aria-label="Buscar no CondoVale" :tabindex="searchOpen ? 0 : -1" @keydown.esc.prevent="closeSearch(true)" @blur="closeSearch(false)" />
    </div>
    <div class="top-actions"><button v-if="notificationCount > 0" class="icon-button" aria-label="Notificações" @click="$emit('notifications')"><SvgIcon name="message" /><b>{{ notificationCount }}</b></button><button v-if="canOpenProfile" class="profile-trigger" aria-label="Abrir meu cadastro" title="Meu cadastro" @click="$emit('profile')"><span class="avatar">{{ initials }}</span><span class="greeting"><small>Bom dia,</small><strong>{{ name }}</strong></span></button><template v-else><div class="avatar">{{ initials }}</div><div class="greeting"><small>Bom dia,</small><strong>{{ name }}</strong></div></template><button class="logout-button" aria-label="Sair" title="Sair" @click="$emit('logout')">↗</button></div>
  </header>
</template>
