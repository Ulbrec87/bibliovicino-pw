import { BookCopyCover } from '../features/library/BookCopyCover'
import { Link } from 'react-router'

export type OfferMode = 'loan'

export type BookCopyCardData = {
  id: string
  title: string
  authors: readonly string[]
  categories: readonly string[]
  publicationYear?: number | null
  approximateDistance: string
  ownerDisplayName: string
  offerModes: readonly OfferMode[]
  cover?: { previewUrl: string; largeUrl: string | null } | null
}

function PinIcon() {
  return (
    <svg viewBox="0 0 24 24" aria-hidden="true">
      <path d="M12 21s6-5.4 6-11a6 6 0 1 0-12 0c0 5.6 6 11 6 11Z" />
      <circle cx="12" cy="10" r="2" />
    </svg>
  )
}


export function BookCopyCard({ copy }: { copy: BookCopyCardData }) {
  const metadata = [
    ...copy.categories,
    ...(copy.publicationYear === null || copy.publicationYear === undefined ? [] : [String(copy.publicationYear)]),
  ].join(' · ')

  return (
    <article className="book-copy-card">
      <div className="book-cover"><BookCopyCover title={copy.title} cover={copy.cover} className="discovery-cover" thumbnailOnly /></div>

      <div className="book-copy-card__content">
        <div className="book-copy-card__heading">
          <div>
            <h3><Link to={`/copie/${copy.id}`}>{copy.title}</Link></h3>
            <p className="book-copy-card__authors">{copy.authors.join(', ')}</p>
          </div>
          <p className="book-copy-card__distance">
            <PinIcon />
            <span>{copy.approximateDistance}</span>
          </p>
        </div>

        {metadata !== '' && <p className="book-copy-card__metadata">{metadata}</p>}

        <div className="book-copy-card__footer">
          <ul className="offer-list" aria-label="Modalità disponibili">
            {copy.offerModes.map((mode) => (
              <li className={`offer-badge offer-badge--${mode}`} key={mode}>
                Prestito
              </li>
            ))}
          </ul>
          <p className="book-copy-card__owner">
            Nella libreria di <strong>{copy.ownerDisplayName}</strong>
          </p>
        </div>
      </div>
    </article>
  )
}
