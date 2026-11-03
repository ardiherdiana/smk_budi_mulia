import { format, parseISO } from "date-fns"
import { id as localeId } from "date-fns/locale"

export function formatTanggal(iso: string | null | undefined): string {
  if (!iso) return "-"
  return format(parseISO(iso), "d MMMM yyyy", { locale: localeId })
}

export function formatTanggalWaktu(iso: string | null | undefined): string {
  if (!iso) return "-"
  return format(parseISO(iso), "d MMM yyyy, HH:mm", { locale: localeId })
}

export function formatAngka(value: number): string {
  return new Intl.NumberFormat("id-ID").format(value)
}
