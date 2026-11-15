import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router'
import { ApiError, publicLibrary, type PublicLibraryData } from '../../api'
import { LibraryCard } from './LibraryCard'

type LibraryState = { status: 'loading' | 'error' | 'missing' } | { status: 'ready'; library: PublicLibraryData }

export function PublicLibrary() {
  const { id = '' } = useParams()
  return <PublicLibraryPage key={id} id={id} />
}

function PublicLibraryPage({ id }: { id: string }) {
  const [state, setState] = useState<LibraryState>({ status: 'loading' })
  const [retry, setRetry] = useState(0)

  useEffect(() => {
    let active = true
    void publicLibrary(id).then((library) => { if (active) setState({ status: 'ready', library }) })
      .catch((error) => { if (active) setState({ status: error instanceof ApiError && error.status === 404 ? 'missing' : 'error' }) })
    return () => { active = false }
  }, [id, retry])

  return (
    <section className="library-page" aria-label="Libreria pubblica">
      <nav className="copy-navigation" aria-label="Navigazione della libreria">
        <Link to="/cerca">Discovery</Link>
      </nav>
      {state.status === 'loading' && <p role="status">Caricamento della libreria…</p>}
      {state.status === 'missing' && <div className="library-page__message"><h1>Libreria non disponibile</h1></div>}
      {state.status === 'error' && (
        <div className="library-page__message" role="alert">
          <h1>Non riusciamo a caricare la libreria.</h1>
          <button type="button" className="secondary-button" onClick={() => { setState({ status: 'loading' }); setRetry((value) => value + 1) }}>Riprova</button>
        </div>
      )}
      {state.status === 'ready' && (
        <>
          <div className="library-page__heading">
            <div>
              <p className="eyebrow">Libreria pubblica</p>
              <h1>La libreria di {state.library.owner.displayName}</h1>
              <p>{state.library.sharedCount} {state.library.sharedCount === 1 ? 'copia condivisa' : 'copie condivise'}</p>
            </div>
          </div>
          {state.library.bookCopies.length === 0 ? (
            <p className="library-page__message">Non ci sono copie condivise in questa libreria.</p>
          ) : (
            <div className="personal-book-grid">
              {state.library.bookCopies.map((copy) => (
                <LibraryCard key={copy.id} copy={copy}>
                  <span>{copy.activeLoanStatus === 'LENT' ? 'In prestito' : copy.activeLoanStatus ? 'Impegnato' : copy.availabilityStatus === 'AVAILABLE' ? 'Disponibile per il prestito' : 'Momentaneamente non disponibile'}</span>
                </LibraryCard>
              ))}
            </div>
          )}
        </>
      )}
    </section>
  )
}
