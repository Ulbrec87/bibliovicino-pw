import { Link, NavLink } from 'react-router'
import type { ReactNode } from 'react'
import type { CurrentUser } from '../api'
import { Brand } from './Brand'

type AppShellProps = {
  user: CurrentUser
  logoutPending: boolean
  logoutError: string | null
  onLogout: () => void
  children: ReactNode
}

type NavigationIcon = 'search' | 'library' | 'requests' | 'profile' | 'admin'

const navigationItems: ReadonlyArray<{
  label: string
  icon: NavigationIcon
  path?: string
  adminOnly?: boolean
}> = [
  { label: 'Cerca', icon: 'search', path: '/cerca' },
  { label: 'Libreria', icon: 'library', path: '/libreria' },
  { label: 'Richieste', icon: 'requests', path: '/richieste' },
  { label: 'Profilo', icon: 'profile', path: '/profilo' },
  { label: 'Amministrazione', icon: 'admin', path: '/admin', adminOnly: true },
]

function NavigationIcon({ name }: { name: NavigationIcon }) {
  if (name === 'search') {
    return (
      <svg viewBox="0 0 24 24" aria-hidden="true">
        <circle cx="10.5" cy="10.5" r="6.5" />
        <path d="m15.5 15.5 4 4" />
      </svg>
    )
  }

  if (name === 'library') {
    return (
      <svg viewBox="0 0 24 24" aria-hidden="true">
        <path d="M4.5 5.5h4v14h-4zM10 4.5h4v15h-4zM15.8 6.2l3.7-1 2.8 13.5-3.7.8z" />
      </svg>
    )
  }

  if (name === 'admin') {
    return (
      <svg viewBox="0 0 24 24" aria-hidden="true">
        <path d="M4 20h16M6 20V8l6-4 6 4v12M9 11h1m4 0h1m-6 4h1m4 0h1" />
      </svg>
    )
  }

  if (name === 'requests') {
    return (
      <svg viewBox="0 0 24 24" aria-hidden="true">
        <path d="M4 7h13M14 4l3 3-3 3M20 17H7M10 14l-3 3 3 3" />
      </svg>
    )
  }

  return (
    <svg viewBox="0 0 24 24" aria-hidden="true">
      <circle cx="12" cy="8" r="3.5" />
      <path d="M5 20c.5-4 3-6 7-6s6.5 2 7 6" />
    </svg>
  )
}

// App gestisce la sessione e passa qui i dati dell'utente e la funzione da chiamare per il logout.
export function AppShell({
  user,
  logoutPending,
  logoutError,
  onLogout,
  children,
}: AppShellProps) {
  return (
    <div className="app-shell">
      <header className="app-header">
        <Link className="app-header__brand" to="/cerca">
          <Brand />
        </Link>

        <nav className={`primary-navigation${user.roles.includes('ROLE_ADMIN') ? ' primary-navigation--admin' : ''}`} aria-label="Navigazione principale">
          <ul>
            {navigationItems.filter((item) => !item.adminOnly || user.roles.includes('ROLE_ADMIN')).map((item) => (
              <li key={item.label}>
                {item.path ? (
                  <NavLink className={({ isActive }) => `primary-navigation__item${isActive ? ' is-current' : ''}`} to={item.path} end={item.path === '/cerca'}>
                    <NavigationIcon name={item.icon} />
                    <span>{item.label}</span>
                  </NavLink>
                ) : (
                  <button
                    className="primary-navigation__item"
                    type="button"
                    disabled
                    aria-label={`${item.label}, non disponibile in questa anteprima`}
                  >
                    <NavigationIcon name={item.icon} />
                    <span>{item.label}</span>
                  </button>
                )}
              </li>
            ))}
          </ul>
        </nav>

        <div className="session-actions">
          <span className="session-actions__user" title={user.displayName}>
            {user.displayName}
          </span>
          <button className="text-button" type="button" disabled={logoutPending} onClick={onLogout}>
            {logoutPending ? 'Uscita…' : 'Esci'}
          </button>
        </div>
      </header>

      {logoutError && (
        <p className="app-shell__error" role="alert">
          {logoutError}
        </p>
      )}

      <main id="contenuto" className="app-content">
        {children}
      </main>
    </div>
  )
}
