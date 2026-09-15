# CondoVale — camada de dados

As páginas e componentes usam apenas composables, por exemplo `useReservations()`. Eles não acessam `$fetch` nem mocks diretamente.

## Alternar a fonte de dados

Copie `.env.example` para `.env` e defina uma única variável:

```env
NUXT_PUBLIC_DATA_SOURCE=mock # desenvolvimento local
# NUXT_PUBLIC_DATA_SOURCE=api # integração Laravel
```

Com `api`, configure também `NUXT_PUBLIC_API_BASE`. Ambos os repositórios devolvem `{ data, message, meta? }`, portanto serviços e telas não precisam mudar.

## Fluxo

`Página → composable → service → repository (mock ou api)`

Reservations, Occurrences, Notices, Users e Units possuem contrato, repositório mock, repositório API, service e composable.

Consulte [a documentação da arquitetura Mock → API](docs/mock-api-architecture.md) para decisões, operações, limitações provisórias e validação.

## Typecheck

```bash
npm install
npm run typecheck
```

O projeto inclui TypeScript, vue-tsc e referências às configurações geradas pelo Nuxt.
