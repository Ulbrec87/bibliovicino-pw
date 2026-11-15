import { ApiError } from '../../api'

export function authErrorMessage(error: unknown): string {
  if (error instanceof ApiError) {
    if (error.code === 'invalid_csrf_token') return 'La sessione è cambiata. Riprova l’operazione.'
    if (error.code === 'invalid_credentials') return 'Credenziali non valide.'
    if (error.code === 'too_many_login_attempts') return 'Troppi tentativi. Riprova più tardi.'
    return error.message
  }

  return 'Impossibile contattare il backend.'
}
