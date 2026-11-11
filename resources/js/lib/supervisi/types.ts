/** Bentuk data modul Supervisi (lihat App\Services\Supervisi\SupervisiPresenter). */

export type StatusSupervisi = "DIJADWALKAN" | "SELESAI" | "DIBATALKAN"

export interface Orang {
  id: number
  nama: string
}

export interface SupervisiRingkas {
  id: string
  tanggal: string
  status: StatusSupervisi
  tahunAjaranId: string
  tahunAjaran: string | null
  /** Penyusun Perencanaan. */
  guru: Orang | null
  /** Pemberi Umpan Balik. */
  supervisor: Orang | null
  mapelId: string | null
  mapel: string | null
  rombelId: string | null
  rombel: string | null
  /** JUMLAH SCORE dan TOTAL SCORE, hanya terisi setelah SELESAI. */
  jumlahSkor: number | null
  totalSkor: number | null
}

export interface SkorButir {
  id: string
  kategori: string
  uraian: string
  skor: number | null
  catatan: string | null
}

export interface Penilaian {
  /** 5 Kelebihan Perencanaan */
  kelebihan: string | null
  /** 6 Hal yang perlu ditingkatkan */
  halDitingkatkan: string | null
  /** 7 Rekomendasi Perbaikan */
  rekomendasi: string | null
  skor: SkorButir[]
}

export interface SupervisiLembar extends SupervisiRingkas {
  selesaiAt: string | null
  /** null bila pengguna belum boleh melihat penilaian (guru sebelum selesai). */
  penilaian: Penilaian | null
}

export interface TahunAjaranOpsi {
  id: string
  label: string
  aktif: boolean
}

export interface MenuSupervisi {
  kelola: boolean
  tugas: boolean
  disupervisi: boolean
}
