export type Role = "ADMIN" | "GURU" | "KEPSEK"

// `auth.user` satu bentuk untuk semua modul sejak absensi digabung ke situs sekolah.
export type { AuthUser } from "@/lib/types"

export interface Guru {
  id: string
  nama: string
  noHp: string | null
  mapel: string | null
  fotoUrl: string | null
  qrToken: string
  aktif: boolean
  createdAt: string
  user: { username: string; role: Role }
}

export interface Attendance {
  id: string
  guruId: string
  tanggal: string
  jamMasuk: string | null
  jamPulang: string | null
  statusMasuk: StatusKehadiran | null
  statusPulang: StatusKehadiran | null
  catatan: string | null
}

export type StatusKehadiran = "HADIR" | "TELAT" | "ALPA" | "IZIN" | "SAKIT"

export interface RekapRow {
  guruId: string
  nama: string
  tanggal: string
  jamMasuk: string | null
  jamPulang: string | null
  status: StatusKehadiran | null
  catatan: string | null
}

export interface GuruAttendanceDetail {
  guruId: string
  nama: string
  fotoUrl: string | null
  streak: number
  days: RekapRow[]
}

export type JenisIzin = "IZIN" | "SAKIT"
export type StatusIzin = "PENDING" | "APPROVED" | "REJECTED"

export interface LeaveRequest {
  id: string
  guruId: string
  jenis: JenisIzin
  tanggalMulai: string
  tanggalSelesai: string
  alasan: string
  lampiranUrl: string | null
  status: StatusIzin
  reviewedBy: string | null
  reviewedAt: string | null
  createdAt: string
  guru?: { nama: string }
}

export interface Holiday {
  id: string
  tanggal: string
  keterangan: string
}

export interface Settings {
  id: number
  namaSekolah: string
}

/** One weekly recurring schedule row - `hari` follows JS's day-of-week
 * convention (0=Minggu ... 6=Sabtu). */
export interface JadwalHari {
  hari: number
  aktif: boolean
  /** Earliest time absen masuk is allowed - separate from `jamMasuk`, which
   * stays only the HADIR/TELAT reference point (compared against
   * `batasTelat`). */
  jamMulaiAbsen: string
  jamMasuk: string
  batasTelat: string
  jamPulang: string
  /** null = no briefing scheduled this day. */
  jamBriefing: string | null
  /** null = no closing gate - absen briefing stays open the rest of the
   * day once jamBriefing opens. */
  jamSelesaiBriefing: string | null
}

export interface CheckinResult {
  /** BRIEFING: scan kiosk dari guru yang sudah absen masuk, saat briefing berlangsung (hanya lewat kiosk). */
  type: "MASUK" | "PULANG" | "BRIEFING"
  statusMasuk?: StatusKehadiran
  jam: string
  attendance: Attendance
  nama: string
  fotoUrl: string | null
  /** Scan kiosk ini mencatat absen briefing (MASUK saat briefing berlangsung, atau BRIEFING). */
  briefing?: boolean
}

export interface BriefingCheckinResult {
  nama: string
  fotoUrl: string | null
  waktu: string
}

export interface BriefingRekapRow {
  guruId: string
  nama: string
  tanggal: string
  waktu: string | null
  status: StatusKehadiran | null
}
