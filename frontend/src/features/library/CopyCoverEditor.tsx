import { useEffect, useRef, useState, type FormEvent } from 'react'
import { ApiError, RequestNotSentError, bookCopyDetail, removeBookCopyCover, uploadBookCopyCover, type CopyCover } from '../../api'
import { CoverPicker, type CoverDraft } from './CoverPicker'
import type { RunCopyOperation } from './OwnerCopyActions'

type Props = { copyId: string; title: string; cover?: CopyCover | null; pending: boolean; runOperation: RunCopyOperation; onSaved: (cover: CopyCover | null) => void }

export function CopyCoverEditor({ copyId, title, cover, pending, runOperation, onSaved }: Props) {
  const [editing, setEditing] = useState(false)
  const [draft, setDraft] = useState<CoverDraft>({ source: cover ? 'keep' : 'none' })
  const [error, setError] = useState<string | null>(null)
  const trigger = useRef<HTMLButtonElement>(null)
  const returnFocus = useRef(false)
  useEffect(() => {
    if (!editing && !pending && returnFocus.current) {
      trigger.current?.focus()
      returnFocus.current = false
    }
  }, [editing, pending])
  function close() {
    setEditing(false)
    setDraft({ source: cover ? 'keep' : 'none' })
    setError(null)
    returnFocus.current = true
  }
  function save(event: FormEvent) {
    event.preventDefault()
    setError(null)
    if (draft.source === 'keep' || (draft.source === 'none' && !cover)) { close(); return }
    let saved: CopyCover | null = null
    void runOperation({
      execute: async () => {
        if (draft.source === 'uploaded') {
          await uploadBookCopyCover(copyId, draft.file)
          saved = (await bookCopyDetail(copyId)).cover ?? null
        } else {
          await removeBookCopyCover(copyId)
        }
      },
      onSuccess: () => { onSaved(saved); close() },
      successMessage: draft.source === 'none' ? 'Copertina rimossa.' : 'Copertina salvata.',
      onError: (failure) => {
        if (failure instanceof RequestNotSentError) {
          setError('Il salvataggio non è partito. Puoi riprovare.')
          return true
        }
        if (failure instanceof ApiError && ['invalid_cover_image', 'cover_too_large'].includes(failure.code)) {
          setError(failure.message)
          return true
        }
        return false
      },
    })
  }
  if (!editing) return <button ref={trigger} type="button" className="secondary-button" disabled={pending} onClick={() => { setDraft({ source: cover ? 'keep' : 'none' }); setEditing(true) }}>Gestisci copertina</button>
  return <form className="copy-cover-form" onSubmit={save}>
    <CoverPicker title={title} current={cover} draft={draft} onChange={setDraft} disabled={pending} />
    {error && <p role="alert" className="message message--error">{error}</p>}
    <div className="copy-actions">
      <button type="submit" className="primary-button" disabled={pending}>{pending ? 'Salvataggio copertina…' : 'Salva copertina'}</button>
      <button type="button" className="secondary-button" disabled={pending} onClick={close}>Annulla</button>
    </div>
  </form>
}
