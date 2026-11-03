import { ArrowUpRight } from "lucide-react"

import type { School } from "@/lib/types"
import { Button } from "@/components/ui/button"
import { Reveal } from "@/components/landing/motion"

function hostOf(url: string): string {
  try {
    return new URL(url).host
  } catch {
    return url
  }
}

/**
 * Satu tindakan, jadi satu pita penuh dengan satu tombol. Tujuannya situs lain, maka alamatnya disebut
 * dan tombolnya memakai ikon panah keluar (satu-satunya panah di halaman ini, karena benar-benar pindah situs).
 */
export function RegistrationSection({ school }: { school: School }) {
  return (
    <section
      id="pendaftaran"
      className="bg-sidebar-primary py-12 text-sidebar-primary-foreground md:py-16 dark:bg-sidebar-accent dark:text-sidebar-accent-foreground"
    >
      <Reveal className="mx-auto grid max-w-7xl items-center gap-6 px-4 md:grid-cols-[1fr_auto] md:gap-12 md:px-6">
        <div>
          <h2 className="text-3xl font-bold tracking-tight text-balance md:text-4xl">Pendaftaran siswa baru</h2>
          <p className="mt-3 max-w-2xl text-lg text-pretty opacity-90">
            Pendaftaran SMK Budi Mulia Karawang dilakukan di situs PPDB terpisah: {hostOf(school.ppdb_url)}.
          </p>
        </div>
        <Button
          className="h-12 px-6 text-lg"
          render={<a href={school.ppdb_url} target="_blank" rel="noopener noreferrer" />}
        >
          Buka situs pendaftaran <ArrowUpRight />
        </Button>
      </Reveal>
    </section>
  )
}
