import { formatAngka } from "@/lib/classroom/format"
import { LABEL_TIPE_SOAL, PETUNJUK_TIPE_SOAL } from "@/lib/classroom/soal"
import type { SoalUjian } from "@/lib/classroom/ujian"
import { Button } from "@/components/ui/button"
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog"

interface Props {
  buka: boolean
  onBukaChange: (buka: boolean) => void
  judul: string | null
  /** Deskripsi/petunjuk ujian dari guru. */
  isi: string | null
  mapel: string
  rombel: string
  durasiMenit: number | null
  jumlahSoal: number
  totalPoin: number
  nomor: number
  soal: SoalUjian
}

function Baris({ label, nilai }: { label: string; nilai: React.ReactNode }) {
  return (
    <div className="flex justify-between gap-4 py-2 text-sm">
      <dt className="text-muted-foreground">{label}</dt>
      <dd className="text-right font-medium">{nilai}</dd>
    </div>
  )
}

/** Ringkasan ujian dan soal yang sedang dibuka (bentuk, petunjuk, poin). */
export function InformasiSoal({ buka, onBukaChange, judul, isi, mapel, rombel, durasiMenit, jumlahSoal, totalPoin, nomor, soal }: Props) {
  return (
    <Dialog open={buka} onOpenChange={onBukaChange}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>Informasi Soal</DialogTitle>
          <DialogDescription>Ringkasan ujian dan soal nomor {nomor}.</DialogDescription>
        </DialogHeader>

        <div className="flex flex-col gap-4">
          <dl className="divide-y">
            <Baris label="Ujian" nilai={judul} />
            <Baris label="Mata pelajaran" nilai={mapel} />
            <Baris label="Rombel" nilai={rombel} />
            {durasiMenit !== null && <Baris label="Durasi" nilai={`${durasiMenit} menit`} />}
            <Baris label="Jumlah soal" nilai={`${jumlahSoal} soal, total ${formatAngka(totalPoin)} poin`} />
          </dl>

          {isi && (
            <div className="flex flex-col gap-1 text-sm">
              <p className="font-medium">Petunjuk dari guru</p>
              <p className="break-words whitespace-pre-wrap text-muted-foreground">{isi}</p>
            </div>
          )}

          <div className="flex flex-col gap-1 rounded-lg border bg-muted/40 p-3 text-sm">
            <p className="font-medium">
              Soal nomor {nomor}: {LABEL_TIPE_SOAL[soal.tipe]}
            </p>
            <p className="text-muted-foreground">{PETUNJUK_TIPE_SOAL[soal.tipe]}</p>
            <p className="text-muted-foreground">Bobot {formatAngka(soal.poin)} poin.</p>
          </div>
        </div>

        <DialogFooter>
          <Button onClick={() => onBukaChange(false)}>Tutup</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
