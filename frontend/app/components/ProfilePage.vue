<script setup lang="ts">
import type { UpdateOwnProfileInput, User } from '~/domain/user'

const props = defineProps<{ user: User; saving?: boolean; error?: string; success?: string }>()
const emit = defineEmits<{ save: [input: UpdateOwnProfileInput] }>()
const name = ref(props.user.name)
const email = ref(props.user.email)
const isDirty = computed(() => name.value.trim() !== props.user.name || email.value.trim().toLowerCase() !== props.user.email)

watch(() => props.user, user => { name.value = user.name; email.value = user.email })

function reset() {
  name.value = props.user.name
  email.value = props.user.email
}

function submit() {
  if (!isDirty.value || !name.value.trim() || !email.value.trim()) return
  emit('save', { name: name.value.trim(), email: email.value.trim().toLowerCase() })
}
</script>

<template>
  <section class="profile-page">
    <header class="profile-heading"><div><span class="eyebrow">Dados pessoais</span><h2>Meu cadastro</h2><p>Atualize seus dados de contato. Informações da unidade e do perfil são administradas pelo condomínio.</p></div></header>
    <div class="profile-layout">
      <aside class="profile-summary glass"><div class="profile-avatar" aria-hidden="true">{{ user.name.split(' ').map(part => part[0]).slice(0, 2).join('').toUpperCase() }}</div><h3>{{ user.name }}</h3><p>{{ user.email }}</p><dl><div><dt>Perfil</dt><dd>Morador</dd></div><div><dt>Unidade vinculada</dt><dd>{{ user.unitId ?? 'Não informada' }}</dd></div><div><dt>Situação</dt><dd><span class="active-status">● Ativo</span></dd></div></dl></aside>
      <form class="profile-form panel" @submit.prevent="submit"><div class="form-heading"><h3>Informações de contato</h3><p>Estes dados serão usados para identificação e acesso ao CondoVale.</p></div><label for="profile-name">Nome completo<input id="profile-name" v-model="name" autocomplete="name" maxlength="120" required /></label><label for="profile-email">E-mail<input id="profile-email" v-model="email" type="email" autocomplete="email" maxlength="160" required /></label><div class="protected-data"><strong>Dados protegidos</strong><p>Perfil, situação e unidade não podem ser alterados nesta tela. Procure a administração caso precise corrigi-los.</p></div><p v-if="error" class="profile-feedback error" role="alert">{{ error }}</p><p v-if="success" class="profile-feedback success" role="status">{{ success }}</p><div class="profile-actions"><button type="button" class="outline-button" :disabled="saving || !isDirty" @click="reset">Descartar alterações</button><button type="submit" class="primary-button" :disabled="saving || !isDirty">{{ saving ? 'Salvando...' : 'Salvar alterações' }}</button></div></form>
    </div>
  </section>
</template>

<style scoped>
.profile-page{max-width:980px;margin:0 auto;padding-bottom:40px;color:var(--navy)}.profile-heading{margin-bottom:24px}.profile-heading h2{margin:8px 0 6px;font-size:30px;letter-spacing:-.04em}.profile-heading p,.form-heading p{margin:0;color:var(--neutral);font-size:13px;line-height:1.55}.profile-layout{display:grid;grid-template-columns:minmax(240px,.65fr) minmax(0,1.35fr);gap:18px;align-items:start}.profile-summary{padding:28px;border-radius:20px;text-align:center}.profile-avatar{width:76px;height:76px;margin:0 auto 15px;display:grid;place-items:center;border-radius:24px;background:var(--primary);color:#fff;font-size:24px;font-weight:800}.profile-summary h3{margin:0;font-size:20px}.profile-summary>p{margin:6px 0 22px;color:var(--neutral);font-size:12px}.profile-summary dl{margin:0;text-align:left}.profile-summary dl div{display:flex;justify-content:space-between;gap:16px;padding:13px 0;border-top:1px solid var(--line)}.profile-summary dt{color:var(--neutral);font-size:11px}.profile-summary dd{margin:0;font-size:11px;font-weight:800}.active-status{color:var(--success-text)}.profile-form{padding:26px}.form-heading{margin-bottom:20px}.form-heading h3{margin:0 0 6px;font-size:18px}.profile-form label{display:grid;gap:7px;margin-top:16px;color:var(--navy);font-size:12px;font-weight:800}.profile-form input{width:100%;min-height:44px;border:1px solid var(--line);border-radius:11px;background:var(--input);color:var(--navy);padding:0 13px;font:inherit}.profile-form input:focus{outline:3px solid var(--focus);outline-offset:2px;border-color:var(--primary)}.protected-data{margin-top:22px;padding:15px;border-radius:12px;background:var(--surface-raised);border:1px solid var(--line)}.protected-data strong{font-size:12px}.protected-data p{margin:5px 0 0;color:var(--neutral);font-size:11px;line-height:1.5}.profile-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:22px}.profile-actions button{min-height:42px}.profile-actions button:disabled{opacity:.5;cursor:not-allowed}.profile-feedback{margin:16px 0 0;padding:11px 13px;border-radius:10px;font-size:12px;font-weight:700}.profile-feedback.success{background:var(--success-bg);color:var(--success-text)}.profile-feedback.error{background:var(--danger-bg);color:var(--danger-text)}@media(max-width:720px){.profile-layout{grid-template-columns:1fr}.profile-summary{text-align:left}.profile-avatar{margin:0 0 15px}.profile-actions{display:grid;grid-template-columns:1fr}.profile-actions button{width:100%;justify-content:center}}
</style>
