// Warna spanduk kartu/header ruang kelas. Hanya token tema (bukan hex mentah) supaya terang dan gelap tetap terbaca.
const WARNA = [
  "bg-primary text-primary-foreground",
  "bg-sidebar-primary text-sidebar-primary-foreground",
  "bg-success text-white dark:bg-success/60",
  "bg-secondary-foreground text-secondary",
  "bg-chart-4 text-primary-foreground",
] as const

/** Warna tetap per ruang kelas (dari id), jadi kartu dan header kelas yang sama selalu berwarna sama. */
export function warnaKelas(id: string): string {
  let hash = 0
  for (const char of id) hash = (hash * 31 + char.charCodeAt(0)) >>> 0
  return WARNA[hash % WARNA.length]
}
