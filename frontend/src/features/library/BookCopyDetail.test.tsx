import { cleanup, render, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes } from 'react-router'
import { afterEach, beforeEach, expect, it, vi } from 'vitest'
import { bookCopyDetail, createLoanRequest } from '../../api'
import { BookCopyDetail } from './BookCopyDetail'

vi.mock('../../api', async (original) => ({
  ...await original<typeof import('../../api')>(),
  bookCopyDetail: vi.fn(),
  createLoanRequest: vi.fn(),
}))

beforeEach(() => {
  vi.resetAllMocks()
  HTMLDialogElement.prototype.showModal = vi.fn(function (this: HTMLDialogElement) { this.open = true })
  HTMLDialogElement.prototype.close = vi.fn(function (this: HTMLDialogElement) { this.open = false })
})

afterEach(() => {
  cleanup()
  Reflect.deleteProperty(HTMLDialogElement.prototype, 'showModal')
  Reflect.deleteProperty(HTMLDialogElement.prototype, 'close')
})

it('confirms before sending a loan request', async () => {
  vi.mocked(bookCopyDetail).mockResolvedValue({
    id: 'copy',
    book: { id: 'book', title: 'Dune', authors: ['Frank Herbert'], categories: ['Fantascienza'], publicationYear: 1965, isbn: null },
    notes: null,
    isOwner: false,
    isPublished: true,
    availabilityStatus: 'AVAILABLE',
    owner: { id: 'owner', displayName: 'Ada' },
    approximateDistance: null,
    canRequestLoan: true,
  })
  vi.mocked(createLoanRequest).mockResolvedValue('request-1')
  const user = userEvent.setup()
  render(<MemoryRouter initialEntries={['/copie/copy']}><Routes>
    <Route path="/copie/:id" element={<BookCopyDetail />} />
    <Route path="/richieste" element={<p>Richieste inviate</p>} />
  </Routes></MemoryRouter>)

  await user.click(await screen.findByRole('button', { name: 'Richiedi in prestito' }))
  expect(createLoanRequest).not.toHaveBeenCalled()
  expect(screen.getByText(/Vuoi inviare una richiesta di prestito/)).toBeTruthy()

  await user.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Invia richiesta' }))
  await waitFor(() => expect(createLoanRequest).toHaveBeenCalledExactlyOnceWith('copy'))
  expect(await screen.findByText('Richieste inviate')).toBeTruthy()
})
