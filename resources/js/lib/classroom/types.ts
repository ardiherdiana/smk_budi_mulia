import type { TipeSoal } from "@/lib/classroom/soal"

export type Role = "ADMIN" | "KEPSEK" | "GURU" | "SISWA"

export interface AuthUser {
  id: string
  username: string
  role: Role
  name: string
  fotoUrl: string | null
  siswa: { id: string; nis: string | null; rombel: string | null } | null
}

export type TipePost = "PENGUMUMAN" | "MATERI" | "TUGAS" | "UJIAN"

export interface KelasHeader {
  id: string
  mapel: string
  rombel: string
  guru: string
  tahunAjaran: string
  deskripsi: string | null
  diarsipkan: boolean
  jumlahSiswa: number
  dapatDikelola: boolean
}

export interface KelasCard {
  id: string
  mapel: string
  rombel: string
  guru: string
  tahunAjaran: string
  jumlahSiswa: number
  diarsipkan: boolean
  penanda: number
}

/** Ruang kelas yang bisa dituju saat membuat postingan ke banyak kelas. */
export interface KelasTujuan {
  id: string
  mapel: string
  rombel: string
  tingkat: "X" | "XI" | "XII"
  jurusan: string
}

/** Ruang kelas dari satu ujian (kelas ini dan kelas lain satu grup) untuk dialog ekspor nilai. */
export interface KelasUjian {
  postId: string
  kelasId: string
  rombel: string
  mapel: string
  tingkat: "X" | "XI" | "XII"
  jurusan: string
  /** Kelas tempat halaman ini dibuka. */
  utama: boolean
  jumlahSiswa: number
  /** Sudah selesai mengerjakan (termasuk yang esainya belum dikoreksi). */
  selesai: number
  perluKoreksi: number
  /** Rata-rata nilai 0-100 siswa yang sudah selesai; null bila belum ada. */
  rata: number | null
}

export interface Lampiran {
  id: string
  tipe: "FILE" | "LINK"
  nama: string
  url: string
  ukuran: number | null
  mime: string | null
}

export interface Komentar {
  id: string
  isi: string
  createdAt: string
  user: { id: string; name: string; role: Role; fotoUrl: string | null }
  dapatDihapus: boolean
}

export interface RingkasPengumpulan {
  id: string
  status: "DIKUMPULKAN" | "DINILAI"
  terlambat: boolean
  nilai: number | null
  feedback: string | null
  teks: string | null
  submittedAt: string
  gradedAt: string | null
}

export type JendelaUjian = "BELUM_DIBUKA" | "BERLANGSUNG" | "DITUTUP"

export interface Post {
  id: string
  kelasId: string
  /** Jumlah ruang kelas yang menerima postingan ini sekaligus (1 bila berdiri sendiri). */
  jumlahGrup: number
  tipe: TipePost
  judul: string | null
  isi: string | null
  deadline: string | null
  poinMaks: number | null
  izinTerlambat: boolean
  durasiMenit: number | null
  mulaiAt: string | null
  selesaiAt: string | null
  acakSoal: boolean
  tampilkanNilai: boolean
  /** Ujian wajib token untuk memulai. Tokennya sendiri hanya dikirim ke guru/admin/kepsek (`tokenAkses`). */
  wajibToken: boolean
  tokenAkses?: string | null
  createdAt: string
  updatedAt: string
  pembuat: string | null
  lampiran: Lampiran[]
  komentar?: Komentar[]
  // Siswa
  pengumpulanSaya?: RingkasPengumpulan | null
  percobaanSaya?: { status: StatusPercobaan } | null
  jendela?: JendelaUjian
  // Guru
  ringkasan?: { dikumpulkan: number; dinilai: number } | { mulai: number; selesai: number }
}

export type StatusPercobaan = "BERJALAN" | "SELESAI" | "DINILAI"

export interface Mendatang {
  id: string
  tipe: TipePost
  judul: string | null
  batas: string | null
}

/** Gambar soal di editor guru: `path` dikirim balik saat menyimpan, `url` untuk pratinjau. */
export interface ItemGambar {
  path: string
  url: string
}

export interface Soal {
  id: string
  urut: number
  tipe: TipeSoal
  teks: string
  /** URL gambar di teks soal; `opsiGambar` sejajar dengan `opsi` (null = pilihan tanpa gambar). */
  gambar?: string[]
  opsiGambar?: (string | null)[]
  kompetensi?: string | null
  subKompetensi?: string | null
  poin: number
  opsi: string[] | null
  kunci: string | null
}

export interface Paginasi<T> {
  data: T[]
  current_page: number
  per_page: number
  total: number
}

/** Ruang kelas aktif milik pengguna, dibagikan ke semua halaman untuk sidebar. */
export interface KelasSaya {
  id: string
  mapel: string
  rombel: string
}

/** Tugas/ujian lintas ruang kelas untuk Daftar tugas dan Kalender. */
export interface ItemAgenda {
  id: string
  judul: string
  tipe: "TUGAS" | "UJIAN"
  tenggat: string | null
  diposting: string
  kelasId: string
  mapel: string
  rombel: string
  /** Siswa saja. */
  status?: "BELUM" | "DIKUMPULKAN" | "DINILAI"
  /** Guru, admin, kepsek: jumlah yang menunggu dinilai. */
  menunggu?: number
}
