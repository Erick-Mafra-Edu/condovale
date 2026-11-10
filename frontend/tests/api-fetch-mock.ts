import { vi } from 'vitest'

export function stubApiFetch(fetch = vi.fn()) {
  vi.stubGlobal('$fetch', {
    raw: vi.fn(async (...args: unknown[]) => ({
      _data: await fetch(...args),
      headers: new Headers({ 'content-type': 'application/json' }),
    })),
  })

  return fetch
}
