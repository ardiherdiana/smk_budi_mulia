import * as React from "react"
import { Link, router } from "@inertiajs/react"
import { toast } from "sonner"
import { CalendarDays, Flame, LocateFixed, MapPin, QrCode } from "lucide-react"

import { useAuth } from "@/context/auth-context"
import { api, ApiError, assetUrl } from "@/lib/absensi/api"
import type { Attendance, CheckinResult, GuruAttendanceDetail } from "@/lib/absensi/types"
import { distanceMeters } from "@/lib/absensi/geo"
import { AttendanceHeatmap } from "@/components/absensi/attendance-heatmap"
import { CheckinLocationMap } from "@/components/absensi/checkin-location-map"
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Card, CardAction, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import { PushReminderToggle } from "@/components/absensi/push-reminder-toggle"
import { statusBadgeVariant, statusLabel, startOfMonthIso } from "@/lib/absensi/attendance-format"

interface SchoolLocation {
  lat: number
  lng: number
  radiusMeters: number
}

function getCurrentPosition(): Promise<GeolocationPosition> {
  return new Promise((resolve, reject) => {
    if (!navigator.geolocation) {
      reject(new Error("Perangkat/browser ini tidak mendukung lokasi"))
      return
    }
    navigator.geolocation.getCurrentPosition(resolve, reject, {
      enableHighAccuracy: true,
      timeout: 10_000,
    })
  })
}

function geolocationErrorMessage(err: unknown): string {
  if (err instanceof GeolocationPositionError) {
    if (err.code === err.PERMISSION_DENIED) {
      return "Izin lokasi ditolak. Aktifkan izin lokasi untuk browser ini lalu coba lagi."
    }
    if (err.code === err.TIMEOUT) {
      return "Gagal mendapatkan lokasi (timeout). Coba lagi di tempat dengan sinyal GPS lebih baik."
    }
    return "Gagal mendapatkan lokasi perangkat."
  }
  return err instanceof Error ? err.message : "Gagal mendapatkan lokasi perangkat."
}

interface Props {
  today: Attendance | null
  detail: GuruAttendanceDetail
}

export function GuruDashboardPage({ today, detail }: Props) {
  const { user } = useAuth()
  const [checkingInWeb, setCheckingInWeb] = React.useState(false)
  const [schoolLocation, setSchoolLocation] = React.useState<SchoolLocation | null>(null)
  const [myPosition, setMyPosition] = React.useState<{ lat: number; lng: number } | null>(null)
  const [locatingMe, setLocatingMe] = React.useState(false)
  const [locationError, setLocationError] = React.useState<string | null>(null)
  const [heatmapOpen, setHeatmapOpen] = React.useState(false)

  React.useEffect(() => {
    api.get<SchoolLocation>("/school-location").then(setSchoolLocation)
  }, [])

  const locateMe = React.useCallback(async () => {
    setLocatingMe(true)
    setLocationError(null)
    try {
      const position = await getCurrentPosition()
      setMyPosition({ lat: position.coords.latitude, lng: position.coords.longitude })
    } catch (err) {
      setLocationError(geolocationErrorMessage(err))
    } finally {
      setLocatingMe(false)
    }
  }, [])

  React.useEffect(() => {
    locateMe()
  }, [locateMe])

  async function handleCheckinWeb() {
    setCheckingInWeb(true)
    try {
      const position = await getCurrentPosition()
      setMyPosition({ lat: position.coords.latitude, lng: position.coords.longitude })
      const result = await api.post<CheckinResult>("/checkin-web", {
        lat: position.coords.latitude,
        lng: position.coords.longitude,
      })
      toast.success(result.type === "MASUK" ? "Absen masuk berhasil" : "Absen pulang berhasil")
      router.reload({ only: ["today", "detail"] })
    } catch (err) {
      if (err instanceof ApiError) {
        toast.error(err.message)
      } else {
        toast.warning(geolocationErrorMessage(err))
      }
    } finally {
      setCheckingInWeb(false)
    }
  }

  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <Avatar size="lg">
            {user?.guru?.fotoUrl && (
              <AvatarImage src={assetUrl(user.guru.fotoUrl)} alt={user.guru.nama} />
            )}
            <AvatarFallback>
              {(user?.guru?.nama ?? user?.username ?? "?").slice(0, 1).toUpperCase()}
            </AvatarFallback>
          </Avatar>
          <div>
            <h1 className="text-xl font-semibold">
              Halo, {user?.guru?.nama ?? user?.username}
            </h1>
            <p className="text-sm text-muted-foreground">
              Berikut status kehadiran Anda hari ini
            </p>
          </div>
        </div>
        <PushReminderToggle />
      </div>

      <Card className="border-orange-500/20 bg-orange-500/5">
        <CardContent className="flex flex-wrap items-center justify-between gap-4 py-5">
          <div className="flex items-center gap-4">
            <div className="flex size-12 shrink-0 items-center justify-center rounded-full bg-orange-500/10 text-orange-500">
              <Flame className="size-6" />
            </div>
            <div>
              <div className="text-2xl font-semibold">{detail.streak} hari beruntun</div>
              <p className="text-sm text-muted-foreground">
                Streak kehadiran Anda - reset hanya kalau Alpa, Izin/Sakit/Telat tetap lanjut
              </p>
            </div>
          </div>
          <Button
            variant="outline"
            className="w-full sm:w-auto"
            onClick={() => setHeatmapOpen(true)}
          >
            <CalendarDays /> Lihat Kalender Kehadiran
          </Button>
        </CardContent>
      </Card>

      <Dialog open={heatmapOpen} onOpenChange={setHeatmapOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Kalender Kehadiran Bulan Ini</DialogTitle>
          </DialogHeader>
          <AttendanceHeatmap monthStart={startOfMonthIso()} days={detail.days} />
        </DialogContent>
      </Dialog>

      <div className="grid grid-cols-2 gap-4">
        <Card>
          <CardHeader>
            <CardTitle className="text-sm text-muted-foreground">Absen Masuk</CardTitle>
          </CardHeader>
          <CardContent>
            {today?.jamMasuk ? (
              <div className="flex flex-col items-start gap-1 sm:flex-row sm:items-center sm:gap-2">
                <span className="text-2xl font-semibold">
                  {new Date(today.jamMasuk).toLocaleTimeString("id-ID", {
                    hour: "2-digit",
                    minute: "2-digit",
                  })}
                </span>
                {today.statusMasuk && (
                  <Badge variant={statusBadgeVariant(today.statusMasuk)}>
                    {statusLabel(today.statusMasuk)}
                  </Badge>
                )}
              </div>
            ) : (
              <p className="text-sm text-muted-foreground">Belum absen masuk</p>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="text-sm text-muted-foreground">Absen Pulang</CardTitle>
          </CardHeader>
          <CardContent>
            {today?.jamPulang ? (
              <span className="text-2xl font-semibold">
                {new Date(today.jamPulang).toLocaleTimeString("id-ID", {
                  hour: "2-digit",
                  minute: "2-digit",
                })}
              </span>
            ) : (
              <p className="text-sm text-muted-foreground">Belum absen pulang</p>
            )}
          </CardContent>
        </Card>
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <Card className="order-2 lg:order-1">
          <CardHeader>
            <CardTitle className="text-base">Lokasi Absen</CardTitle>
            <CardAction>
              <Button variant="outline" size="sm" disabled={locatingMe} onClick={locateMe}>
                <LocateFixed /> {locatingMe ? "Mencari lokasi..." : "Segarkan Lokasi Saya"}
              </Button>
            </CardAction>
          </CardHeader>
          <CardContent className="flex flex-col gap-3">
            <p className="text-sm text-muted-foreground">
              🏫 Titik sekolah dan radius {schoolLocation?.radiusMeters ?? "-"}m - absen lewat
              lokasi (GPS) cuma bisa dilakukan di dalam lingkaran hijau. 📍 Menandai posisi Anda
              sekarang.
            </p>
            {schoolLocation ? (
              <CheckinLocationMap
                schoolLat={schoolLocation.lat}
                schoolLng={schoolLocation.lng}
                radiusMeters={schoolLocation.radiusMeters}
                userPosition={myPosition}
              />
            ) : (
              <p className="text-sm text-muted-foreground">Memuat peta...</p>
            )}
            {schoolLocation && myPosition && (
              <p className="text-sm text-muted-foreground">
                Jarak Anda dari sekolah: sekitar{" "}
                <span className="font-medium text-foreground">
                  {Math.round(
                    distanceMeters(
                      myPosition.lat,
                      myPosition.lng,
                      schoolLocation.lat,
                      schoolLocation.lng
                    )
                  )}
                  m
                </span>
              </p>
            )}
            {locationError && (
              <p className="text-sm text-destructive" role="alert">
                {locationError}
              </p>
            )}
          </CardContent>
        </Card>

        <Card className="order-1 lg:order-2">
          <CardContent className="flex h-full flex-col items-center justify-center gap-4 py-8 text-center">
            <div className="flex size-20 items-center justify-center rounded-full bg-primary/10 text-primary">
              <MapPin className="size-10" />
            </div>
            <p className="text-sm text-muted-foreground">
              Absen bisa lewat lokasi (GPS) di sini, atau tunjukkan QR Anda ke alat scan di
              sekolah
            </p>
            <Button size="lg" disabled={checkingInWeb} onClick={handleCheckinWeb}>
              <MapPin /> {checkingInWeb ? "Mengecek lokasi..." : "Absen Lewat Lokasi (GPS)"}
            </Button>
            <Button variant="outline" render={<Link href="/qr" />}>
              <QrCode /> Lihat QR Saya
            </Button>
            <p className="text-xs text-muted-foreground">
              Absen GPS cuma bisa dipakai kalau Anda sedang berada di lokasi sekolah
            </p>
          </CardContent>
        </Card>
      </div>
    </div>
  )
}
