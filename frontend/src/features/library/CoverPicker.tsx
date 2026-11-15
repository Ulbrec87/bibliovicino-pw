import { useEffect, useId, useState } from 'react'
import type { CopyCover } from '../../api'
import { BookCopyCover } from './BookCopyCover'

export type CoverDraft = { source: 'none' | 'external' | 'keep' } | { source: 'uploaded'; file: File }
type ExternalCover = { coverReference?: string | null; coverPreviewUrl: string | null; coverLargeUrl: string | null }
type Props = {
  title: string
  draft: CoverDraft
  onChange: (draft: CoverDraft) => void
  external?: ExternalCover | null
  current?: CopyCover | null
  disabled?: boolean
}

export function CoverPicker({ title, draft, onChange, external, current, disabled = false }: Props) {
  const id = useId()
  const [local, setLocal] = useState<{ file: File; url: string } | null>(null)
  const [error, setError] = useState<string | null>(null)
  const file = draft.source === 'uploaded' ? draft.file : null
  useEffect(() => () => { if (local) URL.revokeObjectURL(local.url) }, [local])
  const cover = file && local?.file === file
    ? { previewUrl: local.url, largeUrl: local.url }
    : draft.source === 'external' && external?.coverPreviewUrl
      ? { previewUrl: external.coverPreviewUrl, largeUrl: external.coverLargeUrl }
      : draft.source === 'keep' ? current : null

  function changeChoice(source: 'keep' | 'external' | 'none') {
    setLocal(null)
    setError(null)
    onChange({ source })
  }

  return <fieldset className="cover-picker" disabled={disabled}>
    <legend>Copertina della tua copia</legend>
    <BookCopyCover title={title} cover={cover} />
    <div className="cover-picker__choices">
      {current && <label><input type="radio" name={id} checked={draft.source === 'keep'} onChange={() => changeChoice('keep')} />Conserva la copertina attuale</label>}
      {external?.coverReference && <label><input type="radio" name={id} checked={draft.source === 'external'} onChange={() => changeChoice('external')} />Usa la copertina trovata</label>}
      <label><input type="radio" name={id} checked={draft.source === 'none'} onChange={() => changeChoice('none')} />Nessuna copertina</label>
      <label htmlFor={`${id}-file`}>Carica una copertina</label>
      <input id={`${id}-file`} type="file" accept="image/jpeg,image/png,image/webp" aria-describedby={`${id}-help`} onChange={(event) => {
        const selected = event.target.files?.[0]
        event.target.value = ''
        if (!selected) return
        if (selected.size > 10000000) {
          setError('La copertina non può superare 10 MB.')
          return
        }
        setError(null)
        setLocal({ file: selected, url: URL.createObjectURL(selected) })
        onChange({ source: 'uploaded', file: selected })
      }} />
      <small id={`${id}-help`}>JPEG, PNG o WebP, massimo 10 MB. Le animazioni vengono salvate come immagini statiche.</small>
      {file && <small role="status">Immagine selezionata: {file.name}</small>}
      {error && <p role="alert" className="message message--error">{error}</p>}
    </div>
  </fieldset>
}
