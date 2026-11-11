import * as React from "react"
import { router } from "@inertiajs/react"
import { Eye } from "lucide-react"

import type { Lampiran, Post, RingkasPengumpulan } from "@/lib/classroom/types"
import { formatAngka, formatWaktu } from "@/lib/classroom/format"
import { LampiranList } from "@/components/classroom/lampiran-list"
import { Badge } from "@/components/ui/badge"
import { TombolAksi } from "@/components/tombol-aksi"
import { Button } from "@/components/ui/button"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import { Field, FieldGroup, FieldLabel } from "@/components/ui/field"
import { Input } from "@/components/ui/input"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import { Textarea } from "@/components/ui/textarea"

export interface BarisPengumpulan {
  siswaId: string
  nama: string
  nis: string | null
  pengumpulan: (RingkasPengumpulan & { lampiran: Lampiran[] }) | null
}

function StatusBadge({ p }: { p: BarisPengumpulan["pengumpulan"] }) {
  if (!p) return <Badge variant="outline">Belum mengumpulkan</Badge>
  if (p.status === "DINILAI") return <Badge variant="success">Dinilai</Badge>
  return <Badge variant={p.terlambat ? "warning" : "secondary"}>{p.terlambat ? "Terlambat" : "Perlu dinilai"}</Badge>
}

/** Daftar siswa dan status pengumpulannya, dengan dialog untuk memeriksa dan menilai. */
export function TugasGuru({ post, baris }: { post: Post; baris: BarisPengumpulan[] }) {
  const [dipilih, setDipilih] = React.useState<BarisPengumpulan | null>(null)
  const [nilai, setNilai] = React.useState("")
  const [feedback, setFeedback] = React.useState("")
  const [errors, setErrors] = React.useState<Record<string, string>>({})
  const [memproses, setMemproses] = React.useState(false)
  const maks = post.poinMaks ?? 100

  const sudah = baris.filter((b) => b.pengumpulan).length
  const dinilai = baris.filter((b) => b.pengumpulan?.status === "DINILAI").length

  function periksa(b: BarisPengumpulan) {
    setDipilih(b)
    setNilai(b.pengumpulan?.nilai !== null && b.pengumpulan?.nilai !== undefined ? String(b.pengumpulan.nilai) : "")
    setFeedback(b.pengumpulan?.feedback ?? "")
    setErrors({})
  }

  function simpan(event: React.FormEvent) {
    event.preventDefault()
    const p = dipilih?.pengumpulan
    if (!p) return
    router.patch(`/classroom/pengumpulan/${p.id}/nilai`, { nilai, feedback }, {
      preserveScroll: true,
      onStart: () => {
        setMemproses(true)
        setErrors({})
      },
      onSuccess: () => setDipilih(null),
      onError: (e) => setErrors(e),
      onFinish: () => setMemproses(false),
    })
  }

  const p = dipilih?.pengumpulan

  return (
    <section className="flex flex-col gap-3">
      <h2 className="flex flex-wrap items-center justify-between gap-2 text-lg font-medium">
        Pengumpulan Siswa
        <span className="text-sm font-normal text-muted-foreground">
          {sudah}/{baris.length} mengumpulkan · {dinilai} dinilai
        </span>
      </h2>
      <div className="overflow-x-auto rounded-lg border">
        <Table kartu>
          <TableHeader>
            <TableRow>
              <TableHead className="w-10">No</TableHead>
              <TableHead>Nama</TableHead>
              <TableHead>Status</TableHead>
              <TableHead>Dikumpulkan</TableHead>
              <TableHead className="text-right">Nilai</TableHead>
              <TableHead className="text-right">Aksi</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {baris.length === 0 ? (
              <TableRow>
                <TableCell colSpan={6} className="text-center text-muted-foreground">
                  Belum ada siswa di rombel ini
                </TableCell>
              </TableRow>
            ) : (
              baris.map((b, i) => (
                <TableRow key={b.siswaId}>
                  <TableCell kartu="sembunyi" className="text-muted-foreground">{i + 1}</TableCell>
                  <TableCell kartu="judul" className="font-medium">{b.nama}</TableCell>
                  <TableCell>
                    <StatusBadge p={b.pengumpulan} />
                  </TableCell>
                  <TableCell className="text-muted-foreground">{formatWaktu(b.pengumpulan?.submittedAt)}</TableCell>
                  <TableCell className="text-right">
                    {b.pengumpulan?.nilai !== null && b.pengumpulan?.nilai !== undefined ? (
                      <>
                        {formatAngka(b.pengumpulan.nilai)} <span className="text-muted-foreground">/ {maks}</span>
                      </>
                    ) : (
                      <span className="text-muted-foreground">-</span>
                    )}
                  </TableCell>
                  <TableCell kartu="aksi" className="text-right">
                    <TombolAksi
                      label="Periksa"
                      onClick={() => periksa(b)}
                      disabled={!b.pengumpulan}
                      aria-label={`Periksa pengumpulan ${b.nama}`}
                    >
                      <Eye />
                    </TombolAksi>
                  </TableCell>
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>
      </div>

      <Dialog open={!!dipilih} onOpenChange={(buka) => !buka && setDipilih(null)}>
        <DialogContent className="max-h-[90svh] overflow-y-auto sm:max-w-lg">
          <form onSubmit={simpan} className="flex flex-col gap-4">
            <DialogHeader>
              <DialogTitle>{dipilih?.nama}</DialogTitle>
              <DialogDescription>
                Dikumpulkan {formatWaktu(p?.submittedAt)}
                {p?.terlambat ? " (terlambat)" : ""}
              </DialogDescription>
            </DialogHeader>

            {p?.teks && (
              <div className="rounded-lg border bg-muted/40 p-3 text-sm break-words whitespace-pre-wrap">{p.teks}</div>
            )}
            {p && p.lampiran.length > 0 ? (
              <LampiranList lampiran={p.lampiran} />
            ) : (
              !p?.teks && <p className="text-sm text-muted-foreground">Tidak ada isi.</p>
            )}

            <FieldGroup>
              <Field data-invalid={!!errors.nilai}>
                <FieldLabel htmlFor="nilai-tugas">Nilai (maksimal {maks})</FieldLabel>
                <Input
                  id="nilai-tugas"
                  type="number"
                  min={0}
                  max={maks}
                  step="0.01"
                  value={nilai}
                  onChange={(e) => setNilai(e.target.value)}
                  required
                  aria-invalid={!!errors.nilai}
                  className="sm:max-w-40"
                />
                {errors.nilai && <p className="text-sm text-destructive">{errors.nilai}</p>}
              </Field>
              <Field>
                <FieldLabel htmlFor="feedback-tugas">Umpan balik (opsional)</FieldLabel>
                <Textarea id="feedback-tugas" value={feedback} onChange={(e) => setFeedback(e.target.value)} rows={3} maxLength={2000} />
              </Field>
            </FieldGroup>

            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => setDipilih(null)}>
                Tutup
              </Button>
              <Button type="submit" disabled={memproses}>
                {memproses ? "Menyimpan..." : p?.status === "DINILAI" ? "Perbarui nilai" : "Simpan nilai"}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </section>
  )
}
