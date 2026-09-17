# Rastreabilidade de requisitos e conformidade do frontend

Documento consolidado em 17/09/2026 para relacionar os requisitos formais recebidos, a implementação atual do frontend e as regras adicionais registradas durante a evolução do CondoVale.

## Escopo e precedência

Os identificadores `RF01–RF06`, `RNF01–RNF04` e `RN01–RN08` pertencem à especificação formal recebida. As regras `RN09–RN15` não constavam nessa especificação: foram registradas posteriormente em [business-rules.md](./business-rules.md) a partir de decisões funcionais e de interface.

As regras adicionais complementam os requisitos formais, exceto quando este documento indicar conflito. Em caso de conflito, a decisão deve ser registrada antes da alteração do código e dos testes.

Legenda de conformidade:

- **Atendido no frontend/mock:** fluxo funcional e verificável sem backend real.
- **Parcial:** existem camadas ou telas, mas falta autorização, regra, interface ou integração.
- **Não atendido:** não existe fluxo funcional correspondente.
- **Depende do backend:** não pode ser garantido somente pelo frontend.

## Fluxo de atribuição de ocorrências

O morador é responsável pela abertura, mas não escolhe o funcionário. Conforme o diagrama de casos de uso e a RN06, a administração analisa e atribui o atendimento a um funcionário ativo.

```text
Morador abre a ocorrência
          ↓
        open
          ↓ administrador analisa
       analysis
          ↓ administrador atribui funcionário ativo
       assigned
          ↓ funcionário atribuído inicia o atendimento
     in_progress
          ↓ funcionário atribuído finaliza
      completed
```

| Estado | Responsável pela entrada | Operação permitida |
| --- | --- | --- |
| `open` | Morador | Criar ocorrência com descrição e vínculo ao morador autenticado |
| `analysis` | Administrador | Analisar a solicitação antes da distribuição |
| `assigned` | Administrador | Selecionar funcionário ativo e registrar a atribuição |
| `in_progress` | Funcionário atribuído | Informar que o atendimento foi iniciado |
| `completed` | Funcionário atribuído | Finalizar com observação opcional |
| `cancelled` | Não definido | A especificação recebida não determina quem pode cancelar nem em qual etapa |

Toda mudança deve registrar ocorrência, autor autenticado, data, estado anterior, estado novo e observação aplicável. O morador acompanha apenas as próprias ocorrências; o funcionário consulta apenas as que foram atribuídas a ele; o administrador consulta a fila completa.

### Implementação existente

- `Occurrence` possui `residentId`, `assignedEmployeeId`, `status` e datas em [occurrence.ts](../app/domain/occurrence.ts).
- Contratos mock/API oferecem criação, atribuição, mudança de status, conclusão e histórico em [occurrence-repository.ts](../app/repositories/contracts/occurrence-repository.ts).
- O mock permite atribuição somente por administrador e exige funcionário ativo em [mock-occurrence-repository.ts](../app/repositories/mock/mock-occurrence-repository.ts).
- Funcionários diferentes e ocorrências atribuídas estão disponíveis em [mock-user-repository.ts](../app/repositories/mock/mock-user-repository.ts) e [state.ts](../app/repositories/mock/state.ts).
- A interface filtra ocorrências pela identidade autenticada e permite ao funcionário iniciar/finalizar seu próprio atendimento em [app.vue](../app/app.vue).
- A RN15 e os testes registram isolamento, transições e histórico.

### Implementação ainda necessária

1. Criar a fila administrativa de ocorrências abertas, em análise e atribuídas.
2. Usar `assignOccurrence()` no modal administrativo, permitindo selecionar somente funcionários ativos.
3. Exibir responsável atual e histórico de atribuições.
4. Definir e validar as transições administrativas; atualmente o mock permite ao administrador informar qualquer status.
5. No backend, obter o autor pela sessão autenticada. O `userId` enviado pelo cliente no contrato API é provisório e não deve ser usado como prova de identidade.
6. Aplicar policy no backend para impedir consulta, atribuição ou alteração fora do escopo do usuário.
7. Testar atribuição, tentativa por papel indevido, funcionário inativo, reatribuição e transições inválidas.

## Requisitos funcionais

| Requisito | Situação | Evidência e lacuna |
| --- | --- | --- |
| **RF01** — administrador cadastra, edita e inativa usuários | **Parcial** | Contrato, service, composable e adaptadores oferecem `create`, `update` e `deactivate`; falta tela administrativa e os mocks ainda não recebem o administrador autenticado para autorizar/auditar a operação. |
| **RF02** — login com e-mail e senha | **Atendido no frontend/mock; depende do backend em produção** | O mock autentica somente usuários ativos e a API prevê login, restauração de sessão e logout. A autenticação real ainda depende do Laravel. |
| **RF03** — morador reserva área disponível por data e horário | **Parcial** | Consulta, seleção de blocos, diária, criação e conflito funcionam. Horário permitido, status da área e duração máxima ainda precisam ser validados no service/repositório/backend, não apenas na interface. |
| **RF04** — morador abre ocorrência com descrição textual | **Atendido no frontend/mock** | A criação exige título, categoria, descrição e morador responsável. O backend deverá derivar o morador da sessão. |
| **RF05** — síndico/administrador publica comunicados | **Parcial** | A matriz de permissões inclui os dois papéis e existem contrato/service/adaptadores; falta tela de publicação e autorização no repositório/backend. |
| **RF06** — síndico/administrador gera relatórios de reservas e manutenções | **Parcial** | Síndico e administrador possuem `generate-reports`, e o administrador também consulta auditoria. Relatórios de reservas ainda não foram implementados. |

## Requisitos não funcionais

| Requisito | Situação | Evidência e lacuna |
| --- | --- | --- |
| **RNF01** — consultas abaixo de 2 segundos | **Depende do backend** | Não há banco, carga padrão definida, métricas nem teste de desempenho. Deve ser validado com volume, ambiente e percentil acordados. |
| **RNF02** — senhas exclusivamente com Argon2id | **Depende do backend** | O domínio frontend não armazena hash ou token. A senha fixa `condovale` existe somente no mock de desenvolvimento. Laravel deverá usar Argon2id e nunca devolver o hash. |
| **RNF03** — responsividade e navegadores modernos | **Parcial** | As telas possuem estilos responsivos e acessibilidade básica; falta uma matriz automatizada/manual para Chrome, Firefox e Edge. |
| **RNF04** — auditoria de operações críticas | **Parcial** | Existe relatório administrativo e o mock registra ações de ocorrências e decisões de reservas. Cadastro/inativação de usuários, comunicados e demais ações administrativas ainda precisam integrar a trilha uniforme. |

## Regras de negócio formais

| Regra | Situação | Evidência e lacuna |
| --- | --- | --- |
| **RN01** — impedir conflito de reserva | **Atendido no mock** | Reservas `pending` e `approved` bloqueiam sobreposição para mesma área/data. |
| **RN02** — respeitar disponibilidade e regras da área | **Parcial** | Conflito e disponibilidade visual existem; validação central de área ativa, funcionamento e duração máxima permanece pendente. |
| **RN03** — somente síndico/admin exclui avisos; somente autorizados alteram unidades | **Não atendido efetivamente** | Existe `remove` de comunicado sem ator/autorização. Unidades possuem somente leitura e não há fluxo administrativo estrutural. |
| **RN04** — somente usuários ativos e autorizados acessam funções | **Parcial** | Login mock verifica usuário ativo e a interface usa matriz de permissões. Alguns repositórios mock ainda aceitam operações sem validar sessão/papel; a proteção definitiva depende de policies no backend. |
| **RN05** — ocorrência associada ao morador e com status | **Atendido no frontend/mock** | `residentId` e `status` são obrigatórios no domínio e na criação. |
| **RN06** — somente autorizados alteram/finalizam ocorrências | **Parcial** | O mock protege atribuição e ações do funcionário. Falta tela administrativa de atribuição, policy backend e remoção da confiança em `userId` fornecido pelo cliente. |
| **RN07** — somente síndico/admin publica comunicados | **Parcial** | A permissão está declarada, mas o service e o mock não validam o papel do autor e não existe tela de publicação. |
| **RN08** — cancelamento libera período | **Atendido no mock** | Reservas canceladas e rejeitadas deixam de bloquear a agenda. |

## Comparação com regras adicionais do frontend

As regras abaixo foram criadas durante a implementação e não vieram na especificação formal desta revisão.

| Regra adicional | Relação com os requisitos formais | Compatibilidade |
| --- | --- | --- |
| **RN09** — PDF sempre em modo claro | Detalha apresentação e acessibilidade dos relatórios relacionados ao RF06 e RNF03. | **Complementar, sem conflito.** |
| **RN10** — relatórios operacionais do síndico e administrador | Define quem possui `generate-reports` e restringe auditoria ao administrador. | **Compatível após revisão pelo RF06.** |
| **RN11** — filtros e paginação de relatórios | Detalha a interface e o contrato de consulta do RF06. | **Complementar, sem conflito.** |
| **RN12** — exportação consulta todos os registros | Garante que a paginação visual não corte PDF/Excel do RF06. | **Complementar, sem conflito.** |
| **RN13** — seleção de datas com calendário | Detalha acessibilidade e formato dos filtros do RF06/RNF03. | **Complementar, sem conflito.** |
| **RN14** — disponibilidade e solicitação de reservas | Expande RF03, RN01, RN02 e RN08 com blocos de 30 minutos, diária, privacidade e aprovação. | **Complementar.** Exige que as validações deixem de depender apenas da UI. |
| **RN15** — atendimento por funcionário atribuído | Expande RN05 e RN06, definindo isolamento e transições do funcionário. | **Complementar, sem conflito.** A etapa administrativa de atribuição ainda precisa ser implementada. |
| **RN16** — relatórios de auditoria | Detalha RNF04 com campos mínimos, acesso administrativo, filtros, paginação e exportação. | **Complementar, sem conflito.** |

## Divergências prioritárias

1. **RF06:** acrescentar relatório de reservas; o acesso operacional de síndico e administrador já foi alinhado.
2. **RN06/RN15:** implementar a análise e atribuição administrativa na interface.
3. **RN04/RN07:** mover autorização efetiva para repositories mock e, posteriormente, policies do backend.
4. **RF01/RN03:** implementar telas administrativas de usuários/unidades e registrar auditoria.
5. **RNF02/RNF04:** implementar Argon2id, sessão, policies e logs de auditoria no backend Laravel.
6. **RNF01:** definir carga padrão, volume de dados e percentil antes de criar o teste de desempenho.

## Decisões ainda necessárias

- Definir quem pode cancelar uma ocorrência e em quais estados.
- Definir se o administrador pode reatribuir atendimento já iniciado e se justificativa é obrigatória.
- Definir se ocorrências concluídas podem ser reabertas.
- Definir os eventos mínimos e a retenção dos logs exigidos pela RNF04.
