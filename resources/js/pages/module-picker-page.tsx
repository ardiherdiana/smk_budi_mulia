import { Head, Link, router, usePage } from "@inertiajs/react"
import { ArrowRight, FileCheck2, Globe, GraduationCap, LogOut } from "lucide-react"

import { ThemeToggle } from "@/components/theme-toggle"
import { Button } from "@/components/ui/button"
import { cn } from "@/lib/utils"

type ModuleKey = "website" | "skl" | "classroom"

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
}

interface PageProps {
  modul: { key: ModuleKey; href: string }[]
  auth: { user: { email?: string; username?: string; name?: string } | null }
  [key: string]: unknown
}

export function ModulePickerPage() {
  const { modul, auth } = usePage<PageProps>().props
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
          <Button variant="ghost" size="icon" aria-label="Keluar" title="Keluar" onClick={() => router.post("/logout")}>
            <LogOut className="size-5" />
          </Button>
        </div>
      </header>

      <div className={cn("grid flex-1 grid-cols-1", modul.length >= 3 ? "sm:grid-cols-3" : "sm:grid-cols-2")}>
        {modul.map(({ key, href }) => {
          const card = CARDS[key]
          return (
            <Link
              key={key}
              href={href}
              className="group relative flex flex-col items-center justify-center gap-4 overflow-hidden border-b p-10 text-center transition-colors duration-300 last:border-b-0 hover:bg-muted sm:border-r sm:border-b-0 sm:last:border-r-0"
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
  )
}
