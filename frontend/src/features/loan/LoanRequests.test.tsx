import { cleanup, render, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router'
import { afterEach, beforeEach, expect, it, vi } from 'vitest'
import { changeLoanRequest, loanRequests, type LoanRequestItem } from '../../api'
import { LoanRequests } from './LoanRequests'

vi.mock('../../api', async (original) => ({
  ...await original<typeof import('../../api')>(),
  changeLoanRequest: vi.fn(),
  loanRequests: vi.fn(),
}))

const pending: LoanRequestItem = {
  id: 'request-1', status: 'PENDING', requestedAt: '2026-10-23', updatedAt: '2026-10-23',
  copy: { id: 'copy-1', title: 'Dune', authors: ['Frank Herbert'], cover: null, canOpenDetail: true },
  otherParticipant: { displayName: 'Ada' }, actions: ['accept', 'reject'],
}

beforeEach(() => {
  vi.resetAllMocks()
  HTMLDialogElement.prototype.showModal = vi.fn(function (this: HTMLDialogElement) { this.open = true })
  HTMLDialogElement.prototype.close = vi.fn(function (this: HTMLDialogElement) { this.open = false })
  vi.mocked(changeLoanRequest).mockResolvedValue(undefined)
})

afterEach(() => {
  cleanup()
  Reflect.deleteProperty(HTMLDialogElement.prototype, 'showModal')
  Reflect.deleteProperty(HTMLDialogElement.prototype, 'close')
})

it('confirms acceptance and explains email sharing', async () => {
  const user = userEvent.setup()
  vi.mocked(loanRequests)
    .mockResolvedValueOnce({ received: [pending], sent: [] })
    .mockResolvedValue({ received: [{ ...pending, status: 'ACCEPTED', actions: ['cancel', 'lend'] }], sent: [] })
  render(<MemoryRouter><LoanRequests /></MemoryRouter>)
  await user.click(await screen.findByRole('button', { name: 'Accetta' }))
  expect(screen.getByText(/indirizzo email/)).toBeTruthy()
  expect(changeLoanRequest).not.toHaveBeenCalled()
  await user.click(screen.getByRole('button', { name: 'Annulla' }))
  expect(changeLoanRequest).not.toHaveBeenCalled()
  await user.click(screen.getByRole('button', { name: 'Accetta' }))
  await user.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Accetta' }))
  await waitFor(() => expect(changeLoanRequest).toHaveBeenCalledExactlyOnceWith('request-1', 'accept'))
  expect(await screen.findByText('Prestito accettato')).toBeTruthy()
  expect(screen.queryByText('owner@example.com')).toBeNull()
})

it('removes the contact when the request ends', async () => {
  const accepted: LoanRequestItem = { ...pending, status: 'ACCEPTED', actions: ['cancel'], ownerEmail: 'owner@example.com' }
  vi.mocked(loanRequests).mockResolvedValueOnce({ received: [], sent: [accepted] })
    .mockResolvedValue({ received: [], sent: [{ ...accepted, status: 'CANCELLED', ownerEmail: undefined, actions: [] }] })
  render(<MemoryRouter><LoanRequests /></MemoryRouter>)
  const email = await screen.findByRole('link', { name: 'owner@example.com' })
  expect(email.getAttribute('href')).toBe('mailto:owner@example.com')
  window.dispatchEvent(new Event('focus'))
  await screen.findByText('Richiesta annullata')
  expect(screen.queryByText('owner@example.com')).toBeNull()
})
