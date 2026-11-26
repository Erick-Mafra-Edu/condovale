# Teste de carga local

## Benchmark Apache + MySQL

O benchmark isolado fica em `docker-compose.infra.yml`; o smoke SQLite descrito abaixo permanece disponível. Requer Docker Compose e Python 3.10+ no host. A partir da raiz do repositório:

```sh
python load-tests/run.py --profile small --scenario smoke --duration 20s
python load-tests/run.py --profile medium --scenario mixed --rate 10 --duration 5m
python load-tests/run.py --profile small --scenario baseline
```

No PowerShell, para limitar recursos antes de uma execução: `$env:PHP_CPUS='0.5'; $env:PHP_MEMORY='384m'; $env:MYSQL_CPUS='0.5'; $env:MYSQL_MEMORY='384m'; python load-tests/run.py --profile small --scenario smoke`.

`baseline` usa quatro patamares de cinco minutos (5, 10, 20, 40 iterações/s), além de 10 s de aquecimento. `short-baseline` usa os mesmos patamares por 30 s para diagnóstico no CI; não comprova estabilidade prolongada. `stress` usa patamares crescentes de dois minutos e interrompe ao violar thresholds. `spike` vai de `--rate` para três vezes a taxa e retorna; `soak` exige `--duration 30m`, `45m` ou `60m`. Testes longos e stress são exclusivamente manuais. O script cria um projeto Compose exclusivo por execução, reinicializa seu volume MySQL, prepara os dados e encerra os contêineres mesmo quando o k6 falha.

Os perfis `small`, `medium`, `large` criam respectivamente 100/1.200/5.000 usuários, 50/500/2.000 unidades e 500/10.000/50.000 reservas. `--seed` controla a geração. O comando `benchmark:prepare` exige o banco `condovale_benchmark`; ao ser repetido com o mesmo perfil e semente, valida as quantidades e retorna sem modificar dados. Se o banco contiver outra carga, ele recusa a operação. A senha das contas sintéticas é exclusiva deste ambiente. Resultados ficam em `load-tests/results/<timestamp>/` e estão ignorados pelo Git.

Limites podem ser definidos com `PHP_CPUS`, `PHP_MEMORY`, `MYSQL_CPUS`, `MYSQL_MEMORY`; por exemplo, `PHP_CPUS=0.5 MYSQL_CPUS=0.5 PHP_MEMORY=384m MYSQL_MEMORY=384m`. O coletor grava `resource-usage.csv`, `metadata.json`, `k6-raw.json` e `k6-summary.json`. O gerador escreve `summary.json`, `summary.md`, `capacity-report.md` e três gráficos SVG. Para comparar dois resultados, confronte os `summary.json` somente se host, perfil e cenário forem equivalentes. Inspecione `dropped_iterations`: qualquer valor acima de zero invalida a afirmação de capacidade sustentável. O relatório não extrapola resultados para produção.

```sh
python load-tests/compare.py load-tests/results/RUN1/summary.json load-tests/results/RUN2/summary.json
```

No GitHub Actions, o workflow **Benchmark Apache MySQL** é apenas manual e oferece smoke ou baseline curta. O workflow econômico SQLite original continua separado. Veja [arquitetura e limitações](../docs/performance/architecture.md).

Para a matriz experimental local, execute cada configuração com as variáveis `PHP_CPUS`, `MYSQL_CPUS`, `PHP_MEMORY` e `MYSQL_MEMORY` ajustadas ao host. Repita cada configuração três vezes com o mesmo perfil e semente; compare p95, RPS, erro, CPU e memória. Execute stress e soak somente após avaliar a baseline e com autorização explícita. Preserve os relatórios de cada execução fora do Git.

Este diretório sobe uma API Laravel descartável com SQLite, executa o `DatabaseSeeder` completo e roda um cenário k6 contra rotas de leitura autenticadas. O banco é criado dentro do container e desaparece com ele.

## Pré-requisitos

- Docker Desktop com Docker Compose;
- portas locais livres (a API fica exposta em `http://localhost:8000`).

## Executar

Na raiz do repositório:

```sh
docker compose -f load-tests/docker-compose.yml --profile load up --build --abort-on-container-exit --exit-code-from k6
```

Para salvar o log do Compose junto das evidencias locais:

```powershell
docker compose -f load-tests/docker-compose.yml --profile load logs --no-color | Tee-Object load-tests/results/compose.log
```

O comando:

1. constrói a imagem PHP com SQLite e dependências do Laravel;
2. executa `php artisan migrate:fresh --seed` no banco descartável;
3. sobe a API;
4. autentica uma vez como `admin@gmail.com` e `resident@gmail.com` / `123456` para preparar as sessões;
5. executa leituras de sessão, usuários, unidades, ocupações, ocorrências e tipos de ocorrência;
6. imprime um resumo curto no terminal e grava as métricas agregadas em `load-tests/results/summary.json`, uma tabela em Markdown e um gráfico SVG;
7. falha se a taxa de erro atingir 1%, p95 atingir 800 ms, p99 atingir 1,5 s ou os checks ficarem abaixo de 99%.

## Variáveis de carga

```sh
K6_VUS=100 K6_DURATION=60s docker compose -f load-tests/docker-compose.yml --profile load up --build --abort-on-container-exit --exit-code-from k6
```

Valores padrão: 50 usuários virtuais por 30 segundos. O relatório JSON é salvo em `load-tests/results/summary.json`.

## Evidências de performance

Ao terminar, o k6 grava em `load-tests/results/`:

- `summary.json`: métricas agregadas, incluindo throughput, média, p95, p99, erros e checks;
- `summary.md`: relatório legível para anexar à entrega M3;
- `performance.svg`: gráfico de média e p95 para cada uma das seis funcionalidades.

O cenário mede as principais leituras da API nesta baseline: `GET /api/auth/session`, `/api/users`, `/api/units`, `/api/unit-occupancies`, `/api/occurrences` e `/api/occurrence-types`. O login dos dois perfis ocorre apenas no `setup` e serve para preparar cookies; não entra na carga contínua das iterações. O throughput é calculado sobre todas as requisições HTTP da execução. Esses arquivos são evidências da execução específica e devem ser publicados como artefato do CI; o README não é alterado automaticamente para evitar commits de métricas transitórias.

No GitHub Actions com conta estudantil, o PR rotulado usa 2 VUs por 10 segundos. No PowerShell, o equivalente local é:

```powershell
$env:K6_VUS = '2'
$env:K6_DURATION = '10s'
docker compose -f load-tests/docker-compose.yml --profile load up --build --abort-on-container-exit --exit-code-from k6
```

O `workflow_dispatch` permite apenas 2, 10 ou 50 VUs por 10, 30 ou 60 segundos. Deixe cargas Normal prolongadas (10–50 VUs por minutos) e Stress (100–200 VUs) para execução local/manual e evidência de performance. Não coloque uma matriz desses valores no CI.

## Limites do GitHub Student/Pro

O Student Developer Pack inclui GitHub Pro enquanto a elegibilidade estiver ativa. Para repositórios privados, o GitHub informa atualmente US$ 18/mês de uso computacional incluído, 1 GB de artefatos e 10 GB de cache por repositório. Runners padrão em repositórios públicos não consomem a franquia de Actions, mas ainda existem limites de tempo, concorrência e armazenamento.

Por isso, o workflow deste projeto é manual ou opt-in pelo label `run-load-tests`, usa 2 VUs/10s no PR, publica apenas o resumo/logs necessários e retém os artefatos por 3 dias. Consulte [GitHub Actions billing](https://docs.github.com/en/billing/concepts/product-billing/github-actions) antes de alterar a política ou executar stress no CI.

No PowerShell:

```powershell
$env:K6_VUS = '100'
$env:K6_DURATION = '60s'
docker compose -f load-tests/docker-compose.yml --profile load up --build --abort-on-container-exit --exit-code-from k6
```

O container k6 roda como root apenas para garantir a escrita no bind mount `results` em runners Linux. A pasta contém somente o resumo agregado da execução; não é produzido um stream de cada amostra.

## Apresentação e leitura das evidências

Os números atualmente disponíveis como evidência são de execução **local**, usando Docker Compose. Para apresentar essa evidência local, abra `load-tests/results/summary.md` e projete `load-tests/results/performance.svg`; use `summary.json` como dados brutos agregados e os logs do Compose para demonstrar o ambiente/seed.

Para uma evidência futura do CI, abra o PR ou a execução do workflow **Testes de carga** em **Actions**. Após a conclusão, consulte o **Job Summary** para a leitura rápida dos números e, na seção **Artifacts**, baixe `load-test-results-<run_id>`. Dentro do pacote, abra `results/summary.md`, `results/performance.svg`, `results/summary.json` e `compose.log`.

Localmente, os mesmos arquivos ficam em `load-tests/results/` após a execução. Use `summary.md` e `performance.svg` na apresentação; use `summary.json` para auditoria ou processamento posterior, não como material principal de projeção.

## Segurança e limites

- O cenário é somente leitura depois do login; não cria ocorrências nem altera dados.
- As credenciais são as contas de demonstração já existentes nos seeders e só devem ser usadas no ambiente descartável.
- Não aponte `DB_DATABASE` para produção. `migrate:fresh` remove todas as tabelas do banco configurado.
- O teste usa os cookies de sessão retornados no `setup` do k6 e os compartilha apenas para leituras; o nome do cookie não é fixado, pois deriva da configuração da aplicação.

## Exemplo CI validado

[GitHub Actions run 45231566706](https://github.com/Erick-Mafra-Edu/condovale/actions/runs/45231566706) executou 2 VUs por 10 segundos com throughput de 74,94 req/s, media de 25,21 ms, p95 de 29,79 ms, erro 0% e checks 100%. O artifact `load-test-results-45231566706` foi conferido e expira em 26/11/2026; use o workflow Testes de carga como caminho estavel para novas execucoes.

## Escopo atual

Este é o primeiro smoke de carga da API real. Ele não mede frontend, upload, escrita concorrente, banco de produção ou limites do provedor de hospedagem. Novos cenários devem ser adicionados em `scenarios/` com thresholds próprios.
