export default defineNuxtConfig({
  modules: ['@nuxt/ui'],
  app: {
    head: {
      title: 'CondoVale',
      htmlAttrs: { lang: 'pt-BR' },
    },
  },
  runtimeConfig: {
    public: {
      apiBase: process.env.NUXT_PUBLIC_API_BASE ?? '/api',
      dataSource: process.env.NUXT_PUBLIC_DATA_SOURCE ?? 'mock',
    },
  },
})
