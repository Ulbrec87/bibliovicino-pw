import { useEffect, useRef, useState } from 'react'
import { disableDiscoveryLocation, getDiscoveryLocation } from '../../api'
import { ConfirmationDialog } from '../../components/ConfirmationDialog'
import { LocationSetup } from '../discovery/LocationSetup'
import { useCurrentLocation } from '../discovery/useCurrentLocation'

type ZoneState = 'loading' | 'active' | 'missing' | 'error'

const DISABLE_MESSAGE = 'La posizione approssimata memorizzata verrà rimossa e i tuoi libri non compariranno più nella ricerca geografica. Le copie già pubblicate resteranno pubblicate e consultabili dalla libreria pubblica e tramite link diretto. Se disponibili, potranno ancora ricevere richieste di prestito dal dettaglio. Prestiti e richieste esistenti non saranno cancellati.'

export function ProfileDiscoveryLocation() {
  const [zone, setZone] = useState<ZoneState>('loading')
  const [reload, setReload] = useState(0)
  const [confirmationOpener, setConfirmationOpener] = useState<HTMLButtonElement | null>(null)
  const [disabling, setDisabling] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [notice, setNotice] = useState<string | null>(null)
  const disableButton = useRef<HTMLButtonElement>(null)
  const heading = useRef<HTMLHeadingElement>(null)
  const currentLocation = useCurrentLocation()
  const configuring = currentLocation.state.status === 'locating' || currentLocation.state.status === 'saving'
  const pending = configuring || disabling

  useEffect(() => {
    let active = true
    void getDiscoveryLocation()
      .then((location) => { if (active) setZone(location ? 'active' : 'missing') })
      .catch(() => { if (active) setZone('error') })
    return () => { active = false }
  }, [reload])

  useEffect(() => {
    if (notice && !pending) heading.current?.focus()
    if (error && !pending) disableButton.current?.focus()
  }, [notice, error, pending])

  async function configure() {
    setError(null)
    setNotice(null)
    const location = await currentLocation.configure()
    if (!location) return
    setZone('active')
    setNotice('Zona aggiornata.')
  }

  async function disable() {
    setConfirmationOpener(null)
    setDisabling(true)
    setError(null)
    setNotice(null)
    currentLocation.reset()
    try {
      await disableDiscoveryLocation()
      setZone('missing')
      setNotice('Zona disattivata.')
    } catch {
      setError('Non è stato possibile confermare la disattivazione. Riprova o ricarica la pagina.')
    } finally {
      setDisabling(false)
    }
  }

  return (
    <section className="profile-section" aria-labelledby="profile-zone-title">
      <h2 ref={heading} id="profile-zone-title" tabIndex={-1}>Zona geografica approssimativa</h2>
      <p>Conserviamo soltanto una posizione approssimata in una zona di circa 1 × 1 km. La tua posizione esatta non viene mostrata agli altri utenti.</p>
      {zone === 'loading' && <p role="status">Caricamento della zona…</p>}
      {zone === 'error' && (
        <div>
          <p className="message message--error" role="alert">Non è stato possibile caricare la zona.</p>
          <button className="secondary-button" type="button" onClick={() => { setZone('loading'); setReload((value) => value + 1) }}>Riprova</button>
        </div>
      )}
      {zone === 'missing' && (
        <>
          <p>Nessuna zona attiva. Impostala per cercare e rendere ricercabili i tuoi libri nelle vicinanze.</p>
          <LocationSetup useCurrentLocationState={currentLocation.state} onUseCurrentLocation={() => void configure()} />
        </>
      )}
      {zone === 'active' && (
        <>
          <p><strong>Zona attiva</strong></p>
          <div className="profile-actions">
            <button className="primary-button" type="button" disabled={pending} aria-busy={configuring} onClick={() => void configure()}>
              {currentLocation.state.status === 'locating' ? 'Rilevamento della posizione…' : currentLocation.state.status === 'saving' ? 'Salvataggio della zona…' : 'Aggiorna zona'}
            </button>
            <button ref={disableButton} className="secondary-button" type="button" disabled={pending} aria-busy={disabling} onClick={(event) => { setNotice(null); setError(null); setConfirmationOpener(event.currentTarget) }}>
              {disabling ? 'Disattivazione…' : 'Disattiva zona'}
            </button>
          </div>
          {configuring && <p role="status">Aggiornamento della zona in corso…</p>}
          {currentLocation.state.status === 'error' && <p className="message message--error" role="alert">{currentLocation.state.message}</p>}
        </>
      )}
      {error && <p className="message message--error" role="alert">{error}</p>}
      {notice && <p className="message" role="status">{notice}</p>}
      {confirmationOpener && <ConfirmationDialog
        title="Disattivare la zona?"
        message={DISABLE_MESSAGE}
        confirmLabel="Disattiva zona"
        returnFocus={confirmationOpener}
        onCancel={() => setConfirmationOpener(null)}
        onConfirm={() => void disable()}
      />}
    </section>
  )
}
