<script setup lang="ts">
import type { Unit, User } from '~/domain/user'
const props = defineProps<{ active: string; items: ReadonlyArray<{ label: string; icon: 'home' | 'alert' | 'calendar' | 'message' | 'building' | 'users' }>; user: User; units: Unit[] }>()
const unit = computed(() => props.user.unit ?? (props.user.unitId ? props.units.find(item => item.id === props.user.unitId) : undefined))
const unitLabel = computed(() => unit.value ? `${unit.value.block ? `${unit.value.block} · ` : ''}Unidade ${unit.value.number}` : 'Sem unidade vinculada')
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
    <div class="unit-card"><div class="unit-photo"><SvgIcon name="building" /></div><div><strong>Residencial Vale Verde</strong><small>{{ unitLabel }}</small></div><span class="chevron"></span></div>
    <div class="sidebar-foot"><span>◌</span> Ajuda e suporte <span>↗</span></div>
  </aside>
</template>
