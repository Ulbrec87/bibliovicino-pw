import { useCallback, useEffect, useRef, useState } from 'react'
import { setDiscoveryLocation, type DiscoveryLocation } from '../../api'
import type { UseCurrentLocationState } from './LocationSetup'
import { browserPosition, geolocationErrorMessage } from './location'

export function useCurrentLocation() {
  const [state, setState] = useState<UseCurrentLocationState>({ status: 'idle' })
  const serial = useRef(0)
  const pending = useRef<AbortController | null>(null)

  const cancel = useCallback(() => {
    serial.current += 1
    pending.current?.abort()
    pending.current = null
  }, [])

  useEffect(() => () => cancel(), [cancel])

  function reset() {
    cancel()
    setState({ status: 'idle' })
  }

  async function configure(): Promise<DiscoveryLocation | null> {
    if (state.status === 'locating' || state.status === 'saving') return null
    if (!navigator.geolocation) {
      setState({ status: 'error', message: 'La geolocalizzazione non è supportata da questo browser.' })
      return null
    }

    const generation = ++serial.current
    setState({ status: 'locating' })
    let position: GeolocationPosition
    try {
      position = await browserPosition()
    } catch (error) {
      if (generation === serial.current) setState({ status: 'error', message: geolocationErrorMessage(error) })
      return null
    }
    if (generation !== serial.current) return null

    setState({ status: 'saving' })
    const abort = new AbortController()
    pending.current = abort
    try {
      // Le coordinate precise restano locali alla richiesta e non entrano nello stato React.
      const location = await setDiscoveryLocation(position.coords.latitude, position.coords.longitude, abort.signal)
      if (generation !== serial.current) return null
      setState({ status: 'idle' })
      return location
    } catch {
      if (generation === serial.current) setState({ status: 'error', message: 'Non è stato possibile salvare la zona. Riprova.' })
      return null
    } finally {
      if (pending.current === abort) pending.current = null
    }
  }

  return { state, configure, cancel, reset }
}
