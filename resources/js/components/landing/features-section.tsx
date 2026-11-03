import type { School } from "@/lib/types"
import { Reveal } from "@/components/landing/motion"

const PATHS = ["Bekerja.", "Melanjutkan.", "Wirausaha."]

/**
 * Konsep BMW (Bekerja, Melanjutkan, Wirausaha) tertulis di misi sekolah dan itulah kekhasannya, jadi ia yang
 * jadi tipografi besar. Enam pernyataan sekolah tampil sebagai daftar polos, bukan enam kartu kembar.
 */
export function FeaturesSection({ school }: { school: School }) {
  return (
    <section id="keunggulan" className="bg-background py-16 md:py-24">
      <div className="mx-auto grid max-w-7xl gap-12 px-4 md:px-6 lg:grid-cols-[minmax(0,0.85fr)_minmax(0,1.15fr)] lg:gap-20">
        <Reveal className="lg:sticky lg:top-28 lg:self-start">
          <p className="text-lg text-muted-foreground">Arah lulusan SMK Budi Mulia</p>
          <h2 className="mt-2 text-5xl leading-[1.05] font-bold tracking-tight text-sidebar-primary md:text-6xl">
            {PATHS.map((path) => (
              <span key={path} className="block">
                {path}
              </span>
            ))}
          </h2>
          <p className="mt-5 max-w-sm text-pretty text-muted-foreground">
            Ketiganya ditetapkan dalam misi sekolah dan dikenal sebagai konsep BMW.
          </p>
        </Reveal>

        <div>
          <h3 className="text-2xl font-semibold">Yang ditawarkan sekolah</h3>
          <dl className="mt-5 divide-y border-y">
            {school.features.map((feature) => (
              <Reveal key={feature.title} className="grid gap-1 py-5 sm:grid-cols-[13rem_1fr] sm:gap-6">
                <dt className="font-semibold">{feature.title}</dt>
                <dd className="text-muted-foreground">{feature.description}</dd>
              </Reveal>
            ))}
          </dl>
        </div>
      </div>
    </section>
  )
}
