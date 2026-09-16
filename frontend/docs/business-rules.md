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

## RN13 — Seleção de datas do relatório

- **Solicitação:** os filtros de data devem seguir o padrão visual dos demais inputs e usar um ícone de calendário.
- **Comportamento esperado:** clicar no campo abre um modal acessível com o componente `UCalendar` do Nuxt UI.
- **Formato de dados:** a seleção continua sendo convertida para `YYYY-MM-DD`, compatível com `ReportQuery` e com a API futura.
- **Acessibilidade:** o botão informa o valor atual, o estado aberto/fechado e permite cancelar sem alterar o filtro.

## RN14 — Consulta e solicitação de reservas

- **Solicitação:** o morador deve consultar a disponibilidade de uma área comum por dia e horário, com estados compreensíveis sem depender apenas de cor.
- **Disponibilidade:** um dia é disponível quando nenhum horário está reservado; parcialmente ocupado quando há horários reservados e livres; ocupado quando não há horários livres; áreas inativas ou fora do período permitido são indisponíveis.
- **Comportamento esperado:** somente horários livres são selecionáveis. Horários ocupados exibem apenas o estado reservado e não parecem interativos.
- **Solicitação:** o botão “Solicitar reserva” só pode ser acionado após a seleção de um horário livre e deve usar o morador autenticado, sem aceitar identidade arbitrária na interface.
- **Granularidade:** reservas por horário são compostas por blocos contíguos de 30 minutos, selecionáveis por clique, teclado ou arraste do ponteiro, respeitando a duração máxima configurada.
- **Diária:** quando `startTime` e `endTime` estão ausentes, a reserva representa o dia inteiro e bloqueia qualquer outro período da mesma área/data. Informar apenas um dos horários é inválido.
- **Privacidade:** a agenda não exibe nome, apartamento ou qualquer dado pessoal do morador que realizou outra reserva.
- **Análise administrativa:** usuários com `approve-or-reject-reservation` abrem os detalhes no modal administrativo e podem aprovar ou reprovar somente solicitações pendentes; moradores não visualizam essas ações.
- **Validação:** o mock deve demonstrar disponibilidade, ocupação parcial e ocupação total para permitir a validação visual e funcional da tela.

## Histórico de solicitações

| Data | Solicitação | Regra registrada |
| --- | --- | --- |
| 15/09/2026 | PDF sempre em modo claro | RN09 |
| 15/09/2026 | Relatórios conforme papel do usuário | RN10 |
| 15/09/2026 | Filtros e paginação preparados no relatório | RN11 |
| 15/09/2026 | Exportação consulta todos os dados antes de gerar arquivo | RN12 |
| 15/09/2026 | Filtros de data com calendário acessível em modal | RN13 |
| 15/09/2026 | Tela de reservas com disponibilidade por dia e horário | RN14 |
