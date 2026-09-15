<script setup lang="ts">
withDefaults(defineProps<{ compact?: boolean }>(), { compact: false })
</script>

<template>
  <div :class="['condo-brand', { compact }]">
    <span class="brand-mark"><SvgIcon name="building" /></span>
    <span class="brand-name">Condo<span>Vale</span></span>
    <small v-if="!compact">PESSOAS EM PRIMEIRO LUGAR</small>
  </div>
</template>
