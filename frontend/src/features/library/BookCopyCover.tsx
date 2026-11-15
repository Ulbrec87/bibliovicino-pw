import { useState } from 'react'
import { EditionCover } from './EditionCover'

type CopyCover = { previewUrl: string; largeUrl: string | null }

export function BookCopyCover({ title, cover, thumbnailOnly = false, className = '' }: { title: string; cover?: CopyCover | null; thumbnailOnly?: boolean; className?: string }) {
  const [failedUrl, setFailedUrl] = useState<string | null>(null)
  if (cover && cover.previewUrl !== failedUrl) {
    if (thumbnailOnly) return <img className={`cover-frame copy-cover-thumbnail ${className}`} src={cover.previewUrl} alt={`Copertina di ${title}`} loading="lazy" referrerPolicy="no-referrer" onError={() => setFailedUrl(cover.previewUrl)} />
    return <EditionCover className={className} edition={{ title, coverPreviewUrl: cover.previewUrl, coverLargeUrl: cover.largeUrl }} />
  }
  return <div className={`cover-frame personal-book__cover ${className}`} role="img" aria-label={`Copertina non disponibile per ${title}`}>
    <small>Senza copertina</small>
  </div>
}
