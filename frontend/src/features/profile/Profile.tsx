import { useEffect, useRef, useState, type FormEvent } from 'react'
import { Link } from 'react-router'
import { ApiError, updateProfile, type CurrentUser } from '../../api'
import { ProfileDiscoveryLocation } from './ProfileDiscoveryLocation'

type Props = {
  user: CurrentUser
  onUserUpdated: (user: CurrentUser) => void
  onSessionExpired: () => void
}

export function Profile({ user, onUserUpdated, onSessionExpired }: Props) {
  const [editing, setEditing] = useState(false)
  const [draft, setDraft] = useState(user.displayName)
  const [pending, setPending] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [notice, setNotice] = useState<string | null>(null)
  const editButton = useRef<HTMLButtonElement>(null)
  const nameField = useRef<HTMLInputElement>(null)
  const returnToEdit = useRef(false)

  useEffect(() => {
    if (error && !pending) nameField.current?.focus()
    if (!editing && !pending && returnToEdit.current) {
      editButton.current?.focus()
      returnToEdit.current = false
    }
  }, [editing, error, pending])

  function startEditing() {
    setDraft(user.displayName)
    setError(null)
    setNotice(null)
    setEditing(true)
  }

  function cancel() {
    setError(null)
    returnToEdit.current = true
    setEditing(false)
  }

  async function save(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setPending(true)
    setError(null)
    try {
      const updated = await updateProfile(draft)
      onUserUpdated(updated)
      setNotice('Nome aggiornato.')
      returnToEdit.current = true
      setEditing(false)
    } catch (failure) {
      if (failure instanceof ApiError && failure.fields?.displayName) {
        setError(failure.fields.displayName.join(' '))
      } else if (failure instanceof ApiError && failure.code === 'invalid_csrf_token') {
        setError('La sessione è cambiata. Riprova l’operazione.')
      } else if (failure instanceof ApiError && failure.status === 401) {
        onSessionExpired()
      } else {
        setError('Non è stato possibile confermare il salvataggio del nome. Riprova o ricarica la pagina.')
      }
    } finally {
      setPending(false)
    }
  }

  return (
    <div className="profile-page">
      <div className="profile-heading">
        <p className="section-kicker">Il tuo spazio personale</p>
        <h1>Profilo</h1>
        <p>Gestisci il nome con cui ti presenti e la tua zona di ricerca.</p>
      </div>

      <section className="profile-section" aria-labelledby="profile-personal-title">
        <h2 id="profile-personal-title">Informazioni personali</h2>
        <dl className="profile-details">
          <div><dt>Nome visualizzato</dt><dd>{user.displayName}</dd></div>
          <div><dt>Email dell’account</dt><dd>{user.email}</dd></div>
        </dl>
        {editing ? (
          <form className="profile-form" onSubmit={(event) => void save(event)}>
            <label htmlFor="profile-display-name">Nome visualizzato</label>
            <input
              ref={nameField}
              id="profile-display-name"
              name="displayName"
              autoComplete="name"
              autoFocus
              required
              value={draft}
              disabled={pending}
              aria-invalid={Boolean(error)}
              aria-describedby={error ? 'profile-name-help profile-name-error' : 'profile-name-help'}
              onChange={(event) => { setDraft(event.target.value); setError(null) }}
            />
            <small id="profile-name-help">Da 1 a 80 caratteri. Gli spazi iniziali e finali vengono rimossi.</small>
            {error && <p id="profile-name-error" className="message message--error" role="alert">{error}</p>}
            <div className="profile-actions">
              <button className="primary-button" type="submit" disabled={pending} aria-busy={pending}>
                {pending ? 'Salvataggio…' : 'Salva'}
              </button>
              <button className="secondary-button" type="button" disabled={pending} onClick={cancel}>Annulla</button>
            </div>
          </form>
        ) : (
          <button ref={editButton} className="secondary-button" type="button" onClick={startEditing}>Modifica</button>
        )}
        {notice && <p className="message" role="status">{notice}</p>}
      </section>

      <ProfileDiscoveryLocation />

      <section className="profile-section" aria-labelledby="profile-library-title">
        <h2 id="profile-library-title">La tua libreria</h2>
        <div className="profile-links">
          <Link to="/libreria">La mia libreria</Link>
          <Link to={`/utenti/${user.id}/libreria`}>Visualizza la mia libreria pubblica</Link>
        </div>
      </section>
    </div>
  )
}
