import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'

const appSource = readFileSync(resolve(process.cwd(), 'app/app.vue'), 'utf8')
const topbarSource = readFileSync(resolve(process.cwd(), 'app/components/AppTopbar.vue'), 'utf8')

describe('alternância de tema', () => {
  it('propaga o tema do botão até o elemento raiz e persiste a escolha', () => {
    expect(topbarSource).toMatch(/emit\('theme-change', props\.theme === 'dark' \? 'light' : 'dark'\)/)
    expect(appSource).toMatch(/:theme="theme"[\s\S]*@theme-change="setTheme"/)
    expect(appSource).toMatch(/document\.documentElement\.dataset\.theme = nextTheme/)
    expect(appSource).toMatch(/localStorage\.setItem\('condovale-theme', nextTheme\)/)
  })

  it('não deixa o tema do sistema sobrescrever a escolha explícita', () => {
    expect(appSource).not.toMatch(/@media\s*\(\s*prefers-color-scheme\s*:/)
  })
})
