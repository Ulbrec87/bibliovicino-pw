import { useEffect, useState } from 'react'
import { Link, useLocation } from 'react-router'
import { myBookCopies, type LibraryCopy } from '../../api'
import { LibraryCard } from './LibraryCard'

type LibraryState =
  | { status: 'loading' | 'error' }
  | { status: 'ready'; copies: LibraryCopy[] }

export function MyLibrary() {
  const location = useLocation()
  const feedback = location.state as { added?: boolean; archived?: boolean } | null
  const added = Boolean(feedback?.added)
  const [state, setState] = useState<LibraryState>({ status: 'loading' })
  const [retry, setRetry] = useState(0)

  useEffect(() => {
    let active = true
    void myBookCopies()
      .then((copies) => { if (active) setState({ status: 'ready', copies }) })
      .catch(() => { if (active) setState({ status: 'error' }) })
    return () => { active = false }
  }, [retry])

  return (
    <section className="library-page" aria-labelledby="library-title">
      <div className="library-page__heading">
        <div>
          <p className="eyebrow">La tua collezione</p>
          <h1 id="library-title">La mia libreria</h1>
        </div>
        <Link className="primary-button" to="/libreria/aggiungi">+ Aggiungi libro</Link>
      </div>
      {added && <p className="message" role="status">Libro aggiunto alla tua libreria.</p>}
      {feedback?.archived && <p className="message" role="status">Copia rimossa dalla tua libreria.</p>}
      {state.status === 'loading' && (
        <div role="status" aria-label="Caricamento della tua libreria" className="personal-book-grid">
          {[0, 1, 2].map((index) => (
            <div className="personal-book personal-book--loading" aria-hidden="true" key={index}>
              <div className="personal-book__cover library-cover" />
              <div className="personal-book__details"><span /><span /></div>
            </div>
          ))}
        </div>
      )}
      {state.status === 'error' && (
        <div className="library-page__message" role="alert">
          <p>Non siamo riusciti a caricare la tua libreria.</p>
          <button className="secondary-button" type="button" onClick={() => { setState({ status: 'loading' }); setRetry((value) => value + 1) }}>Riprova</button>
        </div>
      )}
      {state.status === 'ready' && state.copies.length === 0 && (
        <div className="library-page__message">
          <p>Non hai ancora aggiunto libri.</p>
          <Link className="primary-button" to="/libreria/aggiungi">+ Aggiungi libro</Link>
        </div>
      )}
      {state.status === 'ready' && state.copies.length > 0 && (
        <div className="personal-book-grid">
          {state.copies.map((copy) => (
            <LibraryCard key={copy.id} copy={copy}>
              <span className="personal-book__status">{copy.isPublished ? 'Pubblicato' : 'Privato'}</span>
              {copy.offerModes.map((mode) => <span key={mode}>Prestito</span>)}
              {copy.activeLoanStatus && <span>{copy.activeLoanStatus === 'LENT' ? 'In prestito' : 'Impegnato'}</span>}
            </LibraryCard>
          ))}
        </div>
      )}
    </section>
  )
}
