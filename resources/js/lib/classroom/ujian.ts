import * as React from "react"

import type { TipeSoal } from "@/lib/classroom/soal"

/** Satu soal di layar siswa. Kunci jawaban tidak pernah ikut dikirim. */
export interface SoalUjian {
  id: string
  tipe: TipeSoal
  teks: string
  poin: number
  opsi: string[] | null
  gambar?: string[]
  opsiGambar?: (string | null)[]
  jawaban: string | null
}

/** Sisa waktu dalam HH:MM:SS seperti layar ujian berbasis komputer. */
export function formatSisa(detik: number): string {
  const h = Math.floor(detik / 3600)
  const m = Math.floor((detik % 3600) / 60)
  const s = detik % 60
  return [h, m, s].map((n) => String(n).padStart(2, "0")).join(":")
}

/** Tiga ukuran huruf soal: kelas untuk teks soal dan pilihan, serta kelas untuk huruf "A" di pemilihnya. */
export const UKURAN_FONT = [
  { nama: "Kecil", teks: "text-sm", tombol: "text-xs" },
  { nama: "Sedang", teks: "text-base", tombol: "text-base" },
  { nama: "Besar", teks: "text-xl", tombol: "text-xl" },
] as const

export const UKURAN_FONT_AWAL = 1

/**
 * State yang ikut tersimpan di localStorage (mis. soal terakhir dibuka, tanda ragu-ragu, ukuran huruf). Semua akses
 * dibungkus try/catch karena penyimpanan bisa dimatikan (mode privat, kebijakan sekolah); tanpa itu halaman tetap jalan.
 */
export function useSimpanLokal<T>(
  kunci: string,
  awal: T,
  valid: (nilai: unknown) => nilai is T
): [T, (nilai: T | ((sebelumnya: T) => T)) => void] {
  const [nilai, setNilai] = React.useState<T>(() => {
    try {
      const mentah = window.localStorage.getItem(kunci)
      if (mentah !== null) {
        const urai: unknown = JSON.parse(mentah)
        if (valid(urai)) return urai
      }
    } catch {
      // Penyimpanan tidak tersedia atau isinya rusak: pakai nilai awal.
    }
    return awal
  })

  const ubah = React.useCallback(
    (berikutnya: T | ((sebelumnya: T) => T)) => {
      setNilai((sebelumnya) => {
        const baru = typeof berikutnya === "function" ? (berikutnya as (s: T) => T)(sebelumnya) : berikutnya
        try {
          window.localStorage.setItem(kunci, JSON.stringify(baru))
        } catch {
          // Gagal menyimpan tidak boleh mengganggu ujian.
        }
        return baru
      })
    },
    [kunci]
  )

  return [nilai, ubah]
}

export const adalahDaftarTeks = (nilai: unknown): nilai is string[] =>
  Array.isArray(nilai) && nilai.every((n) => typeof n === "string")

export const adalahBilangan = (nilai: unknown): nilai is number => typeof nilai === "number" && Number.isFinite(nilai)
