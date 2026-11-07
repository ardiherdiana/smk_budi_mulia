import type { StatusKehadiran } from "./types"

export const STATUS_OPTIONS: StatusKehadiran[] = ["HADIR", "TELAT", "IZIN", "SAKIT", "ALPA"]

export function toDateInputValue(date: Date): string {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, "0")
  const day = String(date.getDate()).padStart(2, "0")
  return `${year}-${month}-${day}`
}

export function todayIso(): string {
  return toDateInputValue(new Date())
}

export function startOfMonthIso(): string {
  const now = new Date()
  return toDateInputValue(new Date(now.getFullYear(), now.getMonth(), 1))
}

export function daysAgoIso(days: number): string {
  const date = new Date()
  date.setDate(date.getDate() - days)
  return toDateInputValue(date)
}

export function statusLabel(status: StatusKehadiran): string {
  switch (status) {
    case "HADIR":
      return "Hadir"
    case "TELAT":
      return "Telat"
    case "ALPA":
      return "Alpa"
    case "IZIN":
      return "Izin"
    case "SAKIT":
      return "Sakit"
  }
}

export function statusBadgeVariant(
  status: StatusKehadiran
): "success" | "warning" | "destructive" | "outline" {
  switch (status) {
    case "HADIR":
      return "success"
    case "TELAT":
      return "warning"
    case "ALPA":
      return "destructive"
    case "IZIN":
    case "SAKIT":
      return "outline"
  }
}

export function formatJam(iso: string | null): string {
  if (!iso) return "-"
  return new Date(iso).toLocaleTimeString("id-ID", {
    hour: "2-digit",
    minute: "2-digit",
  })
}

/** ISO datetime -> "HH:mm" (local time), for prefilling a <TimePicker>. */
export function toTimeInputValue(iso: string | null): string {
  if (!iso) return ""
  const date = new Date(iso)
  const hh = String(date.getHours()).padStart(2, "0")
  const mm = String(date.getMinutes()).padStart(2, "0")
  return `${hh}:${mm}`
}

export function formatTanggal(iso: string): string {
  return new Date(iso).toLocaleDateString("id-ID", {
    weekday: "short",
    day: "2-digit",
    month: "short",
    year: "numeric",
  })
}
