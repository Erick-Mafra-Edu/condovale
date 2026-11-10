export default defineNuxtConfig({
  modules: ['@nuxt/ui'],
  ui: {
    colors: {
      primary: 'cyan',
      neutral: 'slate',
    },
  },
  app: {
    head: {
      title: 'CondoVale',
      htmlAttrs: { lang: 'pt-BR' },
      meta: [
        {
          name: 'description',
          content: 'CondoVale simplifica a convivência, reservas e comunicação do seu condomínio.',
        },
      ],
      script: [
        {
          innerHTML: `try {
  const savedTheme = localStorage.getItem('condovale-theme')
  const theme = savedTheme === 'dark' || savedTheme === 'light'
    ? savedTheme
    : window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
  document.documentElement.dataset.theme = theme
} catch {}`,
        },
        {
          innerHTML: `!function(b,e,t,r){
  b[t]=b[t]||function(...args){(b[t].q=b[t].q||[]).push(args)};
  b[t].l=+new Date;
  var s=e.createElement('script'); s.async=1; s.crossOrigin='anonymous';
  s.src='https://betterstack.net/b.js?t='+r;
  (e.head||e.getElementsByTagName('head')[0]).appendChild(s);
}(window,document,'betterstack','1vQGdko8MvAdVrL72h5H8848');
betterstack('init', { environment: 'production' });`,
        },
      ],
    },
  },
  runtimeConfig: {
    public: {
      apiBase: process.env.NUXT_PUBLIC_API_BASE ?? '/api',
      dataSource: process.env.NUXT_PUBLIC_DATA_SOURCE ?? 'mock',
    },
  },
})
