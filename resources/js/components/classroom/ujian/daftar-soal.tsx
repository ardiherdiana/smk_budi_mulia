import type { SoalUjian } from "@/lib/classroom/ujian"
import { cn } from "@/lib/classroom/utils"
import { Button } from "@/components/ui/button"
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from "@/components/ui/sheet"

interface Props {
  buka: boolean
  onBukaChange: (buka: boolean) => void
  soal: SoalUjian[]
  terisi: (soal: SoalUjian) => boolean
  /** Id soal yang ditandai ragu-ragu. */
  ragu: string[]
  aktif: number
  onPilih: (indeks: number) => void
  onKumpulkan: () => void
  mengirim: boolean
}

function Legenda({ className, children }: { className: string; children: React.ReactNode }) {
  return (
    <li className="flex items-center gap-2">
      <span className={cn("size-4 rounded border", className)} /> {children}
    </li>
  )
}

/** Panel nomor soal: lompat ke soal mana pun, lihat yang sudah dijawab atau ragu-ragu, dan kumpulkan ujian. */
export function DaftarSoal({ buka, onBukaChange, soal, terisi, ragu, aktif, onPilih, onKumpulkan, mengirim }: Props) {
  const jumlahRagu = soal.filter((s) => ragu.includes(s.id)).length
  const jumlahTerjawab = soal.filter(terisi).length

  return (
    <Sheet open={buka} onOpenChange={onBukaChange}>
      <SheetContent className="overflow-y-auto">
        <SheetHeader>
          <SheetTitle>Daftar Soal</SheetTitle>
          <SheetDescription>Pilih nomor untuk pindah ke soal tersebut.</SheetDescription>
        </SheetHeader>

        <div className="flex flex-col gap-4 px-4">
          <div className="grid grid-cols-5 gap-2">
            {soal.map((s, i) => {
              const tandaRagu = ragu.includes(s.id)
              const sudah = terisi(s)
              return (
                <button
                  key={s.id}
                  type="button"
                  onClick={() => {
                    onPilih(i)
                    onBukaChange(false)
                  }}
                  aria-current={i === aktif ? "true" : undefined}
                  aria-label={`Soal ${i + 1}${tandaRagu ? ", ragu-ragu" : sudah ? ", sudah dijawab" : ", belum dijawab"}`}
                  className={cn(
                    "flex h-11 items-center justify-center rounded-md border text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none",
                    tandaRagu
                      ? "border-warning bg-warning text-warning-foreground"
                      : sudah
                        ? "border-primary bg-primary text-primary-foreground"
                        : "bg-background hover:bg-muted",
                    i === aktif && "ring-2 ring-ring ring-offset-2 ring-offset-background"
                  )}
                >
                  {i + 1}
                </button>
              )
            })}
          </div>

          <ul className="flex flex-col gap-1.5 text-sm text-muted-foreground">
            <Legenda className="border-primary bg-primary">Sudah dijawab ({jumlahTerjawab})</Legenda>
            <Legenda className="border-warning bg-warning">Ragu-ragu ({jumlahRagu})</Legenda>
            <Legenda className="bg-background">Belum dijawab ({soal.length - jumlahTerjawab})</Legenda>
          </ul>
        </div>

        <SheetFooter>
          <Button size="lg" onClick={onKumpulkan} disabled={mengirim}>
            {mengirim ? "Mengirim..." : "Kumpulkan ujian"}
          </Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  )
}
