import { cleanup, render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, beforeEach, expect, it, vi } from 'vitest'
import { disableDiscoveryLocation, getDiscoveryLocation, setDiscoveryLocation } from '../../api'
import { browserPosition } from '../discovery/location'
import { ProfileDiscoveryLocation } from './ProfileDiscoveryLocation'

vi.mock('../../api', async (original) => ({
  ...await original<typeof import('../../api')>(),
  getDiscoveryLocation: vi.fn(), setDiscoveryLocation: vi.fn(), disableDiscoveryLocation: vi.fn(),
}))
vi.mock('../discovery/location', async (original) => ({
  ...await original<typeof import('../discovery/location')>(), browserPosition: vi.fn(),
}))

const location = { representativePoint: { latitude: 41.8, longitude: 12.4 } }

beforeEach(() => {
  vi.resetAllMocks()
  vi.stubGlobal('navigator', Object.assign(Object.create(window.navigator), { geolocation: { getCurrentPosition: vi.fn() } }))
  HTMLDialogElement.prototype.showModal = vi.fn(function (this: HTMLDialogElement) { this.open = true })
  HTMLDialogElement.prototype.close = vi.fn(function (this: HTMLDialogElement) { this.open = false })
  vi.mocked(getDiscoveryLocation).mockResolvedValue(location)
  vi.mocked(browserPosition).mockResolvedValue({ coords: { latitude: 41.9, longitude: 12.5 } } as GeolocationPosition)
  vi.mocked(setDiscoveryLocation).mockResolvedValue(location)
  vi.mocked(disableDiscoveryLocation).mockResolvedValue(undefined)
})
afterEach(() => {
  cleanup()
  vi.unstubAllGlobals()
  Reflect.deleteProperty(HTMLDialogElement.prototype, 'showModal')
  Reflect.deleteProperty(HTMLDialogElement.prototype, 'close')
})

it('confirms zone disabling and allows reactivation', async () => {
  const user = userEvent.setup()
  render(<ProfileDiscoveryLocation />)
  await user.click(await screen.findByRole('button', { name: 'Disattiva zona' }))
  await user.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Disattiva zona' }))
  expect(await screen.findByText('Zona disattivata.')).toBeTruthy()
  expect(disableDiscoveryLocation).toHaveBeenCalledOnce()
  expect(screen.queryByText('Zona attiva')).toBeNull()
  await user.click(screen.getByRole('button', { name: 'Usa la mia posizione' }))
  expect(await screen.findByText('Zona attiva')).toBeTruthy()
})
