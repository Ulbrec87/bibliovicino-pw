import { cleanup, render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router'
import { afterEach, expect, it, vi } from 'vitest'
import { currentUser } from './api'
import App from './App'

vi.mock('./api', async (original) => ({
  ...await original<typeof import('./api')>(),
  currentUser: vi.fn(),
}))

afterEach(() => cleanup())

it('requires login to open the profile', async () => {
  vi.mocked(currentUser).mockResolvedValue(null)
  render(<MemoryRouter initialEntries={['/profilo']}><App /></MemoryRouter>)

  expect(await screen.findByRole('heading', { name: 'Bentornato' })).toBeTruthy()
  expect(screen.queryByRole('heading', { name: 'Profilo' })).toBeNull()
})
