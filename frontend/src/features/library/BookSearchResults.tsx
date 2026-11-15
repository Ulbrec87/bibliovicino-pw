import { useCallback, useEffect, useRef, useState } from 'react'
import {
  ApiError, lookupBookIsbn, searchBooks, searchExternalBooks,
  type BibliographicBook, type ExternalBookSearchResponse,
  type ExternalEditionSearchResult,
} from '../../api'
import { EditionCover } from './EditionCover'
import { mergeIsbnEdition, mergeSearchCards, type SearchCard } from './searchResults'

type Props = {
  query: string
  onSelectLocal: (book: BibliographicBook, cover?: ExternalEditionSearchResult | null) => void
  onSelectExternal: (edition: ExternalEditionSearchResult) => void
}
type Source = { status: 'idle' | 'loading' | 'ready' | 'error' | 'invalid'; page: number; hasMore: boolean }
type Selection = { key: string; status: 'loading' | 'error' }

export function BookSearchResults({ query, onSelectLocal, onSelectExternal }: Props) {
  const [cards, setCards] = useState<SearchCard[]>([])
  const [local, setLocal] = useState<Source>({ status: 'loading', page: 0, hasMore: false })
  const [external, setExternal] = useState<Source>({ status: 'idle', page: 0, hasMore: false })
  const [selection, setSelection] = useState<Selection | null>(null)
  const [loadingMore, setLoadingMore] = useState(false)
  const [moreError, setMoreError] = useState(false)
  const serial = useRef(0)
  const selectionSerial = useRef(0)
  const pendingSelection = useRef<string | null>(null)
  const morePending = useRef(false)

  const receiveExternal = useCallback((response: ExternalBookSearchResponse, generation: number) => {
    if (generation !== serial.current) return
    setCards((previous) => {
      const merged = mergeSearchCards(previous, response.localBook ? [response.localBook] : [], response.works)
      return mergeIsbnEdition(merged, response.edition)
    })
  }, [])

  useEffect(() => {
    const generation = ++serial.current
    async function search() {
      let skipExternal = !query
      try {
        const response = await searchBooks(query, 1)
        if (generation !== serial.current) return
        setCards((previous) => mergeSearchCards(previous, response.results, []))
        setLocal({ status: 'ready', page: 1, hasMore: response.hasMore })
        skipExternal ||= response.mode === 'isbn' && response.results.length > 0
      } catch (error) {
        if (generation !== serial.current) return
        const invalid = error instanceof ApiError && error.status === 422
        setLocal({ status: invalid ? 'invalid' : 'error', page: 0, hasMore: false })
        skipExternal ||= invalid
      }
      if (skipExternal) return
      setExternal({ status: 'loading', page: 0, hasMore: false })
      try {
        const response = await searchExternalBooks(query, 1)
        if (generation !== serial.current) return
        receiveExternal(response, generation)
        const unavailable = response.status === 'unavailable'
        setExternal({ status: unavailable ? 'error' : 'ready', page: unavailable ? 0 : 1, hasMore: response.hasMore })
      } catch {
        if (generation === serial.current) setExternal({ status: 'error', page: 0, hasMore: false })
      }
    }
    void search()
    return () => {
      serial.current += 1
      selectionSerial.current += 1
    }
  }, [query, receiveExternal])

  async function loadSource(source: 'local' | 'external') {
    const generation = serial.current
    const current = source === 'local' ? local : external
    try {
      if (source === 'local') {
        const response = await searchBooks(query, current.page + 1)
        if (generation !== serial.current) return
        setCards((previous) => mergeSearchCards(previous, response.results, []))
        setLocal({ status: 'ready', page: current.page + 1, hasMore: response.hasMore })
      } else {
        const response = await searchExternalBooks(query, current.page + 1)
        if (generation !== serial.current) return
        if (response.status === 'unavailable') throw new Error('Unavailable')
        receiveExternal(response, generation)
        setExternal({ status: 'ready', page: current.page + 1, hasMore: response.hasMore })
      }
    } catch {
      if (generation === serial.current) {
        if (current.page > 0) setMoreError(true)
        if (source === 'local') setLocal({ ...current, status: 'error' })
        else setExternal({ ...current, status: 'error' })
      }
    }
  }

  async function loadMore(sources: ('local' | 'external')[]) {
    if (morePending.current) return
    const generation = serial.current
    morePending.current = true
    setLoadingMore(true)
    setMoreError(false)
    await Promise.allSettled(sources.map(loadSource))
    if (generation === serial.current) {
      setLoadingMore(false)
      morePending.current = false
    }
  }

  async function selectCard(card: SearchCard) {
    if (pendingSelection.current === card.key) return
    const generation = ++selectionSerial.current
    if (card.book) {
      onSelectLocal(card.book, card.edition)
      return
    }
    const edition = card.edition!
    if (!edition.isbn) {
      onSelectExternal(edition)
      return
    }

    pendingSelection.current = card.key
    setSelection({ key: card.key, status: 'loading' })
    try {
      // I metadati sono quelli della ricerca; prima dell'importazione verifichiamo solo l'ISBN locale.
      const { book } = await lookupBookIsbn(edition.isbn)
      if (generation !== selectionSerial.current) return
      if (book) onSelectLocal(book, edition)
      else onSelectExternal(edition)
    } catch {
      if (generation === selectionSerial.current) setSelection({ key: card.key, status: 'error' })
    } finally {
      if (generation === selectionSerial.current) pendingSelection.current = null
    }
  }

  const searching = local.status === 'loading' || external.status === 'loading'
  const showEmptyResults = local.status === 'ready'
    && (external.status === 'ready' || external.status === 'idle')
    && cards.length === 0
  const nextSources: ('local' | 'external')[] = []
  const retryingPage = (local.page > 0 && local.status === 'error') || (external.page > 0 && external.status === 'error')

  if (local.hasMore && (!retryingPage || local.status === 'error')) nextSources.push('local')
  if (external.hasMore && (!retryingPage || external.status === 'error')) nextSources.push('external')

  return (
    <section className="book-search-results" aria-label="Risultati della ricerca">
      <p className="book-search-status" role="status">
        {searching ? 'Ricerca in corso…' : 'Risultati della ricerca'}
      </p>
      {local.status === 'invalid' && (
        <p className="field-error" role="alert">Controlla l’ISBN o la ricerca inserita.</p>
      )}
      {local.status === 'error' && local.page === 0 && (
        <div role="alert">
          <p>Non riusciamo a cercare i libri su BiblioVicino.</p>
          <button className="secondary-button" type="button" disabled={loadingMore} onClick={() => void loadMore(['local'])}>
            Riprova ricerca locale
          </button>
        </div>
      )}
      {external.status === 'error' && external.page === 0 && (
        <div role="status">
          <p>Il catalogo esterno non è disponibile al momento. Puoi usare un libro locale o inserirlo manualmente.</p>
          <button className="secondary-button" type="button" disabled={loadingMore} onClick={() => void loadMore(['external'])}>
            Riprova catalogo esterno
          </button>
        </div>
      )}
      {showEmptyResults && (
        <p>Nessun libro da mostrare. Puoi provare altri risultati o inserire il libro manualmente.</p>
      )}
      <ul className="book-results unified-book-results">
        {cards.map((card) => {
          const metadata = card.book ?? card.edition!
          const authors = card.book?.authors ?? card.edition?.authors ?? []
          const loading = selection?.key === card.key && selection.status === 'loading'
          return (
            <li key={card.key} className="search-card">
              <div className="search-card-body">
                <EditionCover edition={{
                  title: metadata.title,
                  coverPreviewUrl: card.edition?.coverPreviewUrl ?? null,
                  coverLargeUrl: card.edition?.coverLargeUrl ?? null,
                }} />
                <div className="search-card-metadata">
                  <strong>{metadata.title}</strong>
                  <span>{authors.join(', ')}</span>
                  <small>{metadata.publicationYear}</small>
                  <small className="book-isbn">{metadata.isbn}</small>
                  <span className={`book-source-badge${card.book ? ' book-source-badge--local' : ''}`}>
                    {card.book ? 'Già su BiblioVicino' : 'Catalogo esterno'}
                  </span>
                </div>
                <button
                  type="button"
                  className="primary-button search-card-select"
                  disabled={loading}
                  aria-busy={loading}
                  onClick={() => void selectCard(card)}
                >
                  {loading ? 'Caricamento…' : 'Seleziona'}
                </button>
              </div>
              {selection?.key === card.key && selection.status !== 'loading' && (
                <p role="alert" className="field-error">
                  Non riusciamo a controllare l'ISBN su BiblioVicino. Puoi riprovare selezionando di nuovo il libro o inserirlo manualmente.
                </p>
              )}
            </li>
          )
        })}
      </ul>
      {nextSources.length > 0 && (
        <button className="secondary-button" type="button" disabled={loadingMore} onClick={() => void loadMore(nextSources)}>
          {loadingMore ? 'Caricamento…' : 'Mostra altri risultati'}
        </button>
      )}
      {moreError && (
        <p role="alert">Non riusciamo a caricare altri risultati. Quelli già trovati restano disponibili.</p>
      )}
    </section>
  )
}
