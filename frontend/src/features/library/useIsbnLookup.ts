import { useEffect, useRef, useState } from 'react'
import { ApiError, lookupBookIsbn, type BibliographicBook } from '../../api'

type IsbnCheckResult = { status: 'valid'; book: BibliographicBook | null } | { status: 'invalid' } | { status: 'stale' }

export function useIsbnLookup(onInvalid: () => void) {
  const [checking, setChecking] = useState(false)
  const [notice, setNotice] = useState<string | null>(null)
  const [duplicateBook, setDuplicateBook] = useState<BibliographicBook | null>(null)
  const serial = useRef(0)
  const pending = useRef<{ value: string; promise: Promise<IsbnCheckResult> } | null>(null)

  useEffect(() => () => { serial.current += 1 }, [])

  function invalidate() {
    serial.current += 1
    pending.current = null
    setChecking(false)
    setNotice(null)
    setDuplicateBook(null)
  }

  function check(value: string): Promise<IsbnCheckResult> {
    const trimmed = value.trim()
    // Quando si esce dal campo ISBN e quando si salva, riutilizziamo la stessa verifica.
    // Se cambiano l'ISBN o la modalità di inserimento, ignoriamo le risposte della verifica precedente.
    if (pending.current?.value === trimmed) return pending.current.promise
    const generation = ++serial.current
    const promise = (async (): Promise<IsbnCheckResult> => {
      if (!trimmed) return { status: 'valid', book: null }
      if (!/^(?:[0-9]{9}[0-9Xx]|[0-9]{13})$/.test(trimmed.replace(/[ -]/g, ''))) {
        onInvalid()
        return { status: 'invalid' }
      }
      setChecking(true)
      setNotice(null)
      try {
        const response = await lookupBookIsbn(trimmed)
        if (generation !== serial.current) return { status: 'stale' }
        setDuplicateBook(response.book)
        return { status: 'valid', book: response.book }
      } catch (error) {
        if (generation !== serial.current) return { status: 'stale' }
        if (error instanceof ApiError && error.status === 422) {
          onInvalid()
          return { status: 'invalid' }
        }
        pending.current = null
        setNotice('Non possiamo verificare ora l’ISBN. Il controllo sarà ripetuto al salvataggio.')
        return { status: 'valid', book: null }
      } finally {
        if (generation === serial.current) setChecking(false)
      }
    })()
    pending.current = { value: trimmed, promise }
    return promise
  }

  return { checking, notice, duplicateBook, setDuplicateBook, check, invalidate }
}
