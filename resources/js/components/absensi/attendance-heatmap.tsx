import { cn } from "@/lib/utils"
import { statusLabel } from "@/lib/absensi/attendance-format"
import type { RekapRow, StatusKehadiran } from "@/lib/absensi/types"

const STATUS_COLOR: Record<StatusKehadiran, string> = {
  HADIR: "bg-emerald-500",
  TELAT: "bg-amber-400",
  IZIN: "bg-sky-400",
  SAKIT: "bg-sky-400",
  ALPA: "bg-destructive",
}

const WEEKDAY_LABELS = ["Min", "Sen", "Sel", "Rab", "Kam", "Jum", "Sab"]

const LEGEND = [
  { status: "HADIR" as const, label: "Hadir" },
  { status: "TELAT" as const, label: "Telat" },
  { status: "IZIN" as const, label: "Izin/Sakit" },
  { status: "ALPA" as const, label: "Alpa" },
]

interface AttendanceHeatmapProps {
  /** First day of the month, e.g. "2026-09-01". */
  monthStart: string
  days: RekapRow[]
}

export function AttendanceHeatmap({ monthStart, days }: AttendanceHeatmapProps) {
  const [year, month] = monthStart.split("-").map(Number)
  const statusByDate = new Map(days.map((d) => [d.tanggal, d.status]))

  const firstWeekday = new Date(Date.UTC(year, month - 1, 1)).getUTCDay()
  const daysInMonth = new Date(Date.UTC(year, month, 0)).getUTCDate()

  const cells: { date: string; status: StatusKehadiran | null }[] = []
  for (let d = 1; d <= daysInMonth; d++) {
    const date = `${year}-${String(month).padStart(2, "0")}-${String(d).padStart(2, "0")}`
    cells.push({ date, status: statusByDate.get(date) ?? null })
  }

  return (
    <div>
      <div className="grid grid-cols-7 gap-1 text-center text-[10px] text-muted-foreground">
        {WEEKDAY_LABELS.map((w) => (
          <div key={w}>{w}</div>
        ))}
      </div>
      <div className="mt-1 grid grid-cols-7 gap-1">
        {Array.from({ length: firstWeekday }).map((_, i) => (
          <div key={`pad-${i}`} />
        ))}
        {cells.map((cell) => (
          <div
            key={cell.date}
            title={`${cell.date}${cell.status ? ` - ${statusLabel(cell.status)}` : ""}`}
            className={cn(
              "aspect-square rounded-sm",
              cell.status ? STATUS_COLOR[cell.status] : "bg-muted"
            )}
          />
        ))}
      </div>
      <div className="mt-3 flex flex-wrap items-center gap-3 text-xs text-muted-foreground">
        {LEGEND.map(({ status, label }) => (
          <span key={status} className="flex items-center gap-1">
            <span className={cn("size-2.5 rounded-sm", STATUS_COLOR[status])} />
            {label}
          </span>
        ))}
        <span className="flex items-center gap-1">
          <span className="size-2.5 rounded-sm bg-muted" />
          Libur/Belum
        </span>
      </div>
    </div>
  )
}
