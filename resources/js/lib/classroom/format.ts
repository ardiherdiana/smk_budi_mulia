import { format, formatDistanceToNowStrict, isPast } from "date-fns"
import { id } from "date-fns/locale"

export function formatTanggal(value: string | null | undefined): string {
  return value ? format(new Date(value), "d MMM yyyy", { locale: id }) : "-"
}

export function formatWaktu(value: string | null | undefined): string {
  return value ? format(new Date(value), "d MMM yyyy, HH:mm", { locale: id }) : "-"
}

/** "3 jam lagi" / "2 hari yang lalu" untuk tenggat dan waktu posting. */
export function formatRelatif(value: string | null | undefined): string {
  if (!value) return "-"
  const date = new Date(value)
  const jarak = formatDistanceToNowStrict(date, { locale: id })
  return isPast(date) ? `${jarak} yang lalu` : `${jarak} lagi`
}

export function formatUkuran(bytes: number | null | undefined): string {
  if (!bytes) return ""
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`
  return `${(bytes / 1024 / 1024).toFixed(1)} MB`
}

/** Angka tanpa nol di belakang koma: 85 -> "85", 12.5 -> "12,5". */
export function formatAngka(value: number | null | undefined): string {
  if (value === null || value === undefined) return "-"
  return new Intl.NumberFormat("id-ID", { maximumFractionDigits: 2 }).format(value)
}

/** Nilai untuk `<input type="datetime-local">` dari ISO server (waktu lokal peramban). */
export function keInputWaktu(value: string | null | undefined): string {
  return value ? format(new Date(value), "yyyy-MM-dd'T'HH:mm") : ""
}

/** Pesan galat pertama dari objek error Inertia. */
export function pesanError(errors: Record<string, string>): string | undefined {
  return Object.values(errors)[0]
}
