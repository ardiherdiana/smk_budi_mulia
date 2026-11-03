import { initials } from "@/lib/utils"
import type { PrincipalItem } from "@/lib/types"
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import { Reveal, SectionHeader } from "@/components/landing/motion"

/** Satu kepala sekolah menjabat (potret besar) dan sisanya riwayat berurutan waktu (daftar, bukan kartu). */
export function LeadershipSection({ principals }: { principals: PrincipalItem[] }) {
  if (principals.length === 0) return null

  const [current, ...previous] = principals

  return (
    <section id="kepemimpinan" className="bg-sidebar py-16 md:py-20">
      <div className="mx-auto grid max-w-7xl gap-10 px-4 md:px-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] lg:gap-20">
        <div>
          <SectionHeader title="Kepala sekolah" />
          <Reveal className="mt-8 flex flex-col gap-6 sm:flex-row sm:items-center">
            {/* Bingkai 3:4 dijangkar ke atas: foto kepala sekolah tegak (rasio 0,67 s.d. 1,0), jangan sampai kepala terpotong. */}
            <div className="aspect-[3/4] w-40 shrink-0 overflow-hidden rounded-2xl bg-secondary ring-1 ring-foreground/10">
              {current.foto_url ? (
                <img src={current.foto_url} alt={current.nama} className="size-full object-cover object-top" />
              ) : (
                <div className="flex size-full items-center justify-center text-4xl font-semibold text-secondary-foreground" aria-hidden>
                  {initials(current.nama)}
                </div>
              )}
            </div>
            <div>
              <p className="text-muted-foreground">Menjabat saat ini</p>
              <p className="mt-1 text-3xl font-bold text-balance">{current.nama}</p>
              <p className="mt-1 text-lg text-muted-foreground">Periode {current.tahun_jabatan}</p>
            </div>
          </Reveal>
        </div>

        {previous.length > 0 && (
          <div>
            <Reveal>
              <h3 className="text-xl font-semibold">Kepala sekolah sebelumnya</h3>
            </Reveal>
            <div role="list" className="mt-4 divide-y border-y">
              {previous.map((principal) => (
                <Reveal key={principal.id} role="listitem" className="flex items-center gap-4 py-4">
                  <Avatar className="size-14">
                    {principal.foto_url && <AvatarImage src={principal.foto_url} alt={principal.nama} loading="lazy" className="object-cover object-top" />}
                    <AvatarFallback>{initials(principal.nama)}</AvatarFallback>
                  </Avatar>
                  <div>
                    <p className="font-semibold">{principal.nama}</p>
                    <p className="text-sm text-muted-foreground">Periode {principal.tahun_jabatan}</p>
                  </div>
                </Reveal>
              ))}
            </div>
          </div>
        )}
      </div>
    </section>
  )
}
