import { useEffect, useRef, useState } from 'react'
import { Link, useParams, useLocation, useNavigate } from 'react-router'
import { ApiError, bookCopyDetail, createLoanRequest, type BookCopyDetailData } from '../../api'
import { ConfirmationDialog } from '../../components/ConfirmationDialog'
import { BookCopySummary } from './BookCopySummary'
import { OwnerCopyActions } from './OwnerCopyActions'

type DetailState = { status: 'loading' | 'error' | 'missing' } | { status: 'ready'; copy: BookCopyDetailData }

export function BookCopyDetail() {
  const { id = '' } = useParams()
  return <BookCopyDetailPage key={id} id={id} />
}

function BookCopyDetailPage({ id }: { id: string }) {
  const [state, setState] = useState<DetailState>({ status: 'loading' })
  const [retry, setRetry] = useState(0)
  const pageActive = useRef(false)
  const location = useLocation()
  const navigate = useNavigate()
  const [requestTrigger, setRequestTrigger] = useState<HTMLElement | null>(null)
  const [requestPending, setRequestPending] = useState(false)
  const [requestError, setRequestError] = useState<string | null>(null)
  const [coverUploadNotice, setCoverUploadNotice] = useState<string | undefined>(location.state?.coverUpload)

  useEffect(() => {
    pageActive.current = true
    return () => { pageActive.current = false }
  }, [])

  useEffect(() => {
    let active = true
    void bookCopyDetail(id).then((copy) => {
      if (active) setState({ status: 'ready', copy })
    }).catch((error) => {
      if (active) setState({ status: error instanceof ApiError && error.status === 404 ? 'missing' : 'error' })
    })
    return () => { active = false }
  }, [id, retry])

  async function sendRequest() {
    if (requestPending) return
    setRequestTrigger(null)
    setRequestPending(true)
    setRequestError(null)
    try {
      await createLoanRequest(id)
      if (!pageActive.current) return
      navigate('/richieste', { state: { sent: true } })
    } catch (error) {
      if (!pageActive.current) return
      setRequestError(error instanceof ApiError && error.status === 409
        ? 'Questa copia è già impegnata o non può più ricevere richieste.'
        : 'Non possiamo confermare l’invio. Verifica in Richieste prima di riprovare.')
      try {
        const copy = await bookCopyDetail(id)
        if (pageActive.current) setState({ status: 'ready', copy })
      } catch (refreshError) {
        if (pageActive.current) setState({ status: refreshError instanceof ApiError && refreshError.status === 404 ? 'missing' : 'error' })
      }
    } finally {
      if (pageActive.current) setRequestPending(false)
    }
  }

  function changed(changes: Partial<BookCopyDetailData>) {
    if ('cover' in changes) setCoverUploadNotice(undefined)
    setState((current) => current.status === 'ready' ? { status: 'ready', copy: { ...current.copy, ...changes } } : current)
  }

  return <section className="library-page copy-detail" aria-label="Dettaglio della copia">
    <nav className="copy-navigation" aria-label="Navigazione della copia">
      <Link to="/cerca">Discovery</Link><Link to="/libreria">La mia libreria</Link>
    </nav>
    {state.status === 'loading' && <div className="copy-detail__loading" role="status" aria-busy="true">Caricamento della copia…</div>}
    {state.status === 'missing' && <div className="library-page__message"><h1>Copia non disponibile</h1><p>La copia cercata non è disponibile per la consultazione.</p></div>}
    {state.status === 'error' && <div className="library-page__message" role="alert"><h1>Non riusciamo a caricare la copia.</h1><button type="button" className="secondary-button" onClick={() => { setState({ status: 'loading' }); setRetry((value) => value + 1) }}>Riprova</button></div>}
    {state.status === 'ready' && <>
      {coverUploadNotice && state.copy.cover?.source !== 'uploaded' && <p role="status" className="message">{coverUploadNotice === 'failed' ? 'Il libro è stato salvato, ma la copertina no. Puoi riprovare da Gestisci copertina.' : 'Il libro è stato salvato. Non possiamo confermare il salvataggio della copertina: controllala qui prima di riprovare.'}</p>}
      <BookCopySummary copy={state.copy} />
      {state.copy.notes && (
        <section className="copy-detail__notes" aria-labelledby="copy-notes-title">
          <h2 id="copy-notes-title">Note su questa copia</h2>
          <p>{state.copy.notes}</p>
        </section>
      )}
      {!state.copy.isOwner && state.copy.canRequestLoan && <div className="copy-actions"><button type="button" className="primary-button" disabled={requestPending} onClick={(event) => setRequestTrigger(event.currentTarget)}>Richiedi in prestito</button></div>}
      {requestError && <p role="alert" className="message message--error">{requestError} <Link to="/richieste">Vai a Richieste</Link></p>}
      {state.copy.isOwner && <OwnerCopyActions copy={state.copy} onChanged={changed} />}
      {requestTrigger && <ConfirmationDialog returnFocus={requestTrigger} title="Richiedi in prestito" message={`Vuoi inviare una richiesta di prestito per questa copia di ${state.copy.book.title}?`} confirmLabel="Invia richiesta" onConfirm={() => void sendRequest()} onCancel={() => setRequestTrigger(null)} />}
    </>}
  </section>
}
