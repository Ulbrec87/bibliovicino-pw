import { useEffect, useRef, useState, type FormEvent } from 'react'
import { ApiError, updateBookCopyNotes } from '../../api'
import type { RunCopyOperation } from './OwnerCopyActions'

type Props = {
  copyId: string
  notes: string | null
  pending: boolean
  runOperation: RunCopyOperation
  onSaved: (notes: string | null) => void
  onEdit: () => void
}

export function CopyNotesEditor({ copyId, notes, pending, runOperation, onSaved, onEdit }: Props) {
  const [editing, setEditing] = useState(false)
  const [draft, setDraft] = useState(notes ?? '')
  const [error, setError] = useState<string | null>(null)
  const editButton = useRef<HTMLButtonElement>(null)
  const noteField = useRef<HTMLTextAreaElement>(null)
  const returnToEdit = useRef(false)

  useEffect(() => {
    // Il focus viene spostato dopo il render, quando i controlli sono nuovamente abilitati.
    if (error && !pending) noteField.current?.focus()
    if (!editing && !pending && returnToEdit.current) {
      editButton.current?.focus()
      returnToEdit.current = false
    }
  }, [error, pending, editing])

  function startEditing() {
    setDraft(notes ?? '')
    setEditing(true)
    onEdit()
  }

  function cancel() {
    returnToEdit.current = true
    setEditing(false)
    setError(null)
  }

  function save(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const value = draft.trim() || null
    if ([...(value ?? '')].length > 1100) {
      setError('Le note non possono superare 1100 caratteri.')
      noteField.current?.focus()
      return
    }
    setError(null)
    void runOperation({
      execute: () => updateBookCopyNotes(copyId, value),
      onSuccess: () => {
        onSaved(value)
        returnToEdit.current = true
        setEditing(false)
      },
      successMessage: 'Note salvate.',
      onError: (error) => {
        if (!(error instanceof ApiError) || !error.fields?.notes) return false
        setError(error.fields.notes.join(' '))
        return true
      },
    })
  }

  if (!editing) {
    return <button ref={editButton} type="button" className="secondary-button" disabled={pending} onClick={startEditing}>Modifica</button>
  }

  return <form className="copy-notes-form" onSubmit={save}>
    <label htmlFor="copy-notes">Note della copia</label>
    <textarea
      ref={noteField} id="copy-notes" autoFocus rows={5} value={draft} disabled={pending}
      aria-invalid={Boolean(error)} aria-describedby={error ? 'notes-error' : 'notes-help'}
      onChange={(event) => { setDraft(event.target.value); setError(null) }}
    />
    <small id="notes-help">Facoltative, fino a 1100 caratteri.</small>
    {error && <p id="notes-error" className="message message--error" role="alert">{error}</p>}
    <div className="copy-actions">
      <button type="submit" className="primary-button" disabled={pending}>{pending ? 'Salvataggio…' : 'Salva'}</button>
      <button type="button" className="secondary-button" disabled={pending} onClick={cancel}>Annulla</button>
    </div>
  </form>
}
