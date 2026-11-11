import * as React from "react"
import { AnimatePresence, motion } from "framer-motion"

import { cn } from "@/lib/utils"
import type { AboutItem, School } from "@/lib/types"
import { Card } from "@/components/ui/card"
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { iconFor } from "@/components/landing/icon-map"
import { EASE_OUT, Reveal, SectionHeader } from "@/components/landing/motion"
import { ImageLightbox } from "@/components/landing/image-lightbox"

function AboutTabs({ abouts }: { abouts: AboutItem[] }) {
  const [activeId, setActiveId] = React.useState(abouts[0]?.id ?? "")
  const [lightbox, setLightbox] = React.useState<{ src: string; alt: string } | null>(null)
  const active = abouts.find((a) => a.id === activeId) ?? abouts[0]

  if (!active) return null

  return (
    <div className="mt-12 grid grid-cols-1 gap-6 lg:grid-cols-[18rem_1fr]">
      <Reveal>
        <Tabs value={active.id} onValueChange={(value) => setActiveId(String(value))} orientation="vertical" className="gap-0">
          <TabsList className="w-full">
            {abouts.map((item) => {
              const Icon = iconFor(item.icon_type)
              return (
                <TabsTrigger key={item.id} value={item.id} className="w-full gap-2.5 px-4">
                  <Icon /> <span className="truncate">{item.title}</span>
                </TabsTrigger>
              )
            })}
          </TabsList>
        </Tabs>
      </Reveal>

      <Card className="min-h-72 [--card-spacing:--spacing(6)]">
        <AnimatePresence mode="wait">
          <motion.div
            key={active.id}
            initial={{ opacity: 0, x: 16 }}
            animate={{ opacity: 1, x: 0 }}
            exit={{ opacity: 0, x: -16 }}
            transition={{ duration: 0.25, ease: EASE_OUT }}
            className="flex flex-col gap-5 px-(--card-spacing)"
          >
            <h3 className="text-2xl font-semibold text-balance">{active.title}</h3>
            {active.description.trim() ? (
              <p className="text-lg leading-relaxed whitespace-pre-line text-muted-foreground">{active.description}</p>
            ) : (
              <p className="text-muted-foreground">Isi bagian ini belum ditambahkan.</p>
            )}
            {active.media.length > 0 && (
              <div className={cn("grid grid-cols-1 gap-3", active.media.length > 1 && "sm:grid-cols-2")}>
                {active.media.map((media, index) => (
                  <button
                    key={media.id}
                    type="button"
                    aria-label={`Perbesar foto ${index + 1}: ${active.title}`}
                    onClick={() => setLightbox({ src: media.url, alt: `${active.title} ${index + 1}` })}
                    className={cn(
                      "overflow-hidden rounded-xl bg-muted ring-1 ring-foreground/10 outline-none focus-visible:ring-3 focus-visible:ring-ring/50",
                      active.media.length > 2 && index === 0 && "sm:col-span-2"
                    )}
                  >
                    <img src={media.url} alt="" loading="lazy" className="aspect-video w-full object-cover" />
                  </button>
                ))}
              </div>
            )}
          </motion.div>
        </AnimatePresence>
      </Card>

      <ImageLightbox src={lightbox?.src ?? null} alt={lightbox?.alt ?? ""} onClose={() => setLightbox(null)} />
    </div>
  )
}

/** Visi = satu kalimat, jadi tampil sebagai pernyataan besar. Misi = delapan butir bernomor, daftar polos dua kolom. */
function VisionMission({ school }: { school: School }) {
  return (
    <div className="mt-20 md:mt-24">
      <Reveal>
        <div className="rounded-2xl bg-sidebar-primary p-8 text-sidebar-primary-foreground md:p-12 dark:bg-sidebar-accent dark:text-sidebar-accent-foreground">
          <h3 className="text-lg font-semibold">Visi</h3>
          <p className="mt-3 max-w-4xl text-2xl leading-snug font-medium text-balance md:text-3xl">&ldquo;{school.vision}&rdquo;</p>
        </div>
      </Reveal>

      <div className="mt-12">
        <Reveal>
          <h3 className="text-2xl font-semibold">Misi</h3>
        </Reveal>
        <div role="list" className="mt-5 grid gap-x-12 md:grid-cols-2">
          {school.missions.map((mission, index) => (
            <Reveal key={mission} role="listitem" className="flex gap-4 border-t py-4">
              <span className="w-6 shrink-0 text-lg font-semibold text-muted-foreground tabular-nums">{index + 1}</span>
              <p className="text-pretty">{mission}</p>
            </Reveal>
          ))}
        </div>
      </div>
    </div>
  )
}

export function ProfileSection({ school, abouts }: { school: School; abouts: AboutItem[] }) {
  return (
    <section id="profil" className="bg-background py-16 md:py-28">
      <div className="mx-auto max-w-7xl px-4 md:px-6">
        <SectionHeader title="Tentang sekolah" />

        <Reveal className="mt-8 grid gap-3 border-y py-8 md:grid-cols-[13rem_1fr] md:gap-10">
          <h3 className="text-lg font-semibold">Budaya sekolah</h3>
          <p className="max-w-3xl text-xl leading-relaxed text-pretty">{school.culture}</p>
        </Reveal>

        {abouts.length > 0 ? (
          <AboutTabs abouts={abouts} />
        ) : (
          <p className="mt-12 text-muted-foreground">Profil sekolah belum ditambahkan.</p>
        )}

        <VisionMission school={school} />
      </div>
    </section>
  )
}
