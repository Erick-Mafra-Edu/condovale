# Inventário de documentação, testes e evidências do CondoVale

**Data do levantamento:** 23/11/2026

**Documento de referência:** `M3 - Documento final do Projeto - Testes.pdf`
**Objetivo:** consolidar o que já existe, o que ainda precisa ser documentado e como produzir as evidências exigidas, incluindo testes E2E gravados com cursor visível.

## 1. Escopo e limitações do levantamento

Este documento foi produzido comparando os requisitos do documento de referência com os arquivos disponíveis no repositório CondoVale. Ele é um inventário e um plano de trabalho; não comprova que os testes foram executados.

Não foram criados ou executados testes durante o levantamento. Também não foram validadas evidências externas que possam estar armazenadas no Google Drive. Portanto, algum item marcado como ausente pode já existir fora do repositório e deve ser conferido antes de ser recriado.

## 2. Resumo executivo

O projeto já possui documentação relevante sobre regras de negócio, requisitos, arquitetura do frontend, integração com o backend, processos, usuários e planejamento de testes. As maiores lacunas estão nos documentos de execução e nas evidências exigidas para a entrega:

- catálogo formal com pelo menos 20 casos de teste;
- relatórios de execução e cobertura dos testes unitários;
- resultados dos testes de integração e E2E;
- relatórios de performance, carga, segurança e usabilidade;
- índice rastreável das evidências;
- registro consolidado de defeitos e retestes;
- relatório final e roteiro da apresentação;
- documentação técnica centralizada de API, dados, deploy e operação.

A base automatizada de carga foi implementada em `load-tests/`, com Docker Compose, seed Laravel, cenário k6 e workflow no GitHub Actions. O plano detalhado está em [docs/plano-testes-carga-docker-ci.md](plano-testes-carga-docker-ci.md). Isso ainda não conclui o requisito M3: falta executar os perfis definidos, coletar os artefatos e produzir o relatório com resultados.

O PDF contém uma inconsistência sobre integração: uma seção pede pelo menos 3 cenários e o checklist final pede 5 testes. Para evitar perda de nota, este inventário adota **5 testes de integração** como mínimo seguro.

## 3. Documentação já existente

Os materiais abaixo devem ser revisados e reutilizados, evitando duplicação.

| Material | Localização | Cobertura atual |
| --- | --- | --- |
| Entrada do projeto | `README.md` | Aponta para usuários do backend, mas ainda é insuficiente como guia principal. |
| Guia arquitetural | `CLAUDE.md` | Arquitetura, respostas, paginação, auditoria, segurança, endpoints, testes e pendências. |
| Histórico de mudanças | `CHANGELOG.md` | Funcionalidades, correções e melhorias de desempenho da versão 1.0.0. |
| Instalação do backend | `laravel/README.md` | Ambiente local, banco, seeders, deploy, respostas, autenticação, segurança e testes. |
| Instalação do frontend | `frontend/README.md` | Modos mock/API, camadas do frontend e verificação de tipos. |
| Usuários de demonstração | `docs/usuarios-backend.md` | Contas e capacidades por perfil. |
| Plano de testes | `docs/plano-testes-regras-processos.md` | Estratégia, processos, E2E, carga, CI/CD, deploy, rollback e segurança. |
| Integração e auditoria | `docs/ocorrencias-integracao-auditoria.md` | Rotas integradas e rotas bloqueadas. |
| Diagramas | `docs/plantuml/*.puml` | Diagrama de classes e processos em fonte PlantUML. |
| Arquitetura mock/API | `frontend/docs/mock-api-architecture.md` | Contratos provisórios, operações e validações do frontend. |
| Rastreabilidade de requisitos | `frontend/docs/requirements-compliance.md` | RF01–RF06, RNF01–RNF04 e RN01–RN17. |
| Regras de negócio | `frontend/docs/business-rules.md` | Regras RN09–RN18. |
| Guia visual do frontend | `docs/frontend-guia/` | Autenticação, UI, usuários, ocorrências, configuração e evidências do GitHub. |
| Coleções de API | `collections/CondoVale/**/*.yml` | Requisições reutilizáveis para testes de API. Não substituem OpenAPI. |

### 3.1 Pontos que precisam ser corrigidos nos documentos existentes

- O `README.md` raiz precisa se tornar a entrada principal do projeto.
- O `laravel/README.md` contém trecho histórico afirmando que ainda não existem rotas `/api`, embora elas já estejam implementadas.
- O deploy automático a partir da branch `main`, definido em `.github/workflows/deploy-infinityfree.yml`, diverge da descrição de deploy manual do `laravel/README.md`.
- Os diagramas PlantUML não estão acompanhados de imagens renderizadas e de uma visão arquitetural consolidada.
- Algumas rotas continuam provisórias, bloqueadas ou disponíveis somente no modo mock e precisam ser identificadas dessa forma.

## 4. Requisitos da entrega M3

### 4.1 Plano de Testes em PDF

O plano existente deve ser revisado e convertido para PDF contendo:

- visão geral e objetivos;
- escopo incluído e excluído;
- estratégia;
- tipos de teste;
- ferramentas;
- ambiente, incluindo sistema operacional, versões, banco e hardware;
- riscos, dependências e bloqueios;
- justificativa técnica para qualquer tipo de teste considerado não aplicável.

### 4.2 Catálogo de casos de teste

Deve conter no mínimo 20 casos, cobrindo cenários positivos, negativos e valores-limite.

Campos obrigatórios:

| Campo | Finalidade |
| --- | --- |
| ID | Identificador único, como `CT-001`. |
| Requisito/funcionalidade | Item validado pelo caso. |
| Tipo | Unitário, integração, E2E, segurança, desempenho etc. |
| Pré-condições | Estado necessário antes da execução. |
| Passos | Sequência reproduzível. |
| Dados de entrada | Usuários, valores, arquivos ou parâmetros empregados. |
| Resultado esperado | Comportamento correto. |
| Resultado obtido | Comportamento observado. |
| Status | Aprovado, reprovado, bloqueado ou não executado. |
| Evidência | Link para print, vídeo, log ou relatório. |

### 4.3 Testes unitários

O código deve permanecer no repositório e o relatório deve registrar:

- commit testado;
- data e ambiente;
- comandos executados;
- quantidade de testes;
- aprovados e reprovados;
- cobertura das regras de negócio;
- arquivo ou link do relatório de cobertura.

Meta exigida: **pelo menos 60% de cobertura das regras de negócio**.

### 4.4 Testes de integração

Executar e documentar pelo menos 5 cenários. O conjunto deve cobrir integrações relevantes, como:

- API com banco de dados;
- frontend com backend;
- autenticação e restauração de sessão;
- autorização entre perfis;
- upload e recuperação de anexo.

Entregáveis:

- coleção de requisições exportada;
- relatório de execução;
- dados e ambiente utilizados;
- resultado esperado e obtido;
- evidências associadas aos casos.

### 4.5 Testes E2E e funcionais

Executar pelo menos 3 fluxos completos, com scripts e vídeos ou capturas de tela. Fluxos iniciais recomendados:

1. login como morador e abertura do painel;
2. tentativa de login inválido e tratamento do erro;
3. criação e consulta de ocorrência.

Outros fluxos já mapeados no plano de testes incluem administração e atribuição de ocorrência, conclusão por funcionário, acesso negado a recurso de terceiro, perfil, upload, reservas, comunicados e relatórios.

Reservas, comunicados e relatórios ainda dependem de módulos completos na API. Enquanto isso, seus fluxos devem ser descritos como **E2E de interface com mock**, não como validação de integração real.

### 4.6 Performance

Medir as funcionalidades principais em uso normal e registrar:

- média de tempo de resposta;
- percentil p95;
- throughput;
- quantidade de execuções;
- ambiente e massa de dados;
- gráficos;
- análise dos resultados.

Já existe uma base executável para a API em `load-tests/docker-compose.yml`, mas a execução e o relatório M3 continuam pendentes.

### 4.7 Carga e estresse

Simular carga progressiva, preferencialmente com 50, 100 e 200 usuários simultâneos, até identificar degradação.

O relatório deve apresentar:

- tempo de resposta;
- throughput;
- taxa de erro;
- consumo de recursos, quando disponível;
- ponto de degradação;
- gargalos encontrados;
- gráficos e conclusão.

### 4.8 Segurança

Avaliar pelo menos:

- SQL Injection;
- XSS;
- autenticação e sessão;
- autorização e controle de acesso;
- uploads;
- itens aplicáveis do OWASP Top 10.

Para cada achado, registrar:

- identificador;
- cenário testado;
- severidade;
- evidência;
- impacto;
- correção aplicada ou proposta;
- resultado do reteste.

### 4.9 Usabilidade e acessibilidade

Realizar avaliação com pelo menos 3 usuários e documentar:

- perfil dos participantes;
- tarefas propostas;
- formulário utilizado;
- resultados;
- dificuldades observadas;
- sugestões recebidas;
- melhorias propostas.

### 4.10 Regressão

O teste de regressão é bônus. Caso seja realizado, apresentar reexecução após correções com log de CI ou comparação entre antes e depois.

### 4.11 Evidências

Organizar uma pasta do projeto no Drive com subpastas por categoria:

```text
Evidencias/
├── 01-casos-de-teste/
├── 02-unitarios-cobertura/
├── 03-integracao/
├── 04-e2e/
├── 05-performance/
├── 06-carga-estresse/
├── 07-seguranca/
├── 08-usabilidade-acessibilidade/
├── 09-defeitos-retestes/
└── 10-apresentacao/
```

Regras para as evidências:

- cada evidência deve estar vinculada ao ID do caso de teste;
- prints devem mostrar data/hora quando aplicável;
- logs e relatórios devem indicar o commit testado;
- nomes devem ser previsíveis, como `CT-004-login-invalido.webm`;
- tokens, cookies, senhas e dados reais de moradores não podem aparecer;
- evidências no Drive devem ser referenciadas em um índice versionado no repositório.

### 4.12 Registro de defeitos

Criar uma tabela consolidada contendo:

- ID do defeito;
- caso de teste relacionado;
- descrição;
- passos para reprodução;
- severidade e prioridade;
- resultado esperado e observado;
- evidência;
- causa identificada;
- correção;
- status;
- evidência do reteste.

### 4.13 Relatório final

O relatório final deve consolidar:

- total de casos executados;
- aprovados, reprovados e bloqueados;
- cobertura unitária;
- resultados de integração e E2E;
- resultados de performance e carga;
- vulnerabilidades e correções;
- defeitos encontrados;
- lições aprendidas;
- melhorias futuras;
- ferramentas de IA utilizadas, indicando onde e como foram usadas.

### 4.14 Apresentação

Preparar um roteiro contendo:

- demonstração ao vivo do sistema;
- execução da suíte unitária;
- pelo menos um teste de integração ou E2E;
- gráficos de performance e carga;
- vulnerabilidades encontradas e correções;
- apresentação de um bug e sua solução;
- vídeo gravado como plano B.

## 5. Critérios e penalidades informados no PDF

| Critério | Pontuação |
| --- | ---: |
| Plano de Testes | 0,5 |
| Casos de teste | 2,0 |
| Testes unitários | 1,5 |
| Integração/E2E | 1,0 |
| Performance/carga | 1,5 |
| Segurança | 1,0 |
| Evidências/defeitos | 1,5 |
| Relatório final | 1,0 |

Penalidades e riscos mencionados:

- atraso: desconto de 1 ponto por dia;
- evidência forjada ou copiada: nota zero;
- sistema sem funcionamento e sem vídeo de contingência: perda de pontos na apresentação;
- falta de domínio individual do conteúdo: possível impacto na nota individual.

## 6. Documentação técnica ainda ausente

### 6.1 Prioridade P0 — necessária para execução e rastreabilidade

1. **README raiz completo:** visão do produto, requisitos, instalação, comandos, arquitetura resumida e índice da documentação.
2. **Inventário da API:** matriz única de endpoints implementados, somente mock, bloqueados e planejados.
3. **Contrato HTTP formal:** OpenAPI/Swagger com autenticação, payloads, respostas, erros, paginação e uploads multipart.
4. **Matriz de rastreabilidade:** requisito → regra → código → teste → evidência → defeito/reteste.
5. **Runbook de deploy:** gatilhos, secrets, FTP, migrações, verificação, falhas e rollback.

O runbook deve destacar que `migrate:fresh --seed` apaga dados e nunca deve ser utilizado contra um banco que precise ser preservado.

### 6.2 Prioridade P1 — arquitetura, dados e usuários

1. Arquitetura consolidada de frontend, Laravel, banco e implantação.
2. ERD e dicionário de dados com relações, índices, enums e estados.
3. Manual por perfil, incluindo permissões e fluxos permitidos.
4. Guias de reservas, comunicados, relatórios, exportações e auditoria.
5. ADRs para decisões arquiteturais relevantes.
6. Política de segurança e privacidade/LGPD.
7. Contratos de erro, paginação e versionamento da API.

### 6.3 Prioridade P2 — operação e manutenção

1. Catálogo das variáveis de ambiente do frontend e backend.
2. Matriz de configuração local, CI e produção.
3. Requisitos de PHP, Node, extensões e banco.
4. Procedimentos de backup e restauração.
5. Logs, monitoramento, alertas e health checks.
6. Runbook de incidentes.
7. Retenção de logs, anexos e auditoria.
8. Gestão e rotação de credenciais.
9. Navegadores suportados e matriz de acessibilidade.
10. Guia de contribuição, branches, commits e pull requests.

## 7. Testes E2E gravados com cursor visível

### 7.1 Viabilidade

A gravação é viável com as ferramentas já instaladas:

- Puppeteer `24.43.1`;
- Chromium fornecido pelo Puppeteer;
- FFmpeg disponível no ambiente;
- script existente `frontend/scripts/capture-ui.mjs` para login e navegação.

A versão instalada oferece `page.screencast()`, permitindo gravar a página. Não é necessário adicionar Playwright apenas para produzir os vídeos iniciais.

### 7.2 Implementação mínima recomendada

Criar um script separado:

```text
frontend/scripts/record-e2e.mjs
```

Adicionar um comando ao `frontend/package.json`:

```json
"e2e:record": "node scripts/record-e2e.mjs"
```

O roteiro deve:

1. abrir o navegador e a aplicação;
2. iniciar a gravação;
3. inserir um cursor visual sobre a página;
4. mover o indicador até o elemento selecionado;
5. destacar a ação;
6. interagir por meio do Puppeteer;
7. validar o resultado esperado;
8. registrar aprovação ou falha;
9. encerrar a gravação;
10. salvar o vídeo e o resultado com o ID do caso.

Estrutura sugerida:

```text
docs/evidencias/e2e/
├── E2E-01-login-morador.webm
├── E2E-02-login-invalido.webm
├── E2E-04-criar-ocorrencia.webm
└── resultados.json
```

### 7.3 Diferença entre cursor visual e cursor do sistema

A gravação direta da página não captura o ponteiro real do Windows. Para produzir uma evidência clara e reproduzível, o script deve inserir um indicador visual de cursor na própria página e animá-lo até cada ação.

Esse indicador precisa ser descrito como cursor visual da automação. Se a apresentação exigir o cursor real e toda a janela do navegador, será necessário executar o Chrome visível e gravar a tela com OBS ou ferramenta equivalente. Essa alternativa serve melhor para demonstração, mas é menos reproduzível como teste automatizado.

### 7.4 Ambiente necessário para E2E real

Os fluxos com integração completa precisam de:

- Nuxt em execução;
- Laravel em execução;
- banco de teste isolado e semeado;
- `NUXT_PUBLIC_DATA_SOURCE=api`;
- `NUXT_PUBLIC_API_BASE=http://localhost:8000/api` ou endereço equivalente;
- usuários de teste conhecidos;
- arquivos de teste sem dados sensíveis.

Não usar a rota de deploy/migração destrutiva durante E2E. O banco deve ser descartável e exclusivo para testes.

### 7.5 Evolução posterior

Puppeteer é suficiente para os três vídeos iniciais. Playwright só deve ser adicionado quando houver necessidade concreta de:

- testes em Chrome, Firefox e Edge;
- relatórios HTML estruturados;
- traces detalhados;
- execução formal na CI;
- isolamento e paralelização de cenários.

## 8. Ordem recomendada de execução

### Etapa 1 — Confirmar o acervo existente

- verificar o Google Drive;
- localizar relatórios ou evidências ainda não versionados;
- remover da lista os documentos já concluídos;
- definir responsáveis e prazo.

### Etapa 2 — Preparar a rastreabilidade

- finalizar os 20 casos de teste;
- criar a matriz requisito → teste → evidência;
- padronizar IDs e nomes de arquivo;
- organizar as pastas do Drive.

### Etapa 3 — Produzir as evidências essenciais

- executar unitários e gerar cobertura;
- executar 5 integrações;
- implementar e gravar 3 fluxos E2E;
- registrar defeitos e retestes.

### Etapa 4 — Requisitos não funcionais

- performance;
- carga e estresse;
- segurança;
- usabilidade e acessibilidade.

### Etapa 5 — Consolidar a entrega

- atualizar o plano de testes;
- preencher todos os resultados obtidos;
- gerar o relatório final;
- preparar roteiro e vídeo de contingência;
- revisar links e permissões das evidências.

## 9. Checklist final

- [ ] Plano de Testes revisado e exportado em PDF.
- [ ] Pelo menos 20 casos de teste completos.
- [ ] Cenários positivos, negativos e valores-limite cobertos.
- [ ] Cobertura unitária mínima de 60% das regras de negócio.
- [ ] Relatório e arquivo de cobertura anexados.
- [ ] Pelo menos 5 testes de integração executados.
- [ ] Pelo menos 3 fluxos E2E executados e gravados.
- [ ] Relatório de performance com média, p95 e throughput.
- [ ] Relatório de carga/estresse com taxa de erro e gargalos.
- [ ] Relatório de segurança com correções e retestes.
- [ ] Avaliação de usabilidade/acessibilidade com 3 usuários.
- [ ] Registro de defeitos completo.
- [ ] Evidências rastreáveis por ID.
- [ ] Links do Drive revisados e acessíveis.
- [ ] Relatório final concluído.
- [ ] Uso de IA declarado.
- [ ] Apresentação preparada.
- [ ] Vídeo de contingência disponível.

## 10. Estado deste documento

Este documento registra o inventário realizado, não a conclusão das atividades listadas. Os status devem ser atualizados conforme documentos, testes e evidências forem produzidos e validados.

### Nota sobre recursos do GitHub Student

O workflow de carga deve permanecer como smoke curto e opt-in para preservar a franquia do GitHub Student/Pro. Os perfis Normal e Stress devem ser executados localmente ou manualmente, com artefatos mínimos e retenção curta. Os limites e a estratégia estão detalhados em [docs/plano-testes-carga-docker-ci.md](plano-testes-carga-docker-ci.md).
