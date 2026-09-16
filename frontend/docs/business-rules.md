# Regras de negócio registradas

Este arquivo registra regras solicitadas durante a evolução do produto. Alterações futuras em componentes, services ou adaptadores devem preservar estas regras ou atualizar explicitamente este documento e seus testes.

## RN09 — PDF de relatório sempre em modo claro

- **Solicitação:** o gerador de PDF deve sempre produzir um documento em modo claro.
- **Escopo:** exportação de relatórios no frontend, independentemente do tema claro/escuro do sistema ou da interface.
- **Comportamento esperado:** durante a captura, o relatório recebe a classe `pdf-light` e uma folha de estilo temporária com fundo branco, texto escuro, bordas claras, status legíveis e remoção dos elementos `.no-print` (ações Excel, PDF e Imprimir).
- **Persistência:** a classe é removida após a exportação; o tema visual da aplicação não é alterado.
- **Validação:** exportar um relatório com o sistema em tema escuro deve gerar PDF claro.

## RN10 — Relatórios restritos ao síndico

- **Solicitação:** disponibilizar a opção de relatórios conforme o caso de uso do diagrama.
- **Comportamento esperado:** somente o papel `syndic` possui `generate-reports` e visualiza o módulo Relatórios.
- **Validação:** a matriz de permissões e a navegação devem continuar testando esta restrição.

## RN11 — Consulta de relatório com filtros e paginação

- **Solicitação:** a interface de relatório deve aceitar filtros e estar preparada para paginação.
- **Comportamento esperado:** a consulta usa `ReportQuery`, com período, categoria, status, `page` e `perPage`.
- **Comportamento no mock:** os filtros são aplicados localmente e a tabela exibe somente a página selecionada.
- **Compatibilidade futura:** um repositório/API pode receber a mesma consulta sem alterar o componente visual.

## RN12 — Exportação sempre consulta o conjunto completo

- **Solicitação:** ao exportar, todos os dados devem ser obtidos antes de gerar o documento.
- **Comportamento esperado:** a exportação envia `exportAll: true`, usa `perPage: 0` e ignora a página atual.
- **PDF e Excel:** ambos recebem todos os registros filtrados; a paginação permanece apenas na visualização da tabela.
- **Validação:** exportar a partir de qualquer página deve incluir todos os registros que atendem aos filtros.

## Histórico de solicitações

| Data | Solicitação | Regra registrada |
| --- | --- | --- |
| 15/09/2026 | PDF sempre em modo claro | RN09 |
| 15/09/2026 | Relatórios conforme papel do usuário | RN10 |
| 15/09/2026 | Filtros e paginação preparados no relatório | RN11 |
| 15/09/2026 | Exportação consulta todos os dados antes de gerar arquivo | RN12 |
