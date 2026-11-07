import { Head, Link, router, usePage } from "@inertiajs/react"
import {
  ArrowRight,
  CalendarCheck,
  CircleUserRound,
  Contact,
  FileCheck2,
  Globe,
  GraduationCap,
  LogOut,
  Send,
} from "lucide-react"

import { NotifikasiLonceng, type DataNotifikasi } from "@/components/notifikasi-lonceng"
import { ThemeToggle } from "@/components/theme-toggle"
import { Button } from "@/components/ui/button"
import { cn } from "@/lib/utils"

type ModuleKey = "website" | "skl" | "classroom" | "absensi" | "sppd" | "dataguru" | "profil"

interface ModuleCard {
  title: string
  description: string
  icon: React.ComponentType<{ className?: string }>
}

const CARDS: Record<ModuleKey, ModuleCard> = {
  website: {
    title: "Website",
    description: "Kelola berita, profil sekolah, jurusan, guru dan staf",
    icon: Globe,
  },
  skl: {
    title: "SKL",
    description: "Surat keterangan lulus: akun siswa, unggah dan unduh berkas",
    icon: FileCheck2,
  },
  classroom: {
    title: "Classroom",
    description: "Ruang kelas, materi, tugas, ujian, dan nilai",
    icon: GraduationCap,
  },
  absensi: {
    title: "Absen",
    description: "Rekap kehadiran, jadwal, dan izin guru",
    icon: CalendarCheck,
  },
  sppd: {
    title: "SPPD",
    description: "Pengajuan perjalanan dinas, persetujuan, dan pencairan",
    icon: Send,
  },
  dataguru: {
    title: "Data Guru",
    description: "Data master guru untuk semua modul: akun, foto, dan QR absen",
    icon: Contact,
  },
  profil: {
    title: "Profil",
    description: "Data akun, foto, dan password untuk semua modul",
    icon: CircleUserRound,
  },
}

// 2 kartu (siswa) berdampingan; 3 kartu satu baris; 4 kartu (guru) 2x2; 5 kartu ke atas (kepsek, admin) 3 kolom di layar lebar.
function kolomGrid(jumlah: number) {
  if (jumlah === 3) return "sm:grid-cols-3"
  if (jumlah >= 5) return "sm:grid-cols-2 lg:grid-cols-3"
  return "sm:grid-cols-2"
}

interface PageProps {
  modul: { key: ModuleKey; href: string }[]
  /** Notifikasi terpusat (Absensi + SPPD); satu-satunya tempat notifikasi ditampilkan. */
  notifikasi: DataNotifikasi
  auth: { user: { email?: string; username?: string; name?: string } | null }
  [key: string]: unknown
}

export function ModulePickerPage() {
  const { modul, notifikasi, auth } = usePage<PageProps>().props
  const nama = auth.user?.name ?? auth.user?.email ?? auth.user?.username

  return (
    <div className="flex min-h-svh flex-col">
      <Head title="Menu Utama - SMK Budi Mulia" />
      <header className="flex h-14 shrink-0 items-center justify-between border-b px-4 md:px-6">
        <div className="flex items-center gap-2">
          <img src="/logo_smk.png" alt="Logo SMK Budi Mulia" className="size-7 shrink-0 object-contain" />
          <div className="flex flex-col leading-none">
            <span className="font-medium">SMK Budi Mulia</span>
            <span className="text-xs text-muted-foreground">Pilih modul</span>
          </div>
        </div>
        <div className="flex items-center gap-3">
          <span className="hidden text-sm text-muted-foreground sm:inline">{nama}</span>
          <ThemeToggle />
          <NotifikasiLonceng data={notifikasi} />
          <Button variant="ghost" size="icon" aria-label="Keluar" title="Keluar" onClick={() => router.post("/logout")}>
            <LogOut className="size-5" />
          </Button>
        </div>
      </header>

      {/* Tiap kartu bergaris kanan + bawah; garis di tepi luar terpotong overflow-hidden, jadi pemisah tetap rapi
          untuk berapa pun jumlah baris. */}
      <div className="flex flex-1 flex-col overflow-hidden">
        <div className={cn("-mr-px -mb-px grid flex-1 auto-rows-fr grid-cols-1", kolomGrid(modul.length))}>
          {modul.map(({ key, href }) => {
            const card = CARDS[key]
            return (
              <Link
                key={key}
                href={href}
                className="group relative flex flex-col items-center justify-center gap-4 overflow-hidden border-r border-b p-10 text-center transition-colors duration-300 hover:bg-muted"
              >
                <span className="absolute top-0 left-0 h-0.5 w-0 bg-foreground transition-all duration-500 ease-out group-hover:w-full" />

                <div className="flex size-14 items-center justify-center rounded-lg bg-primary/10 text-primary transition-all duration-300 group-hover:scale-110 group-hover:bg-primary group-hover:text-primary-foreground">
                  <card.icon className="size-6" />
                </div>
                <div className="space-y-1.5">
                  <h2 className="text-lg font-semibold">{card.title}</h2>
                  <p className="text-sm text-muted-foreground">{card.description}</p>
                </div>
                <span className="flex items-center gap-2 text-sm font-medium text-muted-foreground transition-all duration-300 group-hover:gap-3 group-hover:text-foreground">
                  Akses Modul <ArrowRight className="size-4 transition-transform duration-300 group-hover:translate-x-1" />
                </span>
              </Link>
            )
          })}
        </div>
      </div>
    </div>
  )
}
