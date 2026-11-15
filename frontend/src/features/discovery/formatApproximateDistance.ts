import type { ApproximateDistance } from '../../api'

export function formatApproximateDistance(distance: ApproximateDistance): string {
  return distance.kind === 'less_than_km'
    ? `meno di ${distance.kilometres} km`
    : `circa ${distance.kilometres} km`
}
