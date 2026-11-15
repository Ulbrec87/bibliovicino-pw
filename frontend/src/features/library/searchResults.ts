import type { BibliographicBook, ExternalEditionSearchResult, ExternalWorkSearchResult } from '../../api'

export type SearchCard = {
  key: string
  book: BibliographicBook | null
  workReference: string | null
  edition: ExternalEditionSearchResult | null
}

export function mergeSearchCards(previous: SearchCard[], books: BibliographicBook[], works: ExternalWorkSearchResult[]): SearchCard[] {
  const result = [...previous]
  for (const book of books) {
    const index = result.findIndex((card) => card.book?.id === book.id || (book.isbn !== null && (card.book?.isbn ?? card.edition?.isbn) === book.isbn))
    if (index >= 0) result[index] = { ...result[index], book }
    else result.push({ key: `local:${book.id}`, book, workReference: null, edition: null })
  }
  for (const work of works) {
    const isbn = work.edition.isbn
    const index = result.findIndex((card) => card.workReference === work.reference || (isbn !== null && (card.book?.isbn ?? card.edition?.isbn) === isbn))
    if (index >= 0) {
      // Se l'ISBN coincide, aggiungiamo i dati dell'edizione esterna alla scheda del libro locale.
      // La scheda mantiene la stessa chiave e la stessa posizione nell'elenco.
      if (result[index].book && !result[index].workReference) result[index] = { ...result[index], workReference: work.reference, edition: work.edition }
    } else result.push({ key: `external:${work.reference}`, book: null, workReference: work.reference, edition: work.edition })
  }
  return result
}

export function mergeIsbnEdition(previous: SearchCard[], edition: ExternalEditionSearchResult | null): SearchCard[] {
  if (!edition) return previous
  const index = previous.findIndex((card) => card.edition?.reference === edition.reference || (edition.isbn !== null && (card.book?.isbn ?? card.edition?.isbn) === edition.isbn))
  if (index >= 0) return previous.map((card, position) => position === index ? { ...card, edition } : card)
  return [...previous, { key: `edition:${edition.reference}`, book: null, workReference: null, edition }]
}
