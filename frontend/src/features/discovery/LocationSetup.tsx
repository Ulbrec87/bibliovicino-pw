export type UseCurrentLocationState =
  | { status: 'idle' }
  | { status: 'locating' }
  | { status: 'saving' }
  | { status: 'error'; message: string }

export function PinIcon() {
  return (
    <svg viewBox="0 0 24 24" aria-hidden="true">
      <path d="M12 21s6-5.4 6-11a6 6 0 1 0-12 0c0 5.6 6 11 6 11Z" />
      <circle cx="12" cy="10" r="2" />
    </svg>
  )
}


export function LocationSetup({
  useCurrentLocationState,
  onUseCurrentLocation,
}: {
  useCurrentLocationState: UseCurrentLocationState
  onUseCurrentLocation: () => void
}) {
  const pending = useCurrentLocationState.status === 'locating' || useCurrentLocationState.status === 'saving'
  const buttonLabel =
    useCurrentLocationState.status === 'locating'
      ? 'Rilevamento della posizione…'
      : useCurrentLocationState.status === 'saving'
        ? 'Salvataggio della zona…'
        : 'Usa la mia posizione'

  return (
    <section className="location-setup" aria-labelledby="location-setup-title">
      <div className="location-setup__icon" aria-hidden="true">
        <PinIcon />
      </div>
      <div className="location-setup__content">
        <p className="section-kicker">Scopri cosa c'è intorno a te</p>
        <h2 id="location-setup-title">Imposta la tua zona</h2>
        <p>
          Ci basta una zona approssimativa per trovare i libri vicini. La tua posizione esatta non viene mostrata agli altri utenti.
        </p>
        <div className="location-setup__actions">
          <button
            className="primary-button"
            type="button"
            aria-busy={pending}
            disabled={pending}
            onClick={onUseCurrentLocation}
          >
            {buttonLabel}
          </button>
        </div>
        <span className="visually-hidden" aria-live="polite">
          {pending ? buttonLabel : ''}
        </span>
        {useCurrentLocationState.status === 'error' && (
          <div className="message message--error" role="alert">
            {useCurrentLocationState.message}
          </div>
        )}
      </div>
    </section>
  )
}
