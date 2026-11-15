import { useEffect, useRef, useState } from 'react'
import { Link, useNavigate } from 'react-router'
import { ApiError, archiveBookCopy, type BookCopyDetailData } from '../../api'
import { ConfirmationDialog } from '../../components/ConfirmationDialog'
import { CopyCoverEditor } from './CopyCoverEditor'
import { CopyNotesEditor } from './CopyNotesEditor'
import { CopyPublicationActions } from './CopyPublicationActions'
import { usePublicationLocation } from './usePublicationLocation'

type Props = { copy: BookCopyDetailData; onChanged: (changes: Partial<BookCopyDetailData>) => void }

export type RunCopyOperation = (operation: {
  execute: () => Promise<void>
  onSuccess: () => void
  successMessage?: string
  onError?: (error: unknown) => boolean
}) => Promise<void>

export function OwnerCopyActions({ copy, onChanged }: Props) {
  const navigate = useNavigate()
  const [archiveTrigger, setArchiveTrigger] = useState<HTMLElement | null>(null)
  const [pending, setPending] = useState(false)
  const [feedback, setFeedback] = useState<{ error: boolean; message: string; verify?: boolean } | null>(null)
  const busy = useRef(false)
  const active = useRef(false)
  const publication = usePublicationLocation()

  useEffect(() => {
    active.current = true
    return () => { active.current = false }
  }, [])

  const runOperation: RunCopyOperation = async ({ execute, onSuccess, successMessage, onError }) => {
    if (busy.current) return
    busy.current = true
    setPending(true)
    setFeedback(null)
    try {
      await execute()
      if (!active.current) return
      onSuccess()
      if (successMessage) setFeedback({ error: false, message: successMessage })
    } catch (error) {
      if (!active.current || onError?.(error)) return
      setFeedback({ error: true, verify: true, message: error instanceof ApiError && error.status === 409
        ? 'La copia è cambiata e questa operazione non è più consentita. Ricarica il dettaglio.'
        : error instanceof ApiError && error.status === 404
          ? 'Questa copia non è più disponibile.'
          : 'Non riusciamo a confermare l’operazione. Ricarica il dettaglio per verificarne lo stato.' })
    } finally {
      busy.current = false
      if (active.current) setPending(false)
    }
  }

  function archive() {
    setArchiveTrigger(null)
    void runOperation({
      execute: () => archiveBookCopy(copy.id),
      onSuccess: () => navigate('/libreria', { replace: true, state: { archived: true } }),
    })
  }

  function requireLocation() {
    publication.changePublication(true)
    setFeedback({ error: true, message: 'Configura una zona approssimativa prima di pubblicare questa copia.' })
  }

  const locating = publication.locationState.status === 'locating' || publication.locationState.status === 'saving'
  return <section className="copy-management" aria-label="Gestisci la tua copia">
    <h2>La tua copia</h2>
    <p>Le modifiche riguardano soltanto questa copia fisica.</p>
    {copy.activeLoanStatus && <p className="message">Questa copia è {copy.activeLoanStatus === 'LENT' ? 'in prestito' : 'impegnata'}. Puoi gestire la richiesta in <Link to="/richieste">Richieste</Link>; ritiro e rimozione saranno disponibili dopo la conclusione.</p>}
    {feedback && <p className={`message${feedback.error ? ' message--error' : ''}`} role={feedback.error ? 'alert' : 'status'}>{feedback.message}</p>}
    {feedback?.verify && <a className="secondary-button" href={`/copie/${copy.id}`}>Ricarica dettaglio</a>}
    <CopyCoverEditor copyId={copy.id} title={copy.book.title} cover={copy.cover} pending={pending} runOperation={runOperation} onSaved={(cover) => onChanged({ cover })} />
    <CopyNotesEditor
      copyId={copy.id} notes={copy.notes} pending={pending} runOperation={runOperation}
      onSaved={(notes) => onChanged({ notes })} onEdit={() => setFeedback(null)}
    />
    <CopyPublicationActions
      copyId={copy.id} isPublished={copy.isPublished} pending={pending} activeLoanStatus={copy.activeLoanStatus}
      publication={publication} runOperation={runOperation}
      onLocationRequired={requireLocation}
      onPublishedChange={(isPublished) => onChanged({ isPublished })}
    >
      <button type="button" className="secondary-button copy-remove" disabled={pending || locating || Boolean(copy.activeLoanStatus)} onClick={(event) => setArchiveTrigger(event.currentTarget)}>Rimuovi dalla libreria</button>
    </CopyPublicationActions>
    {archiveTrigger && <ConfirmationDialog
      returnFocus={archiveTrigger}
      title="Rimuovi dalla libreria"
      message="Vuoi rimuovere questa copia dalla tua libreria? Non sarà più visibile nella tua libreria personale né agli altri utenti. Non potrai ripristinarla."
      confirmLabel="Rimuovi dalla libreria"
      onConfirm={archive} onCancel={() => setArchiveTrigger(null)}
    />}
  </section>
}
