import { motion } from "framer-motion"
import { ArrowUp } from "lucide-react"
import { Link } from "@inertiajs/react"

import type { School } from "@/lib/types"
import { NAV_LINKS, scrollToSection } from "@/components/landing/site-header"

const linkClass = "inline-block py-2 opacity-90 hover:underline hover:opacity-100"

/** Tiga kolom (sekolah, tautan, kontak) sesuai isi yang memang ada; tanpa kolom "Produk/Perusahaan/Sumber". */
export function SiteFooter({ school }: { school: School }) {
  return (
    <footer className="bg-sidebar-primary text-sidebar-primary-foreground dark:bg-sidebar dark:text-sidebar-foreground">
      <div className="mx-auto grid max-w-7xl gap-10 px-4 py-12 md:grid-cols-[1.2fr_1fr_1.2fr] md:px-6">
        <div>
          <div className="flex items-center gap-3">
            <img src="/logo_smk.png" alt="" className="size-11 rounded-lg bg-white/90 object-contain p-1" />
            <p className="text-lg font-semibold">{school.name}</p>
          </div>
          <p className="mt-4 max-w-xs text-pretty opacity-90">{school.tagline}</p>
        </div>

        <nav aria-label="Tautan cepat">
          <h2 className="font-semibold">Tautan</h2>
          <ul className="mt-2 grid grid-cols-2 gap-x-4">
            {NAV_LINKS.map((link) => (
              <li key={link.id}>
                <a
                  href={`#${link.id}`}
                  onClick={(e) => {
                    e.preventDefault()
                    scrollToSection(link.id)
                  }}
                  className={linkClass}
                >
                  {link.label}
                </a>
              </li>
            ))}
            <li>
              <a href={school.ppdb_url} target="_blank" rel="noopener noreferrer" className={linkClass}>
                Pendaftaran
              </a>
            </li>
            <li>
              <Link href="/login" className={linkClass}>
                Portal siswa
              </Link>
            </li>
          </ul>
        </nav>

        <div>
          <h2 className="font-semibold">Kontak</h2>
          <address className="mt-2 flex flex-col text-base not-italic">
            <span className="py-1 opacity-90">{school.address}</span>
            <span className="py-1 opacity-90">{school.phone}</span>
            <a href={`mailto:${school.email}`} className={linkClass}>
              {school.email}
            </a>
            <a href={school.instagram} target="_blank" rel="noopener noreferrer" className={linkClass}>
              @smk_budimuliakrw
            </a>
          </address>
        </div>
      </div>

      <div className="border-t border-white/15">
        <div className="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4 text-sm md:px-6">
          <p className="opacity-90">
            &copy; {new Date().getFullYear()} {school.name}
          </p>
          <motion.button
            type="button"
            whileTap={{ scale: 0.94 }}
            onClick={() => scrollToSection("beranda")}
            aria-label="Kembali ke atas"
            className="flex size-11 items-center justify-center rounded-lg border border-white/30 hover:bg-white/10"
          >
            <ArrowUp className="size-4" />
          </motion.button>
        </div>
      </div>
    </footer>
  )
}
