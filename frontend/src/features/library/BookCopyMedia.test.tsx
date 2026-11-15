import { act, cleanup, render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes } from 'react-router'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { bookCopyDetail, createBookCopy, getDiscoveryLocation, removeBookCopyCover, searchBooks, searchExternalBooks, uploadBookCopyCover, type BookCopyDetailData } from '../../api'
import { AddBook } from './AddBook'
import { BookCopyDetail } from './BookCopyDetail'

vi.mock('../../api', async (original) => ({
  ...await original<typeof import('../../api')>(),
  searchBooks: vi.fn(), searchExternalBooks: vi.fn(), createBookCopy: vi.fn(), uploadBookCopyCover: vi.fn(), removeBookCopyCover: vi.fn(), bookCopyDetail: vi.fn(), getDiscoveryLocation: vi.fn(),
}))
const book = { id: 'book', title: 'Dune', authors: ['Frank Herbert'], publicationYear: 1965, isbn: '9780441172719' }
const copy: BookCopyDetailData = { id: 'copy', book: { ...book, categories: ['Narrativa'] }, notes: null, isOwner: true, isPublished: false, availabilityStatus: 'AVAILABLE', owner: { id: 'owner', displayName: 'Ada' }, approximateDistance: null, cover: null }
const file = () => new File(['image'], 'cover.png', { type: 'image/png' })
function deferred<T>() { let resolve!: (value: T) => void; const promise = new Promise<T>((done) => { resolve = done }); return { promise, resolve } }
function mount(path = '/libreria/aggiungi') {
  return render(<MemoryRouter initialEntries={[path]}><Routes>
    <Route path="/libreria/aggiungi" element={<AddBook />} />
    <Route path="/copie/:id" element={<BookCopyDetail />} />
    <Route path="/libreria" element={<h1>La mia libreria</h1>} />
  </Routes></MemoryRouter>)
}
async function select(user: ReturnType<typeof userEvent.setup>) {
  await user.type(screen.getByLabelText('Cerca per titolo, autore o ISBN'), 'Dune')
  await user.click(screen.getByRole('button', { name: 'Cerca' }))
  await user.click(await screen.findByRole('button', { name: 'Seleziona' }))
}
beforeEach(() => {
  vi.resetAllMocks()
  URL.createObjectURL = vi.fn(() => 'blob:local-cover')
  URL.revokeObjectURL = vi.fn()
  HTMLDialogElement.prototype.showModal = vi.fn(function (this: HTMLDialogElement) { this.open = true })
  HTMLDialogElement.prototype.close = vi.fn(function (this: HTMLDialogElement) { this.open = false })
  vi.mocked(searchBooks).mockResolvedValue({ results: [book], hasMore: false, mode: 'text', canonicalIsbn: null })
  vi.mocked(searchExternalBooks).mockResolvedValue({ mode: 'text', status: 'empty', works: [], edition: null, localBook: null, hasMore: false })
  vi.mocked(createBookCopy).mockResolvedValue({ id: 'copy', bookId: 'book', isPublished: false })
  vi.mocked(uploadBookCopyCover).mockResolvedValue(undefined)
  vi.mocked(removeBookCopyCover).mockResolvedValue(undefined)
  vi.mocked(bookCopyDetail).mockResolvedValue(copy)
  vi.mocked(getDiscoveryLocation).mockResolvedValue(null)
})
afterEach(() => { cleanup(); vi.restoreAllMocks() })

describe('Copertina durante Add Book', () => {
  it('uploads the cover after creating one copy', async () => {
    const user = userEvent.setup()
    const creation = deferred<{ id: string; bookId: string; isPublished: boolean }>()
    const upload = deferred<void>()
    vi.mocked(createBookCopy).mockReturnValueOnce(creation.promise)
    vi.mocked(uploadBookCopyCover).mockReturnValueOnce(upload.promise)
    mount(); await select(user)
    const image = file()
    await user.upload(screen.getByLabelText('Carica una copertina'), image)
    expect(screen.getByRole('img', { name: 'Copertina di Dune' }).getAttribute('src')).toBe('blob:local-cover')
    expect(uploadBookCopyCover).not.toHaveBeenCalled()
    await user.dblClick(screen.getByRole('button', { name: 'Aggiungi alla libreria' }))
    expect(uploadBookCopyCover).not.toHaveBeenCalled()
    await act(async () => creation.resolve({ id: 'copy', bookId: 'book', isPublished: false }))
    await waitFor(() => expect(uploadBookCopyCover).toHaveBeenCalledExactlyOnceWith('copy', image))
    expect(screen.getByRole('button', { name: 'Salvataggio copertina…' }).matches(':disabled')).toBe(true)
    expect(createBookCopy).toHaveBeenCalledTimes(1)
    await act(async () => upload.resolve())
    await screen.findByRole('heading', { name: 'La mia libreria' })
    expect(URL.revokeObjectURL).toHaveBeenCalledWith('blob:local-cover')
  })
})
