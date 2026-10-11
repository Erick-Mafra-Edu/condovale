<script setup lang="ts">
type CoverageStatus = 'implemented' | 'missing'

interface CoverageRow {
  screen: string
  operations: string
  endpoints: string
  backend: CoverageStatus
}

const coverage: CoverageRow[] = [
  { screen: 'Autenticação e perfil', operations: 'Entrar, restaurar sessão, sair, atualizar cadastro', endpoints: 'POST /auth/login · GET /auth/session · POST /auth/logout · GET/PATCH /users/me', backend: 'implemented' },
  { screen: 'Usuários', operations: 'Listar, criar, editar, inativar', endpoints: 'GET/POST /users · PATCH /users/{id} · POST /users/{id}/deactivate', backend: 'implemented' },
  { screen: 'Unidades', operations: 'Listar, criar, editar/reativar, inativar', endpoints: 'GET/POST /units · PATCH /units/{id} · POST /units/{id}/deactivate', backend: 'implemented' },
  { screen: 'Moradores e unidades', operations: 'Listar vínculos, criar, consultar, encerrar', endpoints: 'GET/POST /unit-occupancies · GET /unit-occupancies/{id} · DELETE /unit-occupancies/{id}', backend: 'implemented' },
  { screen: 'Comunicados', operations: 'Listar publicados', endpoints: 'GET /notices', backend: 'missing' },
  { screen: 'Reservas e áreas comuns', operations: 'Listar áreas/ocupação, listar reservas próprias/todas, criar, cancelar, aprovar/reprovar', endpoints: 'GET /reservations · GET /reservations/me · POST /reservations · POST /reservations/{id}/cancel · PATCH /reservations/{id}/status · GET /common-areas · GET /common-areas/{id}/occupancy', backend: 'missing' },
  { screen: 'Ocorrências', operations: 'Listar, criar, consultar histórico, atualizar e finalizar', endpoints: 'GET/POST /occurrences · GET /occurrences/{id}/history · PATCH /occurrences/{id}/status · POST /occurrences/{id}/finish', backend: 'missing' },
  { screen: 'Auditoria', operations: 'Listar e exportar logs de auditoria', endpoints: 'GET /audit-logs', backend: 'missing' },
]
</script>

<template>
  <section class="section-intro integration-intro">
    <div class="section-icon"><SvgIcon name="building" /></div>
    <div><h2>Integrações</h2><p>Relação entre telas do frontend e endpoints esperados pelo backend.</p></div>
  </section>

  <article class="panel detail-panel integration-panel" aria-labelledby="integration-heading">
    <div class="panel-head"><h2 id="integration-heading">Cobertura de integrações</h2><span class="integration-count">{{ coverage.length }} módulos</span></div>
    <div class="integration-table" role="table" aria-label="Cobertura de telas e endpoints">
      <div class="integration-row integration-header" role="row">
        <span role="columnheader">Tela / módulo</span><span role="columnheader">Operações usadas</span><span role="columnheader">Endpoints esperados</span><span role="columnheader">Backend</span>
      </div>
      <div v-for="item in coverage" :key="item.screen" class="integration-row" role="row">
        <strong role="cell">{{ item.screen }}</strong>
        <span role="cell">{{ item.operations }}</span>
        <code role="cell">{{ item.endpoints }}</code>
        <em role="cell" :class="item.backend === 'implemented' ? 'green' : 'red'">{{ item.backend === 'implemented' ? 'Implementado' : 'Ausente' }}</em>
      </div>
    </div>
  </article>
</template>

<style scoped>
.integration-intro{align-items:flex-start}.integration-panel{overflow:hidden}.integration-count{color:var(--neutral);font-size:10px}.integration-table{overflow-x:auto}.integration-row{display:grid;grid-template-columns:minmax(150px,.8fr) minmax(220px,1.2fr) minmax(280px,1.5fr) 110px;gap:14px;align-items:center;padding:14px 4px;border-top:1px solid var(--line);font-size:10px;line-height:1.45}.integration-header{border-top:0;padding-top:4px;color:var(--neutral);font-size:9px;font-weight:700}.integration-row strong{font-size:11px}.integration-row code{font:inherit;color:var(--neutral);overflow-wrap:anywhere}.integration-row em{justify-self:start;font-size:9px;font-style:normal;border-radius:12px;padding:5px 8px;white-space:nowrap}@media(max-width:900px){.integration-panel{padding:12px}.integration-row{grid-template-columns:minmax(130px,.8fr) minmax(190px,1fr) minmax(260px,1.3fr) 100px;gap:10px}.integration-header{display:none}}
</style>
