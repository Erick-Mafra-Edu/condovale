import http from 'k6/http'
import { check, fail } from 'k6'
import { Counter, Trend } from 'k6/metrics'

const base = __ENV.BASE_URL || 'http://api'
const kind = __ENV.K6_SCENARIO || 'smoke'
const rate = Number(__ENV.K6_RATE || 5)
const duration = __ENV.K6_DURATION || '20s'
const vus = Number(__ENV.K6_VUS || 2)
const weights = [40, 25, 15, 10, 10]
const configured = (__ENV.K6_WEIGHTS || weights.join(',')).split(',').map(Number)
if (configured.length !== 5 || configured.some(x => !Number.isInteger(x) || x < 0) || configured.reduce((a, b) => a + b, 0) !== 100) fail('K6_WEIGHTS must contain five nonnegative percentages summing to 100')
const preAllocatedVUs = Number(__ENV.K6_PREALLOCATED_VUS || 20)
const maxVUs = Number(__ENV.K6_MAX_VUS || 80)
const stages = kind === 'baseline'
  ? [5, 10, 20, 40].flatMap(target => [{ target, duration: '1s' }, { target, duration: '5m' }])
  : kind === 'short-baseline'
    ? [5, 10, 20, 40].flatMap(target => [{ target, duration: '1s' }, { target, duration: '30s' }])
  : kind === 'stress'
    ? [5, 10, 20, 40, 80, 120].flatMap(target => [{ target, duration: '1s' }, { target, duration: '2m' }])
    : kind === 'spike'
      ? [{ target: rate, duration: '2m' }, { target: rate * 3, duration: '30s' }, { target: rate, duration: '2m' }]
      : [{ target: rate, duration }]

export const options = {
  scenarios: kind === 'smoke'
    ? { smoke: { executor: 'constant-vus', vus, duration } }
    : { [kind]: { executor: 'ramping-arrival-rate', startRate: rate, timeUnit: '1s', preAllocatedVUs, maxVUs, stages } },
  thresholds: {
    http_req_failed: [{ threshold: 'rate<0.01', abortOnFail: kind === 'stress', delayAbortEval: '30s' }],
    'http_req_duration{operation:read}': [{ threshold: 'p(95)<800', abortOnFail: kind === 'stress', delayAbortEval: '30s' }],
    checks: ['rate>0.99'],
    ...(kind === 'smoke' ? {} : {
      dropped_iterations: ['count<1'],
      'operation_latency{operation:occurrence-read}': ['p(95)<800'],
      'operation_latency{operation:reservation-read}': ['p(95)<800'],
      ...(kind === 'soak' ? {} : { 'operation_latency{operation:occurrence-write}': ['p(95)<1500'] }),
    }),
  },
}

const operationLatency = new Trend('operation_latency', true)
const expectedRequests = new Counter('expected_requests')
const loginLatency = new Trend('login_latency', true)
let ready = false
let actor = 0

function request(method, path, body, operation, expected = 201) {
  const response = http.request(method, `${base}${path}`, body ? JSON.stringify(body) : null, {
    headers: { Accept: 'application/json', ...(body ? { 'Content-Type': 'application/json' } : {}) },
    tags: { operation, endpoint: path.replace(/\d+/g, ':id') },
  })
  expectedRequests.add(1)
  if (operation !== 'login') operationLatency.add(response.timings.duration, { operation, endpoint: path.replace(/\d+/g, ':id') })
  check(response, {
    [`${operation} status`]: r => r.status === expected,
    [`${operation} JSON`]: r => String(r.headers['Content-Type'] || '').includes('application/json'),
    [`${operation} content`]: r => {
      try { const body = r.json(); return body.status === true && body.data !== undefined }
      catch (_) { return false }
    },
  })
  return response
}

function login(id) {
  http.cookieJar().clear(base)
  const response = request('POST', '/api/auth/login', { email: `vu${id}@benchmark.invalid`, password: 'benchmark-only-2026' }, 'login')
  loginLatency.add(response.timings.duration)
  if (response.status !== 201) fail(`Login failed for synthetic user ${id}: ${response.status}`)
}

function ensureSession() {
  if (ready) return
  const total = Number(__ENV.BENCHMARK_USERS || 100)
  const residents = Array.from({ length: total - 2 }, (_, i) => i + 3).filter(id => id % 20 !== 0)
  if (__VU > residents.length) fail(`Profile has only ${residents.length} independent resident accounts; reduce VUs or increase profile`)
  actor = residents[__VU - 1]
  login(actor)
  ready = true
}

function read() {
  const path = ['/api/auth/session', '/api/occurrences?limit=10&page=2&sort=created_at&direction=desc&kind=maintenance', '/api/reservations/me?limit=10', '/api/notices?limit=10&filters=status:=:published', '/api/common-areas?limit=10'][__ITER % 5]
  request('GET', path, null, 'read')
}

function occurrence() {
  if (__ITER % 3 === 0 && kind !== 'soak' && kind !== 'smoke') {
    request('POST', '/api/occurrences', { title: `Benchmark VU ${__VU} iteration ${__ITER}`, description: 'Synthetic benchmark occurrence', occurrence_type_id: 1 }, 'occurrence-write')
  } else request('GET', '/api/occurrences?limit=10&page=1', null, 'occurrence-read')
}

function reservation() {
  if (kind !== 'smoke' && kind !== 'soak' && __ITER % 5 === 0) {
    const day = new Date(Date.UTC(2030, 0, 1) + (__VU * 10000 + __ITER) * 86400000).toISOString().slice(0, 10)
    const created = request('POST', '/api/reservations', { common_area_id: 1, date: day, start_time: '10:00', end_time: '12:00' }, 'reservation-write')
    if (created.status === 201) {
      const id = created.json('data.id')
      if (id) request('POST', `/api/reservations/${id}/cancel`, null, 'reservation-cancel')
    }
  } else request('GET', '/api/reservations/me?limit=10', null, 'reservation-read')
}

function authentication() {
  request('GET', '/api/auth/session', null, 'auth-read')
}

function profile() {
  if (kind !== 'smoke' && kind !== 'soak' && __ITER % 2 === 0) request('PATCH', '/api/users/me', { name: `Benchmark User ${actor}`, email: `vu${actor}@benchmark.invalid` }, 'profile-write')
  else request('GET', '/api/users/me', null, 'profile-read')
}

export default function () {
  ensureSession()
  if (kind === 'smoke') {
    read(); occurrence(); reservation(); profile()
    return
  }
  const pick = (__ITER * 37 + __VU * 13) % 100
  const cumulative = configured.map((_, index) => configured.slice(0, index + 1).reduce((a, b) => a + b, 0))
  if (pick < cumulative[0]) read()
  else if (pick < cumulative[1]) occurrence()
  else if (pick < cumulative[2]) reservation()
  else if (pick < cumulative[3]) authentication()
  else profile()
}

export function setup() {
  login(1)
  request('GET', '/api/users?limit=1', null, 'admin-read')
  login(3)
  const forbidden = http.get(`${base}/api/users?limit=1`, { headers: { Accept: 'application/json' }, tags: { operation: 'permission-probe' }, responseCallback: http.expectedStatuses(403) })
  check(forbidden, { 'resident cannot list users': r => r.status === 403 })
}

export function handleSummary(data) {
  return { '/results/k6-summary.json': JSON.stringify(data, null, 2) }
}
