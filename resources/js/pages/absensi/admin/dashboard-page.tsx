import * as React from "react"
import { Link } from "@inertiajs/react"
import { CheckCircle2, ClipboardList, Clock, FileText, QrCode, Users, XCircle } from "lucide-react"

import { cn, initials } from "@/lib/utils"
import { assetUrl } from "@/lib/absensi/api"
import type { Guru, LeaveRequest, RekapRow } from "@/lib/absensi/types"
import { statusLabel } from "@/lib/absensi/attendance-format"
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import { Button } from "@/components/ui/button"
import { Card, CardAction, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { AttendanceTrendChart, type TrendPoint } from "@/components/absensi/charts/attendance-trend-chart"

const TREND_DAYS = 30

const STATUS_CARDS = [
  { status: "HADIR", icon: CheckCircle2, tint: "bg-emerald-500/5 text-emerald-600" },
  { status: "TELAT", icon: Clock, tint: "bg-amber-500/5 text-amber-600" },
  { status: "ALPA", icon: XCircle, tint: "bg-destructive/5 text-destructive" },
] as const

function GuruChip({ nama, fotoUrl }: { nama: string; fotoUrl?: string | null }) {
  return (
    <div className="flex w-14 flex-col items-center gap-1 text-center">
      <Avatar>
        {fotoUrl && <AvatarImage src={assetUrl(fotoUrl)} alt={nama} />}
        <AvatarFallback>{initials(nama)}</AvatarFallback>
      </Avatar>
      <span className="line-clamp-2 text-[11px] leading-tight text-muted-foreground">{nama}</span>
    </div>
  )
}

interface Props {
  guruList: Guru[]
  today: RekapRow[]
  pendingIzin: LeaveRequest[]
  trend: TrendPoint[]
}

export function AdminDashboardPage({ guruList, today, pendingIzin, trend }: Props) {
  const guruById = React.useMemo(() => new Map(guruList.map((g) => [g.id, g])), [guruList])

  const aktifCount = guruList.filter((g) => g.aktif).length
  const hadirCount = today.filter((r) => r.status === "HADIR" || r.status === "TELAT").length
  const telatCount = today.filter((r) => r.status === "TELAT").length

  const stats = [
    { label: "Guru Aktif", value: aktifCount, icon: Users },
    { label: "Hadir Hari Ini", value: hadirCount, icon: ClipboardList },
    { label: "Telat Hari Ini", value: telatCount, icon: ClipboardList },
    { label: "Izin Menunggu", value: pendingIzin.length, icon: FileText },
  ]

  return (
    <div className="flex flex-col gap-6">
      <div>
        <h1 className="text-xl font-semibold">Dashboard Absensi</h1>
        <p className="text-sm text-muted-foreground">Ringkasan absensi hari ini</p>
      </div>

      <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
        {stats.map((stat) => (
          <Card key={stat.label}>
            <CardHeader className="pb-2">
              <CardTitle className="text-sm font-medium text-muted-foreground">
                {stat.label}
              </CardTitle>
              <CardAction>
                <stat.icon className="size-4 text-muted-foreground" />
              </CardAction>
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-semibold">{stat.value}</div>
            </CardContent>
          </Card>
        ))}
      </div>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">Status Kehadiran Hari Ini</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="grid gap-4 sm:grid-cols-3 lg:grid-cols-2">
            {STATUS_CARDS.map(({ status, icon: Icon, tint }) => {
              const rows = today.filter((r) => r.status === status)
              return (
                <div
                  key={status}
                  data-kartu-dalam
                  className={cn(
                    "rounded-lg border p-4",
                    tint,
                    status === "HADIR" && "lg:col-span-2"
                  )}
                >
                  <div className="flex items-center gap-2 text-sm font-medium">
                    <Icon className="size-4" />
                    {statusLabel(status)}
                    <span className="font-normal text-muted-foreground">({rows.length})</span>
                  </div>
                  {rows.length === 0 ? (
                    <p className="mt-3 text-xs text-muted-foreground">Tidak ada</p>
                  ) : (
                    <div className="mt-3 grid grid-cols-[repeat(auto-fill,minmax(3.5rem,1fr))] justify-items-center gap-3">
                      {rows.map((row) => (
                        <GuruChip
                          key={row.guruId}
                          nama={row.nama}
                          fotoUrl={guruById.get(row.guruId)?.fotoUrl}
                        />
                      ))}
                    </div>
                  )}
                </div>
              )
            })}
          </div>
        </CardContent>
      </Card>

      {/* Notifikasi tidak lagi di dashboard: dipusatkan di lonceng topbar Menu Utama. */}
      <div className="grid gap-4 lg:grid-cols-3">
        <Card className="lg:col-span-2">
          <CardHeader>
            <CardTitle className="text-base">
              Tren Kehadiran ({TREND_DAYS} Hari Terakhir)
            </CardTitle>
          </CardHeader>
          <CardContent>
            <AttendanceTrendChart data={trend} />
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="text-base">Aksi Cepat</CardTitle>
          </CardHeader>
          <CardContent className="flex flex-col gap-3">
            <Button render={<Link href="/kiosk" />}>
              <QrCode /> Buka Layar Kiosk
            </Button>
            <Button variant="outline" render={<Link href="/data-guru" />}>
              <Users /> Kelola Data Guru
            </Button>
            <Button variant="outline" render={<Link href="/rekap" />}>
              <ClipboardList /> Lihat Rekap Absensi
            </Button>
          </CardContent>
        </Card>
      </div>
    </div>
  )
}
