import { useEffect, useRef, useState } from 'react'

type Cover = { title: string; coverPreviewUrl: string | null; coverLargeUrl: string | null }

type Props = { edition: Cover; className?: string }

export function EditionCover({ edition, className = '' }: Props) {
  const [readyUrl, setReadyUrl] = useState<string | null>(null)
  const [failedUrl, setFailedUrl] = useState<string | null>(null)
  const [largeFailedUrl, setLargeFailedUrl] = useState<string | null>(null)
  const [largeReadyUrl, setLargeReadyUrl] = useState<string | null>(null)
  const [open, setOpen] = useState(false)
  const dialog = useRef<HTMLDialogElement>(null)
  const trigger = useRef<HTMLButtonElement>(null)
  const ready = readyUrl === edition.coverPreviewUrl

  useEffect(() => {
    if (!open) return
    const element = dialog.current!
    const opener = trigger.current
    const previousOverflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    // La finestra si apre con il focus su Chiudi copertina e impedisce di interagire con il resto della pagina.
    element.showModal()
    return () => {
      element.close()
      document.body.style.overflow = previousOverflow
      opener?.focus({ preventScroll: true })
    }
  }, [open])

  if (!edition.coverPreviewUrl || failedUrl === edition.coverPreviewUrl) {
    return <span className={`cover-frame edition-cover-placeholder ${className}`} aria-label="Copertina non disponibile">Senza copertina</span>
  }

  return <>
    <button ref={trigger} type="button" className={`cover-frame edition-cover-button ${className}`} aria-label={`Ingrandisci la copertina di ${edition.title}`} disabled={!ready} aria-busy={!ready} onClick={() => setOpen(true)}>
      {!ready && <span className="edition-cover-loading" aria-hidden="true">Caricamento</span>}
      <img className={`edition-cover-preview${ready ? ' edition-cover-preview--ready' : ''}`} src={edition.coverPreviewUrl} alt={`Copertina di ${edition.title}`} loading="lazy" referrerPolicy="no-referrer" onLoad={() => setReadyUrl(edition.coverPreviewUrl)} onError={() => { setFailedUrl(edition.coverPreviewUrl); setOpen(false) }} />
    </button>
    <dialog ref={dialog} className="cover-dialog" aria-label={`Copertina di ${edition.title}`} onKeyDown={(event) => {
      if (event.key === 'Tab') { event.preventDefault(); dialog.current?.querySelector('button')?.focus() }
    }} onClose={() => setOpen(false)} onCancel={() => setOpen(false)} onClick={(event) => { if (event.target === event.currentTarget) setOpen(false) }}>
      {open && <div className="cover-dialog__content">
        <button className="secondary-button" type="button" autoFocus onClick={() => setOpen(false)}>Chiudi copertina</button>
        <h2>{edition.title}</h2>
        <div className="cover-dialog__stage">
          <img src={edition.coverPreviewUrl} alt={`Copertina di ${edition.title}`} referrerPolicy="no-referrer" />
          {edition.coverLargeUrl && largeFailedUrl !== edition.coverLargeUrl && <img className={`cover-dialog__large${largeReadyUrl === edition.coverLargeUrl ? ' cover-dialog__large--ready' : ''}`} src={edition.coverLargeUrl} alt="" aria-hidden="true" referrerPolicy="no-referrer" onLoad={() => setLargeReadyUrl(edition.coverLargeUrl)} onError={() => setLargeFailedUrl(edition.coverLargeUrl)} />}
        </div>
        <p className="cover-dialog__status" role="status">{edition.coverLargeUrl && (largeFailedUrl === edition.coverLargeUrl ? 'Ingrandimento non disponibile: mostriamo l’anteprima.' : largeReadyUrl !== edition.coverLargeUrl ? 'Caricamento ingrandimento…' : '')}</p>
      </div>}
    </dialog>
  </>
}
