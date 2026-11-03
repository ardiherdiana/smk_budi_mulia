import * as React from "react"
import { Link, usePage } from "@inertiajs/react"
import { motion, useMotionValueEvent, useScroll } from "framer-motion"
import { LayoutDashboard, LogIn, Menu, UserRound } from "lucide-react"

import { cn } from "@/lib/utils"
import type { AuthUser, School } from "@/lib/types"
import { Button } from "@/components/ui/button"
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from "@/components/ui/sheet"
import { ThemeToggle } from "@/components/theme-toggle"

export const NAV_LINKS = [
  { id: "beranda", label: "Beranda" },
  { id: "jurusan", label: "Jurusan" },
  { id: "profil", label: "Profil" },
  { id: "guru", label: "Guru & Staf" },
  { id: "berita", label: "Berita" },
  { id: "kontak", label: "Kontak" },
] as const

export function scrollToSection(id: string, behavior: ScrollBehavior = "smooth") {
  const el = document.getElementById(id)
  if (!el) return
  el.scrollIntoView({ behavior })
  history.replaceState(null, "", `#${id}`)
}

/** Seksi yang sedang di tengah layar: menyorot menu aktif supaya pengunjung tahu posisinya di halaman satu-scroll. */
function useActiveSection(ids: readonly string[]) {
  const [active, setActive] = React.useState<string>(ids[0])

  React.useEffect(() => {
    const observer = new IntersectionObserver(
      (entries) => {
        for (const entry of entries) {
          if (entry.isIntersecting) setActive(entry.target.id)
        }
      },
      { rootMargin: "-45% 0px -50% 0px" }
    )
    ids.forEach((id) => {
      const el = document.getElementById(id)
      if (el) observer.observe(el)
    })
    return () => observer.disconnect()
  }, [ids])

  return active
}

function AccountLink({ user, className }: { user: AuthUser | null; className?: string }) {
  if (!user) {
    return (
      <Button variant="ghost" className={className} render={<Link href="/login" />}>
        <LogIn /> Masuk
      </Button>
    )
  }
  return (
    <Button variant="ghost" className={className} render={<Link href={user.role === "admin" ? "/admin" : "/portal"} />}>
      {user.role === "admin" ? <LayoutDashboard /> : <UserRound />}
      {user.role === "admin" ? "Panel Admin" : "Portal Siswa"}
    </Button>
  )
}

export function SiteHeader({ school }: { school: School }) {
  const { auth } = usePage<{ auth: { user: AuthUser | null } }>().props
  const [scrolled, setScrolled] = React.useState(false)
  const [menuOpen, setMenuOpen] = React.useState(false)
  const { scrollY } = useScroll()
  const active = useActiveSection(NAV_LINKS.map((l) => l.id))

  useMotionValueEvent(scrollY, "change", (y) => setScrolled(y > 12))

  function go(event: React.MouseEvent, id: string) {
    event.preventDefault()
    setMenuOpen(false)
    // Tunggu sheet menutup supaya kunci scroll body lepas sebelum menggulir.
    window.setTimeout(() => scrollToSection(id), menuOpen ? 250 : 0)
  }

  return (
    // Blur hanya di elemen ini (satu-satunya), supaya teks seksi di bawah tidak menabrak menu saat di-scroll.
    <header
      className={cn(
        "fixed inset-x-0 top-0 z-50 transition-colors duration-300",
        scrolled ? "border-b bg-background/90 backdrop-blur-md" : "border-b border-transparent bg-transparent"
      )}
    >
      <div className="mx-auto flex h-16 max-w-7xl items-center gap-3 px-4 md:px-6">
        <a href="#beranda" onClick={(e) => go(e, "beranda")} className="flex items-center gap-2.5">
          <img src="/logo_smk.png" alt="" className="size-9 shrink-0 object-contain" />
          <span className="flex flex-col leading-none">
            <span className="text-sm font-semibold">{school.short_name}</span>
            <span className="text-xs text-muted-foreground">Karawang</span>
          </span>
        </a>

        <nav aria-label="Navigasi utama" className="ml-6 hidden items-center gap-1 lg:flex">
          {NAV_LINKS.map((link) => (
            <a
              key={link.id}
              href={`#${link.id}`}
              onClick={(e) => go(e, link.id)}
              aria-current={active === link.id ? "true" : undefined}
              className={cn(
                "relative rounded-lg px-3 py-1.5 text-sm font-medium transition-colors",
                active === link.id ? "text-secondary-foreground" : "text-muted-foreground hover:text-foreground"
              )}
            >
              {active === link.id && (
                <motion.span
                  layoutId="nav-active"
                  className="absolute inset-0 rounded-lg bg-secondary"
                  transition={{ type: "spring", stiffness: 380, damping: 32 }}
                />
              )}
              <span className="relative">{link.label}</span>
            </a>
          ))}
        </nav>

        <div className="ml-auto flex items-center gap-1.5">
          <ThemeToggle className="max-sm:size-11" />
          <AccountLink user={auth.user} className="hidden sm:inline-flex" />
          {/* Varian secondary: teks putih di atas oranye hanya 3,4:1, tidak cukup untuk teks kecil. Oranye dipakai di CTA besar. */}
          <Button
            variant="secondary"
            className="hidden sm:inline-flex"
            render={<a href={school.ppdb_url} target="_blank" rel="noopener noreferrer" />}
          >
            Pendaftaran
          </Button>

          <Sheet open={menuOpen} onOpenChange={setMenuOpen}>
            <SheetTrigger render={<Button variant="outline" size="icon" className="size-11 lg:hidden" aria-label="Buka menu" />}>
              <Menu className="size-5" />
            </SheetTrigger>
            <SheetContent side="right">
              <SheetHeader>
                <SheetTitle className="flex items-center gap-2">
                  <img src="/logo_smk.png" alt="" className="size-7 object-contain" /> {school.short_name}
                </SheetTitle>
              </SheetHeader>
              <nav aria-label="Navigasi seluler" className="flex flex-col px-4">
                {NAV_LINKS.map((link) => (
                  <a
                    key={link.id}
                    href={`#${link.id}`}
                    onClick={(e) => go(e, link.id)}
                    className={cn(
                      "flex min-h-11 items-center rounded-lg px-3 text-base font-medium",
                      active === link.id ? "bg-secondary text-secondary-foreground" : "hover:bg-muted"
                    )}
                  >
                    {link.label}
                  </a>
                ))}
              </nav>
              <div className="mt-auto flex flex-col gap-2 p-4">
                <AccountLink user={auth.user} className="h-11 w-full justify-center text-base" />
                <Button
                  variant="secondary"
                  className="h-11 w-full text-base"
                  render={<a href={school.ppdb_url} target="_blank" rel="noopener noreferrer" />}
                >
                  Pendaftaran
                </Button>
              </div>
            </SheetContent>
          </Sheet>
        </div>
      </div>
    </header>
  )
}
