import { useEffect, useRef, useState } from 'react'
import { getDiscoveryLocation } from '../../api'
import { useCurrentLocation } from '../discovery/useCurrentLocation'

type ZoneState = 'checking' | 'missing' | 'ready' | 'error'

export function usePublicationLocation() {
  const [publish, setPublish] = useState(false)
  const [zone, setZone] = useState<ZoneState>('checking')
  const [zoneRetry, setZoneRetry] = useState(0)
  const currentLocation = useCurrentLocation()
  const cancelLocation = currentLocation.cancel
  const serial = useRef(0)

  useEffect(() => {
    const generation = ++serial.current
    if (publish) {
      void getDiscoveryLocation().then((location) => {
        if (generation === serial.current) setZone(location ? 'ready' : 'missing')
      }).catch(() => { if (generation === serial.current) setZone('error') })
    }
    return () => { serial.current += 1; cancelLocation() }
  }, [publish, zoneRetry, cancelLocation])

  function changePublication(next: boolean) {
    serial.current += 1
    currentLocation.reset()
    setZone('checking')
    setPublish(next)
  }

  function retryZone() {
    setZone('checking')
    setZoneRetry((value) => value + 1)
  }

  async function configureLocation() {
    if (currentLocation.state.status === 'locating' || currentLocation.state.status === 'saving') return
    const generation = ++serial.current
    const location = await currentLocation.configure()
    if (location && generation === serial.current) setZone('ready')
  }

  return { publish, zone, locationState: currentLocation.state, changePublication, retryZone, configureLocation, requireLocation: () => setZone('missing') }
}
