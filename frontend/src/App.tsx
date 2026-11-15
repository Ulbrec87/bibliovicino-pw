import { Navigate, Route, Routes } from 'react-router'
import { useEffect, useState } from 'react'
import { currentUser, logout, type CurrentUser } from './api'
import { AppShell } from './components/AppShell'
import { Brand } from './components/Brand'
import { AuthScreen } from './features/auth/AuthScreen'
import { authErrorMessage } from './features/auth/errors'
import { DiscoveryHome } from './features/discovery/DiscoveryHome'
import { MyLibrary } from './features/library/MyLibrary'
import { BookCopyDetail } from './features/library/BookCopyDetail'
import { PublicLibrary } from './features/library/PublicLibrary'
import { AddBook } from './features/library/AddBook'
import { LoanRequests } from './features/loan/LoanRequests'
import { Profile } from './features/profile/Profile'
import { AdminRoute } from './features/admin/AdminRoute'

type AuthState =
  | { status: 'loading' }
  | { status: 'anonymous' }
  | { status: 'authenticated'; user: CurrentUser }
  | { status: 'error'; message: string }

function SessionStatus({ message, isError = false }: { message: string; isError?: boolean }) {
  return (
    <main className="session-status">
      <Brand />
      <p className={isError ? 'message message--error' : ''} role={isError ? 'alert' : 'status'}>
        {message}
      </p>
    </main>
  )
}

function App() {
  const [auth, setAuth] = useState<AuthState>({ status: 'loading' })
  const [logoutPending, setLogoutPending] = useState(false)
  const [logoutError, setLogoutError] = useState<string | null>(null)

  useEffect(() => {
    let active = true
    // All'avvio ripristiniamo l'utente dalla sessione sul server;
    // i dati utente restano in memoria, senza copiarli nello storage del browser.
    void currentUser()
      .then((user) => {
        if (active) setAuth(user ? { status: 'authenticated', user } : { status: 'anonymous' })
      })
      .catch((error: unknown) => {
        if (active) setAuth({ status: 'error', message: authErrorMessage(error) })
      })
    return () => { active = false }
  }, [])

  async function handleLogout() {
    setLogoutPending(true)
    setLogoutError(null)

    try {
      await logout()
      setAuth({ status: 'anonymous' })
    } catch (error) {
      setLogoutError(authErrorMessage(error))
    } finally {
      setLogoutPending(false)
    }
  }

  if (auth.status === 'loading') {
    return <SessionStatus message="Verifica della sessione…" />
  }

  if (auth.status === 'error') {
    return <SessionStatus message={auth.message} isError />
  }

  if (auth.status === 'anonymous') {
    return <AuthScreen onAuthenticated={(user) => setAuth({ status: 'authenticated', user })} />
  }

  return (
    <AppShell
      user={auth.user}
      logoutPending={logoutPending}
      logoutError={logoutError}
      onLogout={() => void handleLogout()}
    >
      <Routes>
        <Route path="/" element={<Navigate to="/cerca" replace />} />
        <Route path="/cerca" element={<DiscoveryHome />} />
        <Route path="/libreria" element={<MyLibrary />} />
        <Route path="/richieste" element={<LoanRequests />} />
        <Route path="/profilo" element={<Profile user={auth.user} onUserUpdated={(user) => {
          setAuth((current) => current.status === 'authenticated' && current.user.id === user.id
            ? { status: 'authenticated', user }
            : current)
        }} onSessionExpired={() => {
          setAuth((current) => current.status === 'authenticated' && current.user.id === auth.user.id
            ? { status: 'anonymous' }
            : current)
        }} />} />
        <Route path="/admin" element={<AdminRoute user={auth.user} />} />
        <Route path="/libreria/aggiungi" element={<AddBook />} />
        <Route path="/copie/:id" element={<BookCopyDetail />} />
        <Route path="/utenti/:id/libreria" element={<PublicLibrary />} />
        <Route path="*" element={<Navigate to="/cerca" replace />} />
      </Routes>
    </AppShell>
  )
}

export default App
