import { act, cleanup, fireEvent, render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes } from 'react-router'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { activeCategories, createBookCopy, getDiscoveryLocation, lookupBookIsbn, searchBooks, searchExternalBooks, setDiscoveryLocation } from '../../api'
import { browserPosition } from '../discovery/location'
import { AddBook } from './AddBook'

vi.mock('../../api', async (original) => ({
  ...await original<typeof import('../../api')>(),
  activeCategories: vi.fn(), createBookCopy: vi.fn(), getDiscoveryLocation: vi.fn(), searchBooks: vi.fn(), lookupBookIsbn: vi.fn(), searchExternalBooks: vi.fn(), setDiscoveryLocation: vi.fn(),
}))
vi.mock('../discovery/location', async (original) => ({
  ...await original<typeof import('../discovery/location')>(), browserPosition: vi.fn(),
}))

const book = { id: 'book-id', title: 'Dune', authors: ['Frank Herbert'], publicationYear: 1965, isbn: '9780441172719' }
const categories = [{ id: 'one', name: 'Narrativa', slug: 'narrativa' }, { id: 'two', name: 'Fantascienza', slug: 'fantascienza' }]
const location = { representativePoint: { latitude: 41.9, longitude: 12.5 } }
const position = { coords: { latitude: 41.9, longitude: 12.5 } } as GeolocationPosition

function deferred<T>() {
  let resolve!: (value: T) => void
  const promise = new Promise<T>((done) => { resolve = done })
  return { promise, resolve }
}
function mount() {
  return render(<MemoryRouter initialEntries={['/libreria/aggiungi']}><Routes><Route path="/libreria/aggiungi" element={<AddBook />} /><Route path="/libreria" element={<h1>La mia libreria</h1>} /></Routes></MemoryRouter>)
}
async function selectExisting(user: ReturnType<typeof userEvent.setup>) {
  await user.type(screen.getByLabelText('Cerca per titolo, autore o ISBN'), 'Dune')
  await user.click(screen.getByRole('button', { name: 'Cerca' }))
  await user.click(await screen.findByRole('button', { name: 'Seleziona' }))
}
async function enterNew(user: ReturnType<typeof userEvent.setup>) {
  await user.click(screen.getByRole('button', { name: 'Inseriscilo' }))
  await user.type(screen.getByLabelText('Titolo *'), 'Libro nuovo')
  await user.type(screen.getByLabelText('Autore 1'), 'Autore nuovo')
  await user.click(await screen.findByLabelText('Narrativa'))
}

beforeEach(() => {
  vi.resetAllMocks()
  vi.stubGlobal('navigator', Object.assign(Object.create(window.navigator), { geolocation: { getCurrentPosition: vi.fn() } }))
  vi.mocked(activeCategories).mockResolvedValue(categories)
  vi.mocked(searchBooks).mockResolvedValue({ results: [book], hasMore: false, mode: 'text', canonicalIsbn: null })
  vi.mocked(lookupBookIsbn).mockResolvedValue({ canonicalIsbn: book.isbn, book })
  vi.mocked(searchExternalBooks).mockResolvedValue({ mode: 'text', status: 'empty', works: [], edition: null, localBook: null, hasMore: false })
  vi.mocked(getDiscoveryLocation).mockResolvedValue(null)
  vi.mocked(createBookCopy).mockResolvedValue({ id: 'copy-id', bookId: book.id, isPublished: false })
  vi.mocked(browserPosition).mockResolvedValue(position)
  vi.mocked(setDiscoveryLocation).mockResolvedValue(location)
})
afterEach(() => { cleanup(); vi.unstubAllGlobals() })

describe('Aggiunta libro progressiva', () => {
  it('adds an existing book privately', async () => {
    const user = userEvent.setup()
    mount()
    await selectExisting(user)
    expect(screen.queryByLabelText('Titolo *')).toBeNull()
    await user.click(screen.getByRole('button', { name: 'Aggiungi alla libreria' }))
    expect(createBookCopy).toHaveBeenCalledWith({ bookId: book.id, notes: null, publish: false })
    expect(await screen.findByRole('heading', { name: 'La mia libreria' })).toBeTruthy()
  })

  it('waits for confirmation after zone setup', async () => {
    const user = userEvent.setup()
    mount()
    await selectExisting(user)
    await user.click(screen.getByLabelText('Rendi disponibile per il prestito'))
    await user.click(await screen.findByRole('button', { name: 'Usa la mia posizione' }))
    await screen.findByText('Zona configurata. Puoi confermare l’aggiunta.')
    expect(createBookCopy).not.toHaveBeenCalled()
    await user.click(screen.getByRole('button', { name: 'Aggiungi e pubblica' }))
    expect(createBookCopy).toHaveBeenCalledWith({ bookId: book.id, notes: null, publish: true })
  })

  it('prevents duplicate submissions', async () => {
    const pending = deferred<{ id: string; bookId: string; isPublished: boolean }>()
    vi.mocked(createBookCopy).mockReturnValue(pending.promise)
    const user = userEvent.setup()
    mount()
    await selectExisting(user)
    const form = screen.getByRole('button', { name: 'Aggiungi alla libreria' }).closest('form')!
    fireEvent.submit(form)
    fireEvent.submit(form)
    expect(createBookCopy).toHaveBeenCalledTimes(1)
    await act(async () => { pending.resolve({ id: 'copy', bookId: book.id, isPublished: false }) })
  })

})

describe('Smart Search Open Library', () => {
  async function search(user: ReturnType<typeof userEvent.setup>, query = 'Dune') {
    await user.type(screen.getByLabelText('Cerca per titolo, autore o ISBN'), query)
    await user.click(screen.getByRole('button', { name: 'Cerca' }))
  }

  it('allows manual entry when external search fails', async () => {
    vi.mocked(searchExternalBooks).mockRejectedValue(new TypeError('Network failed'))
    const user = userEvent.setup()
    mount()
    await search(user)
    await screen.findByText(/Il catalogo esterno non è disponibile/)
    await enterNew(user)
    await user.click(screen.getByRole('button', { name: 'Aggiungi alla libreria' }))
    expect(createBookCopy).toHaveBeenCalledTimes(1)
  })

})
