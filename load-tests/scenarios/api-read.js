import http from 'k6/http'
import { check, fail } from 'k6'

const baseUrl = __ENV.BASE_URL || 'http://localhost:8000'
const vus = Number(__ENV.K6_VUS || 50)
const duration = __ENV.K6_DURATION || '30s'

export const options = {
  vus,
  duration,
  thresholds: {
    http_req_failed: ['rate<0.01'],
    http_req_duration: ['p(95)<800', 'p(99)<1500'],
    checks: ['rate>0.99'],
  },
}

export function handleSummary(data) {
  const metrics = data.metrics || {}
  const duration = metrics.http_req_duration?.values || {}
  const failed = metrics.http_req_failed?.values?.rate
  const checks = metrics.checks?.values?.rate
  const text = [
    `http_req_duration p95=${duration['p(95)'] ?? 'n/a'}ms p99=${duration['p(99)'] ?? 'n/a'}ms`,
    `http_req_failed rate=${failed ?? 'n/a'}`,
    `checks rate=${checks ?? 'n/a'}`,
  ].join('\n') + '\n'

  return {
    stdout: text,
    '/results/summary.json': JSON.stringify(data, null, 2),
  }
}

export function setup() {
  const response = http.post(`${baseUrl}/api/auth/login`, JSON.stringify({
    email: 'resident@gmail.com',
    password: '123456',
  }), { headers: { 'Content-Type': 'application/json', Accept: 'application/json' } })

  check(response, { 'login returned 200': (r) => r.status === 200 })
  const cookie = response.cookies.laravel_session?.[0]?.value
  if (!cookie) fail(`login did not return laravel_session: ${response.status}`)
  return cookie
}

export default function (sessionCookie) {
  const params = {
    headers: {
      Accept: 'application/json',
      Cookie: `laravel_session=${sessionCookie}`,
    },
    tags: { scenario: 'resident-read' },
  }

  const session = http.get(`${baseUrl}/api/auth/session`, params)
  check(session, { 'session returned 200': (r) => r.status === 200 })

  const occurrences = http.get(`${baseUrl}/api/occurrences`, params)
  check(occurrences, {
    'occurrences returned 200': (r) => r.status === 200,
    'occurrences returned json': (r) => r.headers['Content-Type']?.includes('application/json'),
  })
}
