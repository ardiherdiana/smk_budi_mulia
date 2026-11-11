import * as React from "react"
import { router } from "@inertiajs/react"
import { Bell, CheckCheck, ClipboardCheck, FileText, LogIn, LogOut, Send } from "lucide-react"

import { Button } from "@/components/ui/button"
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover"
import { cn } from "@/lib/utils"

export interface NotifikasiItem {
  id: string
  sumber: "absensi" | "sppd" | "supervisi"
  tipe: "CHECKIN" | "CHECKOUT" | "LEAVE_REQUEST" | "SPPD" | "SUPERVISI"
  judul: string
  pesan: string
  waktu: string
  dibaca: boolean
}

export interface DataNotifikasi {
  items: NotifikasiItem[]
  belumDibaca: number
}

const TIPE: Record<NotifikasiItem["tipe"], { icon: React.ComponentType<{ className?: string }>; kelas: string }> = {
  CHECKIN: { icon: LogIn, kelas: "bg-emerald-500/10 text-emerald-600" },
  CHECKOUT: { icon: LogOut, kelas: "bg-sky-500/10 text-sky-600" },
  LEAVE_REQUEST: { icon: FileText, kelas: "bg-amber-500/10 text-amber-600" },
  SPPD: { icon: Send, kelas: "bg-secondary text-secondary-foreground" },
  SUPERVISI: { icon: ClipboardCheck, kelas: "bg-secondary text-secondary-foreground" },
}

function formatWaktu(iso: string): string {
  return new Date(iso).toLocaleString("id-ID", { day: "2-digit", month: "short", hour: "2-digit", minute: "2-digit" })
}

/**
 * Lonceng notifikasi terpusat di topbar Menu Utama: gabungan notifikasi Absensi (admin dan kepsek), SPPD, dan Supervisi
 * (milik akun sendiri). Klik satu item = tandai dibaca lalu buka halamannya; server yang menentukan tujuannya.
 */
export function NotifikasiLonceng({ data }: { data: DataNotifikasi }) {
  const [buka, setBuka] = React.useState(false)
  const label = data.belumDibaca > 0 ? `Notifikasi, ${data.belumDibaca} belum dibaca` : "Notifikasi"

  function bacaSemua() {
    router.post("/notifikasi/baca-semua", {}, { preserveScroll: true, preserveState: true })
  }

  function buka1(item: NotifikasiItem) {
    setBuka(false)
    router.post(`/notifikasi/${item.sumber}/${item.id}/baca`)
  }

  return (
    <Popover open={buka} onOpenChange={setBuka}>
      <PopoverTrigger
        render={<Button variant="ghost" size="icon" className="relative" aria-label={label} title={label} />}
      >
        <Bell className="size-5" />
        {data.belumDibaca > 0 && (
          <span className="absolute top-0.5 right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-destructive px-1 text-[10px] leading-none font-semibold text-white tabular-nums">
            {data.belumDibaca > 99 ? "99+" : data.belumDibaca}
          </span>
        )}
      </PopoverTrigger>
      <PopoverContent align="end" className="w-[min(24rem,calc(100vw-2rem))] gap-0 p-0">
        <div className="flex items-center justify-between gap-2 border-b px-4 py-3">
          <div>
            <p className="font-medium">Notifikasi</p>
            <p className="text-xs text-muted-foreground">
              {data.belumDibaca > 0 ? `${data.belumDibaca} belum dibaca` : "Semua sudah dibaca"}
            </p>
          </div>
          {data.belumDibaca > 0 && (
            <Button variant="ghost" size="sm" onClick={bacaSemua}>
              <CheckCheck /> Tandai semua dibaca
            </Button>
          )}
        </div>

        {data.items.length === 0 ? (
          <div className="flex flex-col items-center gap-2 px-4 py-10 text-center text-muted-foreground">
            <Bell className="size-6" />
            <p className="text-sm">Belum ada notifikasi</p>
          </div>
        ) : (
          <ul className="max-h-[min(28rem,70svh)] divide-y overflow-y-auto">
            {data.items.map((item) => {
              const tipe = TIPE[item.tipe]
              return (
                <li key={`${item.sumber}-${item.id}`}>
                  <button
                    type="button"
                    onClick={() => buka1(item)}
                    className={cn(
                      "flex w-full items-start gap-3 px-4 py-3 text-left transition-colors hover:bg-muted focus-visible:bg-muted focus-visible:outline-none",
                      !item.dibaca && "bg-accent/40"
                    )}
                  >
                    <span className={cn("mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full", tipe.kelas)}>
                      <tipe.icon className="size-4" />
                    </span>
                    <span className="min-w-0 flex-1">
                      <span className="flex items-center gap-2">
                        <span className="truncate text-sm font-medium">{item.judul}</span>
                        {!item.dibaca && <span className="size-2 shrink-0 rounded-full bg-primary" aria-label="Belum dibaca" />}
                      </span>
                      <span className="line-clamp-2 text-sm text-muted-foreground">{item.pesan}</span>
                      <span className="text-xs text-muted-foreground">{formatWaktu(item.waktu)}</span>
                    </span>
                  </button>
                </li>
              )
            })}
          </ul>
        )}
      </PopoverContent>
    </Popover>
  )
}
