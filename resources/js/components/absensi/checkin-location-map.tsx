import { Circle, MapContainer, Marker, TileLayer } from "react-leaflet"
import L from "leaflet"
import "leaflet/dist/leaflet.css"

const schoolIcon = L.divIcon({
  html: '<div style="font-size:22px;line-height:1;transform:translateY(-2px)">🏫</div>',
  className: "",
  iconSize: [24, 24],
  iconAnchor: [12, 12],
})

const userIcon = L.divIcon({
  html: '<div style="font-size:22px;line-height:1;transform:translateY(-2px)">📍</div>',
  className: "",
  iconSize: [24, 24],
  iconAnchor: [12, 24],
})

interface CheckinLocationMapProps {
  schoolLat: number
  schoolLng: number
  radiusMeters: number
  userPosition: { lat: number; lng: number } | null
}

export function CheckinLocationMap({
  schoolLat,
  schoolLng,
  radiusMeters,
  userPosition,
}: CheckinLocationMapProps) {
  return (
    <div className="relative isolate h-64 w-full overflow-hidden rounded-lg border">
      <MapContainer
        center={[schoolLat, schoolLng]}
        zoom={17}
        style={{ height: "100%", width: "100%" }}
        scrollWheelZoom={false}
      >
        <TileLayer
          url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
          attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
        />
        <Circle
          center={[schoolLat, schoolLng]}
          radius={radiusMeters}
          pathOptions={{ color: "#16a34a", fillColor: "#16a34a", fillOpacity: 0.15 }}
        />
        <Marker position={[schoolLat, schoolLng]} icon={schoolIcon} />
        {userPosition && <Marker position={[userPosition.lat, userPosition.lng]} icon={userIcon} />}
      </MapContainer>
    </div>
  )
}
