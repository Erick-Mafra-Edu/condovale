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

## Histórico de solicitações

| Data | Solicitação | Regra registrada |
| --- | --- | --- |
| 15/09/2026 | PDF sempre em modo claro | RN09 |
| 15/09/2026 | Relatórios conforme papel do usuário | RN10 |
