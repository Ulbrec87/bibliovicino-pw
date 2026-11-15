import { type FocusEvent, type ReactNode } from 'react'

type Props = {
  title: string
  authors: string[]
  year: string
  isbn: string
  fieldErrors: Record<string, string[]>
  onTitleChange: (value: string) => void
  onAuthorsChange: (value: string[]) => void
  onYearChange: (value: string) => void
  onIsbnChange: (value: string) => void
  onIsbnBlur: (event: FocusEvent<HTMLInputElement>) => void
  children: ReactNode
}

export function BookMetadataFields({ title, authors, year, isbn, fieldErrors, onTitleChange, onAuthorsChange, onYearChange, onIsbnChange, onIsbnBlur, children }: Props) {
  const fieldError = (name: string) => fieldErrors[name] && <p id={`${name}-error`} className="field-error" role="alert">{fieldErrors[name][0]}</p>
  const invalid = (name: string) => ({ 'aria-invalid': !!fieldErrors[name], 'aria-describedby': fieldErrors[name] ? `${name}-error` : undefined })

  function moveAuthor(index: number, direction: -1 | 1) {
    const next = [...authors]
    const other = index + direction
    if (other < 0 || other >= next.length) return
    const value = next[index]
    next[index] = next[other]
    next[other] = value
    onAuthorsChange(next)
  }

  return <>
    <label className="add-book-label" htmlFor="new-title">Titolo *</label>
    <input className="add-book-input" id="new-title" value={title} maxLength={255} onChange={(event) => onTitleChange(event.target.value)} {...invalid('newBook.title')} />
    {fieldError('newBook.title')}
    <fieldset className="author-fields"><legend className="add-book-label">Autore/i *</legend>
      {authors.map((author, index) => <div className={`author-fields__row${authors.length === 1 ? ' author-fields__row--single' : ''}`} key={index}>
        <label className="add-book-label" htmlFor={`author-${index}`}>Autore {index + 1}</label>
        <input className="add-book-input" id={`author-${index}`} value={author} maxLength={255} onChange={(event) => onAuthorsChange(authors.map((value, position) => position === index ? event.target.value : value))} {...invalid('newBook.authors')} />
        {authors.length > 1 && <>
          <button className="secondary-button author-action" type="button" aria-label={`Sposta autore ${index + 1} sopra`} disabled={index === 0} onClick={() => moveAuthor(index, -1)}>↑</button>
          <button className="secondary-button author-action" type="button" aria-label={`Sposta autore ${index + 1} sotto`} disabled={index === authors.length - 1} onClick={() => moveAuthor(index, 1)}>↓</button>
          <button className="secondary-button author-action" type="button" aria-label={`Rimuovi autore ${index + 1}`} onClick={() => onAuthorsChange(authors.filter((_, position) => position !== index))}>×</button>
        </>}
      </div>)}
      <button className="secondary-button" type="button" disabled={authors.length >= 10} onClick={() => onAuthorsChange([...authors, ''])}>+ Aggiungi autore</button>
    </fieldset>
    {fieldError('newBook.authors')}
    {children}
    <div className="bibliographic-optionals">
      <div><label className="add-book-label" htmlFor="publication-year">Anno di pubblicazione <span>(facoltativo)</span></label><input className="add-book-input" id="publication-year" inputMode="numeric" value={year} onChange={(event) => onYearChange(event.target.value)} {...invalid('newBook.publicationYear')} />{fieldError('newBook.publicationYear')}</div>
      <div><label className="add-book-label" htmlFor="new-isbn">ISBN <span>(facoltativo)</span></label><input className="add-book-input" id="new-isbn" value={isbn} onChange={(event) => onIsbnChange(event.target.value)} onBlur={onIsbnBlur} {...invalid('newBook.isbn')} />{fieldError('newBook.isbn')}</div>
    </div>
  </>
}
