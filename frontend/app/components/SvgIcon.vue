<script setup lang="ts">
import alertLight from '~/assets/condovale-icons/warning-dark.svg'
import alertDark from '~/assets/condovale-icons/warning-white.svg'
import brand from '~/assets/condovale-icons/brand-symbol-secondary.svg'
import buildingLight from '~/assets/condovale-icons/building-dark.svg'
import buildingDark from '~/assets/condovale-icons/building-white.svg'
import calendarLight from '~/assets/condovale-icons/calendar-dark.svg'
import calendarDark from '~/assets/condovale-icons/calendar-white.svg'
import homeLight from '~/assets/condovale-icons/home-dark.svg'
import homeDark from '~/assets/condovale-icons/home-white.svg'
import messageLight from '~/assets/condovale-icons/messages-dark.svg'
import messageDark from '~/assets/condovale-icons/messages-white.svg'
import usersLight from '~/assets/condovale-icons/residents-dark.svg'
import usersDark from '~/assets/condovale-icons/residents-white.svg'
import profileLight from '~/assets/condovale-icons/profile-dark.svg'
import profileDark from '~/assets/condovale-icons/profile-white.svg'
import occurrencesLight from '~/assets/condovale-icons/occurrences-dark.svg'
import occurrencesDark from '~/assets/condovale-icons/occurrences-white.svg'
import documentsLight from '~/assets/condovale-icons/documents-dark.svg'
import documentsDark from '~/assets/condovale-icons/documents-white.svg'
import approveLight from '~/assets/condovale-icons/check-dark.svg'
import approveDark from '~/assets/condovale-icons/check-white.svg'
import reject from '~/assets/icons/reject.svg'

const icons = {
  alert: { light: alertLight, dark: alertDark },
  brand: { light: brand, dark: brand },
  approve: { light: approveLight, dark: approveDark },
  building: { light: buildingLight, dark: buildingDark },
  calendar: { light: calendarLight, dark: calendarDark },
  home: { light: homeLight, dark: homeDark },
  message: { light: messageLight, dark: messageDark },
  profile: { light: profileLight, dark: profileDark },
  occurrences: { light: occurrencesLight, dark: occurrencesDark },
  documents: { light: documentsLight, dark: documentsDark },
  reject: { light: reject, dark: reject },
  users: { light: usersLight, dark: usersDark },
}
type IconName = keyof typeof icons

const props = withDefaults(defineProps<{ name: IconName; label?: string }>(), { label: '' })
</script>

<template>
  <span class="svg-icon" :role="props.label ? 'img' : undefined" :aria-label="props.label || undefined" :aria-hidden="props.label ? undefined : 'true'">
    <img class="svg-icon-variant svg-icon-light" :src="icons[props.name].light" alt="" aria-hidden="true" />
    <img class="svg-icon-variant svg-icon-dark" :src="icons[props.name].dark" alt="" aria-hidden="true" />
  </span>
</template>

<style>
.svg-icon{position:relative}
.svg-icon-variant{position:absolute;inset:0;width:100%;height:100%;object-fit:contain}
.svg-icon-dark{display:none!important}
html[data-theme='light'] .svg-icon-light,html:not([data-theme]) .svg-icon-light{display:block!important}
html[data-theme='light'] .svg-icon-dark,html:not([data-theme]) .svg-icon-dark{display:none!important}
html[data-theme='dark'] .svg-icon-light{display:none!important}
html[data-theme='dark'] .svg-icon-dark{display:block!important}
html[data-theme='dark'] .svg-icon,html[data-theme='dark'] .svg-icon-variant{filter:none!important}
</style>
