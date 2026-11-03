import { clsx, type ClassValue } from "clsx"
import { twMerge } from "tailwind-merge"

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs))
}

export function initials(nama: string): string {
  return nama
    .split(" ")
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]!.toUpperCase())
    .join("")
}

// Modul SPPD: `HH:MM:SS` (kolom `time` MySQL) atau `HH:MM` -> `HH:MM`.
export function formatJam(value: string | null | undefined) {
  return value ? value.slice(0, 5) : "-"
}

// Modul SPPD: Laravel serializes `date`/`datetime` casts to full ISO timestamps
// even for date-only fields, so this formats them as a short Indonesian date.
export function formatDate(
  value: string | null | undefined,
  options: Intl.DateTimeFormatOptions = { day: "2-digit", month: "short", year: "numeric" },
) {
  if (!value) return "-"
  return new Date(value).toLocaleDateString("id-ID", options)
}
