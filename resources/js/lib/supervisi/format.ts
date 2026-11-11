import { format, parseISO } from "date-fns"
import { id as localeId } from "date-fns/locale"

import type { StatusSupervisi } from "@/lib/supervisi/types"

/** Target sentuh 44px di ponsel, ukuran bawaan (32px) mulai `sm`. */
export const SENTUH = "h-11 sm:h-8"
/** Sama untuk SelectTrigger, yang tingginya dipasang lewat varian `data-[size=default]`. */
export const SELECT_SENTUH = "data-[size=default]:h-11 sm:data-[size=default]:h-8"

export const STATUS_LABEL: Record<StatusSupervisi, string> = {
  DIJADWALKAN: "Dijadwalkan",
  SELESAI: "Selesai",
  DIBATALKAN: "Dibatalkan",
}

export function statusVariant(status: StatusSupervisi): "success" | "warning" | "outline" {
  if (status === "SELESAI") return "success"
  if (status === "DIJADWALKAN") return "warning"
  return "outline"
}

/** "Sel, 10 Nov 2026" */
export function formatHari(iso: string): string {
  return format(parseISO(iso), "EEE, d MMM yyyy", { locale: localeId })
}

/** "Selasa, 10 November 2026" */
export function formatHariPanjang(iso: string): string {
  return format(parseISO(iso), "EEEE, d MMMM yyyy", { locale: localeId })
}

/** 87,5 / 100 / "-" */
export function formatNilai(nilai: number | null | undefined): string {
  if (nilai === null || nilai === undefined) return "-"
  return new Intl.NumberFormat("id-ID", { maximumFractionDigits: 2 }).format(nilai)
}

/** JUMLAH SCORE: jumlah skor butir yang sudah diisi. */
export function jumlahSkor(skor: (number | null)[]): number {
  return skor.reduce<number>((total, s) => total + (s ?? 0), 0)
}

/**
 * TOTAL SCORE = Jumlah Score / (jumlah butir x maks) x 100 (= Jumlah Score / 56 x 100 untuk 14 butir), dibulatkan dua
 * desimal seperti PenilaianSupervisi::total di server. null bila belum ada butir.
 */
export function totalSkor(jumlah: number, jumlahButir: number, maks: number): number | null {
  if (jumlahButir === 0) return null
  return Math.round((jumlah / (jumlahButir * maks)) * 10000) / 100
}

/** Pesan galat pertama dari `errors` Inertia (validasi atau `message` dari abort 4xx). */
export function pesanGalat(errors: Record<string, string>): string {
  return errors.message ?? Object.values(errors)[0] ?? "Permintaan ditolak"
}
