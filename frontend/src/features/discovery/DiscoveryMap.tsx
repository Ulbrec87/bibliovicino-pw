import { BookCopyCover } from '../library/BookCopyCover'
import { Link } from 'react-router'
import { useEffect, useMemo } from 'react'
import { latLng } from 'leaflet'
import { Circle, CircleMarker, MapContainer, Popup, TileLayer, useMap } from 'react-leaflet'
import type { DiscoveryBookCopy, DiscoveryLocation, DiscoverySearchRadiusKm } from '../../api'
import { formatApproximateDistance } from './formatApproximateDistance'
import { mapTiles } from './mapTiles'

type Point = DiscoveryLocation['representativePoint']
type OwnerGroup = {
  id: string
  displayName: string
  point: Point
  distance: DiscoveryBookCopy['approximateDistance']
  books: DiscoveryBookCopy[]
}

type DiscoveryMapProps = {
  origin: Point
  radiusKm: DiscoverySearchRadiusKm
  results: DiscoveryBookCopy[]
}

const MAX_MAP_ZOOM = 14

function groupByOwner(results: DiscoveryBookCopy[]): OwnerGroup[] {
  const groups = new Map<string, OwnerGroup>()

  for (const result of results) {
    const existing = groups.get(result.owner.id)
    if (existing) {
      existing.books.push(result)
      continue
    }

    groups.set(result.owner.id, {
      id: result.owner.id,
      displayName: result.owner.displayName,
      point: result.approximatePoint,
      distance: result.approximateDistance,
      books: [result],
    })
  }

  return [...groups.values()]
}

function MapViewport({ origin, radiusKm, groups }: {
  origin: Point
  radiusKm: DiscoverySearchRadiusKm
  groups: OwnerGroup[]
}) {
  const map = useMap()

  useEffect(() => {
    const bounds = latLng(origin.latitude, origin.longitude).toBounds(radiusKm * 2000)

    for (const group of groups) {
      bounds.extend([group.point.latitude, group.point.longitude])
    }

    function adjustViewport() {
      map.invalidateSize({ pan: false })
      map.fitBounds(bounds, { padding: [24, 24], maxZoom: MAX_MAP_ZOOM, animate: false })
    }

    adjustViewport()
    window.addEventListener('resize', adjustViewport)
    return () => window.removeEventListener('resize', adjustViewport)
  }, [map, origin, radiusKm, groups])

  return null
}

function OwnerPreview({ owner }: { owner: OwnerGroup }) {
  return (
    <div className="discovery-map__owner">
      <strong>{owner.displayName}</strong>
      <p>Zona approssimativa · {formatApproximateDistance(owner.distance)}</p>
      <ul>
        {owner.books.map((copy) => (
          <li key={copy.bookCopyId}>
            <BookCopyCover title={copy.book.title} cover={copy.cover} className="map-cover" thumbnailOnly />
            <strong><Link to={`/copie/${copy.bookCopyId}`}>{copy.book.title}</Link></strong>
            {copy.book.authors.length > 0 && <span> · {copy.book.authors.join(', ')}</span>}
            <span> · Prestito</span>
          </li>
        ))}
      </ul>
    </div>
  )
}

export function DiscoveryMap({ origin, radiusKm, results }: DiscoveryMapProps) {
  const groups = useMemo(() => groupByOwner(results), [results])
  // Proprietari diversi possono condividere lo stesso punto approssimato; un solo marker permette di consultarli tutti.
  const groupsByPoint = useMemo(() => {
    const byPoint = new Map<string, OwnerGroup[]>()

    for (const group of groups) {
      const key = `${group.point.latitude},${group.point.longitude}`
      const atPoint = byPoint.get(key)
      if (atPoint) atPoint.push(group)
      else byPoint.set(key, [group])
    }

    return [...byPoint.entries()]
  }, [groups])

  return (
    <section className="discovery-map" aria-labelledby="discovery-map-title">
      <div className="discovery-map__heading">
        <div>
          <p className="section-kicker">Nella zona selezionata</p>
          <h2 id="discovery-map-title">Mappa dei libri vicini</h2>
        </div>
        <p>Zona di ricerca approssimativa · entro {radiusKm} km</p>
      </div>
      <p className="discovery-map__legend">
        <span className="discovery-map__legend-item">
          <span className="discovery-map__legend-symbol discovery-map__legend-symbol--origin" aria-hidden="true" />
          Centro approssimativo della ricerca
        </span>
        <span className="discovery-map__legend-item">
          <span className="discovery-map__legend-symbol discovery-map__legend-symbol--owner" aria-hidden="true" />
          Zone dei libri
        </span>
      </p>
      <div className="discovery-map__frame">
        <MapContainer
          center={[origin.latitude, origin.longitude]}
          zoom={12}
          maxZoom={MAX_MAP_ZOOM}
          className="discovery-map__canvas"
          scrollWheelZoom={false}
        >
          <TileLayer url={mapTiles.url} attribution={mapTiles.attribution} maxZoom={mapTiles.maxZoom} />
          <Circle
            center={[origin.latitude, origin.longitude]}
            radius={radiusKm * 1000}
            pathOptions={{ color: '#155c3a', fillColor: '#155c3a', fillOpacity: 0.08, weight: 1.5 }}
            interactive={false}
          />
          <CircleMarker
            center={[origin.latitude, origin.longitude]}
            radius={15}
            pathOptions={{ color: '#155c3a', fillColor: '#fffdf8', fillOpacity: 0.65, weight: 4 }}
            interactive={false}
          />
          {groupsByPoint.map(([pointKey, owners]) => (
            <CircleMarker
              key={pointKey}
              center={[owners[0].point.latitude, owners[0].point.longitude]}
              radius={7}
              pathOptions={{ color: '#fffdf8', fillColor: '#d49332', fillOpacity: 1, weight: 2 }}
            >
              <Popup>
                {owners.map((owner) => <OwnerPreview key={owner.id} owner={owner} />)}
              </Popup>
            </CircleMarker>
          ))}
          <MapViewport origin={origin} radiusKm={radiusKm} groups={groups} />
        </MapContainer>
      </div>
      <p className="discovery-map__privacy">I punti indicano zone approssimative, non indirizzi o abitazioni. La tua posizione esatta non viene mostrata.</p>
    </section>
  )
}
