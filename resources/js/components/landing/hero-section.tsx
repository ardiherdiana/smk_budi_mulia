import { motion } from "framer-motion"

import type { Major, School } from "@/lib/types"
import { Button } from "@/components/ui/button"
import { EASE_OUT } from "@/components/landing/motion"
import { scrollToSection } from "@/components/landing/site-header"

const LEAD_WORDS = ["SMK", "Budi", "Mulia"]

function hostOf(url: string): string {
  try {
    return new URL(url).host
  } catch {
    return url
  }
}

/**
 * Titik fokus: nama sekolah + foto gedung asli. Satu-satunya seksi dengan animasi masuk berlapis
 * (judul per kata, lalu foto); sisanya diam setelah muncul. Tanpa parallax, tanpa elemen melayang.
 */
export function HeroSection({ school, majors }: { school: School; majors: Major[] }) {
  return (
    <section id="beranda" className="bg-sidebar pt-28 pb-14 md:pt-36 md:pb-20">
      <div className="mx-auto grid max-w-7xl items-center gap-10 px-4 md:px-6 lg:grid-cols-[1.05fr_1fr] lg:gap-14">
        <div>
          <h1 className="text-5xl leading-[1.05] font-bold tracking-tight text-balance md:text-7xl">
            <span className="text-sidebar-primary">
              {LEAD_WORDS.map((word, i) => (
                <span key={word} className="inline-block overflow-hidden pb-1 align-bottom">
                  <motion.span
                    className="inline-block"
                    initial={{ y: "110%" }}
                    animate={{ y: 0 }}
                    transition={{ duration: 0.7, delay: 0.1 + i * 0.08, ease: EASE_OUT }}
                  >
                    {word}&nbsp;
                  </motion.span>
                </span>
              ))}
            </span>
            <span className="inline-block overflow-hidden pb-1 align-bottom">
              <motion.span
                className="inline-block"
                initial={{ y: "110%" }}
                animate={{ y: 0 }}
                transition={{ duration: 0.7, delay: 0.34, ease: EASE_OUT }}
              >
                Karawang
              </motion.span>
            </span>
          </h1>

          <motion.div
            initial={{ opacity: 0, y: 14 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6, delay: 0.5, ease: EASE_OUT }}
          >
            <p className="mt-6 max-w-xl text-lg text-pretty text-muted-foreground md:text-xl">{school.tagline}</p>

            <div className="mt-8 flex flex-wrap gap-3">
              {/* Teks 18px: putih di atas oranye 3,4:1 hanya cukup untuk teks besar. */}
              <Button
                className="h-12 px-6 text-lg"
                render={<a href={school.ppdb_url} target="_blank" rel="noopener noreferrer" />}
              >
                Daftar Sekarang
              </Button>
              <Button variant="outline" className="h-12 px-6 text-lg" onClick={() => scrollToSection("jurusan")}>
                Lihat Jurusan
              </Button>
            </div>
            <p className="mt-3 text-sm text-muted-foreground">
              Pendaftaran dilakukan di situs PPDB terpisah ({hostOf(school.ppdb_url)}).
            </p>

            {majors.length > 0 && (
              <div className="mt-10 border-t pt-5">
                <p className="text-sm text-muted-foreground">Program keahlian</p>
                <ul className="mt-1 flex flex-wrap gap-x-6">
                  {majors.map((major) => (
                    <li key={major.id}>
                      <a
                        href="#jurusan"
                        onClick={(e) => {
                          e.preventDefault()
                          scrollToSection("jurusan")
                        }}
                        className="inline-block py-2.5 font-medium underline decoration-foreground/30 underline-offset-4 hover:decoration-foreground"
                      >
                        {major.name}
                      </a>
                    </li>
                  ))}
                </ul>
              </div>
            )}
          </motion.div>
        </div>

        <motion.figure
          initial={{ opacity: 0, scale: 0.97 }}
          animate={{ opacity: 1, scale: 1 }}
          transition={{ duration: 0.8, delay: 0.25, ease: EASE_OUT }}
        >
          <img
            src="/images/gedung-sekolah.webp"
            alt="Gedung SMK Budi Mulia Karawang"
            width={1536}
            height={1024}
            fetchPriority="high"
            className="aspect-[4/3] w-full rounded-2xl object-cover ring-1 ring-foreground/10"
          />
          <figcaption className="mt-3 text-sm text-muted-foreground">{school.address}</figcaption>
        </motion.figure>
      </div>
    </section>
  )
}
