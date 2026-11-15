import type { ReactNode } from 'react'
import { Link } from 'react-router'
import type { LibraryCopy } from '../../api'
import { BookCopyCover } from './BookCopyCover'

export function LibraryCard({ copy, children }: { copy: Pick<LibraryCopy, 'id' | 'title' | 'authors' | 'cover'>; children: ReactNode }) {
  return <article className="personal-book">
    <BookCopyCover title={copy.title} cover={copy.cover} className="library-cover" thumbnailOnly />
    <div className="personal-book__details">
      <h2><Link to={`/copie/${copy.id}`}>{copy.title}</Link></h2>
      <p>{copy.authors.join(', ')}</p>
      <div className="personal-book__badges">{children}</div>
    </div>
  </article>
}
