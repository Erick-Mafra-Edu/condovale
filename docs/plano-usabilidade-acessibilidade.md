# Plano de usabilidade e acessibilidade

**Status:** preparação da avaliação; resultados humanos ainda não coletados.

**Escopo:** cumprir o requisito M3 de avaliação com pelo menos três usuários, auditoria Lighthouse/WAVE e registro de melhorias.

## 1. Evidências já disponíveis

Existe um relatório histórico em [`docs/frontend-guia/lighthouse-final.json`](frontend-guia/lighthouse-final.json):

- Lighthouse 12.8.2;
- URL local `http://127.0.0.1:3001/`;
- execução em 09/11/2026;
- acessibilidade: 100%;
- SEO: 100%.

Esse relatório é apenas referência histórica. Ele não substitui uma execução atual, o WAVE nem a avaliação com usuários. Resultados automatizados também não comprovam, sozinhos, usabilidade.

## 2. Critérios de aceite

- [ ] Três participantes distintos avaliados.
- [ ] Participantes identificados somente por códigos (`P01`, `P02`, `P03`).
- [ ] Mesmo roteiro e mesma massa de teste usados nas três sessões.
- [ ] Pelo menos cinco tarefas executadas por participante.
- [ ] Sucesso, tempo, erros e observações registrados por tarefa.
- [ ] Formulário de feedback preenchido ao final de cada sessão.
- [ ] Lighthouse atual executado e arquivado.
- [ ] WAVE atual executado e arquivado.
- [ ] Resultados consolidados e melhorias propostas.
- [ ] Reteste das melhorias registrado quando houver correção.

## 3. Preparação da sessão

Use ambiente de teste, conta sintética e dados sem informação pessoal real. Explique que o objetivo é avaliar o sistema, não o participante. Solicite consentimento para registrar observações e, se aplicável, gravação de tela.

Dados mínimos da ficha da sessão:

| Campo | Preenchimento |
| --- | --- |
| Código | `P01`, `P02` ou `P03` |
| Perfil geral | Ex.: morador, funcionário ou administrador; sem nome/e-mail |
| Experiência | iniciante, intermediária ou avançada |
| Dispositivo/navegador | modelo ou categoria, SO e navegador |
| Data/duração | data e duração da sessão |
| Consentimento | sim/não |

## 4. Roteiro padronizado: cinco tarefas

Não conduza o participante passo a passo. Leia apenas o objetivo da tarefa e registre o que ele tenta fazer.

| ID | Tarefa | Critério de sucesso |
| --- | --- | --- |
| U01 | Entrar com uma conta de demonstração válida e identificar o perfil atual. | Login concluído e perfil reconhecido sem ajuda. |
| U02 | Localizar a área de ocorrências e abrir os detalhes de uma ocorrência própria. | Participante encontra a área e os detalhes corretos. |
| U03 | Abrir uma nova ocorrência informando tipo, título e descrição. | Formulário compreendido e validações tratadas corretamente. |
| U04 | Alterar nome ou e-mail no perfil e localizar a confirmação da operação. | Alteração concluída ou erro compreendido. |
| U05 | Alternar tema ou navegar por teclado até uma ação principal. | Ação encontrada sem depender exclusivamente de mouse ou cor. |

Registre para cada tarefa: concluída (`sim/não`), tempo aproximado, número de erros, necessidade de ajuda e observação livre.

## 5. Formulário de feedback

Aplicar ao final de cada sessão. Use escala de 1 (discordo/difícil) a 5 (concordo/fácil).

| Pergunta | Nota 1–5 |
| --- | ---: |
| Consegui concluir as tarefas sem ajuda. | |
| Os nomes dos menus e botões eram claros. | |
| As mensagens de erro e confirmação foram compreensíveis. | |
| Foi fácil saber em que tela eu estava. | |
| A interface funcionou bem no meu dispositivo. | |
| Eu usaria este sistema novamente. | |

Perguntas abertas:

1. Qual foi a tarefa mais difícil? Por quê?
2. O que você mudaria primeiro?
3. Houve algo confuso, lento ou inacessível?
4. Que recurso ajudaria você a concluir as tarefas?

## 6. Tabela de resultados

Preencha somente com dados coletados. Não invente notas ou tempos.

| Participante | Tarefa | Sucesso | Tempo | Erros | Ajuda | Observações |
| --- | --- | --- | --- | --- | --- | --- |
| P01 | U01 | | | | | |
| P01 | U02 | | | | | |
| P01 | U03 | | | | | |
| P01 | U04 | | | | | |
| P01 | U05 | | | | | |
| P02 | U01 | | | | | |
| P02 | U02 | | | | | |
| P02 | U03 | | | | | |
| P02 | U04 | | | | | |
| P02 | U05 | | | | | |
| P03 | U01 | | | | | |
| P03 | U02 | | | | | |
| P03 | U03 | | | | | |
| P03 | U04 | | | | | |
| P03 | U05 | | | | | |

Consolidação:

| Indicador | Resultado |
| --- | --- |
| Taxa de conclusão por tarefa | |
| Tarefa com mais falhas | |
| Tarefa com maior tempo | |
| Média das notas do formulário | |
| Problemas repetidos por pelo menos dois participantes | |
| Barreiras de teclado, foco, contraste ou leitura | |

## 7. Auditoria Lighthouse atual

1. Inicie o Nuxt em ambiente de teste.
2. Abra as telas que correspondem às tarefas, incluindo login, painel, ocorrências e perfil.
3. No Chrome DevTools, abra **Lighthouse**, selecione **Accessibility**, **Best Practices** e **SEO**, e execute em janela anônima quando possível.
4. Salve o relatório HTML/JSON com data, URL, commit e viewport.
5. Registre a pontuação e cada auditoria reprovada; não registre somente a nota geral.

Nome sugerido:

```text
Evidencias/08-usabilidade-acessibilidade/lighthouse-YYYY-MM-DD.json
Evidencias/08-usabilidade-acessibilidade/lighthouse-YYYY-MM-DD.html
```

O Lighthouse histórico não deve ser sobrescrito. O relatório atual deve indicar se as correções foram retestadas.

## 8. Auditoria WAVE atual

1. Publique ou disponibilize a aplicação em uma URL de teste acessível ao navegador.
2. Abra a URL no WAVE Web Accessibility Evaluation Tool.
3. Salve captura da página de resultados e exporte o relatório, se a ferramenta permitir.
4. Registre URL, data, viewport e commit.
5. Classifique erros, alertas e recursos estruturais; valide manualmente se cada alerta é aplicável.

Nome sugerido:

```text
Evidencias/08-usabilidade-acessibilidade/wave-YYYY-MM-DD.png
Evidencias/08-usabilidade-acessibilidade/wave-YYYY-MM-DD.html
```

Não envie dados reais de moradores para uma ferramenta externa. Prefira dados semeados ou uma cópia sanitizada.

## 9. Melhorias propostas

Preencha esta tabela somente depois de analisar as sessões e as auditorias.

| ID | Evidência/origem | Problema | Impacto | Melhoria proposta | Responsável | Status | Reteste |
| --- | --- | --- | --- | --- | --- | --- | --- |
| UX-01 | | | | | | | |
| UX-02 | | | | | | | |
| A11Y-01 | | | | | | | |

Priorize problemas que impedem concluir uma tarefa, impedem navegação por teclado, ocultam mensagens de erro ou dependem somente de cor.

## 10. Estrutura de evidências

```text
Evidencias/08-usabilidade-acessibilidade/
├── consentimento-modelo.md
├── roteiro-sessoes.md
├── formularios/
│   ├── P01.md
│   ├── P02.md
│   └── P03.md
├── resultados-consolidados.md
├── lighthouse-YYYY-MM-DD.json
├── lighthouse-YYYY-MM-DD.html
├── wave-YYYY-MM-DD.png
├── wave-YYYY-MM-DD.html
├── screenshots/
└── videos/
```

Os arquivos podem ficar no Drive; o relatório consolidado no repositório deve conter os links e os IDs das evidências. Não versionar nomes, e-mails, senhas, cookies ou gravações sem consentimento.

## 11. Estado atual

| Entregável | Estado |
| --- | --- |
| Lighthouse histórico | Disponível; não é auditoria atual |
| Lighthouse atual | Pendente |
| WAVE | Pendente |
| Três participantes | Pendente |
| Formulários preenchidos | Pendente |
| Resultados consolidados | Pendente |
| Melhorias derivadas dos resultados | Pendente |
| Reteste após melhorias | Pendente |
