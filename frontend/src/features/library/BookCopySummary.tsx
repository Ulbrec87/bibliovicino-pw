import { Link } from 'react-router'
import type { BookCopyDetailData } from '../../api'
import { formatApproximateDistance } from '../discovery/formatApproximateDistance'
import { BookCopyCover } from './BookCopyCover'

type Props = { copy: BookCopyDetailData }

export function BookCopySummary({ copy }: Props) {
  const { book, isOwner, isPublished, availabilityStatus, owner, approximateDistance } = copy
  const availability = copy.activeLoanStatus === 'LENT' ? 'In prestito'
    : copy.activeLoanStatus ? 'Impegnato'
    : availabilityStatus === 'UNAVAILABLE' ? 'Momentaneamente non disponibile'
    : isPublished ? 'Disponibile per il prestito' : 'Disponibile'

  return <div className="copy-detail__main">
    <div className="copy-detail__cover">
      <BookCopyCover title={book.title} cover={copy.cover} className="detail-cover" />
    </div>
    <div className="copy-detail__information">
      <p className="eyebrow">{isOwner ? 'Nella tua libreria' : 'Una copia condivisa'}</p>
      <h1>{book.title}</h1>
      <p className="copy-detail__authors">{book.authors.join(', ')}</p>
      {book.categories.length > 0 && (
        <ul className="copy-detail__categories" aria-label="Categorie">
          {book.categories.map((name) => <li key={name}>{name}</li>)}
        </ul>
      )}
      {book.publicationYear !== null && <p>Anno: {book.publicationYear}</p>}
      <p className="copy-detail__status">
        {isOwner && <span>{isPublished ? 'Pubblicato' : 'Privato'} · </span>}
        {availability}
      </p>
      {!isOwner && (
        <div className="copy-detail__owner">
          <p>Nella libreria di <strong>{owner.displayName}</strong></p>
          {approximateDistance && <p>{formatApproximateDistance(approximateDistance)}</p>}
          <Link className="secondary-button" to={`/utenti/${owner.id}/libreria`}>
            Vedi libreria
          </Link>
        </div>
      )}
      {book.isbn && <p className="copy-detail__isbn">ISBN: {book.isbn}</p>}
    </div>
  </div>
}
