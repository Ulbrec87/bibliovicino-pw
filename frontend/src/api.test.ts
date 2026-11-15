import { afterEach, beforeEach, expect, it, vi } from 'vitest'

beforeEach(() => { vi.resetModules() })
afterEach(() => { vi.unstubAllGlobals() })

it('updates the name with session and CSRF', async () => {
  const user = { id: 'reader-1', email: 'reader@example.com', displayName: 'Élodie', roles: ['ROLE_USER'] }
  const fetch = vi.fn().mockResolvedValueOnce(Response.json({ token: 'csrf-test' })).mockResolvedValueOnce(Response.json({ user }))
  vi.stubGlobal('fetch', fetch)
  const { updateProfile } = await import('./api')
  await expect(updateProfile(' Élodie ')).resolves.toEqual(user)
  const [path, request] = fetch.mock.calls[1]
  expect(path).toBe('/api/profile')
  expect(request.method).toBe('PATCH')
  expect(JSON.parse(request.body)).toEqual({ displayName: ' Élodie ' })
  expect(request.credentials).toBe('same-origin')
  expect(request.headers.get('X-CSRF-Token')).toBe('csrf-test')
})
