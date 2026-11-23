# Relatório de segurança e issues do SonarCloud

**Projeto:** Condovale

**Data de referência:** 23 de novembro de 2026

**Repositório:** `Erick-Mafra-Edu/condovale`

**Projeto SonarCloud:** `Erick-Mafra-Edu_condovale`

**Consulta:** issues com estado `OPEN` ou `CONFIRMED`, ordenadas por impacto

## 1. Resumo executivo

Na consulta realizada em 23/11/2026, o SonarCloud apresentou **17 issues de segurança abertos**, com esforço total estimado de **525 minutos (8h45)**.

| Impacto de segurança | Quantidade |
|---|---:|
| Alto | 7 |
| Médio | 8 |
| Baixo | 2 |
| **Total** | **17** |

Os 17 registros correspondem a menos mudanças de código do que o total sugere, porque algumas linhas são reportadas por duas regras diferentes. A avaliação do repositório identificou:

- **12 issues corrigíveis com baixo risco**, agrupadas em sete mudanças pequenas;
- **3 issues corrigíveis somente após validação de instalação e build**;
- **2 issues de FTP que dependem da infraestrutura do provedor** e não devem ser alteradas localmente sem confirmação de suporte a FTPS/SFTP.

As prioridades imediatas são restaurar a validação TLS no verificador de produção, fixar GitHub Actions por SHA e eliminar execuções `npx` que possam baixar pacotes durante o pipeline.

## 2. Escopo e metodologia

O levantamento considerou:

1. a API pública do SonarCloud consultada em 23/11/2026;
2. os arquivos e linhas atualmente presentes no repositório;
3. chamadores dos scripts afetados;
4. lockfiles, manifests e comandos utilizados pelo CI e pelo deploy;
5. riscos de indisponibilidade, alteração de protocolo e quebra de dependências.

Não foram executados deploy, migração, conexão FTP nem testes contra produção. Nenhuma correção foi aplicada durante a elaboração deste relatório.

## 3. Inventário completo

### 3.1 Impacto alto

| Chave | Regra | Local | Descrição | Avaliação |
|---|---|---|---|---|
| `AaDlW-nGz5PIa1mb4GDd` | `githubactions:S7637` | `.github/workflows/deploy-infinityfree.yml:53` | Action PHP referenciada por tag mutável | Correção segura agora |
| `AaDlW-nGz5PIa1mb4GDe` | `githubactions:S7637` | `.github/workflows/deploy-infinityfree.yml:92` | Action de FTP referenciada por tag mutável | Correção segura agora |
| `AaDlW-k2z5PIa1mb4GDZ` | `python:S4790` | `tools/deploy-infinityfree.py:105` | Uso de MD5 | Uso contextual; correção segura sem mudar o hash |
| `AaEnTLyDZUoGI0YrnKM4` | `python:S4790` | `tools/verify-production.py:70` | Uso de MD5 | Uso contextual; correção segura sem mudar o hash |
| `AaEnTLyDZUoGI0YrnKM6` | `python:S4790` | `tools/verify-production.py:121` | Uso de MD5 | Uso contextual; correção segura sem mudar o hash |
| `AaEnTLyDZUoGI0YrnKM7` | `python:S4830` | `tools/verify-production.py:173` | Certificado TLS não validado | Vulnerabilidade real; correção prioritária |
| `AaEnTLyDZUoGI0YrnKM8` | `python:S5527` | `tools/verify-production.py:173` | Hostname TLS não validado | Vulnerabilidade real; mesma correção do item anterior |

### 3.2 Impacto médio

| Chave | Regra | Local | Descrição | Avaliação |
|---|---|---|---|---|
| `AaEJfzDmIX282Dw4d524` | `githubactions:S6505` | `.github/workflows/ci.yml:61` | `npm ci` permite lifecycle scripts | Corrigível após validação |
| `AaEJfzDmIX282Dw4d525` | `githubactions:S6505` | `.github/workflows/ci.yml:65` | `npx` pode instalar pacotes sob demanda | Correção segura agora |
| `AaEJfzDmIX282Dw4d526` | `githubactions:S8543` | `.github/workflows/ci.yml:65` | Pacote executado sem versão exata | Mesma correção do item anterior |
| `AaEJfzDmIX282Dw4d528` | `githubactions:S6505` | `.github/workflows/ci.yml:146` | `npm install` permite lifecycle scripts | Corrigível após validação |
| `AaEJfzDmIX282Dw4d529` | `githubactions:S8543` | `.github/workflows/ci.yml:146` | Dependências resolvidas sem lockfile | Correção segura com criação do lockfile |
| `AaEJseN-HzXM_Jautj7g` | `githubactions:S6505` | `.github/workflows/deploy-infinityfree.yml:46` | `npx` pode instalar pacotes sob demanda | Correção segura agora |
| `AaEJseN-HzXM_Jautj7h` | `githubactions:S8543` | `.github/workflows/deploy-infinityfree.yml:46` | Pacote executado sem versão exata | Mesma correção do item anterior |
| `AaDFGjEG7LxId_Z3bFiM` | `text:S8564` | `laravel/package.json` | Ausência de lockfile Node no Laravel | Correção segura agora |

### 3.3 Impacto baixo

| Chave | Regra | Local | Descrição | Avaliação |
|---|---|---|---|---|
| `AaDlW-k2z5PIa1mb4GDa` | `python:S5332` | `tools/deploy-infinityfree.py:212` | Publicação por FTP sem criptografia | Risco real dependente do provedor |
| `AaEnTLyDZUoGI0YrnKM5` | `python:S5332` | `tools/verify-production.py:93` | Verificação por FTP sem criptografia | Risco real dependente do provedor |

## 4. Análise técnica e remediações

### 4.1 Validação TLS desativada

O método `abrir_navegador` usa `ssl._create_unverified_context()`. Isso desativa simultaneamente a validação da cadeia do certificado e a conferência do hostname, permitindo que uma conexão HTTPS adulterada seja aceita.

O problema também alcança `tools/run-deploy-migration.py`, que importa o verificador e reutiliza o fluxo HTTP. Portanto, o impacto não está limitado a uma conferência passiva: ele alcança chamadas relacionadas à implantação.

**Remediação mínima:** substituir o contexto inseguro por `ssl.create_default_context()` ou remover o handler TLS customizado para usar o comportamento seguro padrão da biblioteca.

**Risco de compatibilidade:** baixo. O fluxo passará a falhar corretamente se o domínio apresentar certificado inválido, expirado ou incompatível com o hostname.

### 4.2 GitHub Actions referenciadas por tags

Tags como `v2` e `v4.4.0` podem ser alteradas no repositório de origem. Fixar o commit impede que uma alteração posterior da tag introduza código diferente no pipeline.

**Remediações validadas:**

- `shivammathur/setup-php@d52fc211a0436f8839a587b3b5b8efddab4744ea`;
- `SamKirkland/FTP-Deploy-Action@110f9186c050f71550953127052e77650219c287`.

O primeiro SHA já é utilizado no workflow de CI do próprio projeto.

### 4.3 MD5 usado como checksum

Os três usos de MD5 não protegem senhas, tokens, assinaturas ou identidade. Eles detectam alterações em arquivos para publicação incremental e conferem se arquivos locais e remotos possuem o mesmo conteúdo.

Trocar imediatamente para SHA-256 não é necessário para esse objetivo e invalidaria os manifests existentes, provocando uma publicação completa na execução seguinte.

**Remediação mínima:** declarar explicitamente o uso não criptográfico:

```python
hashlib.md5(..., usedforsecurity=False)
```

Isso exige Python 3.9 ou superior, preserva os hashes atuais e não quebra o estado incremental.

### 4.4 Execução de `npx` no CI e deploy

O Nuxt já é instalado a partir de `frontend/package-lock.json`. Mesmo assim, `npx nuxt prepare` permite que o `npx` procure ou instale um pacote quando não encontra o executável esperado.

**Remediação mínima nos dois workflows:**

```sh
./node_modules/.bin/nuxt prepare
```

A alteração usa somente a dependência resolvida pelo lockfile e trata quatro issues do SonarCloud.

### 4.5 Dependências Node do Laravel sem lockfile

O diretório `laravel` possui `package.json` com faixas de versão `^`, mas não possui `package-lock.json`. O CI usa `npm install`, de modo que execuções em datas diferentes podem resolver versões distintas.

O `.gitignore` do Laravel não bloqueia o lockfile. O deploy de produção não usa os assets Node em runtime, portanto o impacto da mudança está restrito à reprodutibilidade do build.

**Remediação mínima:**

1. gerar e revisar `laravel/package-lock.json`;
2. versionar o arquivo;
3. trocar o CI de `npm install` para `npm ci`;
4. validar `npm run build`.

### 4.6 Lifecycle scripts durante instalações npm

Adicionar `--ignore-scripts` reduz o risco de execução de código arbitrário contido em scripts de instalação de dependências. Entretanto, algumas dependências legítimas podem depender desses scripts para preparar binários ou artefatos.

Por isso, esta correção não deve ser aplicada sem executar instalação limpa, testes, typecheck e builds. Se alguma dependência exigir script, a exceção precisa ser explícita e limitada, em vez de desabilitar a proteção globalmente.

### 4.7 FTP sem criptografia

FTP transmite credenciais e conteúdo sem proteção criptográfica. Os dois issues são vulnerabilidades reais, não falsos positivos.

O projeto, entretanto, documenta que o plano atual do InfinityFree não oferece Git ou SSH e utiliza FTP como canal disponível. Trocar `ftplib.FTP` por `FTP_TLS`, SFTP ou SCP sem confirmar suporte, porta, certificado e credenciais pode interromper publicação e verificação.

**Tratamento recomendado:**

- confirmar formalmente se a conta suporta FTPS explícito;
- se suportar, migrar scripts e workflow em uma mudança testada contra o servidor;
- se não suportar, registrar aceitação temporária do risco;
- restringir o usuário FTP ao menor diretório possível;
- usar senha exclusiva e rotacioná-la periodicamente;
- planejar hospedagem com SFTP/FTPS como correção estrutural.

## 5. Priorização proposta

| Prioridade | Mudança | Issues tratados | Risco de quebra |
|---|---|---:|---|
| P0 | Restaurar validação de certificado e hostname TLS | 2 | Baixo |
| P0 | Fixar duas GitHub Actions por SHA | 2 | Baixo |
| P1 | Substituir os dois comandos `npx` por binários locais | 4 | Baixo |
| P1 | Marcar os três MD5 como uso não criptográfico | 3 | Baixo, com Python 3.9+ |
| P1 | Criar lockfile Laravel e migrar para `npm ci` | 2 | Baixo após revisão do lockfile |
| P2 | Validar e, se compatível, adicionar `--ignore-scripts` | 2 | Médio |
| P2 | Decidir aceitação ou migração do FTP | 2 | Alto risco operacional se alterado sem suporte |

## 6. Plano de validação

Depois das correções locais, executar:

```powershell
cd frontend
npm ci --ignore-scripts --no-audit --no-fund
./node_modules/.bin/nuxt prepare
npm test
npm run typecheck
npm run build

cd ../laravel
npm ci --ignore-scripts --no-audit --no-fund
npm run build

cd ..
python tools/deploy-infinityfree.py --dry-run
python tools/verify-production.py
```

A execução de `verify-production.py` acessa produção e deve ocorrer somente em ambiente autorizado. O `--dry-run` do publicador deve ser usado antes de qualquer publicação real.

Após merge e nova análise do SonarCloud, confirmar que os issues corrigidos foram encerrados e documentar separadamente os dois riscos de FTP que permanecerem aceitos.

## 7. Conclusão

O conjunto atual não indica falha conhecida na lógica de negócio da aplicação, mas contém riscos relevantes na cadeia de entrega e nas ferramentas operacionais. A maior exposição local é a validação TLS desativada. As referências mutáveis de Actions e os comandos `npx` são riscos de supply chain fáceis de eliminar.

Os alertas de MD5 são contextuais e podem ser tratados sem trocar o algoritmo. A ausência de lockfile no Laravel deve ser corrigida para tornar o build reproduzível. O FTP permanece como débito de infraestrutura e precisa de aceitação formal ou mudança de provedor/protocolo.

## 8. Referência

- Painel consultado: <https://sonarcloud.io/project/issues?id=Erick-Mafra-Edu_condovale&s=IMPACT_RANK&issueStatuses=OPEN%2CCONFIRMED>

