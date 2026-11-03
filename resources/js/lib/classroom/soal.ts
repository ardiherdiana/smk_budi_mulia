/**
 * Bentuk soal format TKA. Kunci dan jawaban siswa memakai teks yang sama untuk tiap bentuk:
 * PG "2" (indeks pilihan), PGK_MCMA "0,2,4" (indeks yang dipilih), PGK_KATEGORI "1,0,1" (1 = Benar, 0 = Salah,
 * urut sesuai pernyataan; yang belum dijawab kosong, mis. "1,,0").
 */
export type TipeSoal = "PG" | "PGK_KATEGORI" | "PGK_MCMA" | "ESSAY"

export const URUTAN_TIPE: TipeSoal[] = ["PG", "PGK_KATEGORI", "PGK_MCMA", "ESSAY"]

export const LABEL_TIPE_SOAL: Record<TipeSoal, string> = {
  PG: "Pilihan ganda",
  PGK_KATEGORI: "PGK Kategori",
  PGK_MCMA: "PGK MCMA",
  ESSAY: "Essay",
}

export const PETUNJUK_TIPE_SOAL: Record<TipeSoal, string> = {
  PG: "Satu jawaban benar.",
  PGK_KATEGORI: "Tiap pernyataan dijawab Benar atau Salah.",
  PGK_MCMA: "Jawaban benar lebih dari satu.",
  ESSAY: "Dikoreksi guru.",
}

export const HURUF = "ABCDEFGHIJ"

/**
 * Poin tiap soal bila total dibagi rata ke 100. Dihitung dalam sen supaya jumlahnya selalu tepat 100
 * (3 soal: 33,33 / 33,33 / 33,34); sama persis dengan `PembagiPoin::bagiRata` di server.
 */
export function bagiPoin(jumlah: number): number[] {
  if (jumlah <= 0) return []
  const sen = 100 * 100
  const dasar = Math.floor(sen / jumlah)
  const sisa = sen % jumlah
  return Array.from({ length: jumlah }, (_, i) => (dasar + (i >= jumlah - sisa ? 1 : 0)) / 100)
}

/** Pecah teks kunci/jawaban per posisi; posisi kosong dipertahankan. */
export function pecahJawaban(teks: string | null | undefined): string[] {
  return teks ? teks.split(",") : []
}

/** Jawaban siswa dianggap terisi bila: PG/MCMA ada pilihan, Kategori semua pernyataan sudah dijawab, Essay ada teks. */
export function sudahDijawab(tipe: TipeSoal, jawaban: string | null | undefined, jumlahOpsi: number): boolean {
  const isi = (jawaban ?? "").trim()
  if (isi === "") return false
  if (tipe === "PGK_KATEGORI") {
    const bagian = pecahJawaban(isi)
    return bagian.length >= jumlahOpsi && bagian.slice(0, jumlahOpsi).every((b) => b === "0" || b === "1")
  }
  return true
}
