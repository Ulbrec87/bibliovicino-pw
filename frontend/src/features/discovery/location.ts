export function browserPosition(): Promise<GeolocationPosition> {
  return new Promise((resolve, reject) => {
    navigator.geolocation.getCurrentPosition(resolve, reject, {
      enableHighAccuracy: false,
      maximumAge: 0,
      timeout: 10_000,
    })
  })
}

export function geolocationErrorMessage(error: unknown): string {
  if (typeof error !== 'object' || error === null || !('code' in error)) {
    return 'Non è stato possibile determinare la posizione. Riprova.'
  }

  const positionError = error as GeolocationPositionError

  switch (positionError.code) {
    case positionError.PERMISSION_DENIED:
      return 'Permesso di localizzazione negato. Puoi riprovare dalle impostazioni del browser.'
    case positionError.POSITION_UNAVAILABLE:
      return 'La posizione non è disponibile. Riprova tra poco.'
    case positionError.TIMEOUT:
      return 'La ricerca della posizione ha impiegato troppo tempo. Riprova.'
    default:
      return 'Non è stato possibile determinare la posizione. Riprova.'
  }
}
