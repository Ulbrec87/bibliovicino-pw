import { Link } from 'react-router'
import type { CurrentUser } from '../../api'
import { AdminDashboard } from './AdminDashboard'

export function AdminRoute({ user }: { user: CurrentUser }) {
  if (!user.roles.includes('ROLE_ADMIN')) {
    return (
      <section className="library-page admin-access-denied" aria-labelledby="admin-denied-title">
        <p className="eyebrow">Amministrazione</p>
        <h1 id="admin-denied-title">Accesso riservato agli amministratori</h1>
        <Link className="secondary-button" to="/cerca">Torna alla ricerca</Link>
      </section>
    )
  }

  return <AdminDashboard />
}