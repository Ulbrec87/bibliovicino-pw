import { useCallback, useEffect, useRef, useState } from 'react'
import { Link, useLocation } from 'react-router'
import { ApiError, changeLoanRequest, loanRequests, type LoanRequestItem, type LoanRequestsData } from '../../api'
import { ConfirmationDialog } from '../../components/ConfirmationDialog'
import { BookCopyCover } from '../library/BookCopyCover'

type ViewState = { status: 'loading' | 'error' } | { status: 'ready'; data: LoanRequestsData }
type LoanAction = LoanRequestItem['actions'][number]
type Confirmation = { request: LoanRequestItem; action: LoanAction; trigger: HTMLElement }

const labels: Record<LoanRequestItem['status'], string> = {
  PENDING: 'In attesa di risposta',
  ACCEPTED: 'Prestito accettato',
  LENT: 'In prestito',
  RETURNED: 'Prestito concluso',
  REJECTED: 'Richiesta rifiutata',
  CANCELLED: 'Richiesta annullata',
}
const actionLabels: Record<LoanAction, string> = {
  accept: 'Accetta',
  reject: 'Rifiuta',
  cancel: 'Annulla accordo',
  lend: 'Ho prestato il libro',
  return: 'Libro restituito',
}

function messageFor(action: LoanAction, request: LoanRequestItem): string {
  if (action === 'accept') return `Accettando la richiesta per ${request.copy.title}, il richiedente potrà vedere il tuo indirizzo email e usarlo per organizzare il prestito. Vuoi continuare?`
  if (action === 'lend') return `Confermi di aver consegnato ${request.copy.title} al richiedente?`
  if (action === 'return') return `Confermi di aver ricevuto indietro ${request.copy.title}?`
  if (action === 'reject') return `Vuoi rifiutare la richiesta per ${request.copy.title}?`
  return `Vuoi annullare ${request.status === 'PENDING' ? 'la richiesta' : 'l’accordo'} per ${request.copy.title}?`
}

function RequestCard({ request, received, onAction, pending }: { request: LoanRequestItem; received: boolean; onAction: (request: LoanRequestItem, action: LoanAction, trigger: HTMLElement) => void; pending: boolean }) {
  return <article className="loan-card">
    <BookCopyCover title={request.copy.title} cover={request.copy.cover} className="loan-cover" thumbnailOnly />
    <div className="loan-card__body">
      <h3>{request.copy.canOpenDetail ? <Link to={`/copie/${request.copy.id}`}>{request.copy.title}</Link> : request.copy.title}</h3>
      <p>{request.copy.authors.join(', ')}</p>
      <p>Con {request.otherParticipant.displayName}</p>
      <p className="loan-card__status">{received && request.status === 'PENDING' ? 'Richiesta ricevuta' : labels[request.status]}</p>
      {request.ownerEmail && <p>Contatta il proprietario: <a href={`mailto:${request.ownerEmail}`}>{request.ownerEmail}</a></p>}
      {request.actions.length > 0 && <div className="copy-actions">
        {request.actions.map((action) => <button key={action} type="button" className={action === 'accept' || action === 'lend' || action === 'return' ? 'primary-button' : 'secondary-button'} disabled={pending} onClick={(event) => onAction(request, action, event.currentTarget)}>
          {action === 'cancel' && request.status === 'PENDING' ? 'Annulla richiesta' : actionLabels[action]}
        </button>)}
      </div>}
    </div>
  </article>
}

function RequestSection({ title, requests, onAction, pending }: { title: string; requests: LoanRequestItem[]; onAction: (request: LoanRequestItem, action: LoanAction, trigger: HTMLElement) => void; pending: boolean }) {
  const active = requests.filter((request) => ['PENDING', 'ACCEPTED', 'LENT'].includes(request.status))
  const history = requests.filter((request) => !['PENDING', 'ACCEPTED', 'LENT'].includes(request.status))
  return <section className="loan-section" aria-label={title}>
    <h2>{title}</h2>
    {active.length === 0 && <p>Nessuna richiesta attiva.</p>}
    {active.map((request) => <RequestCard key={request.id} request={request} received={title === 'Ricevute'} onAction={onAction} pending={pending} />)}
    {history.length > 0 && <details className="loan-history"><summary>Storico ({history.length})</summary>
      {history.map((request) => <RequestCard key={request.id} request={request} received={title === 'Ricevute'} onAction={onAction} pending={pending} />)}
    </details>}
  </section>
}

export function LoanRequests() {
  const location = useLocation()
  const sent = Boolean((location.state as { sent?: boolean } | null)?.sent)
  const [view, setView] = useState<ViewState>({ status: 'loading' })
  const [confirmation, setConfirmation] = useState<Confirmation | null>(null)
  const [pending, setPending] = useState(false)
  const [feedback, setFeedback] = useState<string | null>(null)
  const busy = useRef(false)
  const requestVersion = useRef(0)

  const reload = useCallback(async () => {
    const version = ++requestVersion.current
    try {
      const data = await loanRequests()
      if (version === requestVersion.current) setView({ status: 'ready', data })
    } catch {
      if (version === requestVersion.current) setView({ status: 'error' })
    }
  }, [])

  useEffect(() => {
    const versionRef = requestVersion
    const version = ++versionRef.current
    void loanRequests().then((data) => {
      if (version === versionRef.current) setView({ status: 'ready', data })
    }).catch(() => {
      if (version === versionRef.current) setView({ status: 'error' })
    })
    const onFocus = () => { setView({ status: 'loading' }); void reload() }
    window.addEventListener('focus', onFocus)
    return () => { ++versionRef.current; window.removeEventListener('focus', onFocus) }
  }, [reload])

  async function runAction(request: LoanRequestItem, action: LoanAction) {
    if (busy.current) return
    busy.current = true
    setConfirmation(null)
    setPending(true)
    setFeedback(null)
    try {
      await changeLoanRequest(request.id, action)
      setView({ status: 'loading' })
      await reload()
    } catch (error) {
      setFeedback(error instanceof ApiError && error.status === 409
        ? 'La richiesta è cambiata. Ricarica per vedere le azioni disponibili.'
        : 'Non possiamo confermare l’esito. Ricarica le richieste prima di riprovare.')
      setView({ status: 'loading' })
      await reload()
    } finally {
      busy.current = false
      setPending(false)
    }
  }

  return <section className="library-page loan-requests" aria-labelledby="loan-title">
    <div className="library-page__heading"><div><p className="eyebrow">Prestiti</p><h1 id="loan-title">Richieste</h1></div></div>
    {sent && <p role="status" className="message">Richiesta inviata.</p>}
    {feedback && <p role="alert" className="message message--error">{feedback}</p>}
    {view.status === 'loading' && <p role="status">Caricamento delle richieste…</p>}
    {view.status === 'error' && <div role="alert"><p>Non riusciamo a caricare le richieste.</p><button type="button" className="secondary-button" onClick={() => { setView({ status: 'loading' }); void reload() }}>Riprova</button></div>}
    {view.status === 'ready' && <>
      <RequestSection title="Ricevute" requests={view.data.received} onAction={(request, action, trigger) => setConfirmation({ request, action, trigger })} pending={pending} />
      <RequestSection title="Inviate" requests={view.data.sent} onAction={(request, action, trigger) => setConfirmation({ request, action, trigger })} pending={pending} />
    </>}
    {confirmation && <ConfirmationDialog title={confirmation.action === 'cancel' && confirmation.request.status === 'PENDING' ? 'Annulla richiesta' : actionLabels[confirmation.action]} message={messageFor(confirmation.action, confirmation.request)} confirmLabel={confirmation.action === 'cancel' && confirmation.request.status === 'PENDING' ? 'Annulla richiesta' : actionLabels[confirmation.action]} returnFocus={confirmation.trigger} onConfirm={() => void runAction(confirmation.request, confirmation.action)} onCancel={() => setConfirmation(null)} />}
  </section>
}
