# Testes de carga com Docker Compose e GitHub Actions

**Status:** implementação inicial disponível

**Escopo:** API Laravel em banco SQLite descartável, executada por k6.

## 1. O que já está implementado

| Arquivo | Função |
| --- | --- |
| `load-tests/docker-compose.yml` | Sobe a API Laravel e o runner k6. |
| `load-tests/Dockerfile` | Monta a imagem PHP 8.3 com SQLite e dependências Laravel. |
| `load-tests/scenarios/api-read.js` | Cenário de login, sessão e leitura de ocorrências. |
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

1. `POST /api/auth/login` com `resident@gmail.com` / `123456`;
2. captura do cookie `laravel_session`;
3. `GET /api/auth/session` usando o cookie;
4. `GET /api/occurrences` usando o cookie;
5. validação de status HTTP, JSON e checks.

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

Para alterar a carga:

```bash
K6_VUS=100 K6_DURATION=2m docker compose -f load-tests/docker-compose.yml --profile load up --build --abort-on-container-exit --exit-code-from k6
```

No PowerShell:

```powershell
$env:K6_VUS='100'; $env:K6_DURATION='2m'
docker compose -f load-tests/docker-compose.yml --profile load up --build --abort-on-container-exit --exit-code-from k6
```

O resultado JSON é escrito em `load-tests/results/summary.json`.

## 6. Perfis desta versão

Como existe um único cenário parametrizado, os perfis são combinações de `K6_VUS` e `K6_DURATION`:

| Perfil | VUs | Duração sugerida | Uso |
| --- | ---: | ---: | --- |
| PR opt-in | 2 | 10s | Smoke fixo e econômico; valida imagem, seed, login e endpoints. |
| Manual curto | 2, 10 ou 50 | 10s, 30s ou 60s | Opções disponíveis no `workflow_dispatch`; baseline curto. |
| Normal local | 10–50 | 2–5m | Baseline de leitura fora do CI. |
| Stress local | 100–200 | 5–10m | Procurar degradação fora do CI. |

O cenário atual é somente leitura após autenticação. Ainda não mede criação de ocorrências, autorização negativa, reservas, escrita concorrente, spike ou soak.

## 7. GitHub Actions real

O workflow `.github/workflows/load-tests.yml` é acionado por `workflow_dispatch` ou por `pull_request` com o label `run-load-tests`. Em PR rotulado, executa sempre 2 VUs por 10 segundos. Na execução manual, oferece apenas 2, 10 ou 50 VUs e 10, 30 ou 60 segundos. Ele valida o Compose, executa `up --build --abort-on-container-exit --exit-code-from k6`, coleta `ps`, logs e `load-tests/results` mesmo em falha, publica `load-test-results-<run_id>` por 3 dias e executa `down -v --remove-orphans`.

Para usar no GitHub, aplicar o label `run-load-tests` ao pull request para o smoke fixo ou iniciar manualmente o workflow para uma das combinações curtas permitidas. Cargas Normal prolongadas e Stress de 100–200 VUs continuam sendo locais/manuais.

### 7.1 Limitações da conta estudantil

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

## 8. Evidências

```text
artifacts/load-tests/
├── compose-ps.txt
├── compose.log
└── results/summary.json
```

Registrar commit, run ID, VUs, duração, thresholds, versões Docker/Compose/k6 e resultado. Não publicar credenciais ou dados pessoais. Vincular a execução aos IDs `LOAD-*` do relatório M3.

## 9. Próximas evoluções

1. Avaliar novos inputs do workflow manual somente se continuarem dentro do orçamento; manter o PR fixo em 2 VUs/10s e exigir confirmação para qualquer carga maior.
2. Criar cenários separados para escrita, autorização, conflito e recuperação.
3. Adicionar arquivos smoke/normal/stress somente quando houver fluxos diferentes.
4. Medir CPU, memória e consultas do banco durante stress.
5. Definir thresholds oficiais após baseline repetido e aprovação do responsável.
6. Migrar de SQLite para o motor do ambiente-alvo se a capacidade do banco fizer parte do objetivo.

## 10. Checklist

- [x] Banco isolado e descartável.
- [x] Seed Laravel executado automaticamente.
- [x] Health check da API.
- [x] Cenário de login, sessão e ocorrências.
- [x] Thresholds de falha, p95, p99 e checks.
- [x] Coleta de logs e JSON no CI.
- [x] Limpeza garantida do Compose.
- [ ] Cenários de escrita e concorrência.
- [ ] Perfis separados smoke/normal/stress.
- [ ] Inputs de carga no workflow manual.
- [ ] Relatório com métricas de infraestrutura.
