# Testes de carga com Docker Compose e GitHub Actions

**Status:** implementação inicial disponível

**Escopo:** API Laravel em banco SQLite descartável, executada por k6.

## 1. O que já está implementado

| Arquivo | Função |
| --- | --- |
| `load-tests/docker-compose.yml` | Sobe a API Laravel e o runner k6. |
| `load-tests/Dockerfile` | Monta a imagem PHP 8.3 com SQLite e dependências Laravel. |
| `load-tests/scenarios/api-read.js` | Cenário k6 com duas sessões preparadas e seis leituras principais da API. |
| `.github/workflows/load-tests.yml` | Executa a carga no GitHub Actions e publica artefatos. |

Não existem nesta primeira versão `docker-compose.load.yml`, `smoke.js`, `normal.js` ou `stress.js`. Esses nomes ficam como evolução futura; os comandos abaixo usam somente os arquivos implementados.

## 2. Objetivo e segurança

Medir a API HTTP sob carga sem atingir produção. O banco é criado dentro do container da API em `/tmp/condovale-load.sqlite` e recriado com `migrate:fresh --seed` a cada execução.

- Nunca executar contra produção, InfinityFree ou dados reais.
- Usar apenas os usuários sintéticos dos seeders.
- Não registrar senhas, cookies ou tokens nos artefatos.
- `migrate:fresh --seed` é destrutivo, mas está isolado no container de carga.
- O workflow sempre coleta logs e executa `down -v --remove-orphans`.

## 3. Arquitetura real

```text
k6 (grafana/k6:0.54.0) ──HTTP──> Laravel/php artisan serve:8000
                                      └── SQLite /tmp/condovale-load.sqlite
```

O Compose possui os serviços `api` e `k6`. A API usa PHP 8.3 CLI, instala `pdo_sqlite`, copia o Laravel, cria o SQLite, executa `php artisan migrate:fresh --seed --force` e inicia o servidor na porta 8000. O k6 depende de um health check TCP com `fsockopen('127.0.0.1', 8000)` e acessa `http://api:8000`.

## 4. Cenário atualmente implementado

Arquivo: `load-tests/scenarios/api-read.js`.

Fluxo:

1. prepara uma sessão de administrador com `admin@gmail.com` / `123456`;
2. prepara uma sessão de morador com `resident@gmail.com` / `123456`;
3. captura os cookies `laravel_session` correspondentes;
4. executa, sem escrita, as leituras `GET /api/auth/session`, `/api/users`, `/api/units`, `/api/unit-occupancies`, `/api/occurrences` e `/api/occurrence-types`;
5. usa a sessão apropriada por perfil e valida HTTP 200, JSON e checks por funcionalidade.

O login ocorre somente no `setup` do k6 para preparar as sessões; não é repetido na carga contínua. Cada endpoint possui uma métrica Trend própria para média e p95, além do throughput geral da execução.

Parâmetros:

```text
BASE_URL      (Compose: http://api:8000)
K6_VUS        (padrão: 50)
K6_DURATION   (padrão: 30s)
```

Thresholds reais:

```text
http_req_failed < 1%
http_req_duration p(95) < 800ms
http_req_duration p(99) < 1500ms
checks > 99%
```

Esses limites são um baseline inicial, mais estrito que o limite provisório de 2 segundos do plano geral; precisam ser validados com execuções repetidas.

## 5. Execução local

```bash
docker compose -f load-tests/docker-compose.yml --profile load config
docker compose -f load-tests/docker-compose.yml --profile load up --build --abort-on-container-exit --exit-code-from k6
docker compose -f load-tests/docker-compose.yml --profile load logs --no-color
docker compose -f load-tests/docker-compose.yml --profile load down -v --remove-orphans
```

Para salvar o log do Compose junto das evidências locais:

```bash
docker compose -f load-tests/docker-compose.yml --profile load logs --no-color > load-tests/results/compose.log
```

Para alterar a carga:

```bash
K6_VUS=100 K6_DURATION=2m docker compose -f load-tests/docker-compose.yml --profile load up --build --abort-on-container-exit --exit-code-from k6
```

No PowerShell:

```powershell
$env:K6_VUS='100'; $env:K6_DURATION='2m'
docker compose -f load-tests/docker-compose.yml --profile load up --build --abort-on-container-exit --exit-code-from k6
```

Os resultados são escritos em `load-tests/results/summary.json`, `summary.md` e `performance.svg`.

## 6. Ferramenta e escopo da medição

Esta baseline usa **k6** em container Docker. JMeter não é necessário para o cenário atual: adicionar uma segunda ferramenta duplicaria a medição sem ampliar a cobertura. O Lighthouse existente no repositório é evidência visual/funcional, mas não comprova este critério de performance porque não contém uma auditoria da categoria `performance`. Ele não substitui a medição HTTP do k6.

O conjunto de funcionalidades principais medido nesta baseline é:

| Funcionalidade | Endpoint | Métricas |
| --- | --- | --- |
| Sessão | `GET /api/auth/session` | média, p95 |
| Usuários | `GET /api/users` | média, p95 |
| Unidades | `GET /api/units` | média, p95 |
| Ocupações | `GET /api/unit-occupancies` | média, p95 |
| Ocorrências | `GET /api/occurrences` | média, p95 |
| Tipos de ocorrência | `GET /api/occurrence-types` | média, p95 |
| Execução geral | todas as requisições HTTP | throughput, média, p95, p99, erro e checks |

## 7. Perfis desta versão

Como existe um único cenário parametrizado, os perfis são combinações de `K6_VUS` e `K6_DURATION`:

| Perfil | VUs | Duração sugerida | Uso |
| --- | ---: | ---: | --- |
| PR opt-in | 2 | 10s | Smoke fixo e econômico; valida imagem, seed, login e endpoints. |
| Manual curto | 2, 10 ou 50 | 10s, 30s ou 60s | Opções disponíveis no `workflow_dispatch`; baseline curto. |
| Normal local | 10–50 | 2–5m | Baseline de leitura fora do CI. |
| Stress local | 100–200 | 5–10m | Procurar degradação fora do CI. |

O cenário atual é somente leitura após autenticação. Ainda não mede criação de ocorrências, autorização negativa, reservas, escrita concorrente, spike ou soak.

## 8. GitHub Actions real

O workflow `.github/workflows/load-tests.yml` é acionado por `workflow_dispatch` ou por `pull_request` com o label `run-load-tests`. Em PR rotulado, executa sempre 2 VUs por 10 segundos. Na execução manual, oferece apenas 2, 10 ou 50 VUs e 10, 30 ou 60 segundos. Ele valida o Compose, executa `up --build --abort-on-container-exit --exit-code-from k6`, coleta `ps`, logs e `load-tests/results` mesmo em falha, publica `load-test-results-<run_id>` por 3 dias e executa `down -v --remove-orphans`.

Para usar no GitHub, aplicar o label `run-load-tests` ao pull request para o smoke fixo ou iniciar manualmente o workflow para uma das combinações curtas permitidas. Cargas Normal prolongadas e Stress de 100–200 VUs continuam sendo locais/manuais.

### 8.1 Limitações da conta estudantil

O GitHub Student Developer Pack oferece GitHub Pro enquanto a elegibilidade estiver ativa. Para repositórios privados, a referência atual do GitHub para o plano Pro é **US$ 18/mês de uso computacional incluído**, **1 GB de armazenamento de artefatos** e **10 GB de cache por repositório**. Em repositórios públicos, os runners padrão hospedados pelo GitHub não consomem a franquia de Actions; isso não elimina limites de tempo, concorrência ou armazenamento de artefatos. Consulte os valores vigentes na [documentação oficial de cobrança do GitHub Actions](https://docs.github.com/en/billing/concepts/product-billing/github-actions) e no [Student Developer Pack](https://education.github.com/pack).

Para não transformar o teste de carga em consumo recorrente de quota:

- o workflow não roda em todo `push` nem em todo PR; ele é manual ou opt-in pelo label `run-load-tests`;
- em PR rotulado, o CI usa somente o perfil **PR opt-in** (2 VUs por 10 segundos), suficiente para validar imagem, seed, login e endpoints;
- no `workflow_dispatch`, usar apenas as opções curtas de 2, 10 ou 50 VUs por 10, 30 ou 60 segundos;
- os perfis Normal prolongado e Stress de 100–200 VUs devem rodar localmente ou manualmente, com decisão explícita e acompanhamento do consumo;
- não usar matriz de VUs, navegadores ou sistemas operacionais no job de carga;
- manter artefatos mínimos: resumo JSON, logs e estado do Compose, sem amostras brutas por requisição;
- manter retenção curta dos artefatos, atualmente 3 dias, e apagar execuções antigas quando não forem mais necessárias;
- não adicionar cache de imagens Docker ou dependências sem medir o benefício, pois cache também ocupa a franquia do repositório;
- configurar um orçamento/alerta no GitHub e interromper o uso antes de atingir o limite. Sem método de pagamento válido, o GitHub pode bloquear uso adicional; com cobrança habilitada, o excedente pode gerar cobrança.

Essa política faz do CI um **gate de regressão barato**, não um substituto para a campanha M3 de carga/estresse. Os resultados oficiais de 50, 100 e 200 usuários devem ser executados fora do ciclo automático e anexados como evidência separada.

## 9. Evidências

```text
artifacts/load-tests/
├── compose-ps.txt
├── compose.log
└── results/
    ├── summary.json
    ├── summary.md
    └── performance.svg
```

O `summary.md` contém média, p95, p99, throughput, taxa de erro, checks e a tabela das seis funcionalidades. O `performance.svg` é o gráfico autocontido de média e p95 por endpoint. O workflow já gera o **Job Summary** e publica o artifact de resultados; ainda falta executar o workflow no GitHub para obter a evidência CI correspondente.

| Item do critério | Evidência planejada | Situação |
| --- | --- | --- |
| Tempo médio | `results/summary.md` e `summary.json` por endpoint | Validado em execução local em 23/11/2026 |
| p95 | `results/summary.md` e `summary.json` por endpoint | Validado em execução local em 23/11/2026 |
| Throughput | `results/summary.md` e `summary.json` da execução | Validado em execução local em 23/11/2026 |
| Gráfico | `results/performance.svg` | Validado em execução local em 23/11/2026 |
| Visibilidade no CI | Job Summary + artifact de 3 dias | Implementado no workflow; execução CI ainda pendente |

Registrar commit, run ID, VUs, duração, thresholds, versões Docker/Compose/k6 e resultado. Não publicar credenciais ou dados pessoais. Vincular a execução aos IDs `LOAD-*` do relatório M3. A execução local já valida a geração das métricas; a evidência do CI só deve ser marcada após uma execução GitHub Actions bem-sucedida.

### 9.1 Roteiro para apresentar a evidência local atual

Os números atualmente disponíveis foram obtidos em execução **local** com Docker Compose. Apresente `load-tests/results/summary.md` e `load-tests/results/performance.svg`; mantenha `load-tests/results/summary.json` como dados brutos agregados e `compose.log` como evidência do ambiente e do seed. Esses números não devem ser atribuídos ao GitHub Actions.

### 9.2 Roteiro para localizar evidência futura no GitHub Actions

1. No GitHub, abra o PR ou **Actions → Testes de carga**.
2. Abra a execução concluída e leia o **Job Summary** para a visão rápida das métricas.
3. Na seção **Artifacts**, baixe `load-test-results-<run_id>`.
4. Dentro do pacote, abra `results/summary.md` para os números, `results/performance.svg` para projetar o gráfico, `results/summary.json` como dados brutos agregados e `compose.log` para comprovar o ambiente e o seed.

Localmente, os mesmos arquivos ficam em `load-tests/results/` após o comando do Compose. Para a apresentação, projete `summary.md` e `performance.svg`; reserve `summary.json` para auditoria/processamento e `compose.log` para explicar a preparação do ambiente.

## 10. Próximas evoluções

1. Avaliar novos inputs do workflow manual somente se continuarem dentro do orçamento; manter o PR fixo em 2 VUs/10s e exigir confirmação para qualquer carga maior.
2. Criar cenários separados para escrita, autorização, conflito e recuperação.
3. Adicionar arquivos smoke/normal/stress somente quando houver fluxos diferentes.
4. Medir CPU, memória e consultas do banco durante stress.
5. Definir thresholds oficiais após baseline repetido e aprovação do responsável.
6. Migrar de SQLite para o motor do ambiente-alvo se a capacidade do banco fizer parte do objetivo.

## Resultado CI validado

Execucao: [GitHub Actions run 45231566706](https://github.com/Erick-Mafra-Edu/condovale/actions/runs/45231566706), com 2 VUs por 10 segundos e duracao aproximada de 1m04s.

| Metrica | Resultado CI |
| --- | ---: |
| Throughput | 74,94 req/s |
| Tempo HTTP medio | 25,21 ms |
| Tempo HTTP p95 | 29,79 ms |
| Taxa de erro | 0% |
| Checks aprovados | 100% |

O Job Summary foi concluido e o artifact `load-test-results-45231566706` foi conferido com `summary.md`, `summary.json`, `performance.svg`, `compose.log` e `compose-ps.txt`. O artifact expira em 26/11/2026; o workflow Testes de carga e seu caminho de execucao sao a referencia estavel.

## Resultado local validado

Execucao local em **23/11/2026**, com Docker Compose, SQLite descartavel e **2 VUs por 10 segundos**:

| Metrica | Resultado |
| --- | ---: |
| Throughput | 115,28 req/s |
| Tempo HTTP medio | 16,58 ms |
| Tempo HTTP p95 | 20,52 ms |
| Taxa de erro | 0% |
| Checks aprovados | 100% |

Os valores acima são exclusivamente da execução local. A tabela detalhada por endpoint está em `load-tests/results/summary.md`, os dados agregados em `summary.json` e o gráfico em `performance.svg`. O Job Summary e o upload do artifact estão implementados no workflow, mas a execução CI ainda está pendente.

### Matriz do criterio apos a execucao local

| Item | Situacao |
| --- | --- |
| Tempo médio | Validado localmente em 23/11/2026 |
| p95 | Validado localmente em 23/11/2026 |
| Throughput | Validado localmente em 23/11/2026 |
| Gráfico | Validado localmente em 23/11/2026 |
| Job Summary CI | Implementado no workflow; execução CI pendente |

## 11. Checklist

- [x] Banco isolado e descartável.
- [x] Seed Laravel executado automaticamente.
- [x] Health check da API.
- [x] Cenário k6 com duas sessões preparadas e seis leituras principais.
- [x] Thresholds de falha, p95, p99 e checks.
- [x] Coleta de logs, JSON, Markdown e SVG no CI.
- [x] Limpeza garantida do Compose.
- [ ] Cenários de escrita e concorrência.
- [ ] Perfis separados smoke/normal/stress.
- [ ] Inputs de carga no workflow manual.
- [x] Job Summary com média, p95, throughput e link do gráfico implementado no workflow.
- [ ] Execução CI bem-sucedida anexada como evidência M3.
- [ ] Relatório com métricas de infraestrutura.
