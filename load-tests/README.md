# Teste de carga local

Este diretório sobe uma API Laravel descartável com SQLite, executa o `DatabaseSeeder` completo e roda um cenário k6 contra rotas de leitura autenticadas. O banco é criado dentro do container e desaparece com ele.

## Pré-requisitos

- Docker Desktop com Docker Compose;
- portas locais livres (a API fica exposta em `http://localhost:8000`).

## Executar

Na raiz do repositório:

```sh
docker compose -f load-tests/docker-compose.yml --profile load up --build --abort-on-container-exit --exit-code-from k6
```

O comando:

1. constrói a imagem PHP com SQLite e dependências do Laravel;
2. executa `php artisan migrate:fresh --seed` no banco descartável;
3. sobe a API;
4. autentica como `resident@gmail.com` / `123456`;
5. executa leituras de sessão e ocorrências;
6. imprime um resumo curto no terminal e grava as métricas agregadas em `load-tests/results/summary.json`;
7. falha se a taxa de erro atingir 1%, p95 atingir 800 ms, p99 atingir 1,5 s ou os checks ficarem abaixo de 99%.

## Variáveis de carga

```sh
K6_VUS=100 K6_DURATION=60s docker compose -f load-tests/docker-compose.yml --profile load up --build --abort-on-container-exit --exit-code-from k6
```

Valores padrão: 50 usuários virtuais por 30 segundos. O relatório JSON é salvo em `load-tests/results/summary.json`.

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

## Segurança e limites

- O cenário é somente leitura depois do login; não cria ocorrências nem altera dados.
- As credenciais são as contas de demonstração já existentes nos seeders e só devem ser usadas no ambiente descartável.
- Não aponte `DB_DATABASE` para produção. `migrate:fresh` remove todas as tabelas do banco configurado.
- O teste usa um cookie de sessão obtido uma vez no `setup` do k6 e o compartilha apenas para leituras.

## Escopo atual

Este é o primeiro smoke de carga da API real. Ele não mede frontend, upload, escrita concorrente, banco de produção ou limites do provedor de hospedagem. Novos cenários devem ser adicionados em `scenarios/` com thresholds próprios.
