# Plano de testes — regras de negócio, processos e cenários de erro

## 1. Objetivo

Este documento define os testes funcionais, de integração, segurança e resiliência necessários para validar as regras de negócio e os processos do Condovale. O plano dá ênfase a testes negativos: entradas, permissões e sequências deliberadamente inválidas que devem ser recusadas de forma controlada, sem alterar dados indevidamente e sem expor informações sensíveis.

Um teste negativo é considerado aprovado quando o sistema impede a operação, retorna uma resposta coerente (por exemplo, `400`, `401`, `403`, `404`, `409` ou `422`, conforme o contrato), preserva o estado anterior e registra auditoria quando aplicável. Um erro `5xx`, uma tela quebrada ou uma alteração parcial não são resultados aceitáveis.

## 2. Fontes e escopo

O plano foi elaborado a partir das seguintes fontes existentes:

- `frontend/docs/business-rules.md`: RN09 a RN18, reservas, relatórios, ocorrências, auditoria, perfil e comunicados;
- `frontend/docs/requirements-compliance.md`: RF01 a RF06, RN01 a RN08 e requisitos não funcionais;
- `docs/ocorrencias-integracao-auditoria.md`: integração, transições e auditoria de ocorrências;
- `docs/frontend-guia/01-frontend-autenticacao-ui.md`: autenticação, interface e estratégia Vitest;
- `docs/frontend-guia/02-usuarios-unidades-permissoes.md`: usuários, unidades e permissões;
- `docs/frontend-guia/03-ocorrencias-workflow-auditoria.md`: fluxo de ocorrências e trilha de auditoria;
- testes existentes em `frontend/tests/` e `laravel/tests/Feature/`.

Estão no escopo: autenticação e autorização, usuários e unidades, reservas, ocorrências, comunicados, relatórios, exportações, auditoria, perfil próprio, contratos HTTP e requisitos não funcionais. Casos marcados como dependentes de API devem permanecer planejados enquanto o endpoint correspondente não estiver disponível.

## 3. Critérios gerais

### 3.1 Prioridade

- **P0 — crítica:** acesso indevido, vazamento ou corrupção de dados, quebra de autenticação ou de uma transição central.
- **P1 — alta:** regra de negócio principal, inconsistência de processo ou falha sem alternativa segura.
- **P2 — média:** apresentação, compatibilidade, desempenho ou comportamento com alternativa operacional.

### 3.2 Evidências mínimas

Cada execução deve guardar, conforme o nível do teste:

- identificação do ambiente, versão/commit, data e executor;
- dados de entrada sem credenciais ou dados pessoais reais;
- resposta HTTP e corpo sanitizado;
- estado anterior e posterior dos registros envolvidos;
- captura de tela para testes de interface;
- evento de auditoria gerado;
- log técnico apenas quando houver falha inesperada.

### 3.3 Dados-base

Preparar usuários ativos e inativos para os papéis de administrador, síndico, funcionário e morador; duas unidades distintas; dois funcionários; uma área comum ativa e uma inativa; uma área com aprovação obrigatória; ocorrências em cada estado; comunicados em rascunho e publicados; e reservas livres, pendentes, aprovadas, canceladas e sobrepostas em potencial.

Os testes de autorização devem usar, no mínimo, dois usuários do mesmo papel. Isso evita falsos positivos nos quais o papel é validado, mas a propriedade do recurso não é.

## 4. Casos de teste

### 4.1 Autenticação, sessão e autorização

| ID | Pri. | Cenário e ação | Resultado esperado | Erro provocado / risco coberto | Fonte |
|---|---:|---|---|---|---|
| AUT-01 | P0 | Autenticar usuário ativo com credenciais válidas. | Sessão criada; identidade e papel correspondem ao usuário persistido. | Caso de controle positivo. | RN01, RF01 |
| AUT-02 | P0 | Informar senha incorreta. | Acesso recusado sem revelar se login ou senha está incorreto; nenhuma sessão criada. | Credencial inválida. | RN01 |
| AUT-03 | P0 | Autenticar usuário inativo com credenciais corretas. | Acesso recusado e ausência de sessão utilizável. | Contorno de status do usuário. | RN01 |
| AUT-04 | P0 | Chamar endpoint protegido sem token, com token inválido e com token expirado. | `401`; nenhuma leitura ou mutação; interface redireciona para autenticação quando aplicável. | Ausência/adulteração de sessão. | RN01, RN05 |
| AUT-05 | P0 | Usuário autenticado altera `userId`, `role` ou autor no payload. | Backend deriva identidade e papel da sessão e ignora/recusa os campos adulterados. | Escalada horizontal/vertical de privilégio. | RN05, RN16 |
| AUT-06 | P0 | Executar ação administrativa como morador, funcionário e síndico sem concessão explícita. | `403`; estado e auditoria de sucesso permanecem inalterados. | Falta de policy no backend. | RN03, RN05, RN10 |

### 4.2 Usuários, unidades e perfil próprio

| ID | Pri. | Cenário e ação | Resultado esperado | Erro provocado / risco coberto | Fonte |
|---|---:|---|---|---|---|
| USR-01 | P1 | Administrador cria usuário com dados válidos e associação compatível. | Usuário criado uma única vez e associação persistida. | Caso de controle positivo. | RF01, RF02 |
| USR-02 | P1 | Criar ou editar usuário com e-mail inválido ou já utilizado. | `422`/conflito de domínio; nenhuma gravação parcial. | Formato e unicidade inválidos. | RN17 |
| USR-03 | P0 | Morador tenta criar, editar, ativar ou inativar outro usuário. | `403`; registro alvo inalterado. | Administração indevida. | RN05 |
| USR-04 | P0 | Em `PATCH /users/me`, enviar `role`, `status`, `unitId` ou ID de outro usuário. | Somente nome e e-mail do usuário da sessão podem mudar; campos protegidos são recusados/ignorados de forma explícita. | Mass assignment e troca de identidade. | RN17 |
| USR-05 | P1 | Associar morador a unidade inexistente/inativa ou criar vínculo duplicado. | Operação rejeitada; vínculos existentes preservados. | Referência inválida e duplicidade. | RF02 |
| USR-06 | P1 | Remover ocupante inexistente ou tentar remover vínculo de outra unidade sem permissão. | `404` ou `403`, conforme o caso; nenhuma associação válida removida. | ID adulterado. | RF02, RN05 |

### 4.3 Reservas de áreas comuns

| ID | Pri. | Cenário e ação | Resultado esperado | Erro provocado / risco coberto | Fonte |
|---|---:|---|---|---|---|
| RSV-01 | P1 | Morador reserva área ativa em intervalo livre de 30 minutos, dentro do horário e da duração máxima. | Reserva criada; estado inicial respeita a configuração de aprovação. | Caso de controle positivo. | RN02, RN13 |
| RSV-02 | P0 | Criar reserva que sobrepõe total ou parcialmente outra reserva válida. | Conflito rejeitado, inclusive sob duas requisições concorrentes; apenas uma reserva persiste. | Sobreposição e condição de corrida. | RN02, RN13 |
| RSV-03 | P1 | Reservar área inativa. | Operação rejeitada e agenda não alterada. | Recurso indisponível. | RN02, RN13 |
| RSV-04 | P1 | Reservar antes/depois do horário permitido ou acima de `maxReservationMinutes`. | Validação rejeita o intervalo e informa o campo/regra violado. | Limites de horário e duração. | RN02, RN13 |
| RSV-05 | P1 | Enviar data passada, célula externa ao período exibido ou início maior/igual ao fim. | Operação rejeitada sem normalização silenciosa para outra data/hora. | Data e intervalo inválidos. | RN13 |
| RSV-06 | P1 | Informar somente `startTime`, somente `endTime`, horário fora da grade ou bloco não múltiplo de 30 minutos. | `422`; nenhuma reserva parcial criada. | Payload incompleto/incompatível. | RN13 |
| RSV-07 | P1 | Criar reserva diária quando já existe reserva no dia, ou reservar horário em dia bloqueado por diária. | Conflito rejeitado em ambos os sentidos. | Interação diária versus horária. | RN13 |
| RSV-08 | P0 | Consultar disponibilidade como outro morador. | Retorna somente estado de disponibilidade; não expõe nome, unidade ou dados do titular. | Vazamento de dados pessoais. | RN13 |
| RSV-09 | P0 | Morador/funcionário tenta aprovar ou reprovar; administrador tenta decidir reserva não pendente. | `403` para papel indevido e conflito de estado para reserva não pendente. | Permissão e transição inválida. | RN13 |
| RSV-10 | P0 | Usuário tenta cancelar reserva de terceiro ou reserva em estado não cancelável. | Operação rejeitada; reserva original preservada. | ID adulterado/transição indevida. | RN05, RN13 |

### 4.4 Ocorrências e fluxo de atendimento

Fluxo esperado: `open → analysis → assigned → in_progress → completed`.

| ID | Pri. | Cenário e ação | Resultado esperado | Erro provocado / risco coberto | Fonte |
|---|---:|---|---|---|---|
| OCR-01 | P1 | Morador ativo abre ocorrência válida para sua unidade. | Ocorrência criada em `open`; autor vem da sessão; evento inicial registrado. | Caso de controle positivo. | RF04, RN06 |
| OCR-02 | P1 | Criar ocorrência sem unidade/tipo obrigatório, com tipo inativo ou por morador inativo. | `422`/`403`; nenhuma ocorrência ou histórico órfão criado. | Pré-condição inválida. | RN06 |
| OCR-03 | P0 | Morador tenta informar outra unidade ou outro autor no payload. | Backend utiliza os vínculos e a identidade da sessão; tentativa recusada. | Falsificação de autoria/propriedade. | RN05, RN06 |
| OCR-04 | P0 | Morador consulta ocorrência de outro morador; funcionário consulta ocorrência não atribuída. | `403` ou `404` sem confirmar a existência do recurso. | Enumeração e acesso horizontal. | RN05, RN06 |
| OCR-05 | P1 | Administrador executa `open → analysis` e atribui funcionário ativo. | Estados e responsável atualizados; histórico contém autor, data, anterior, novo e observação. | Caso de controle do processo. | RN06, RN16 |
| OCR-06 | P0 | Tentar `open → completed`, `analysis → in_progress` ou outra transição fora do fluxo. | Conflito de estado; ocorrência e responsável permanecem inalterados. | Salto de etapa. | RN06, RN15 |
| OCR-07 | P0 | Atribuir a usuário inativo, inexistente ou que não seja funcionário. | Operação rejeitada sem alterar o estado para `assigned`. | Responsável inválido. | RN06 |
| OCR-08 | P1 | Reatribuir ocorrência já iniciada ou atribuir sem análise prévia. | Operação rejeitada conforme o fluxo; nenhum histórico contraditório. | Reatribuição/transição indevida. | RN06 |
| OCR-09 | P0 | Funcionário não atribuído tenta iniciar/finalizar; funcionário atribuído tenta finalizar antes de iniciar. | `403` ou conflito de estado; ocorrência preservada. | Apropriação indevida do atendimento. | RN15 |
| OCR-10 | P1 | Duas requisições tentam iniciar ou finalizar a mesma ocorrência simultaneamente. | Uma única transição é efetivada; sem eventos duplicados ou perda de atualização. | Condição de corrida/idempotência. | RN15, RN16 |
| OCR-11 | P0 | Enviar autor, estado anterior ou data falsos no evento. | Backend deriva metadados da sessão e do estado persistido. | Adulteração da auditoria. | RN16 |
| OCR-12 | P1 | Simular falha ao persistir o histórico durante a transição. | Transação revertida integralmente; estado e histórico continuam consistentes. | Atualização parcial. | RN16 |

### 4.5 Comunicados

| ID | Pri. | Cenário e ação | Resultado esperado | Erro provocado / risco coberto | Fonte |
|---|---:|---|---|---|---|
| COM-01 | P1 | Administrador publica comunicado válido. | Comunicado publicado e visível conforme a ordenação definida. | Caso de controle positivo. | RN07, RN18 |
| COM-02 | P0 | Morador, funcionário ou síndico sem concessão publica, edita ou remove comunicado. | `403`; conteúdo permanece inalterado. | Autorização apenas visual no frontend. | RN05, RN07 |
| COM-03 | P0 | Consultar listagem/painel como morador com comunicados em rascunho. | Somente publicados são retornados; rascunhos não vazam em resposta, contagem ou notificação. | Exposição de rascunho. | RN18 |
| COM-04 | P1 | Criar comunicado sem campos obrigatórios ou com formato/tamanho fora do contrato. | `422`; nenhum rascunho inválido ou publicação parcial. | Payload inválido. | RN07 |
| COM-05 | P2 | Validar painel e notificações com mais de três comunicados publicados. | Exibe os três mais recentes em ordem decrescente. | Ordenação/limite incorretos. | RN18 |

### 4.6 Relatórios, exportação e auditoria

| ID | Pri. | Cenário e ação | Resultado esperado | Erro provocado / risco coberto | Fonte |
|---|---:|---|---|---|---|
| RPT-01 | P1 | Aplicar filtros, ordenação e paginação em relatório com massa conhecida. | Total, itens e páginas são consistentes; filtros permanecem ativos ao navegar. | Paginação/filtro divergentes. | RN11 |
| RPT-02 | P1 | Exportar relatório filtrado com várias páginas. | Exportação contém todos e somente os registros filtrados, não apenas a página atual. | Exportação truncada. | RN11 |
| RPT-03 | P2 | Exportar/imprimir enquanto a aplicação usa tema escuro. | Documento/PDF usa fundo claro, oculta elementos `.no-print` e restaura o tema da aplicação. | PDF ilegível/efeito colateral visual. | RN09 |
| RPT-04 | P0 | Morador ou funcionário acessa relatório administrativo; síndico acessa auditoria sem permissão explícita. | `403`; nenhuma informação agregada ou pessoal retornada. | Falha de autorização. | RN10, RN16 |
| AUD-01 | P1 | Administrador filtra auditoria por período, entidade e responsável. | Resultados e paginação correspondem aos eventos persistidos. | Caso de controle positivo. | RN16 |
| AUD-02 | P0 | Gerar mutação auditável e tentar forjar/omitir autor, data, entidade ou ID. | Backend registra todos os campos obrigatórios com valores confiáveis da sessão/servidor. | Trilha incompleta ou adulterada. | RN16, RNF04 |
| AUD-03 | P0 | Provocar falha na operação de negócio após iniciar registro de auditoria. | Não há evento de sucesso falso; regra transacional definida é preservada. | Auditoria divergente do estado real. | RN16 |
| AUD-04 | P1 | Exportar auditoria com filtros e múltiplas páginas. | Arquivo completo respeita filtros e acesso administrativo; sem colunas sensíveis não previstas. | Truncamento/vazamento. | RN10, RN16 |

### 4.7 Contratos HTTP, falhas e resiliência

| ID | Pri. | Cenário e ação | Resultado esperado | Erro provocado / risco coberto | Fonte |
|---|---:|---|---|---|---|
| API-01 | P1 | Backend retorna `400`, `401`, `403`, `404`, `409` e `422` nos fluxos correspondentes. | Frontend normaliza e apresenta mensagem acionável, sem perder dados preenchidos quando seguro. | Tratamento inconsistente de erro. | Testes `api-*.spec.ts`, `app-error.spec.ts` |
| API-02 | P1 | Simular `500`, `503`, timeout, conexão interrompida e resposta não JSON. | Interface sai do estado de carregamento, informa indisponibilidade e permite nova tentativa segura. | Falha de rede/servidor. | RN08 |
| API-03 | P0 | Repetir uma requisição de mutação após timeout cuja resposta é desconhecida. | Não cria reservas, ocorrências, eventos ou comunicados duplicados; consulta/reconciliação é possível. | Reenvio e falta de idempotência. | RN08, RN16 |
| API-04 | P1 | API retorna campos ausentes, IDs em tipo inesperado ou paginação inconsistente. | Contrato é validado; falha é controlada e observável, sem renderização enganosa. | Quebra de contrato snake/camel e tipos. | Testes `api-*.spec.ts` |
| API-05 | P0 | Enviar payload com campos adicionais privilegiados e conteúdo destinado a injeção. | Campos não permitidos são rejeitados; conteúdo é tratado como dado; nenhuma execução ou mass assignment. | Injeção e sobrepostagem. | RN05 |

### 4.8 Requisitos não funcionais e compatibilidade

| ID | Pri. | Cenário e ação | Resultado esperado | Erro provocado / risco coberto | Fonte |
|---|---:|---|---|---|---|
| NFR-01 | P2 | Medir listagens e fluxos principais com volume representativo e concorrência acordada. | Atende aos limites de tempo definidos antes da execução; sem crescimento anormal de erros. | Ausência atual de teste de carga. | RNF01 |
| NFR-02 | P0 | Inspecionar armazenamento/verificação de senha no backend. | Senhas usam Argon2id e nunca aparecem em logs, respostas ou auditoria. | Hash inadequado/vazamento. | RNF02 |
| NFR-03 | P2 | Executar jornada crítica nas versões suportadas de Chrome, Firefox e Edge. | Comportamento funcional e layout equivalentes, inclusive calendário e exportação. | Falta de matriz cross-browser. | RNF03 |
| NFR-04 | P1 | Navegar com teclado e leitor de tela por calendário, formulários, erros e diálogos. | Foco visível, nomes acessíveis, ordem lógica e anúncio dos erros; calendário não depende apenas de cor. | Barreira de acessibilidade. | RN12, RN13 |
| NFR-05 | P2 | Testar larguras de celular, tablet e desktop nas jornadas críticas. | Sem perda de ação, sobreposição ou rolagem impeditiva. | Falta de validação responsiva. | RNF03 |
| NFR-06 | P1 | Verificar logs após credenciais inválidas, erros de validação, timeout e falhas internas. | Logs permitem correlação sem senha, token ou dado pessoal desnecessário. | Observabilidade insegura. | RN08, RNF04 |

## 5. Cobertura automatizada existente e planejada

A suíte do frontend utiliza Vitest e já cobre regras de negócio, contratos da API, normalização de erros, ocupação de unidades, reservas, ocorrências e relatórios. Os testes de feature do Laravel cobrem criação e guardas de transição de ocorrências. Essa cobertura deve ser reutilizada e expandida, sem considerar testes unitários como substitutos dos testes de integração e E2E.

Prioridade de automação:

1. **API/feature:** autorização real, propriedade do recurso, validações centrais, concorrência e transações.
2. **Unitário/componente:** combinações de entrada, mensagens, estados visuais e normalização de erros.
3. **E2E:** login, reserva, fluxo completo de ocorrência, perfil, comunicado e exportação.
4. **Não funcional:** carga, matriz de navegadores, acessibilidade e responsividade.

Casos dependentes de endpoints ainda indisponíveis — especialmente fila, histórico e atribuição de ocorrências — devem ser automatizados no nível de contrato agora e habilitados como integração quando a API estiver pronta. Não devem ser simulados como se comprovassem autorização do backend.

## 6. Ordem recomendada de execução

1. Smoke tests positivos de autenticação e saúde da API.
2. Casos P0 de autorização, propriedade e vazamento.
3. Casos P0/P1 de reservas e transições de ocorrências.
4. Integridade de auditoria e exportações.
5. Falhas HTTP, rede, concorrência e idempotência.
6. Casos P2 e requisitos não funcionais.

Interromper a execução para análise quando houver corrupção de dados, vazamento de informação, indisponibilidade geral ou resultado não determinístico que contamine os casos seguintes.

## 7. Critérios de entrada e saída

### Entrada

- ambiente e versão identificados;
- migrações e dados-base aplicados;
- integrações necessárias disponíveis ou claramente simuladas;
- critérios de desempenho mensuráveis definidos antes do teste;
- nenhum defeito bloqueante conhecido que invalide a execução.

### Saída

- 100% dos casos P0 executados e aprovados;
- casos P1 executados, sem defeito alto aberto sem aceite explícito;
- falhas documentadas com evidência e possibilidade de reprodução;
- ausência de mutações parciais, vazamentos e eventos de auditoria contraditórios;
- relatório final registra aprovados, reprovados, bloqueados e não executados.

## 8. Registro de execução

Usar uma linha por execução, preservando o ID deste plano:

| Execução | Caso | Ambiente/versão | Data | Resultado | Evidência | Defeito | Observação |
|---|---|---|---|---|---|---|---|
| EX-001 | AUT-01 | A preencher | A preencher | Não executado | — | — | — |

Resultados permitidos: **Aprovado**, **Reprovado**, **Bloqueado** e **Não executado**. Um caso bloqueado deve indicar a dependência concreta, como endpoint ausente, massa inválida ou ambiente indisponível.

## 9. Verificação de prontidão E2E

### 9.1 Situação verificada

- O frontend usa Nuxt 4 e Vitest em ambiente `node`. Os testes em `frontend/tests/**/*.spec.ts` são unitários ou de contrato; não são E2E de navegador.
- Não há Playwright, Cypress ou Selenium configurado, nem script `e2e` no `frontend/package.json`.
- O Puppeteer existente é usado por `frontend/scripts/capture-ui.mjs` para gerar capturas. O script consegue fazer login e navegar, mas não contém asserções de negócio e, sozinho, não constitui uma suíte E2E.
- O modo mock permite smoke tests determinísticos com os papéis morador, funcionário, síndico e administrador. Esses testes validam a integração da interface, mas não comprovam autenticação, autorização ou persistência do backend.
- Autenticação, usuários, unidades, ocupações, tipos e ocorrências possuem rotas Laravel. Reservas, comunicados e relatórios ainda não estão disponíveis como módulos completos da API; seus E2E reais permanecem bloqueados.
- O E2E contra API exige Laravel e Nuxt simultaneamente, banco semeado e frontend configurado com `NUXT_PUBLIC_DATA_SOURCE=api` e `NUXT_PUBLIC_API_BASE=http://localhost:8000/api`.

### 9.2 Matriz E2E

| ID | Fluxo | Modalidade | Situação | Critério principal |
|---|---|---|---|---|
| E2E-01 | Login demo como morador e abertura do painel | Navegador + mock | Executável agora | Painel carrega e apresenta somente ações do papel. |
| E2E-02 | Login inválido e recuperação visual do formulário | Navegador + mock/API | Executável agora | Mensagem acessível, sem sessão e sem travamento. |
| E2E-03 | Login real e restauração de sessão | Navegador + Laravel | Executável após iniciar e semear os serviços | Cookie/sessão é usado e a identidade persiste após recarga. |
| E2E-04 | Morador cria e consulta a própria ocorrência | Navegador + Laravel | Executável com seed | Registro persiste e aparece somente para o autor autorizado. |
| E2E-05 | Administrador analisa e atribui ocorrência | Navegador + Laravel | Executável com seed compatível | Estado, responsável e histórico mudam juntos. |
| E2E-06 | Funcionário inicia e finaliza ocorrência atribuída | Navegador + Laravel | Executável com seed compatível | Somente o responsável conclui a sequência permitida. |
| E2E-07 | Usuário tenta abrir ocorrência de terceiro | Navegador + Laravel | Executável com dois usuários | A interface trata `403`/`404` sem revelar dados. |
| E2E-08 | Perfil próprio altera nome/e-mail e tenta alterar papel/status/unidade | Navegador + Laravel | Parcial | Campos legítimos persistem; campos privilegiados dependem da proteção completa da API. |
| E2E-09 | Criar reserva, causar conflito e cancelar | Navegador + mock | Executável apenas como interface | E2E real bloqueado até existir API completa. |
| E2E-10 | Ocultar rascunho e exibir comunicado publicado | Navegador + mock | Executável apenas como interface | E2E real bloqueado até existir API completa. |
| E2E-11 | Filtrar, paginar e exportar relatório | Navegador + mock | Executável apenas como interface | E2E real bloqueado até existir API completa. |
| E2E-12 | Exportar PDF no tema escuro | Navegador + mock | Executável agora | PDF claro, `.no-print` ausente e tema restaurado. |
| E2E-13 | Sessão expira ou usuário é inativado durante o uso | Navegador + Laravel + dois clientes | Planejado | Próxima ação é recusada e a interface encerra/trata a sessão. |
| E2E-14 | Upload e acesso a anexo | Navegador + Laravel | Executável com arquivo e seed | Upload válido funciona; terceiro não acessa o conteúdo. |
| E2E-15 | Teclado e análise automatizada de acessibilidade | Runner E2E + axe | Bloqueado por ferramenta | Jornada sem mouse e sem violações críticas. |
| E2E-16 | Chrome, Firefox e Edge | Runner E2E | Bloqueado por ferramenta | Mesma jornada passa na matriz suportada. |
| E2E-17 | Erros `401`, `403`, `422`, `500` e timeout | Runner E2E + interceptação/API | Planejado | Estado de carregamento termina, mensagem é adequada e retry é seguro. |

### 9.3 Recomendação de implementação E2E

Adotar um runner próprio — preferencialmente Playwright pela execução multibrowser e isolamento de contexto — antes de classificar os fluxos como cobertura E2E automatizada. A suíte deve ter:

- fixtures separadas para mock e API real;
- criação e limpeza determinística de dados, sem usar a rota destrutiva de implantação;
- contextos distintos para testar dois usuários e propriedade de recursos;
- asserções de interface, resposta da API e estado persistido;
- captura de screenshot, trace e vídeo somente em falhas;
- projetos para Chrome, Firefox e Edge/Chromium compatível;
- execução local e em CI com portas e URLs configuráveis.

Não usar `deploy/migrate` durante E2E: essa rota executa recriação e carga do banco. Para ambiente local descartável, o preparo documentado é `composer setup`, que também é destrutivo e só deve apontar para o banco de teste.

## 10. Verificação de desempenho e carga

### 10.1 Baseline existente

Existe `laravel/tests/Feature/ListingPerformanceTest.php`, com massa sintética de 500 unidades, 1.200 usuários, 2.000 vínculos, 10.000 reservas e 5 áreas comuns. Ele verifica paginação, filtros, relações, limites por página e consultas N+1, usando orçamento de 2.000 ms.

Esse teste é um benchmark de integração local com SQLite e `RefreshDatabase`. Ele é útil para detectar regressões de consulta, mas não mede HTTP real, concorrência, rede, servidor web, banco do ambiente alvo ou capacidade sustentada. Portanto, não deve ser apresentado como teste de carga.

Baseline executado em 23/11/2026:

- resultado: **reprovado**;
- `GET /users`: **3.396,5 ms**, acima do teto de 2.000 ms da RNF01;
- demais consultas de reservas medidas entre 0,3 ms e 113,8 ms;
- consultas paginadas de usuários/unidades com relações entre 11,4 ms e 35,3 ms;
- suíte: 1 teste reprovado, 41 asserções, duração total de 16,92 s;
- ambiente: PHP 8.5 e SQLite local; foram emitidos avisos de depreciação de `PDO::MYSQL_ATTR_SSL_CA`.

O número de 3.396,5 ms inclui o custo observado no ambiente local e não deve ser extrapolado para produção. Ainda assim, ele reproduz uma violação do requisito atual e precisa ser investigado antes de estabelecer o baseline de carga.

### 10.2 Perfis de carga planejados

| ID | Perfil | Carga proposta | Duração | Objetivo |
|---|---|---|---|---|
| LOAD-01 | Baseline HTTP | 1 usuário virtual, uma iteração por endpoint | Até concluir | Medir custo mínimo e validar script/dados. |
| LOAD-02 | Degraus | 10, 25, 50 e 100 usuários virtuais | 5 min por degrau | Encontrar tendência, saturação e capacidade segura. |
| LOAD-03 | Spike | 0 → 100 → 0 usuários virtuais | 5–10 min | Verificar fila, timeout e recuperação. |
| LOAD-04 | Soak | Carga normal definida após LOAD-02 | 30–60 min | Detectar vazamento, degradação e exaustão de conexões. |
| LOAD-05 | Conflito de reserva | Requisições simultâneas para o mesmo intervalo | 50–100 tentativas | Confirmar uma única reserva e respostas de conflito. |
| LOAD-06 | Transição concorrente | Iniciar/finalizar a mesma ocorrência em paralelo | 20–50 tentativas | Impedir eventos duplicados e perda de atualização. |
| LOAD-07 | Leitura pesada | Listagens filtradas, paginação distante e relações | Mistura 70% leitura/30% escrita | Medir consultas e N+1 sob concorrência. |
| LOAD-08 | Exportação | Relatório/auditoria com múltiplas páginas | 5–20 exportações simultâneas | Medir memória, duração e completude. Bloqueado até os endpoints existirem. |
| LOAD-09 | Erros simultâneos | Requisições `401`, `403` e `422` | 50–100 usuários virtuais | Confirmar rejeição barata, estável e sem log excessivo. |
| LOAD-10 | Retry após timeout | Mutação seguida de repetição controlada | 20–50 ciclos | Verificar idempotência e ausência de duplicidade. |

### 10.3 Métricas e critérios

Coletar por endpoint e por perfil:

- latência p50, p95 e p99, além do máximo;
- throughput e usuários/requisições simultâneas;
- taxas separadas de respostas esperadas `4xx`, erros `5xx` e timeouts;
- CPU, memória, conexões, tempo e quantidade de consultas no banco;
- duplicidades, transições perdidas e corrupção de dados;
- tempo de recuperação após spike e estabilidade durante soak.

O requisito existente de menos de 2 segundos precisa ser refinado antes da aprovação de carga: definir se vale para p95 ou p99, quais endpoints, tamanho das páginas, volume de dados e nível de concorrência. Como critério provisório, usar **p95 abaixo de 2 segundos e menos de 1% de erros inesperados**, sem qualquer corrupção ou duplicidade. Esse valor provisório deve ser aprovado pelo responsável do produto antes de virar critério de aceite definitivo.

### 10.4 Pré-condições e segurança

- escolher uma ferramenta de carga, como k6, e versionar scripts, dados e configuração;
- executar em staging isolado, nunca contra produção/InfinityFree sem autorização e janela específicas;
- usar massa sintética ou anonimizada e contas próprias para carga;
- preparar e limpar dados por procedimento de teste, sem acionar rotas destrutivas de implantação;
- confirmar que reservas, relatórios e auditoria possuem endpoints antes de habilitar seus cenários;
- aquecer o ambiente e executar baseline antes de cada perfil;
- registrar versão da aplicação, infraestrutura, banco e ferramenta para comparação futura.

## 11. Comandos de verificação existentes

No diretório `frontend`:

```bash
npm test -- --run
npm run typecheck
```

No diretório `laravel`, o benchmark local de consultas pode ser repetido com:

```bash
php artisan test --compact tests/Feature/ListingPerformanceTest.php
```

Para preparar uma instância local descartável da API e iniciar os serviços:

```bash
cd laravel
composer setup
php artisan serve

cd frontend
npm install
npm run dev
```

`composer setup` recria e semeia o banco configurado. Antes de executá-lo, confirmar que o `.env` aponta exclusivamente para uma base local/de teste descartável.

## 12. Análise e proposta de CI/CD

### 12.1 Estado atual verificado

O projeto não parte do zero: existem os workflows `.github/workflows/ci.yml`, `.github/workflows/deploy-infinityfree.yml` e `.github/workflows/release-please.yml`.

O CI atual já verifica:

- convenções de título e branch de pull request;
- frontend com Node.js 22, Vitest, typecheck e build Nuxt;
- backend com PHP 8.2, dependências Composer e testes Laravel;
- paridade de permissões entre frontend e backend;
- build dos assets do Laravel;
- concorrência com cancelamento de execuções obsoletas e permissão padrão de leitura.

O CD atual:

- é disparado automaticamente por todo push em `main`;
- repete testes e builds antes de publicar;
- gera o frontend estático apontando para `/api`;
- reinstala dependências PHP com `--no-dev`;
- cria o `.env` de produção a partir do secret `ENV_PRODUCTION`;
- valida `APP_KEY` e `APP_ENV`;
- publica por FTP sem TLS, na porta 21;
- chama a rota de otimização após o upload;
- permite, em execução manual, chamar uma recriação destrutiva do banco com `migrate:fresh --seed`.

Há uma divergência que precisa ser eliminada: o workflow publica automaticamente em `main`, enquanto a documentação do Laravel descreve o deploy como manual. O comportamento real do workflow deve ser considerado a fonte operacional até que ambos sejam alinhados.

### 12.2 Avaliação de prontidão

| Capacidade | Situação | Avaliação |
|---|---|---|
| Testes unitários/contratuais do frontend | Presente | Pronto para gate obrigatório. |
| Typecheck e build Nuxt | Presente | Pronto para gate obrigatório. |
| Testes Laravel | Presente | Pronto para gate obrigatório, excluindo benchmark instável do gate comum se ele não for determinístico. |
| Formatação PHP | Não automatizada | Adicionar `vendor/bin/pint --test`. |
| Auditoria de dependências | Não automatizada | Adicionar `composer audit` e `npm audit --audit-level=high`, com política de exceção documentada. |
| E2E | Sem runner formal | Não bloquear PRs até a suíte mínima estar implementada; depois promover smoke E2E a gate. |
| Build imutável | Parcial | Produzir uma vez, armazenar como artefato e publicar exatamente esse conteúdo. |
| Aprovação de produção | Ausente | Obrigatória antes de manter CD automático. |
| Transporte seguro | FTP puro | Risco alto; usar FTPS/SFTP se o host suportar. |
| Smoke pós-deploy | Ausente | Obrigatório para detectar publicação incompleta. |
| Rollback | Ausente | Necessário manter último artefato e manifesto; FTP não fornece troca atômica. |
| Migração segura | Ausente no host | `migrate:fresh --seed` não pode fazer parte do deploy normal. |
| Staging | Não identificado | Criar antes de habilitar automação integral. |
| Observabilidade de release | Parcial/ausente | Registrar commit, versão, URL, run ID e resultado do smoke test. |

### 12.3 Arquitetura-alvo

```text
pull request
  ├─ convenções e arquivos sensíveis
  ├─ frontend: npm ci → test → typecheck → build
  ├─ backend: composer install → Pint → testes
  ├─ auditoria de dependências e análise de segurança
  └─ E2E smoke, quando a suíte estiver disponível

merge em main
  ├─ repetir gates obrigatórios
  ├─ gerar uma única versão/artefato imutável
  ├─ publicar artefato do run
  └─ preparar deployment para environment production

environment production
  ├─ aprovação humana obrigatória
  ├─ upload serializado por FTPS/SFTP, ou FTP como exceção aceita
  ├─ otimização segura e idempotente
  ├─ smoke test HTTP/API
  ├─ verificação de integridade da publicação
  └─ registrar sucesso ou iniciar rollback
```

### 12.4 Jobs propostos

| Job | Gatilho | Dependências | Gate/saída |
|---|---|---|---|
| `conventions` | PR | Checkout completo apenas quando necessário | Título, branch e política do PR válidos. |
| `frontend-quality` | PR e `main` | Node 22, cache npm | `npm ci`, testes e typecheck aprovados. |
| `frontend-build` | PR e `main` | `frontend-quality` | Build Nuxt reproduzível; artefato apenas em `main`. |
| `backend-quality` | PR e `main` | PHP alinhado ao host, cache Composer | Pint e testes Laravel aprovados. |
| `backend-build` | `main` | `backend-quality` | Pacote PHP `--no-dev` validado e armazenado. |
| `security` | PR, `main` e agenda | Node/PHP | Auditorias sem vulnerabilidade acima da política ou exceção aprovada. |
| `e2e-smoke` | PR ou `main`, após implementação | Frontend, Laravel e banco efêmero | Login e jornada crítica aprovados. |
| `package` | `main` | Builds frontend/backend | Um artefato versionado, checksum e manifesto. |
| `deploy-production` | `main` ou manual | `package`, environment aprovado | Upload do artefato exato, serializado e com timeout. |
| `verify-production` | Após deploy | `deploy-production` | Health/smoke e `verify-production.py` aprovados. |
| `rollback` | Manual ou falha pós-deploy | Último artefato aprovado | Versão anterior restaurada e novamente verificada. |

O benchmark `ListingPerformanceTest`, que atualmente falha no ambiente local, deve rodar em job de desempenho separado. Ele pode informar regressões, mas não deve tornar todo PR vermelho até que ambiente, aquecimento e tolerância estejam estabilizados.

### 12.5 Gates de pull request

Antes do merge em `main`, exigir:

1. PR obrigatório e ao menos uma revisão aprovada;
2. branch atualizada ou merge queue, conforme a política do repositório;
3. job de convenções aprovado;
4. testes, typecheck e build do frontend aprovados;
5. Pint e testes Laravel aprovados;
6. paridade de permissões aprovada;
7. auditorias sem achado bloqueante ou com exceção temporária registrada;
8. conversas resolvidas e proibição de force-push em `main`.

Quando houver E2E confiável, adicionar inicialmente login real, criação/consulta de ocorrência e acesso negado a recurso de terceiro. Reservas, comunicados e relatórios só podem ser gates E2E de API quando seus endpoints estiverem completos.

### 12.6 Deploy e ambientes

Criar um GitHub Environment chamado `production` com:

- reviewers obrigatórios;
- apenas `main` como branch autorizada;
- secrets de produção armazenados no environment, não no escopo geral do repositório;
- `deployment` serializado, sem cancelamento de uma publicação já iniciada;
- permissões do workflow reduzidas a `contents: read`, salvo necessidade explícita;
- timeout para evitar jobs FTP presos indefinidamente.

Segredos atuais a proteger: `FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD`, `ENV_PRODUCTION` e `DEPLOY_TOKEN`. A variável `APP_URL` deve ser definida por ambiente. Pull requests, especialmente de forks, jamais devem receber esses valores nem executar publicação.

Antes de publicar o `.env`, validar também:

- `APP_ENV=production` e `APP_DEBUG=false`;
- `APP_URL` em HTTPS e compatível com o ambiente;
- banco, sessão e CORS sem valores locais/de desenvolvimento;
- presença da chave e do token necessários;
- ausência de credenciais de teste e senhas padrão;
- compatibilidade do algoritmo Argon2id com o PHP do host.

O CI usa PHP 8.2, enquanto a documentação da hospedagem registra PHP 8.4.25. O job principal deve ser alinhado à versão efetiva do servidor. Uma pequena matriz pode manter compatibilidade com a versão mínima, mas o gate de produção precisa executar na versão mais próxima possível do host.

### 12.7 Publicação, migrações e otimização

- Preferir FTPS explícito ou SFTP. Se InfinityFree aceitar apenas FTP puro, registrar formalmente o risco, limitar a credencial ao diretório necessário e rotacioná-la periodicamente.
- Usar `npm ci`, inclusive para os assets Laravel, desde que o lockfile esteja presente e íntegro.
- Gerar dependências de produção antes do empacotamento e verificar `vendor/autoload.php` e o manifesto.
- Publicar exatamente o artefato criado no job `package`; não reconstruir durante o deploy.
- Não executar `migrate:fresh --seed` automaticamente. Essa operação apaga todos os dados e pode deixar o schema parcial se a requisição expirar.
- Manter qualquer recriação de banco em workflow separado, manual, com environment próprio, aprovação adicional, backup testado e confirmação explícita do alvo.
- Não usar `config:cache` no host enquanto não existir um procedimento confiável para limpar/substituir esse cache. Otimizações devem ser idempotentes e reversíveis.
- Como o FTP não é atômico, publicar arquivos de menor risco primeiro e trocar o ponto de entrada por último, quando tecnicamente possível.

### 12.8 Verificação pós-deploy

O deploy só deve ser considerado concluído após:

1. página inicial responder em HTTPS;
2. endpoint público de saúde responder sem revelar configuração;
3. API responder com estrutura esperada;
4. login de uma conta técnica funcionar, sem registrar credenciais nos logs;
5. endpoint autenticado simples respeitar sessão/autorização;
6. assets versionados carregarem sem `404`;
7. `verify-production.py` confirmar a integridade da publicação;
8. commit, versão, ambiente, URL e run ID serem registrados no deployment.

Falha no smoke test deve marcar o deployment como falho, impedir a promoção da release e oferecer o procedimento de rollback. A conta técnica deve ter privilégio mínimo e senha própria do ambiente.

### 12.9 Rollback

O mecanismo atual não é atômico e não possui rollback. A solução mínima é:

- guardar o artefato completo e o manifesto de cada release por período definido;
- registrar qual versão está em produção;
- manter o último artefato aprovado disponível sem reconstrução;
- disponibilizar workflow manual que restaure arquivos da versão anterior;
- repetir integridade e smoke test após a restauração;
- tratar rollback de banco separadamente, pois restaurar arquivos não desfaz alterações de schema/dados.

Antes de qualquer migração de produção, deve existir backup, teste de restauração e plano de compatibilidade reversa entre duas versões consecutivas. Enquanto a única operação disponível for `migrate:fresh --seed`, não há migração de produção segura.

### 12.10 Segurança da cadeia de entrega

Adicionar gradualmente:

- Dependabot para npm, Composer e GitHub Actions;
- `npm audit --audit-level=high` e `composer audit`;
- CodeQL ou Semgrep para análise estática;
- secret scanning e proteção contra push de segredos;
- SBOM CycloneDX anexado à release;
- actions de terceiros fixadas por SHA, especialmente publicação FTP e release-please;
- `CODEOWNERS` para workflows, configuração de deploy e rotas administrativas;
- revisão obrigatória para mudanças em `.github/workflows/**`, `laravel/config/deploy.php` e rotas de deploy;
- redução das permissões do release-please ao mínimo comprovadamente necessário.

Auditorias automáticas devem ter política de severidade, prazo de correção e processo de exceção. Usar `npm audit fix --force` ou atualizações automáticas não revisadas no pipeline pode introduzir mudanças incompatíveis e não é recomendado.

### 12.11 Plano de implantação do CI/CD

| Fase | Mudança | Critério de conclusão |
|---|---|---|
| 1 — estabilizar CI | `npm ci`, Pint, auditorias, PHP alinhado e branch protection | Todo PR passa por gates reproduzíveis. |
| 2 — proteger produção | GitHub Environment, reviewers, secrets por ambiente e deploy serializado | Nenhum push publica sem aprovação. |
| 3 — artefato e verificação | Build único, checksum, manifesto, smoke e integridade | O conteúdo testado é exatamente o publicado. |
| 4 — rollback | Retenção do último artefato e workflow de restauração | Falha pós-deploy pode ser revertida e verificada. |
| 5 — E2E e staging | Runner E2E e ambiente isolado | Jornadas críticas validam integração real antes da produção. |
| 6 — cadeia de suprimentos | Dependabot, SAST, SBOM e actions por SHA | Dependências e automações possuem rastreabilidade e revisão. |

### 12.12 Decisões necessárias antes da implementação

1. Confirmar se o InfinityFree suporta FTPS explícito; se não, aceitar formalmente FTP ou escolher outro destino.
2. Definir se produção será publicada somente por `workflow_dispatch` ou automaticamente após aprovação do environment.
3. Definir um staging acessível e isolado.
4. Alinhar a versão PHP do CI à versão real do host.
5. Definir retenção dos artefatos e responsável por rollback.
6. Decidir como evoluir o banco sem `migrate:fresh --seed`.
7. Aprovar critérios de vulnerabilidade que bloqueiam merge/deploy.

Os resultados desses comandos devem acompanhar o relatório, mas não substituem a execução dos casos de API, E2E, concorrência, segurança e requisitos não funcionais deste plano.
