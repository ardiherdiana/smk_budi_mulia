import * as React from "react"
import { PlayCircle } from "lucide-react"

import { cn } from "@/lib/utils"
import type { Major } from "@/lib/types"
import { Button } from "@/components/ui/button"
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import { iconFor } from "@/components/landing/icon-map"
import { Reveal, SectionHeader } from "@/components/landing/motion"
import { YouTubeEmbed } from "@/components/landing/youtube-embed"

/**
 * Baris zigzag (gambar kiri/kanan bergantian): jurusan hanya tiga, masing-masing punya gambar dan uraian
 * panjang, jadi tiap jurusan mendapat satu baris penuh, bukan sel kecil di grid.
 */
export function MajorsSection({ majors }: { majors: Major[] }) {
  const [videoMajor, setVideoMajor] = React.useState<Major | null>(null)

  return (
    <section id="jurusan" className="bg-sidebar py-20 md:py-28">
      <div className="mx-auto max-w-7xl px-4 md:px-6">
        <SectionHeader title="Jurusan" description="Program keahlian yang dibuka di SMK Budi Mulia Karawang." />

        {majors.length === 0 ? (
          <p className="mt-10 text-muted-foreground">Data jurusan belum tersedia.</p>
        ) : (
          <div className="mt-12 divide-y border-y">
            {majors.map((major, index) => {
              const Icon = iconFor(major.icon)
              return (
                <Reveal key={major.id} className="grid items-center gap-6 py-10 md:grid-cols-2 md:gap-14 md:py-14">
                  <div
                    className={cn(
                      "flex aspect-[4/3] items-center justify-center overflow-hidden rounded-xl bg-background ring-1 ring-foreground/10",
                      index % 2 === 1 && "md:order-2"
                    )}
                  >
                    {major.image_url ? (
                      <img
                        src={major.image_url}
                        alt={`Jurusan ${major.name}`}
                        loading="lazy"
                        className="size-full object-contain p-3"
                      />
                    ) : (
                      <Icon className="size-16 text-muted-foreground" aria-hidden />
                    )}
                  </div>

                  <div>
                    <h3 className="text-2xl font-semibold text-balance md:text-3xl">{major.name}</h3>
                    <p className="mt-4 text-lg leading-relaxed whitespace-pre-line text-muted-foreground">
                      {major.description?.trim() || "Uraian jurusan belum ditambahkan."}
                    </p>
                    {major.youtube_id && (
                      <Button variant="secondary" className="mt-6 h-11 px-4 text-base" onClick={() => setVideoMajor(major)}>
                        <PlayCircle /> Tonton video jurusan
                      </Button>
                    )}
                  </div>
                </Reveal>
              )
            })}
          </div>
        )}
      </div>

      <Dialog open={videoMajor !== null} onOpenChange={(open) => !open && setVideoMajor(null)}>
        <DialogContent className="sm:max-w-3xl">
          <DialogHeader>
            <DialogTitle>{videoMajor?.name}</DialogTitle>
            <DialogDescription>Video jurusan</DialogDescription>
          </DialogHeader>
          {videoMajor?.youtube_id && <YouTubeEmbed id={videoMajor.youtube_id} title={`Video ${videoMajor.name}`} />}
        </DialogContent>
      </Dialog>
    </section>
  )
}
