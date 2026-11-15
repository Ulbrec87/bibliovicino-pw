import { useState, type ReactNode } from 'react'
import { ApiError, publishBookCopy, unpublishBookCopy } from '../../api'
import { ConfirmationDialog } from '../../components/ConfirmationDialog'
import { LocationSetup } from '../discovery/LocationSetup'
import type { RunCopyOperation } from './OwnerCopyActions'
import type { usePublicationLocation } from './usePublicationLocation'

type Props = {
  copyId: string
  isPublished: boolean
  pending: boolean
  activeLoanStatus?: string | null
  publication: ReturnType<typeof usePublicationLocation>
  runOperation: RunCopyOperation
  onLocationRequired: () => void
  onPublishedChange: (isPublished: boolean) => void
  children: ReactNode
}

export function CopyPublicationActions({ copyId, isPublished, pending, activeLoanStatus, publication, runOperation, onLocationRequired, onPublishedChange, children }: Props) {
  const [withdrawTrigger, setWithdrawTrigger] = useState<HTMLElement | null>(null)
  const locating = publication.locationState.status === 'locating' || publication.locationState.status === 'saving'

  function publish() {
    void runOperation({
      execute: () => publishBookCopy(copyId),
      onSuccess: () => {
        onPublishedChange(true)
        publication.changePublication(false)
      },
      successMessage: 'Copia pubblicata.',
      onError: (error) => {
        if (!(error instanceof ApiError) || error.code !== 'discovery_location_required') return false
        onLocationRequired()
        return true
      },
    })
  }

  function withdraw() {
    setWithdrawTrigger(null)
    void runOperation({
      execute: () => unpublishBookCopy(copyId),
      onSuccess: () => onPublishedChange(false),
      successMessage: 'Copia ritirata dalla pubblicazione.',
    })
  }

  return <>
    {!isPublished && publication.publish && <div className="copy-publication">
      {publication.zone === 'checking' && <p role="status">Verifica della zona…</p>}
      {publication.zone === 'missing' && <LocationSetup useCurrentLocationState={publication.locationState} onUseCurrentLocation={() => void publication.configureLocation()} />}
      {publication.zone === 'error' && <div role="alert"><p>Non riusciamo a verificare la zona.</p><button type="button" onClick={publication.retryZone}>Riprova</button></div>}
      {publication.zone === 'ready' && <p role="status">Zona configurata. Ora puoi pubblicare la copia.</p>}
      <button type="button" className="secondary-button" disabled={pending} onClick={() => publication.changePublication(false)}>Annulla pubblicazione</button>
    </div>}
    <div className="copy-actions">
      {isPublished
        ? <button type="button" className="secondary-button" disabled={pending || Boolean(activeLoanStatus)} onClick={(event) => setWithdrawTrigger(event.currentTarget)}>Ritira dalla pubblicazione</button>
        : <button type="button" className="primary-button" disabled={pending || Boolean(activeLoanStatus) || locating || (publication.publish && publication.zone !== 'ready')} onClick={publish}>Pubblica</button>}
      {children}
    </div>
    {withdrawTrigger && <ConfirmationDialog
      returnFocus={withdrawTrigger}
      title="Ritira dalla pubblicazione"
      message="Vuoi rendere privata questa copia? Non sarà più visibile nella Discovery, ma resterà nella tua libreria personale. Potrai pubblicarla nuovamente."
      confirmLabel="Ritira dalla pubblicazione"
      onConfirm={withdraw} onCancel={() => setWithdrawTrigger(null)}
    />}
  </>
}
