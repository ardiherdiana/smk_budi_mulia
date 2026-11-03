import * as React from "react"
import { router } from "@inertiajs/react"
import { toast } from "sonner"

import type { Lampiran, Post, RingkasPengumpulan } from "@/lib/classroom/types"
import { useConfirm } from "@/hooks/use-confirm"
import { formatAngka, formatWaktu } from "@/lib/classroom/format"
import { LAMPIRAN_KOSONG, LampiranInput, type LampiranBaru } from "@/components/classroom/lampiran-input"
import { LampiranList } from "@/components/classroom/lampiran-list"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Field, FieldLabel } from "@/components/ui/field"
import { Textarea } from "@/components/ui/textarea"

interface Props {
  post: Post
  pengumpulanSaya: (RingkasPengumpulan & { lampiran: Lampiran[] }) | null
  bisaMengumpulkan: boolean
  diarsipkan: boolean
}

/** Kartu "Tugas Anda": kumpulkan, perbarui, batalkan, dan lihat nilai serta umpan balik. */
export function TugasSiswa({ post, pengumpulanSaya: p, bisaMengumpulkan, diarsipkan }: Props) {
  const { confirm, confirmDialog } = useConfirm()
  const [teks, setTeks] = React.useState(p?.teks ?? "")
  const [lampiran, setLampiran] = React.useState<LampiranBaru>(LAMPIRAN_KOSONG)
  const [errors, setErrors] = React.useState<Record<string, string>>({})
  const [memproses, setMemproses] = React.useState(false)

  const dinilai = p?.status === "DINILAI"
  const bisaUbah = !dinilai && bisaMengumpulkan && !diarsipkan

  function kumpulkan(event: React.FormEvent) {
    event.preventDefault()
    router.post(`/classroom/post/${post.id}/kumpulkan`, { teks, berkas: lampiran.berkas, tautan: lampiran.tautan }, {
      forceFormData: true,
      preserveScroll: true,
      onStart: () => {
        setMemproses(true)
        setErrors({})
      },
      onSuccess: () => setLampiran(LAMPIRAN_KOSONG),
      onError: (e) => setErrors(e),
      onFinish: () => setMemproses(false),
    })
  }

  async function batalkan() {
    const ok = await confirm({
      title: "Batalkan pengumpulan?",
      description: "Jawaban dan lampiran yang sudah dikumpulkan akan dihapus. Kamu bisa mengumpulkan lagi selama tenggat belum lewat.",
      confirmLabel: "Batalkan pengumpulan",
      destructive: true,
    })
    if (!ok) return
    router.delete(`/classroom/post/${post.id}/kumpulkan`, {
      preserveScroll: true,
      onSuccess: () => setTeks(""),
    })
  }

  function hapusLampiran(l: Lampiran) {
    router.delete(`/classroom/lampiran/${l.id}`, {
      preserveScroll: true,
      onError: () => toast.error("Gagal menghapus lampiran"),
    })
  }

  return (
    <Card>
      {confirmDialog}
      <CardHeader>
        <CardTitle className="flex flex-wrap items-center justify-between gap-2">
          Tugas Anda
          {dinilai ? (
            <Badge variant="success">
              Nilai {formatAngka(p?.nilai)}/{post.poinMaks ?? 100}
            </Badge>
          ) : p ? (
            <Badge variant={p.terlambat ? "warning" : "secondary"}>{p.terlambat ? "Dikumpulkan terlambat" : "Dikumpulkan"}</Badge>
          ) : (
            <Badge variant="warning">Belum dikumpulkan</Badge>
          )}
        </CardTitle>
      </CardHeader>
      <CardContent className="flex flex-col gap-4">
        {p && (
          <p className="text-xs text-muted-foreground">Dikumpulkan {formatWaktu(p.submittedAt)}</p>
        )}

        {dinilai && p?.feedback && (
          <div className="rounded-lg border bg-muted/40 p-3 text-sm">
            <p className="mb-1 text-xs font-medium text-muted-foreground">Umpan balik guru</p>
            <p className="break-words whitespace-pre-wrap">{p.feedback}</p>
          </div>
        )}

        {p && !bisaUbah && (
          <>
            {p.teks && <p className="text-sm break-words whitespace-pre-wrap">{p.teks}</p>}
            <LampiranList lampiran={p.lampiran} />
          </>
        )}

        {!bisaUbah && !p && (
          <p className="text-sm text-muted-foreground">
            {diarsipkan ? "Ruang kelas ini sudah diarsipkan." : "Batas waktu pengumpulan sudah lewat."}
          </p>
        )}

        {dinilai && <p className="text-sm text-muted-foreground">Tugas sudah dinilai, jadi tidak bisa diubah lagi.</p>}

        {bisaUbah && (
          <form onSubmit={kumpulkan} className="flex flex-col gap-4">
            {p && p.lampiran.length > 0 && (
              <Field>
                <FieldLabel>Lampiran terkumpul</FieldLabel>
                <LampiranList lampiran={p.lampiran} onHapus={hapusLampiran} />
              </Field>
            )}
            <Field data-invalid={!!errors.teks}>
              <FieldLabel htmlFor="tugas-teks">Jawaban (opsional bila melampirkan berkas)</FieldLabel>
              <Textarea
                id="tugas-teks"
                value={teks}
                onChange={(e) => setTeks(e.target.value)}
                rows={4}
                maxLength={10000}
                aria-invalid={!!errors.teks}
              />
              {errors.teks && <p className="text-sm text-destructive">{errors.teks}</p>}
            </Field>
            <Field>
              <FieldLabel>{p ? "Tambah lampiran" : "Lampiran"}</FieldLabel>
              <LampiranInput
                value={lampiran}
                onChange={setLampiran}
                error={errors.berkas ?? errors["berkas.0"] ?? errors.tautan ?? errors["tautan.0"]}
              />
            </Field>
            <div className="flex flex-wrap gap-2">
              <Button type="submit" disabled={memproses}>
                {memproses ? "Mengirim..." : p ? "Perbarui pengumpulan" : "Kumpulkan"}
              </Button>
              {p && (
                <Button type="button" variant="outline" onClick={batalkan}>
                  Batalkan pengumpulan
                </Button>
              )}
            </div>
          </form>
        )}
      </CardContent>
    </Card>
  )
}
