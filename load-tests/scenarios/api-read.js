import http from 'k6/http'
import { check, fail } from 'k6'
import { Trend } from 'k6/metrics'

const baseUrl = __ENV.BASE_URL || 'http://localhost:8000'
const vus = Number(__ENV.K6_VUS || 50)
const duration = __ENV.K6_DURATION || '30s'

const endpointDefinitions = [
  ['session', '/api/auth/session', 'admin'],
  ['users', '/api/users', 'admin'],
  ['units', '/api/units', 'admin'],
  ['unitOccupancies', '/api/unit-occupancies', 'admin'],
  ['occurrences', '/api/occurrences', 'resident'],
  ['occurrenceTypes', '/api/occurrence-types', 'admin'],
]
const trends = Object.fromEntries(endpointDefinitions.map(([name]) => [name, new Trend(`${name}_duration`, true)]))

export const options = {
  vus,
  duration,
  thresholds: {
    http_req_failed: ['rate<0.01'],
    http_req_duration: ['p(95)<800', 'p(99)<1500'],
    checks: ['rate>0.99'],
  },
}

const number = (value) => typeof value === 'number' && Number.isFinite(value) ? value : null
const format = (value, suffix = '') => number(value) === null ? 'n/a' : `${value.toFixed(2)}${suffix}`
const hasJsonContentType = (response) => Object.entries(response.headers || {})
  .some(([name, value]) => name.toLowerCase() === 'content-type' && String(value).toLowerCase().includes('application/json'))

export function handleSummary(data) {
  const metrics = data.metrics || {}
  const requestDuration = metrics.http_req_duration?.values || {}
  const requests = metrics.http_reqs?.values || {}
  const failed = number(metrics.http_req_failed?.values?.rate)
  const checks = number(metrics.checks?.values?.rate)
  const endpoints = Object.fromEntries(endpointDefinitions.map(([name, path]) => {
    const values = metrics[`${name}_duration`]?.values || {}
    return [name, { path, avg: number(values.avg), p95: number(values['p(95)']) }]
  }))
  const markdownRows = endpointDefinitions.map(([name, path]) => {
    const values = endpoints[name]
    return `| ${path} | ${format(values.avg, ' ms')} | ${format(values.p95, ' ms')} |`
  }).join('\n')
  const maxP95 = Math.max(1, ...endpointDefinitions.map(([name]) => endpoints[name].p95 || 0))
  const chartRows = endpointDefinitions.map(([name, path], index) => {
    const values = endpoints[name]
    const y = 42 + index * 28
    const avg = Math.min(420, Math.max(1, (values.avg || 0) / maxP95 * 420))
    const p95 = Math.min(420, Math.max(1, (values.p95 || 0) / maxP95 * 420))
    return `<text x="10" y="${y}" font-size="12">${path.replace('/api/', '')}</text><rect x="150" y="${y - 11}" width="${avg}" height="9" fill="#2563eb"/><rect x="150" y="${y}" width="${p95}" height="9" fill="#f97316"/><text x="580" y="${y}" font-size="11">média ${format(values.avg, ' ms')} · p95 ${format(values.p95, ' ms')}</text>`
  }).join('')
  const summary = {
    generatedAt: new Date().toISOString(), vus, duration,
    throughput: number(requests.rate), httpRequestDuration: requestDuration,
    httpReqFailedRate: failed, checksRate: checks, endpoints,
  }
  return {
    stdout: [
      `Performance (${vus} VUs por ${duration})`,
      `- Throughput: ${format(requests.rate, ' req/s')}`,
      `- Tempo HTTP médio: ${format(requestDuration.avg, ' ms')}`,
      `- Tempo HTTP p95: ${format(requestDuration['p(95)'], ' ms')}`,
      `- Tempo HTTP p99: ${format(requestDuration['p(99)'], ' ms')}`,
      `- Taxa de erro: ${format(failed * 100, '%')}`,
      `- Checks aprovados: ${format(checks * 100, '%')}`,
    ].join('\n') + '\n',
    '/results/summary.json': JSON.stringify(summary, null, 2),
    '/results/summary.md': [
      '# Evidência de performance', '', `- Execução: ${summary.generatedAt}`,
      `- Carga: ${vus} VUs por ${duration}`,
      `- Throughput: ${format(requests.rate, ' req/s')}`,
      `- Tempo HTTP médio: ${format(requestDuration.avg, ' ms')}`,
      `- Tempo HTTP p95: ${format(requestDuration['p(95)'], ' ms')}`,
      `- Tempo HTTP p99: ${format(requestDuration['p(99)'], ' ms')}`,
      `- Taxa de erro: ${format(failed * 100, '%')}`,
      `- Checks aprovados: ${format(checks * 100, '%')}`, '',
      '| Funcionalidade | Média | p95 |', '| --- | ---: | ---: |', markdownRows, '',
      'O gráfico correspondente está em `performance.svg`.', '',
    ].join('\n'),
    '/results/performance.svg': `<svg xmlns="http://www.w3.org/2000/svg" width="760" height="230" role="img" aria-label="Tempo médio e p95 por funcionalidade"><rect width="100%" height="100%" fill="white"/><text x="10" y="20" font-size="16" font-weight="bold">Tempo de resposta (ms)</text>${chartRows}<rect x="10" y="216" width="10" height="10" fill="#2563eb"/><text x="25" y="225" font-size="12">média</text><rect x="75" y="216" width="10" height="10" fill="#f97316"/><text x="90" y="225" font-size="12">p95</text></svg>`,
  }
}

function login(email) {
  http.cookieJar().clear(baseUrl)
  const response = http.post(`${baseUrl}/api/auth/login`, JSON.stringify({ email, password: '123456' }), {
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
  })
  check(response, { [`${email} login returned 201`]: (r) => r.status === 201 })
  const cookie = Object.entries(response.cookies || {})
    .flatMap(([name, values]) => values.map(({ value }) => `${name}=${value}`))
    .join('; ')
  if (!cookie) fail(`${email} login did not return a session cookie: ${response.status}`)
  return cookie
}

export function setup() {
  return { admin: login('admin@gmail.com'), resident: login('resident@gmail.com') }
}

export default function (sessions) {
  for (const [name, path, sessionName] of endpointDefinitions) {
    const response = http.get(`${baseUrl}${path}`, {
      headers: { Accept: 'application/json', Cookie: sessions[sessionName] },
      tags: { scenario: 'normal-read', endpoint: name },
    })
    trends[name].add(response.timings.duration)
    check(response, {
      [`${name} returned 201`]: (r) => r.status === 201,
      [`${name} returned json`]: hasJsonContentType,
    })
  }
}
