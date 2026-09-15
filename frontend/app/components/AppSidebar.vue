<script setup lang="ts">
defineProps<{ active: string; items: ReadonlyArray<{ label: string; icon: 'home' | 'alert' | 'calendar' | 'message' | 'building' }> }>()
defineEmits<{ select: [label: string] }>()
</script>

<template>
  <aside class="sidebar">
    <CondoBrand />
    <nav>
      <button v-for="item in items" :key="item.label" :class="['nav-item', { selected: active === item.label }]" @click="$emit('select', item.label)">
        <span class="nav-icon"><SvgIcon :name="item.icon" /></span>{{ item.label }}
      </button>
    </nav>
    <div class="sidebar-spacer"></div>
    <div class="unit-card"><div class="unit-photo"><SvgIcon name="building" /></div><div><strong>Residencial Vale Verde</strong><small>Bloco B · Unidade 204</small></div><span class="chevron"></span></div>
    <div class="sidebar-foot"><span>◌</span> Ajuda e suporte <span>↗</span></div>
  </aside>
</template>
