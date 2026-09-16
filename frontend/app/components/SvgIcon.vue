<script setup lang="ts">
import alert from '~/assets/icons/alert.svg'
import building from '~/assets/icons/building.svg'
import calendar from '~/assets/icons/calendar.svg'
import home from '~/assets/icons/home.svg'
import message from '~/assets/icons/message.svg'
import users from '~/assets/icons/users.svg'
import approve from '~/assets/icons/approve.svg'
import reject from '~/assets/icons/reject.svg'

const icons = { alert, approve, building, calendar, home, message, reject, users }
type IconName = keyof typeof icons

withDefaults(defineProps<{ name: IconName; label?: string }>(), { label: '' })
</script>

<template>
  <img class="svg-icon" :src="icons[name]" :alt="label" aria-hidden="true" />
</template>
