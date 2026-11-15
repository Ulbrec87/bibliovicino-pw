import { useEffect, useState } from 'react'
import { ApiError, adminStatistics, type AdminStatisticsData } from '../../api'

type ViewState =
  | { status: 'loading' }
  | { status: 'error'; message: string; expired: boolean }
  | { status: 'ready'; data: AdminStatisticsData }

const numberFormat = new Intl.NumberFormat('it-IT')
const weekFormat = new Intl.DateTimeFormat('it-IT', {
  day: 'numeric',
  month: 'short',
  year: 'numeric',
  timeZone: 'UTC',
})
const shortWeekFormat = new Intl.DateTimeFormat('it-IT', {
  day: 'numeric',
  month: 'short',
  timeZone: 'UTC',
})

function formatWeek(weekStart: string): string {
  return weekFormat.format(new Date(`${weekStart}T00:00:00Z`))
}

function WeeklyRequestsChart({ weeks }: { weeks: AdminStatisticsData['weeklyLoanRequests'] }) {
  const maximum = Math.max(1, ...weeks.map((week) => week.count))

  return (
    <figure className="admin-chart">
      <figcaption>
        <h2>Nuove richieste di prestito</h2>
        <p>Per settimana, da lunedì a domenica (UTC). La settimana corrente è parziale.</p>
      </figcaption>
      {weeks.every((week) => week.count === 0) && (
        <p className="admin-chart__empty">Nessuna richiesta nelle ultime 8 settimane.</p>
      )}
      <ol className="admin-chart__list">
        {weeks.map((week) => (
          <li className="admin-chart__column" key={week.weekStart}>
            <span className="admin-chart__count">{numberFormat.format(week.count)}</span>
            <span className="admin-chart__plot" aria-hidden="true">
              <span
                className="admin-chart__bar"
                style={{ height: `${(week.count / maximum) * 100}%` }}
              />
            </span>
            <time dateTime={week.weekStart} aria-label={formatWeek(week.weekStart)}>
              {shortWeekFormat.format(new Date(`${week.weekStart}T00:00:00Z`))}
            </time>
          </li>
        ))}
      </ol>
    </figure>
  )
}

function StatisticsCards({ data }: { data: AdminStatisticsData }) {
  const metrics = [
    ['Utenti registrati', data.registeredUsers],
    ['Copie pubblicate', data.publishedCopies],
    ['Richieste di prestito', data.loanRequests],
    ['Prestiti conclusi', data.completedLoans],
  ] as const

  return (
    <dl className="admin-kpis">
      {metrics.map(([label, value]) => (
        <div className="admin-kpi" key={label}>
          <dt>{label}</dt>
          <dd>{numberFormat.format(value)}</dd>
        </div>
      ))}
    </dl>
  )
}

export function AdminDashboard() {
  const [view, setView] = useState<ViewState>({ status: 'loading' })

  async function load() {
    setView({ status: 'loading' })
    try {
      const data = await adminStatistics()
      setView({ status: 'ready', data })
    } catch (error) {
      const expired = error instanceof ApiError && error.status === 401
      const forbidden = error instanceof ApiError && error.status === 403
      setView({
        status: 'error',
        expired,
        message: expired
          ? 'La sessione è scaduta. Ricarica la pagina per accedere di nuovo.'
          : forbidden
            ? 'La sessione non dispone dei permessi amministrativi.'
            : 'Non riusciamo a caricare le statistiche.',
      })
    }
  }

  useEffect(() => {
    let active = true
    void adminStatistics()
      .then((data) => { if (active) setView({ status: 'ready', data }) })
      .catch((error: unknown) => {
        if (!active) return
        const expired = error instanceof ApiError && error.status === 401
        const forbidden = error instanceof ApiError && error.status === 403
        setView({
          status: 'error',
          expired,
          message: expired
            ? 'La sessione è scaduta. Ricarica la pagina per accedere di nuovo.'
            : forbidden
              ? 'La sessione non dispone dei permessi amministrativi.'
              : 'Non riusciamo a caricare le statistiche.',
        })
      })
    return () => { active = false }
  }, [])

  return (
    <section className="library-page admin-dashboard" aria-labelledby="admin-title">
      <div className="library-page__heading">
        <div>
          <p className="eyebrow">Amministrazione</p>
          <h1 id="admin-title">Statistiche</h1>
        </div>
      </div>
      {view.status === 'loading' && <p role="status">Caricamento delle statistiche…</p>}
      {view.status === 'error' && (
        <div className="library-page__message" role="alert">
          <p>{view.message}</p>
          {view.expired
            ? <button className="secondary-button" type="button" onClick={() => window.location.reload()}>Ricarica la pagina</button>
            : <button className="secondary-button" type="button" onClick={() => void load()}>Riprova</button>}
        </div>
      )}
      {view.status === 'ready' && (
        <>
          <StatisticsCards data={view.data} />
          <WeeklyRequestsChart weeks={view.data.weeklyLoanRequests} />
        </>
      )}
    </section>
  )
}
