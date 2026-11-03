import * as React from "react"
import { FileSpreadsheet, Loader2, TriangleAlert } from "lucide-react"
import { toast } from "sonner"

import { ApiError, downloadFile } from "@/lib/api"
import type { KelasUjian } from "@/lib/classroom/types"
import { formatAngka } from "@/lib/classroom/format"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Checkbox } from "@/components/ui/checkbox"
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog"

interface Props {
  postId: string
  kelas: KelasUjian[]
}

/** Tombol "Ekspor nilai" dan dialog pemilihan kelas. Satu berkas Excel: sheet Rekap + satu sheet per kelas. */
export function EksporNilai({ postId, kelas }: Props) {
  const [buka, setBuka] = React.useState(false)

  if (kelas.length === 0) return null

  return (
    <>
      <Button size="sm" variant="outline" onClick={() => setBuka(true)}>
        <FileSpreadsheet /> Ekspor nilai
      </Button>
      <Dialog open={buka} onOpenChange={setBuka}>
        <DialogContent className="max-h-[90svh] overflow-y-auto sm:max-w-lg">
          {/* Dipasang ulang tiap dialog dibuka, jadi pilihan selalu mulai dari kelas ini. */}
          {buka && <FormEkspor postId={postId} kelas={kelas} onSelesai={() => setBuka(false)} />}
        </DialogContent>
      </Dialog>
    </>
  )
}

function FormEkspor({ postId, kelas, onSelesai }: Props & { onSelesai: () => void }) {
  const [dipilih, setDipilih] = React.useState<string[]>(() => [kelas.find((k) => k.utama)?.postId ?? kelas[0].postId])
  const [perSoal, setPerSoal] = React.useState(true)
  const [belum, setBelum] = React.useState(true)
  const [mengunduh, setMengunduh] = React.useState(false)

  const terpilih = kelas.filter((k) => dipilih.includes(k.postId))
  const jumlahSiswa = terpilih.reduce((n, k) => n + k.jumlahSiswa, 0)
  const perluKoreksi = terpilih.reduce((n, k) => n + k.perluKoreksi, 0)
  const banyak = kelas.length > 1

  function togel(id: string) {
    setDipilih((list) => (list.includes(id) ? list.filter((x) => x !== id) : [...list, id]))
  }

  async function unduh() {
    setMengunduh(true)
    try {
      const nama = await downloadFile(`/classroom/post/${postId}/nilai/export`, {
        "kelas[]": dipilih,
        soal: perSoal ? "1" : "0",
        belum: belum ? "1" : "0",
      })
      toast.success(`Nilai diunduh: ${nama}`)
      onSelesai()
    } catch (e) {
      toast.error(e instanceof ApiError ? e.message : "Nilai gagal diunduh")
    } finally {
      setMengunduh(false)
    }
  }

  return (
    <>
      <DialogHeader>
        <DialogTitle>Ekspor nilai ujian</DialogTitle>
        <DialogDescription>
          Berkas Excel berisi sheet Rekap dan satu sheet untuk setiap kelas, dengan nilai skala 0-100.
        </DialogDescription>
      </DialogHeader>

      {banyak ? (
        <section className="flex flex-col gap-2" aria-labelledby="ekspor-kelas">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <h3 id="ekspor-kelas" className="text-sm font-medium">
              Kelas yang diekspor
            </h3>
            <div className="flex gap-1">
              <Button type="button" size="sm" variant="ghost" onClick={() => setDipilih(kelas.map((k) => k.postId))} disabled={dipilih.length === kelas.length}>
                Pilih semua
              </Button>
              <Button type="button" size="sm" variant="ghost" onClick={() => setDipilih([])} disabled={dipilih.length === 0}>
                Kosongkan
              </Button>
            </div>
          </div>
          <ul className="max-h-72 divide-y overflow-y-auto rounded-lg border">
            {kelas.map((k) => (
              <li key={k.postId}>
                <label className="flex min-h-11 cursor-pointer items-center gap-3 px-3 py-2 hover:bg-muted/50">
                  <Checkbox checked={dipilih.includes(k.postId)} onCheckedChange={() => togel(k.postId)} />
                  <span className="flex min-w-0 flex-1 flex-col text-sm">
                    <span className="flex flex-wrap items-center gap-2 font-medium">
                      {k.rombel}
                      {k.utama && <Badge variant="outline">Kelas ini</Badge>}
                    </span>
                    <span className="text-xs text-muted-foreground">
                      {k.selesai}/{k.jumlahSiswa} siswa selesai
                      {k.rata !== null && ` · rata-rata ${formatAngka(k.rata)}`}
                      {k.perluKoreksi > 0 && ` · ${k.perluKoreksi} perlu koreksi`}
                    </span>
                  </span>
                </label>
              </li>
            ))}
          </ul>
        </section>
      ) : (
        <p className="rounded-lg border bg-muted/40 p-3 text-sm">
          <span className="font-medium">{kelas[0].rombel}</span>
          <span className="text-muted-foreground">
            {" "}
            · {kelas[0].selesai} dari {kelas[0].jumlahSiswa} siswa sudah selesai
            {kelas[0].rata !== null && ` · rata-rata ${formatAngka(kelas[0].rata)}`}
          </span>
        </p>
      )}

      <section className="flex flex-col gap-3" aria-label="Isi berkas">
        <label className="flex cursor-pointer items-start gap-3 text-sm">
          <Checkbox checked={perSoal} onCheckedChange={(v) => setPerSoal(v === true)} className="mt-0.5" />
          <span className="flex flex-col gap-0.5">
            <span className="font-medium">Skor tiap soal</span>
            <span className="text-muted-foreground">Satu kolom per soal, berguna untuk melihat soal yang paling banyak salah.</span>
          </span>
        </label>
        <label className="flex cursor-pointer items-start gap-3 text-sm">
          <Checkbox checked={belum} onCheckedChange={(v) => setBelum(v === true)} className="mt-0.5" />
          <span className="flex flex-col gap-0.5">
            <span className="font-medium">Sertakan siswa yang belum mengerjakan</span>
            <span className="text-muted-foreground">Tercatat dengan status Belum mengerjakan dan tanpa nilai.</span>
          </span>
        </label>
      </section>

      {perluKoreksi > 0 && (
        <p role="status" className="flex items-start gap-2 rounded-lg border bg-muted/40 p-3 text-sm text-muted-foreground">
          <TriangleAlert className="mt-0.5 size-4 shrink-0" />
          {perluKoreksi} siswa belum selesai dikoreksi esainya, jadi skornya masih sementara dan ditandai Perlu koreksi di berkas.
        </p>
      )}

      <DialogFooter className="items-center sm:justify-between">
        <p className="text-sm text-muted-foreground" aria-live="polite">
          {terpilih.length} kelas · {jumlahSiswa} siswa
        </p>
        <div className="flex flex-col-reverse gap-2 sm:flex-row">
          <Button type="button" variant="outline" onClick={onSelesai} disabled={mengunduh}>
            Batal
          </Button>
          <Button type="button" onClick={unduh} disabled={mengunduh || dipilih.length === 0}>
            {mengunduh ? <Loader2 className="animate-spin" /> : <FileSpreadsheet />}
            {mengunduh ? "Menyiapkan..." : `Unduh Excel${banyak ? ` (${terpilih.length} kelas)` : ""}`}
          </Button>
        </div>
      </DialogFooter>
    </>
  )
}
