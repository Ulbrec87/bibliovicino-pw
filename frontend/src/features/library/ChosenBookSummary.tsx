type Props = {
  title: string
  authors: string[]
  publicationYear: number | string | null
  isbn: string | null
}

export function ChosenBookSummary({ title, authors, publicationYear, isbn }: Props) {
  return <div id="chosen-book" className="chosen-book" tabIndex={-1}>
    <div className="chosen-book__metadata">
      <strong>{title}</strong><span>{authors.join(', ')}</span><small>{publicationYear}</small><small className="book-isbn">{isbn}</small>
    </div>
  </div>
}
