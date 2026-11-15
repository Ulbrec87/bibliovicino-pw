export type CurrentUser = {
  id: string
  email: string
  displayName: string
  roles: string[]
}

export type DiscoveryLocation = {
  representativePoint: {
    latitude: number
    longitude: number
  }
}

export type DiscoverySearchSort = 'relevance' | 'distance'
export type DiscoverySearchRadiusKm = 1 | 2 | 5 | 10
export type DiscoveryOfferMode = 'loan'

export type ApproximateDistance =
  | { kind: 'less_than_km'; kilometres: 1 }
  | { kind: 'around_km'; kilometres: number }

export type CopyCover = { source: 'external' | 'uploaded'; previewUrl: string; largeUrl: string | null; reference?: string }

export type DiscoveryBookCopy = {
  cover?: CopyCover | null
  bookCopyId: string
  book: {
    title: string
    authors: string[]
    publicationYear: number | null
    categories: string[]
  }
  owner: {
    id: string
    displayName: string
  }
  approximateDistance: ApproximateDistance
  offerModes: DiscoveryOfferMode[]
  approximatePoint: {
    latitude: number
    longitude: number
  }
}

export type DiscoverySearchResponse = {
  results: DiscoveryBookCopy[]
  meta: {
    radiusKm: DiscoverySearchRadiusKm
    sort: DiscoverySearchSort
    returnedCount: number
  }
}

export type DiscoverySearchParameters = {
  query: string
  radiusKm: DiscoverySearchRadiusKm
  sort: DiscoverySearchSort
}

type UserResponse = { user: CurrentUser }
type DiscoveryLocationResponse = { discoveryLocation: DiscoveryLocation | null }
type ConfiguredDiscoveryLocationResponse = { discoveryLocation: DiscoveryLocation }
type ErrorResponse = { error?: { code?: string; message?: string; fields?: Record<string, string[]>; book?: BibliographicBook } }

export type BibliographicBook = { id: string; title: string; authors: string[]; publicationYear: number | null; isbn: string | null }
export type ActiveLoanStatus = 'PENDING' | 'ACCEPTED' | 'LENT'
export type LoanStatus = ActiveLoanStatus | 'RETURNED' | 'REJECTED' | 'CANCELLED'
export type LoanAction = 'accept' | 'reject' | 'cancel' | 'lend' | 'return'
export type AdminStatisticsData = {
  registeredUsers: number
  publishedCopies: number
  loanRequests: number
  completedLoans: number
  weeklyLoanRequests: { weekStart: string; count: number }[]
}

export type LoanRequestItem = {
  id: string
  status: LoanStatus
  requestedAt: string
  updatedAt: string
  copy: { id: string; title: string; authors: string[]; cover: CopyCover | null; canOpenDetail: boolean }
  otherParticipant: { displayName: string }
  actions: LoanAction[]
  ownerEmail?: string
}
export type LoanRequestsData = { received: LoanRequestItem[]; sent: LoanRequestItem[] }
export type LibraryCopy = { cover?: CopyCover | null; id: string; title: string; authors: string[]; isPublished: boolean; offerModes: DiscoveryOfferMode[]; availabilityStatus?: CopyAvailability; activeLoanStatus?: ActiveLoanStatus | null }
export type CopyAvailability = 'AVAILABLE' | 'UNAVAILABLE'
export type BookCopyDetailData = {
  cover?: CopyCover | null
  id: string
  book: BibliographicBook & { categories: string[] }
  notes: string | null
  isOwner: boolean
  isPublished: boolean
  availabilityStatus: CopyAvailability
  activeLoanStatus?: ActiveLoanStatus | null
  canRequestLoan?: boolean
  owner: { id: string; displayName: string }
  approximateDistance: ApproximateDistance | null
}
export type PublicLibraryCopy = { cover?: CopyCover | null; id: string; title: string; authors: string[]; availabilityStatus: CopyAvailability; activeLoanStatus?: ActiveLoanStatus | null }
export type PublicLibraryData = { owner: { id: string; displayName: string }; sharedCount: number; bookCopies: PublicLibraryCopy[] }
export type ActiveCategory = { id: string; name: string; slug: string }
export type BookSearchResponse = { results: BibliographicBook[]; hasMore: boolean; mode: 'text' | 'isbn'; canonicalIsbn: string | null }
export type ExternalWorkSearchResult = { reference: string; edition: ExternalEditionSearchResult }
export type ExternalEditionSearchResult = { reference: string; title: string; authors: string[]; publicationYear: number | null; isbn: string | null; coverReference?: string | null; coverPreviewUrl: string | null; coverLargeUrl: string | null; correctionRequired: boolean }
export type ExternalCatalogStatus = 'available' | 'empty' | 'unavailable'
export type ExternalBookSearchResponse = { mode: 'text' | 'isbn'; status: ExternalCatalogStatus | 'local'; works: ExternalWorkSearchResult[]; edition: ExternalEditionSearchResult | null; localBook: BibliographicBook | null; hasMore: boolean }
export type NewBookInput = { title: string; authors: string[]; publicationYear: number | null; categoryIds: string[]; isbn: string | null }
export type CreateCopyInput = {
  bookId?: string
  newBook?: NewBookInput
  notes: string | null
  publish: boolean
  cover?: { source: 'none' } | { source: 'external'; reference: string }
}

export class ApiError extends Error {
  readonly status: number
  readonly code: string
  readonly fields?: Record<string, string[]>
  readonly book?: BibliographicBook

  constructor(
    status: number,
    code: string,
    message: string,
    fields?: Record<string, string[]>,
    book?: BibliographicBook,
  ) {
    super(message)
    this.status = status
    this.code = code
    this.fields = fields
    this.book = book
  }
}

export class RequestNotSentError extends Error {
  constructor(cause: unknown) {
    super('The request was not sent.', { cause })
  }
}

let csrfToken: string | null = null

async function obtainCsrfToken(): Promise<string> {
  if (csrfToken !== null) return csrfToken

  const response = await fetch('/api/csrf-token', {
    credentials: 'same-origin',
    headers: { Accept: 'application/json' },
  })
  if (!response.ok) {
    throw new ApiError(response.status, 'csrf_unavailable', 'CSRF token unavailable.')
  }

  const payload = (await response.json()) as { token: string }
  if (typeof payload?.token !== 'string' || payload.token.length === 0) {
    throw new Error('Invalid CSRF token response.')
  }
  csrfToken = payload.token
  return csrfToken
}

async function apiRequest<T>(path: string, init: RequestInit = {}): Promise<T> {
  const method = (init.method ?? 'GET').toUpperCase()
  const unsafe = ['POST', 'PUT', 'PATCH', 'DELETE'].includes(method)
  const headers = new Headers(init.headers)
  headers.set('Accept', 'application/json')
  if (unsafe) {
    try {
      headers.set('X-CSRF-Token', await obtainCsrfToken())
    } catch (error) {
      // Se non riusciamo a ottenere il token CSRF, ci fermiamo prima di inviare la richiesta di modifica.
      throw new RequestNotSentError(error)
    }
  }

  const response = await fetch(path, {
    ...init,
    method,
    headers,
    credentials: 'same-origin',
  })

  if (!response.ok) {
    const payload = (await response.json().catch(() => ({}))) as ErrorResponse
    const code = payload.error?.code ?? 'request_failed'
    // Dopo un errore 401 o un token CSRF rifiutato, servirà un nuovo token per la prossima richiesta di modifica.
    // La richiesta fallita non viene ripetuta automaticamente.
    if (code === 'invalid_csrf_token' || response.status === 401) csrfToken = null
    throw new ApiError(response.status, code, payload.error?.message ?? 'API request failed.', payload.error?.fields, payload.error?.book)
  }

  if (response.status === 204) return undefined as T
  return (await response.json()) as T
}

export async function currentUser(): Promise<CurrentUser | null> {
  try {
    return (await apiRequest<UserResponse>('/api/auth/me')).user
  } catch (error) {
    if (error instanceof ApiError && error.status === 401) return null
    throw error
  }
}

export async function updateProfile(displayName: string): Promise<CurrentUser> {
  return (await apiRequest<UserResponse>('/api/profile', {
    method: 'PATCH',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ displayName }),
  })).user
}

export async function disableDiscoveryLocation(): Promise<void> {
  return apiRequest<void>('/api/profile/discovery-location', { method: 'DELETE' })
}

export async function getDiscoveryLocation(): Promise<DiscoveryLocation | null> {
  return (await apiRequest<DiscoveryLocationResponse>('/api/profile/discovery-location')).discoveryLocation
}

export async function setDiscoveryLocation(latitude: number, longitude: number, signal?: AbortSignal): Promise<DiscoveryLocation> {
  return (await apiRequest<ConfiguredDiscoveryLocationResponse>('/api/profile/discovery-location', {
    method: 'PUT',
    signal,
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ latitude, longitude }),
  })).discoveryLocation
}

export async function searchBookCopies(parameters: DiscoverySearchParameters): Promise<DiscoverySearchResponse> {
  const query = new URLSearchParams({
    radiusKm: String(parameters.radiusKm),
    sort: parameters.sort,
  })
  const submittedQuery = parameters.query.trim()
  if (submittedQuery !== '') query.set('q', submittedQuery)

  return apiRequest<DiscoverySearchResponse>(`/api/discovery/book-copies?${query.toString()}`)
}

export async function login(email: string, password: string): Promise<CurrentUser> {
  return (await apiRequest<UserResponse>('/api/auth/login', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, password }),
  })).user
}

export async function register(email: string, password: string, displayName: string): Promise<CurrentUser> {
  return (await apiRequest<UserResponse>('/api/auth/register', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, password, displayName }),
  })).user
}

export async function logout(): Promise<void> {
  await apiRequest<void>('/api/auth/logout', { method: 'POST' })
  csrfToken = null
}

export async function adminStatistics(): Promise<AdminStatisticsData> {
  return (await apiRequest<{ statistics: AdminStatisticsData }>('/api/admin/statistics')).statistics
}

export async function createLoanRequest(bookCopyId: string): Promise<string> {
  const result = await apiRequest<{ id?: string }>('/api/loan/requests', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ bookCopyId }) })
  if (typeof result?.id !== 'string' || result.id.length === 0) throw new Error('Unconfirmed loan request creation.')
  return result.id
}

export async function loanRequests(): Promise<LoanRequestsData> {
  return (await apiRequest<{ requests: LoanRequestsData }>('/api/loan/requests')).requests
}

export async function changeLoanRequest(id: string, action: LoanAction): Promise<void> {
  return apiRequest<void>(`/api/loan/requests/${encodeURIComponent(id)}/${action}`, { method: 'POST' })
}

export async function myBookCopies(): Promise<LibraryCopy[]> {
  return (await apiRequest<{ bookCopies: LibraryCopy[] }>('/api/library/my-book-copies')).bookCopies
}

export async function searchBooks(query: string, page: number): Promise<BookSearchResponse> {
  return apiRequest<BookSearchResponse>(`/api/library/books?${new URLSearchParams({ q: query, page: String(page) })}`)
}

export async function activeCategories(): Promise<ActiveCategory[]> {
  return (await apiRequest<{ categories: ActiveCategory[] }>('/api/library/categories')).categories
}

export async function createBookCopy(input: CreateCopyInput): Promise<{ id: string; bookId: string; isPublished: boolean }> {
  const response = await apiRequest<{ bookCopy?: { id: string; bookId: string; isPublished: boolean } }>('/api/library/book-copies', {
    method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(input),
  })
  if (!response?.bookCopy || typeof response.bookCopy.id !== 'string' || typeof response.bookCopy.bookId !== 'string' || typeof response.bookCopy.isPublished !== 'boolean') {
    throw new Error('Unconfirmed book copy creation.')
  }
  return response.bookCopy
}

export async function lookupBookIsbn(isbn: string): Promise<{ canonicalIsbn: string; book: BibliographicBook | null }> {
  return apiRequest(`/api/library/books/isbn/${encodeURIComponent(isbn)}`)
}

export async function searchExternalBooks(query: string, page = 1): Promise<ExternalBookSearchResponse> {
  return apiRequest(`/api/library/external-books/search?${new URLSearchParams({ q: query, page: String(page) })}`)
}

export async function bookCopyDetail(id: string): Promise<BookCopyDetailData> {
  return (await apiRequest<{ bookCopy: BookCopyDetailData }>(`/api/library/book-copies/${encodeURIComponent(id)}`)).bookCopy
}

export async function publicLibrary(id: string): Promise<PublicLibraryData> {
  return (await apiRequest<{ library: PublicLibraryData }>(`/api/library/users/${encodeURIComponent(id)}/book-copies`)).library
}

export async function updateBookCopyNotes(id: string, notes: string | null): Promise<void> {
  return apiRequest(`/api/library/book-copies/${encodeURIComponent(id)}/notes`, {
    method: 'PATCH', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ notes }),
  })
}

export async function publishBookCopy(id: string): Promise<void> {
  return apiRequest(`/api/library/book-copies/${encodeURIComponent(id)}/publish`, { method: 'POST' })
}

export async function unpublishBookCopy(id: string): Promise<void> {
  return apiRequest(`/api/library/book-copies/${encodeURIComponent(id)}/unpublish`, { method: 'POST' })
}

export async function archiveBookCopy(id: string): Promise<void> {
  return apiRequest(`/api/library/book-copies/${encodeURIComponent(id)}/archive`, { method: 'POST' })
}

export async function uploadBookCopyCover(id: string, file: File): Promise<void> {
  const body = new FormData()
  body.append('image', file)
  return apiRequest(`/api/library/book-copies/${encodeURIComponent(id)}/cover`, { method: 'POST', body })
}

export async function removeBookCopyCover(id: string): Promise<void> {
  return apiRequest(`/api/library/book-copies/${encodeURIComponent(id)}/cover`, { method: 'DELETE' })
}

export async function setExternalBookCopyCover(id: string, reference: string): Promise<void> {
  return apiRequest(`/api/library/book-copies/${encodeURIComponent(id)}/cover`, {
    method: 'PUT', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ source: 'external', reference }),
  })
}
