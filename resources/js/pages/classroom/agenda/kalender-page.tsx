import { Link, router } from "@inertiajs/react"
import {
  addMonths,
  eachDayOfInterval,
  endOfMonth,
  endOfWeek,
  format,
  isSameMonth,
  isToday,
  startOfMonth,
  startOfWeek,
} from "date-fns"
import { id as localeId } from "date-fns/locale"
import { ChevronLeft, ChevronRight } from "lucide-react"

import type { ItemAgenda } from "@/lib/classroom/types"
import { warnaKelas } from "@/lib/classroom/kelas-warna"
import { cn } from "@/lib/classroom/utils"
import { Button } from "@/components/ui/button"
import { Card, CardContent } from "@/components/ui/card"

interface Props {
  /** Format YYYY-MM. */
  bulan: string
  items: ItemAgenda[]
}

const NAMA_HARI = ["Sen", "Sel", "Rab", "Kam", "Jum", "Sab", "Min"]

function Chip({ item }: { item: ItemAgenda }) {
  return (
    <Link
      href={`/classroom/post/${item.id}`}
      title={`${item.judul} · ${item.mapel} ${item.rombel}`}
      className={cn("block truncate rounded px-1.5 py-0.5 text-xs", warnaKelas(item.kelasId))}
    >
      {item.tenggat ? `${format(new Date(item.tenggat), "HH:mm")} ` : ""}
      {item.judul}
    </Link>
  )
}

export function KalenderPage({ bulan, items }: Props) {
  const acuan = new Date(`${bulan}-01T00:00:00`)
  const hari = eachDayOfInterval({
    start: startOfWeek(startOfMonth(acuan), { weekStartsOn: 1 }),
    end: endOfWeek(endOfMonth(acuan), { weekStartsOn: 1 }),
  })

  const perHari = new Map<string, ItemAgenda[]>()
  for (const item of items) {
    if (!item.tenggat) continue
    const kunci = format(new Date(item.tenggat), "yyyy-MM-dd")
    perHari.set(kunci, [...(perHari.get(kunci) ?? []), item])
  }

  function pindah(selisih: number) {
    router.get("/classroom/kalender", { bulan: format(addMonths(acuan, selisih), "yyyy-MM") }, { preserveScroll: true })
  }

  return (
    <div className="flex w-full flex-col gap-4">
      <div className="flex items-center justify-between gap-3">
        <div>
          <h1 className="text-xl font-semibold">Kalender</h1>
          <p className="text-sm text-muted-foreground capitalize">{format(acuan, "MMMM yyyy", { locale: localeId })}</p>
        </div>
        <div className="flex items-center gap-1">
          <Button variant="outline" size="icon" aria-label="Bulan sebelumnya" onClick={() => pindah(-1)}>
            <ChevronLeft />
          </Button>
          <Button variant="outline" onClick={() => router.get("/classroom/kalender")}>
            Hari ini
          </Button>
          <Button variant="outline" size="icon" aria-label="Bulan berikutnya" onClick={() => pindah(1)}>
            <ChevronRight />
          </Button>
        </div>
      </div>

      {/* Layar lebar: grid bulan. */}
      <Card className="hidden md:block">
        <CardContent className="p-0">
          <div className="grid grid-cols-7 border-b text-center text-xs text-muted-foreground">
            {NAMA_HARI.map((n) => (
              <div key={n} className="py-2">
                {n}
              </div>
            ))}
          </div>
          <div className="grid grid-cols-7">
            {hari.map((tgl) => {
              const daftar = perHari.get(format(tgl, "yyyy-MM-dd")) ?? []
              const bulanIni = isSameMonth(tgl, acuan)
              return (
                <div
                  key={tgl.toISOString()}
                  className={cn("min-h-24 border-r border-b p-1.5 [&:nth-child(7n)]:border-r-0", !bulanIni && "bg-muted/40")}
                >
                  <span
                    className={cn(
                      "mb-1 inline-flex size-6 items-center justify-center rounded-full text-xs",
                      isToday(tgl) ? "bg-primary text-primary-foreground" : !bulanIni && "text-muted-foreground",
                    )}
                  >
                    {format(tgl, "d")}
                  </span>
                  <div className="flex flex-col gap-1">
                    {daftar.map((item) => (
                      <Chip key={item.id} item={item} />
                    ))}
                  </div>
                </div>
              )
            })}
          </div>
        </CardContent>
      </Card>

      {/* Ponsel: daftar per hari yang punya tenggat. */}
      <div className="flex flex-col gap-3 md:hidden">
        {perHari.size === 0 && <p className="py-8 text-center text-sm text-muted-foreground">Tidak ada tenggat bulan ini</p>}
        {hari
          .filter((tgl) => perHari.has(format(tgl, "yyyy-MM-dd")))
          .map((tgl) => (
            <section key={tgl.toISOString()} className="flex flex-col gap-1">
              <h2 className="text-sm font-medium">{format(tgl, "EEEE, d MMMM", { locale: localeId })}</h2>
              {perHari.get(format(tgl, "yyyy-MM-dd"))?.map((item) => (
                <Chip key={item.id} item={item} />
              ))}
            </section>
          ))}
      </div>
    </div>
  )
}
