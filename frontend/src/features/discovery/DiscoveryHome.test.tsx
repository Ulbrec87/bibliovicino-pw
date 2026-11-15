import { cleanup, render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router'
import { afterEach, beforeEach, expect, it, vi } from 'vitest'
import { getDiscoveryLocation, searchBookCopies, setDiscoveryLocation } from '../../api'
import { DiscoveryHome } from './DiscoveryHome'
import { browserPosition } from './location'

vi.mock('../../api', async (original) => ({
  ...await original<typeof import('../../api')>(),
  getDiscoveryLocation: vi.fn(), searchBookCopies: vi.fn(), setDiscoveryLocation: vi.fn(),
}))
vi.mock('./location', async (original) => ({
  ...await original<typeof import('./location')>(), browserPosition: vi.fn(),
}))
vi.mock('./DiscoveryMap', () => ({ DiscoveryMap: () => null }))

beforeEach(() => {
  vi.resetAllMocks()
  vi.stubGlobal('navigator', Object.assign(Object.create(window.navigator), { geolocation: { getCurrentPosition: vi.fn() } }))
  vi.mocked(getDiscoveryLocation).mockResolvedValue(null)
  vi.mocked(browserPosition).mockResolvedValue({ coords: { latitude: 41.9, longitude: 12.5 } } as GeolocationPosition)
  vi.mocked(setDiscoveryLocation).mockResolvedValue({ representativePoint: { latitude: 41.8, longitude: 12.4 } })
  vi.mocked(searchBookCopies).mockResolvedValue({ results: [], meta: { radiusKm: 5, sort: 'distance', returnedCount: 0 } })
})

afterEach(() => { cleanup(); vi.unstubAllGlobals() })

it('configures the zone and starts searching', async () => {
  const user = userEvent.setup()
  render(<MemoryRouter><DiscoveryHome /></MemoryRouter>)
  await user.click(await screen.findByRole('button', { name: 'Usa la mia posizione' }))

  expect(await screen.findByText('Zona approssimativa · entro 5 km')).toBeTruthy()
  expect(setDiscoveryLocation).toHaveBeenCalledWith(41.9, 12.5, expect.any(AbortSignal))
  expect(searchBookCopies).toHaveBeenCalledWith({ query: '', radiusKm: 5, sort: 'distance' })
})
