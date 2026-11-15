import { useState, type FormEvent } from 'react'
import { ApiError, login, register, type CurrentUser } from '../../api'
import { Brand } from '../../components/Brand'
import { authErrorMessage } from './errors'

type AuthScreenProps = {
  onAuthenticated: (user: CurrentUser) => void
}

export function AuthScreen({ onAuthenticated }: AuthScreenProps) {
  const [mode, setMode] = useState<'login' | 'register'>('login')
  const [submitting, setSubmitting] = useState(false)
  const [formError, setFormError] = useState<string | null>(null)
  const [nameError, setNameError] = useState<string | null>(null)

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setSubmitting(true)
    setFormError(null)
    setNameError(null)

    const form = event.currentTarget
    const data = new FormData(form)
    const email = String(data.get('email') ?? '')
    const password = String(data.get('password') ?? '')

    try {
      const user = mode === 'register'
        ? await register(email, password, String(data.get('displayName') ?? ''))
        : await login(email, password)

      onAuthenticated(user)
      form.reset()
    } catch (error) {
      if (mode === 'register' && error instanceof ApiError && error.fields?.displayName) {
        setNameError(error.fields.displayName.join(' '))
        form.querySelector<HTMLInputElement>('[name="displayName"]')?.focus()
      } else {
        setFormError(authErrorMessage(error))
      }
    } finally {
      setSubmitting(false)
    }
  }

  function selectMode(nextMode: 'login' | 'register') {
    setMode(nextMode)
    setFormError(null)
    setNameError(null)
  }

  return (
    <main className="auth-page">
      <section className="auth-introduction" aria-labelledby="auth-heading">
        <Brand />
        <p className="auth-introduction__eyebrow">La tua biblioteca di quartiere</p>
        <h1 id="auth-heading">I libri più vicini possono portarti lontano.</h1>
        <p>
          Entra nella comunità e scopri le copie condivise dalle persone intorno a te.
        </p>
      </section>

      <section className="auth-card" aria-live="polite">
        <div className="auth-tabs" aria-label="Scegli operazione">
          <button
            className={mode === 'login' ? 'is-active' : ''}
            type="button"
            aria-pressed={mode === 'login'}
            onClick={() => selectMode('login')}
          >
            Accedi
          </button>
          <button
            className={mode === 'register' ? 'is-active' : ''}
            type="button"
            aria-pressed={mode === 'register'}
            onClick={() => selectMode('register')}
          >
            Registrati
          </button>
        </div>

        <h2>{mode === 'login' ? 'Bentornato' : 'Crea il tuo account'}</h2>
        <p className="auth-card__intro">
          {mode === 'login'
            ? 'Accedi per continuare a esplorare BiblioVicino.'
            : 'Bastano pochi dati per iniziare.'}
        </p>

        <form className="auth-form" onSubmit={(event) => void handleSubmit(event)}>
          {mode === 'register' && (
            <div className="auth-form__field">
              <label htmlFor="registration-display-name">Nome visualizzato</label>
              <input id="registration-display-name" name="displayName" required autoComplete="name" aria-invalid={Boolean(nameError)} aria-describedby={nameError ? 'registration-name-help registration-name-error' : 'registration-name-help'} onChange={() => setNameError(null)} />
              <small id="registration-name-help">Da 1 a 80 caratteri. Gli spazi iniziali e finali vengono rimossi.</small>
              {nameError && <span id="registration-name-error" className="message message--error" role="alert">{nameError}</span>}
            </div>
          )}
          <label>
            Email
            <input name="email" required type="email" autoComplete="email" />
          </label>
          <label>
            Password
            <input
              name="password"
              required
              type="password"
              minLength={12}
              maxLength={64}
              autoComplete={mode === 'login' ? 'current-password' : 'new-password'}
            />
          </label>
          <button className="primary-button auth-form__submit" type="submit" disabled={submitting}>
            {submitting ? 'Invio…' : mode === 'login' ? 'Accedi' : 'Crea account'}
          </button>
        </form>

        {formError && (
          <p className="message message--error" role="alert">
            {formError}
          </p>
        )}
      </section>
    </main>
  )
}
