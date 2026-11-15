import { cleanup, render, screen } from '@testing-library/react'
import { afterEach, expect, it, vi } from 'vitest'
import { adminStatistics } from '../../api'
import { AdminDashboard } from './AdminDashboard'

vi.mock('../../api', async (original) => ({
  ...await original<typeof import('../../api')>(),
  adminStatistics: vi.fn(),
}))

const emptyData = {
  registeredUsers: 0,
  publishedCopies: 0,
  loanRequests: 0,
  completedLoans: 0,
  weeklyLoanRequests: Array.from({ length: 8 }, (_, index) => ({
    weekStart: new Date(Date.UTC(2026, 8, 7 + index * 7)).toISOString().slice(0, 10),
    count: 0,
  })),
}

afterEach(() => { cleanup(); vi.resetAllMocks() })

it('renders statistics and eight empty weeks', async () => {
  vi.mocked(adminStatistics).mockResolvedValue(emptyData)
  render(<AdminDashboard />)
  expect(await screen.findByText('Utenti registrati')).toBeTruthy()
  expect(screen.getAllByText('0')).toHaveLength(12)
  expect(screen.getByText('Nessuna richiesta nelle ultime 8 settimane.')).toBeTruthy()
  expect(screen.getAllByRole('listitem')).toHaveLength(8)
})
