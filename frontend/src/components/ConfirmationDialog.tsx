import { useEffect, useRef } from 'react'

type Props = { title: string; message: string; confirmLabel: string; returnFocus: HTMLElement | null; onConfirm: () => void; onCancel: () => void }

export function ConfirmationDialog({ title, message, confirmLabel, returnFocus, onConfirm, onCancel }: Props) {
  const dialog = useRef<HTMLDialogElement>(null)

  useEffect(() => {
    const element = dialog.current!
    const opener = returnFocus
    const overflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    // La finestra si apre con il focus su Annulla e impedisce di interagire con il resto della pagina.
    element.showModal()
    return () => {
      element.close()
      document.body.style.overflow = overflow
      opener?.focus({ preventScroll: true })
    }
  }, [returnFocus])

  return <dialog ref={dialog} className="confirmation-dialog" aria-labelledby="confirmation-title" aria-describedby="confirmation-message" onCancel={(event) => { event.preventDefault(); onCancel() }}>
    <h2 id="confirmation-title">{title}</h2>
    <p id="confirmation-message">{message}</p>
    <div className="copy-actions">
      <button type="button" className="secondary-button" autoFocus onClick={onCancel}>Annulla</button>
      <button type="button" className="primary-button" onClick={onConfirm}>{confirmLabel}</button>
    </div>
  </dialog>
}
