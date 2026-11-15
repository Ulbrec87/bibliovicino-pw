import { Link } from 'react-router'
import { LocationSetup, PinIcon } from './LocationSetup'
import { useEffect, useRef, useState, type FormEvent } from 'react'
import {
  ApiError,
  getDiscoveryLocation,
  searchBookCopies,
  type DiscoveryBookCopy,
  type DiscoveryLocation,
  type DiscoverySearchRadiusKm,
  type DiscoverySearchResponse,
  type DiscoverySearchSort,
} from '../../api'
import { BookCopyCard, type BookCopyCardData } from '../../components/BookCopyCard'
import { DiscoveryMap } from './DiscoveryMap'
import { formatApproximateDistance } from './formatApproximateDistance'
import { useCurrentLocation } from './useCurrentLocation'

type ResultView = 'list' | 'map'
type DiscoveryLocationState =
  | { status: 'loading' | 'unconfigured' | 'error' }
  | { status: 'configured'; location: DiscoveryLocation }
type ResultState =
  | { status: 'idle' }
  | { status: 'loading' }
  | { status: 'success'; response: DiscoverySearchResponse }
  | { status: 'error' }
type SubmittedSearch = {
  query: string
  radiusKm: DiscoverySearchRadiusKm
  sort: DiscoverySearchSort
}

const EMPTY_RESULTS: DiscoveryBookCopy[] = []

const DEFAULT_SEARCH: SubmittedSearch = {
  query: '',
  radiusKm: 5,
  sort: 'distance',
}

function toBookCopyCardData(result: DiscoveryBookCopy): BookCopyCardData {
  return {
    id: result.bookCopyId,
    cover: result.cover,
    title: result.book.title,
    authors: result.book.authors,
    categories: result.book.categories,
    publicationYear: result.book.publicationYear,
    approximateDistance: formatApproximateDistance(result.approximateDistance),
    ownerDisplayName: result.owner.displayName,
    offerModes: result.offerModes,
  }
}

function SearchIcon() {
  return (
    <svg viewBox="0 0 24 24" aria-hidden="true">
      <circle cx="10.5" cy="10.5" r="6.5" />
      <path d="m15.5 15.5 4 4" />
    </svg>
  )
}

function ShieldIcon() {
  return (
    <svg viewBox="0 0 24 24" aria-hidden="true">
      <path d="M12 3 20 6v5c0 5.1-3.4 8.5-8 10-4.6-1.5-8-4.9-8-10V6l8-3Z" />
      <path d="m8.5 12 2.2 2.2 4.8-5" />
    </svg>
  )
}

function ListIcon() {
  return (
    <svg viewBox="0 0 24 24" aria-hidden="true">
      <path d="M9 6h11M9 12h11M9 18h11" />
      <circle cx="4.5" cy="6" r=".8" />
      <circle cx="4.5" cy="12" r=".8" />
      <circle cx="4.5" cy="18" r=".8" />
    </svg>
  )
}

function MapIcon() {
  return (
    <svg viewBox="0 0 24 24" aria-hidden="true">
      <path d="m3 5 5-2 8 3 5-2v15l-5 2-8-3-5 2V5Z" />
      <path d="M8 3v15M16 6v15" />
    </svg>
  )
}

function LocationLoading() {
  return (
    <section className="location-summary" aria-live="polite" aria-label="Caricamento zona di ricerca">
      <div className="location-summary__main">
        <PinIcon />
        <div>
          <p>Zona di ricerca</p>
          <strong>Caricamento della zona…</strong>
        </div>
      </div>
    </section>
  )
}

function LocationLoadError({ onRetry }: { onRetry: () => void }) {
  return (
    <section className="location-summary" role="alert" aria-labelledby="location-error-title">
      <div className="location-summary__main">
        <PinIcon />
        <div>
          <p>Zona di ricerca</p>
          <strong id="location-error-title">Non riusciamo a caricare la zona.</strong>
        </div>
      </div>
      <button className="secondary-button" type="button" onClick={onRetry}>
        Riprova
      </button>
    </section>
  )
}

export function DiscoveryHome() {
  const [draftQuery, setDraftQuery] = useState('')
  const [submittedSearch, setSubmittedSearch] = useState<SubmittedSearch>(DEFAULT_SEARCH)
  const [resultState, setResultState] = useState<ResultState>({ status: 'idle' })
  const [view, setView] = useState<ResultView>('list')
  const [locationState, setLocationState] = useState<DiscoveryLocationState>({ status: 'loading' })
  const currentLocation = useCurrentLocation()
  const componentActive = useRef(false)
  const locationRequestStarted = useRef(false)
  const searchRequestSequence = useRef(0)

  async function refreshDiscoveryLocation() {
    setLocationState({ status: 'loading' })

    try {
      const location = await getDiscoveryLocation()
      if (!componentActive.current) return

      setLocationState(location ? { status: 'configured', location } : { status: 'unconfigured' })
      if (location) startSearch(DEFAULT_SEARCH)
    } catch {
      if (!componentActive.current) return
      setLocationState({ status: 'error' })
    }
  }

  function startSearch(search: SubmittedSearch) {
    if (!componentActive.current) return

    const requestSequence = ++searchRequestSequence.current
    setSubmittedSearch(search)
    setResultState({ status: 'loading' })

    void searchBookCopies(search)
      .then((response) => {
        if (!componentActive.current || requestSequence !== searchRequestSequence.current) return
        setResultState({ status: 'success', response })
      })
      .catch((error: unknown) => {
        if (!componentActive.current || requestSequence !== searchRequestSequence.current) return

        if (error instanceof ApiError && error.status === 409 && error.code === 'discovery_location_required') {
          setResultState({ status: 'idle' })
          setLocationState({ status: 'unconfigured' })
          return
        }

        setResultState({ status: 'error' })
      })
  }

  useEffect(() => {
    componentActive.current = true

    if (!locationRequestStarted.current) {
      locationRequestStarted.current = true
      void refreshDiscoveryLocation()
    }

    return () => {
      componentActive.current = false
      searchRequestSequence.current += 1
    }
  }, [])

  function submitSearch(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (locationState.status !== 'configured') return

    const query = draftQuery.trim()
    startSearch({
      query,
      radiusKm: submittedSearch.radiusKm,
      sort: query === '' ? 'distance' : submittedSearch.query === '' ? 'relevance' : submittedSearch.sort,
    })
  }

  function changeRadius(radiusKm: DiscoverySearchRadiusKm) {
    startSearch({
      ...submittedSearch,
      radiusKm,
    })
  }

  function changeSort(sort: DiscoverySearchSort) {
    startSearch({
      ...submittedSearch,
      sort,
    })
  }

  function retrySearch() {
    startSearch(submittedSearch)
  }

  async function handleUseCurrentLocation() {
    const location = await currentLocation.configure()
    if (!location || !componentActive.current) return
    setLocationState({ status: 'configured', location })
    startSearch(DEFAULT_SEARCH)
  }

  const draftMatchesSubmittedSearch = draftQuery.trim() === submittedSearch.query
  const searchPending = resultState.status === 'loading'
  const resultHeading = submittedSearch.query === '' ? 'Libri vicino a te' : `Risultati per “${submittedSearch.query}”`
  const results = resultState.status === 'success' ? resultState.response.results : EMPTY_RESULTS

  return (
    <div className="discovery-home">
      <section className="discovery-hero" aria-labelledby="discovery-title">
        <p className="section-kicker">Libri condivisi, storie vicine</p>
        <h1 id="discovery-title">Cosa vuoi leggere?</h1>

        <form className="search-form" role="search" onSubmit={submitSearch}>
          <div className="search-field">
            <label className="visually-hidden" htmlFor="discovery-query">
              Cerca per titolo o autore
            </label>
            <SearchIcon />
            <input
              id="discovery-query"
              type="search"
              value={draftQuery}
              maxLength={255}
              placeholder="Cerca per titolo o autore"
              onChange={(event) => setDraftQuery(event.target.value)}
            />
          </div>
          <button
            className="primary-button search-form__submit"
            type="submit"
            disabled={locationState.status !== 'configured' || (searchPending && draftMatchesSubmittedSearch)}
          >
            Cerca
          </button>
        </form>
      </section>

      <p className="visually-hidden" role="status">
        {resultState.status === 'loading' ? 'Caricamento dei libri vicini…'
          : resultState.status === 'success' ? (results.length === 0
            ? 'Nessun libro trovato.'
            : `${results.length} ${results.length === 1 ? 'libro trovato' : 'libri trovati'}.`)
            : ''}
      </p>

      {locationState.status === 'loading' && <LocationLoading />}
      {locationState.status === 'error' && <LocationLoadError onRetry={() => void refreshDiscoveryLocation()} />}
      {locationState.status === 'unconfigured' && (
        <LocationSetup
          useCurrentLocationState={currentLocation.state}
          onUseCurrentLocation={() => void handleUseCurrentLocation()}
        />
      )}
      {locationState.status === 'configured' && (
        <>
          <section className="location-summary" aria-labelledby="location-summary-title">
            <div className="location-summary__main">
              <PinIcon />
              <div>
                <p id="location-summary-title">Zona di ricerca</p>
                <strong>Zona approssimativa · entro {submittedSearch.radiusKm} km</strong>
              </div>
            </div>
            <Link className="secondary-button" to="/profilo">Modifica zona</Link>
            <p className="location-summary__privacy">
              <ShieldIcon />
              <span>Zona approssimativa: la posizione esatta non viene mostrata.</span>
            </p>
          </section>

          <div className="discovery-controls">
            <label>
              <span>Raggio</span>
              <select
                value={submittedSearch.radiusKm}
                onChange={(event) => changeRadius(Number(event.target.value) as DiscoverySearchRadiusKm)}
              >
                <option value={1}>1 km</option>
                <option value={2}>2 km</option>
                <option value={5}>5 km</option>
                <option value={10}>10 km</option>
              </select>
            </label>
            {submittedSearch.query !== '' && (
              <label>
                <span>Ordina per</span>
                <select
                  value={submittedSearch.sort}
                  onChange={(event) => changeSort(event.target.value as DiscoverySearchSort)}
                >
                  <option value="relevance">Rilevanza</option>
                  <option value="distance">Distanza</option>
                </select>
              </label>
            )}
          </div>

          <div className="view-switcher" aria-label="Visualizzazione risultati">
            <button
              className={view === 'list' ? 'is-active' : ''}
              type="button"
              aria-pressed={view === 'list'}
              onClick={() => setView('list')}
            >
              <ListIcon />
              Lista
            </button>
            <button
              className={view === 'map' ? 'is-active' : ''}
              type="button"
              aria-pressed={view === 'map'}
              onClick={() => setView('map')}
            >
              <MapIcon />
              Mappa
            </button>
          </div>

          <p className="visually-hidden" aria-live="polite">
            {view === 'list' ? 'Vista lista selezionata' : 'Vista mappa selezionata'}
          </p>
          <div className="result-presentation">
            {resultState.status === 'idle' && null}
            {resultState.status === 'loading' && (
              <section className="result-state">
                <p>Caricamento dei libri vicini…</p>
              </section>
            )}
            {resultState.status === 'error' && (
              <section className="result-state result-state--error" role="alert">
                <h2>Non riusciamo a caricare i libri.</h2>
                <p>Riprova mantenendo la ricerca e il raggio selezionati.</p>
                <button className="secondary-button" type="button" onClick={retrySearch}>
                  Riprova
                </button>
              </section>
            )}
            {resultState.status === 'success' && results.length === 0 && (
              <section className="result-state" aria-labelledby="empty-results-title">
                <h2 id="empty-results-title">
                  {submittedSearch.query === ''
                    ? 'Nessun libro disponibile nelle vicinanze.'
                    : 'Nessun libro corrisponde alla tua ricerca.'}
                </h2>
                <p>Puoi provare con un altro titolo, autore o raggio.</p>
              </section>
            )}
            {resultState.status === 'success' && results.length > 0 && view === 'list' && (
              <section className="discovery-results" aria-labelledby="nearby-books-title">
                <div className="section-heading">
                  <div>
                    <p className="section-kicker">Nella zona selezionata</p>
                    <h2 id="nearby-books-title">{resultHeading}</h2>
                  </div>
                </div>

                <ul className="book-copy-grid">
                  {results.map((copy) => {
                    const card = toBookCopyCardData(copy)

                    return (
                      <li key={card.id}>
                        <BookCopyCard copy={card} />
                      </li>
                    )
                  })}
                </ul>
              </section>
            )}
            {view === 'map' && (
              <DiscoveryMap
                origin={locationState.location.representativePoint}
                radiusKm={submittedSearch.radiusKm}
                results={results}
              />
            )}
          </div>
        </>
      )}
    </div>
  )
}
