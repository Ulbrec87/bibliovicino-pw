import { useEffect, useRef, useState, type FormEvent } from 'react'
import { Link, useNavigate } from 'react-router'
import {
  ApiError, RequestNotSentError, activeCategories, createBookCopy, bookCopyDetail, uploadBookCopyCover,
  type ActiveCategory, type BibliographicBook, type CreateCopyInput, type ExternalEditionSearchResult,
} from '../../api'
import { CoverPicker, type CoverDraft } from './CoverPicker'
import { BookSearchResults } from './BookSearchResults'
import { ChosenBookSummary } from './ChosenBookSummary'
import { BookMetadataFields } from './BookMetadataFields'
import { useIsbnLookup } from './useIsbnLookup'
import { usePublicationLocation } from './usePublicationLocation'
import { LocationSetup } from '../discovery/LocationSetup'

type BookMode = 'search' | 'selected' | 'new' | 'external'
type CategoryState =
  | { status: 'loading' | 'error' }
  | { status: 'ready'; categories: ActiveCategory[] }

export function AddBook() {
  const navigate = useNavigate()
  const [mode, setMode] = useState<BookMode>('search')
  const [query, setQuery] = useState('')
  const [searchRequest, setSearchRequest] = useState<{ query: string; version: number } | null>(null)
  const [importedEdition, setImportedEdition] = useState<ExternalEditionSearchResult | null>(null)
  const [selectedCover, setSelectedCover] = useState<ExternalEditionSearchResult | null>(null)
  const [selectedBook, setSelectedBook] = useState<BibliographicBook | null>(null)
  const [categoryState, setCategoryState] = useState<CategoryState>({ status: 'loading' })
  const [categoryRetry, setCategoryRetry] = useState(0)
  const [categorySelection, setCategorySelection] = useState({ ids: [] as string[], removed: false })
  const [title, setTitle] = useState('')
  const [authors, setAuthors] = useState([''])
  const [year, setYear] = useState('')
  const [isbn, setIsbn] = useState('')
  const [notes, setNotes] = useState('')
  const [coverDraft, setCoverDraft] = useState<CoverDraft>({ source: 'none' })
  const [savePhase, setSavePhase] = useState<'idle' | 'saving' | 'uploading'>('idle')
  const [uncertain, setUncertain] = useState(false)
  const [requestError, setRequestError] = useState<string | null>(null)
  const [validationAttempt, setValidationAttempt] = useState(0)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const {
    checking: isbnChecking,
    notice: isbnNotice,
    duplicateBook,
    setDuplicateBook,
    check: checkIsbn,
    invalidate: invalidateIsbn,
  } = useIsbnLookup(() => {
    setFieldErrors((previous) => ({ ...previous, 'newBook.isbn': ['Inserisci un ISBN-10 o ISBN-13 valido.'] }))
  })
  const {
    publish, zone, locationState,
    changePublication, retryZone, configureLocation, requireLocation,
  } = usePublicationLocation()
  const savingRef = useRef(false)
  const mounted = useRef(false)
  const root = useRef<HTMLElement>(null)
  const editsBookMetadata = mode === 'new' || mode === 'external'
  const saving = savePhase !== 'idle'
  const canSubmit = !duplicateBook
    && (!editsBookMetadata || categoryState.status === 'ready')
    && (!publish || zone === 'ready')

  useEffect(() => {
    mounted.current = true
    return () => {
      mounted.current = false
    }
  }, [])

  useEffect(() => {
    if (mode !== 'new' && mode !== 'external') return
    let active = true
    void activeCategories().then((categories) => {
      if (!active) return
      setCategoryState({ status: 'ready', categories })
      setCategorySelection((previous) => {
        const ids = previous.ids.filter((id) => categories.some((category) => category.id === id))
        return { ids, removed: previous.removed || ids.length !== previous.ids.length }
      })
    }).catch(() => { if (active) setCategoryState({ status: 'error' }) })
    return () => { active = false }
  }, [mode, categoryRetry])

  useEffect(() => {
    const target = mode === 'new' ? '#new-title' : mode === 'selected' || mode === 'external' ? '#chosen-book' : '#book-query'
    root.current?.querySelector<HTMLElement>(target)?.focus()
  }, [mode])

  useEffect(() => {
    if (validationAttempt > 0) {
      root.current?.querySelector<HTMLElement>('[aria-invalid="true"]')?.focus()
    }
  }, [validationAttempt])

  function changeMode(next: BookMode) {
    invalidateIsbn()
    setFieldErrors({})
    setRequestError(null)
    if (next === 'new' || next === 'external') setCategoryState({ status: 'loading' })
    setMode(next)
  }

  function selectBook(book: BibliographicBook, cover: ExternalEditionSearchResult | null = null, preserveCover = false) {
    setSelectedCover(cover)
    if (!preserveCover) setCoverDraft({ source: cover?.coverReference ? 'external' : 'none' })
    setImportedEdition(null)
    setSelectedBook(book)
    changeMode('selected')
  }

  function importEdition(edition: ExternalEditionSearchResult) {
    setImportedEdition(edition)
    setCoverDraft({ source: edition.coverReference ? 'external' : 'none' })
    setTitle(edition.title)
    setAuthors(edition.authors.length ? edition.authors : [''])
    setYear(edition.publicationYear ? String(edition.publicationYear) : '')
    setIsbn(edition.isbn ?? '')
    setCategorySelection({ ids: [], removed: false })
    changeMode(edition.correctionRequired ? 'new' : 'external')
  }

  function retryCategories() {
    setCategoryState({ status: 'loading' })
    setCategoryRetry((value) => value + 1)
  }

  function toggleCategory(id: string) {
    setCategorySelection((current) => ({
      ...current,
      ids: current.ids.includes(id)
        ? current.ids.filter((selected) => selected !== id)
        : [...current.ids, id],
    }))
  }

  function validate(): boolean {
    const errors: Record<string, string[]> = {}
    if (editsBookMetadata) {
      if (!title.trim()) errors['newBook.title'] = ['Inserisci il titolo.']
      if (authors.some((author) => !author.trim())) errors['newBook.authors'] = ['Inserisci il nome di ogni autore.']
      if (categorySelection.ids.length === 0) errors['newBook.categoryIds'] = ['Seleziona almeno una categoria.']
      if (year && (!/^\d{4}$/.test(year) || Number(year) < 1000 || Number(year) > new Date().getFullYear())) {
        errors['newBook.publicationYear'] = ['Indica un anno valido.']
      }
      if (isbn.trim() && !/^(?:[0-9]{9}[0-9Xx]|[0-9]{13})$/.test(isbn.trim().replace(/[ -]/g, ''))) {
        errors['newBook.isbn'] = ['Inserisci un ISBN-10 o ISBN-13 valido.']
      }
    }
    setFieldErrors(errors)
    if (Object.keys(errors).length > 0) setValidationAttempt((value) => value + 1)
    return Object.keys(errors).length === 0
  }

  async function save(event: FormEvent) {
    event.preventDefault()
    if (savingRef.current || uncertain || mode === 'search' || !canSubmit) return
    if (!validate()) return
    savingRef.current = true
    setSavePhase('saving')
    setRequestError(null)
    const bibliographic = mode === 'selected' && selectedBook
      ? { bookId: selectedBook.id }
      : {
          newBook: {
            title: title.trim(),
            authors: authors.map((author) => author.trim()),
            publicationYear: year ? Number(year) : null,
            categoryIds: categorySelection.ids,
            isbn: isbn.trim() || null,
          },
        }
    const external = mode === 'selected' ? selectedCover : importedEdition
    const input: CreateCopyInput = { ...bibliographic, notes: notes || null, publish,
      cover: coverDraft.source === 'external' && external?.coverReference
        ? { source: 'external', reference: external.coverReference } : undefined,
    }
    try {
      if (editsBookMetadata && isbn.trim()) {
        const result = await checkIsbn(isbn)
        if (!mounted.current || result.status === 'stale') return
        if (result.status === 'invalid') {
          setFieldErrors((previous) => ({ ...previous, 'newBook.isbn': ['Inserisci un ISBN-10 o ISBN-13 valido.'] }))
          setValidationAttempt((value) => value + 1)
          return
        }
        if (result.book) return
      }
      const created = await createBookCopy(input)
      if (!mounted.current) return
      if (coverDraft.source === 'uploaded') {
        setSavePhase('uploading')
        try {
          await uploadBookCopyCover(created.id, coverDraft.file)
        } catch (failure) {
          // La copia esiste già: usiamo il suo ID per controllare la copertina senza creare un duplicato.
          let confirmed = false
          let verified = failure instanceof RequestNotSentError || (failure instanceof ApiError && failure.status >= 400 && failure.status < 500)
          if (!verified) {
            try {
              confirmed = (await bookCopyDetail(created.id)).cover?.source === 'uploaded'
              verified = confirmed
            } catch {
              // Se non riusciamo a verificare la copertina, apriamo il dettaglio della copia per farla controllare all'utente.
            }
          }
          if (!confirmed) {
            if (mounted.current) navigate(`/copie/${created.id}`, { replace: true, state: { coverUpload: verified ? 'failed' : 'uncertain' } })
            return
          }
        }
      }
      if (mounted.current) navigate('/libreria', { state: { added: true } })
    } catch (error) {
      if (!mounted.current) return
      if (error instanceof RequestNotSentError) {
        setRequestError('Il salvataggio non è partito. I dati sono conservati: puoi riprovare.')
      } else if (!(error instanceof ApiError) || error.status >= 500 || error.status === 408) {
        // La copia potrebbe essere stata salvata anche se abbiamo ricevuto un errore.
        // Blocchiamo un nuovo invio per evitare duplicati.
        setUncertain(true)
      } else if (error.code === 'discovery_location_required') {
        requireLocation()
      } else if (error.code === 'book_not_found') {
        changeMode('search')
        setRequestError('Il libro selezionato non è più disponibile. Cercalo di nuovo.')
      } else if (error.code === 'book_already_exists' && error.book) {
        setDuplicateBook(error.book)
      } else if (error.fields) {
        setFieldErrors(error.fields)
        setValidationAttempt((value) => value + 1)
        setRequestError('Controlla i campi indicati e riprova.')
        if (error.fields['newBook.categoryIds']) {
          retryCategories()
        }
      } else {
        setRequestError(error.status === 401 ? 'La sessione è scaduta. Accedi di nuovo per salvare.' : 'Il salvataggio è stato rifiutato. Riprova.')
      }
    } finally {
      savingRef.current = false
      if (mounted.current) setSavePhase('idle')
    }
  }

  const disabled = saving || uncertain
  const categoryList = categoryState.status === 'ready' ? categoryState.categories : []
  const submitLabel = saving
    ? (savePhase === 'uploading' ? 'Salvataggio copertina…' : isbnChecking ? 'Verifica ISBN…' : 'Salvataggio…')
    : (publish ? 'Aggiungi e pubblica' : 'Aggiungi alla libreria')
  const fieldError = (name: string) => fieldErrors[name] && (
    <p id={`${name}-error`} className="field-error" role="alert">
      {fieldErrors[name][0]}
    </p>
  )
  const invalid = (name: string) => ({
    'aria-invalid': !!fieldErrors[name],
    'aria-describedby': fieldErrors[name] ? `${name}-error` : undefined,
  })

  const editionFields = <>
    {importedEdition?.correctionRequired && mode === 'new' && (
      <p role="status">
        I dati trovati sono incompleti. Verifica gli autori e completa i campi prima di aggiungere il libro.
      </p>
    )}
    <fieldset className="category-fields" tabIndex={-1} {...invalid('newBook.categoryIds')}>
      <legend className="add-book-label">Categorie *</legend>
      {categoryState.status === 'loading' && <p role="status">Caricamento categorie…</p>}
      {categoryState.status === 'error' && (
        <div role="alert">
          <p>Non riusciamo a caricare le categorie.</p>
          <button className="secondary-button" type="button" onClick={retryCategories}>Riprova</button>
        </div>
      )}
      {categoryList.map((category) => (
        <label className="category-choice" key={category.id}>
          <input
            type="checkbox"
            checked={categorySelection.ids.includes(category.id)}
            onChange={() => toggleCategory(category.id)}
          />
          {category.name}
        </label>
      ))}
    </fieldset>
    {categorySelection.removed && (
      <p role="status">
        Una categoria selezionata non è più disponibile ed è stata rimossa. Controlla la selezione.
      </p>
    )}
    {fieldError('newBook.categoryIds')}
  </>

  return (
    <section ref={root} className="add-book-page" aria-labelledby="add-book-title">
      <Link className="back-link" to="/libreria">← La mia libreria</Link>
      <p className="eyebrow">La mia libreria</p>
      <h1 id="add-book-title">Aggiungi libro</h1>
      <p className="add-book-page__intro">
        Trova il libro o inseriscilo, poi scegli se renderlo disponibile per il prestito.
      </p>
      {requestError && <p className="message message--error" role="alert">{requestError}</p>}
      {uncertain && (
        <div className="message message--error" role="alert">
          <p>
            Non possiamo confermare il salvataggio. Controlla la tua libreria prima di aggiungere nuovamente il libro.
          </p>
          <Link to="/libreria">Controlla la mia libreria</Link>
        </div>
      )}
      {mode === 'search' ? (
        <section className="add-book-panel" aria-labelledby="find-book-title">
          <h2 className="add-book-panel__title" id="find-book-title">Quale libro vuoi aggiungere?</h2>
          <form onSubmit={(event) => {
            event.preventDefault()
            setSearchRequest((previous) => ({
              query: query.trim(),
              version: (previous?.version ?? 0) + 1,
            }))
          }}>
            <label className="add-book-label" htmlFor="book-query">Cerca per titolo, autore o ISBN</label>
            <div className="add-book-search">
              <input className="add-book-input" id="book-query" value={query} onChange={(event) => setQuery(event.target.value)} maxLength={255} />
              <button className="secondary-button" type="submit">Cerca</button>
            </div>
          </form>
          {searchRequest && (
            <BookSearchResults
              key={searchRequest.version}
              query={searchRequest.query}
              onSelectLocal={selectBook}
              onSelectExternal={importEdition}
            />
          )}
          <div className="add-book-escape">
            <p>Non lo trovi?</p>
            <button className="secondary-button" type="button" onClick={() => {
              setImportedEdition(null)
              setCoverDraft({ source: 'none' })
              changeMode('new')
            }}>
              Inseriscilo
            </button>
          </div>
        </section>
      ) : (
        <form className="add-book-panel" onSubmit={(event) => void save(event)} noValidate>
          <fieldset className="add-book-fields" disabled={disabled}>
            <legend className="visually-hidden">Dati del libro</legend>
            {mode === 'selected' && selectedBook ? (
              <div>
                <ChosenBookSummary
                  title={selectedBook.title}
                  authors={selectedBook.authors}
                  publicationYear={selectedBook.publicationYear}
                  isbn={selectedBook.isbn}
                />
                <button className="secondary-button change-book-button" type="button" onClick={() => changeMode('search')}>
                  Cambia libro
                </button>
              </div>
            ) : <>
              {mode === 'external' && importedEdition ? <>
                <ChosenBookSummary title={title} authors={authors} publicationYear={year} isbn={isbn} />
                <div className="external-book-actions">
                  <button className="secondary-button" type="button" onClick={() => changeMode('new')}>Correggi dati</button>
                  <button className="secondary-button" type="button" onClick={() => changeMode('search')}>Cambia libro</button>
                </div>
                {editionFields}
              </> : <>
                <div className="add-book-panel__heading">
                  <h2 className="add-book-panel__title">Inserisci il libro</h2>
                  <button className="secondary-button" type="button" onClick={() => changeMode('search')}>Torna alla ricerca</button>
                </div>
                <BookMetadataFields
                  title={title}
                  authors={authors}
                  year={year}
                  isbn={isbn}
                  fieldErrors={fieldErrors}
                  onTitleChange={setTitle}
                  onAuthorsChange={setAuthors}
                  onYearChange={setYear}
                  onIsbnChange={(value) => {
                    invalidateIsbn()
                    setIsbn(value)
                    setImportedEdition((previous) => previous ? { ...previous, coverPreviewUrl: null, coverLargeUrl: null, coverReference: null } : null)
                    setCoverDraft((previous) => previous.source === 'uploaded' ? previous : { source: 'none' })
                    setFieldErrors((previous) => {
                      const next = { ...previous }
                      delete next['newBook.isbn']
                      return next
                    })
                  }}
                  onIsbnBlur={(event) => {
                    // Se l'utente sta premendo il pulsante di salvataggio, lasciamo che sia save a verificare l'ISBN.
                    // Avviare la verifica qui potrebbe spostare il pulsante prima che il clic sia completato.
                    if (event.relatedTarget instanceof HTMLButtonElement && event.relatedTarget.type === 'submit') return
                    void checkIsbn(isbn)
                  }}
                >
                  {editionFields}
                </BookMetadataFields>
              </>}
              {isbnChecking && <p role="status">Verifica ISBN…</p>}
              {isbnNotice && <p role="status">{isbnNotice}</p>}
              {duplicateBook && (
                <div className="message" role="alert">
                  <p>
                    Questa edizione è già presente: <strong>{duplicateBook.title}</strong> — {duplicateBook.authors.join(', ')}.
                  </p>
                  <p>{[duplicateBook.publicationYear, duplicateBook.isbn].filter(Boolean).join(' · ')}</p>
                  <button className="secondary-button" type="button" onClick={() => selectBook(duplicateBook, importedEdition?.isbn === duplicateBook.isbn ? importedEdition : null, true)}>Usa questa edizione</button>
                </div>
              )}
            </>}
            <CoverPicker title={mode === 'selected' ? selectedBook?.title ?? '' : title} external={mode === 'selected' ? selectedCover : importedEdition} draft={coverDraft} onChange={setCoverDraft} disabled={disabled} />
            {fieldError('cover')}
            <details className="copy-notes" open={fieldErrors.notes ? true : undefined}>
              <summary>Aggiungi una nota <span>(facoltativa)</span></summary>
              <label className="add-book-label" htmlFor="copy-notes">Note sulla tua copia</label>
              <textarea className="add-book-input add-book-textarea"
                id="copy-notes"
                value={notes}
                maxLength={1100}
                placeholder="Ad esempio: alcune pagine sono sottolineate."
                onChange={(event) => setNotes(event.target.value)}
                {...invalid('notes')}
              />
              {fieldError('notes')}
            </details>
            <div className="publication-choice">
              <label className="category-choice">
                <input
                  type="checkbox"
                  checked={publish}
                  onChange={(event) => changePublication(event.target.checked)}
                  aria-describedby="publication-help"
                />
                Rendi disponibile per il prestito
              </label>
              <p id="publication-help">
                {publish
                  ? 'Il libro sarà visibile agli altri utenti, nella tua zona approssimativa.'
                  : 'Il libro resterà privato, visibile solo nella tua libreria.'}
              </p>
            </div>
            {publish && zone === 'checking' && <p role="status">Verifica della zona…</p>}
            {publish && zone === 'error' && (
              <div role="alert">
                <p>Non riusciamo a verificare la tua zona. Puoi riprovare oppure salvare il libro come privato.</p>
                <button className="secondary-button" type="button" onClick={retryZone}>Riprova verifica zona</button>
              </div>
            )}
            {publish && zone === 'missing' && (
              <div>
                <p>Per pubblicare serve la tua zona approssimativa. Configurala, poi conferma l’aggiunta del libro.</p>
                <LocationSetup
                  useCurrentLocationState={locationState}
                  onUseCurrentLocation={() => void configureLocation()}
                />
                <button className="secondary-button" type="button" onClick={() => changePublication(false)}>Continua con il libro privato</button>
              </div>
            )}
            {publish && zone === 'ready' && <p className="zone-ready" role="status">Zona configurata. Puoi confermare l’aggiunta.</p>}
            <div className="add-book-actions">
              <button className="primary-button" type="submit" disabled={!canSubmit}>
                {submitLabel}
              </button>
            </div>
          </fieldset>
          {saving && <p role="status">Salvataggio in corso…</p>}
        </form>
      )}
    </section>
  )
}
