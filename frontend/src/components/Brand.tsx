export function Brand() {
  return (
    <span className="brand" aria-label="BiblioVicino">
      <svg className="brand__mark" viewBox="0 0 40 32" aria-hidden="true">
        <path d="M4 5.5c5.7 0 10.1 1.6 13.3 4.8v16.2C14.1 23.8 9.7 22.4 4 22.4V5.5Z" />
        <path d="M36 5.5c-5.7 0-10.1 1.6-13.3 4.8v16.2c3.2-2.7 7.6-4.1 13.3-4.1V5.5Z" />
        <path className="brand__page" d="M20 9.4c-1.2-2-3.4-3.7-6.4-5.2v14.7c2.7.8 4.8 2 6.4 3.6V9.4Z" />
      </svg>
      <span className="brand__name">BiblioVicino</span>
    </span>
  )
}
