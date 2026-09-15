<script setup lang="ts">
defineProps<{ title: string; icon: 'alert' | 'calendar' | 'message' }>()
defineEmits<{ close: [] }>()
</script>

<template>
  <div class="modal-backdrop" @click.self="$emit('close')">
    <section class="modal detail-modal glass" role="dialog" aria-modal="true" :aria-label="title">
      <button class="modal-close" aria-label="Fechar detalhes" @click="$emit('close')"></button>
      <div class="section-icon"><SvgIcon :name="icon" /></div>
      <h2>{{ title }}</h2>
      <slot />
      <div class="modal-actions"><button class="outline-button" @click="$emit('close')">Fechar</button></div>
    </section>
  </div>
</template>
