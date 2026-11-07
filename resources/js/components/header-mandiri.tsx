import { Link, router } from "@inertiajs/react"
import { Home, LogOut } from "lucide-react"

import { ThemeToggle } from "@/components/theme-toggle"
import { Button } from "@/components/ui/button"

/** Kop halaman yang dibuka langsung dari kartu Menu Utama (Profil, Data Guru): tanpa sidebar modul, dengan jalan kembali ke Menu Utama. */
export function HeaderMandiri({ judul }: { judul: string }) {
  return (
    <header className="flex h-14 shrink-0 items-center justify-between border-b px-4 md:px-6">
      <div className="flex items-center gap-2">
        <img src="/logo_smk.png" alt="Logo SMK Budi Mulia" className="size-7 shrink-0 object-contain" />
        <div className="flex flex-col leading-none">
          <span className="font-medium">SMK Budi Mulia</span>
          <span className="text-xs text-muted-foreground">{judul}</span>
        </div>
      </div>
      <div className="flex items-center gap-1 sm:gap-3">
        <Button variant="ghost" render={<Link href="/menu-utama" />}>
          <Home /> <span className="hidden sm:inline">Menu Utama</span>
        </Button>
        <ThemeToggle />
        <Button variant="ghost" size="icon" aria-label="Keluar" title="Keluar" onClick={() => router.post("/logout")}>
          <LogOut className="size-5" />
        </Button>
      </div>
    </header>
  )
}
