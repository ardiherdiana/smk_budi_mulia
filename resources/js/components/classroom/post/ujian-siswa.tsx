import * as React from "react"
import { Link, router } from "@inertiajs/react"
import { CircleCheck, Clock, KeyRound, ListChecks, Timer } from "lucide-react"

import type { JendelaUjian, Post, StatusPercobaan } from "@/lib/classroom/types"
import { useConfirm } from "@/hooks/use-confirm"
import { formatAngka, formatWaktu } from "@/lib/classroom/format"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import { Field, FieldLabel } from "@/components/ui/field"
import { Input } from "@/components/ui/input"

interface Props {
  post: Post
  jumlahSoal: number
  totalPoin: number
  jendela: JendelaUjian
  percobaanSaya: { status: StatusPercobaan; selesaiAt: string | null; skor: number | null; menungguKoreksi: boolean } | null
  diarsipkan: boolean
}

function Info({ ikon: Ikon, children }: { ikon: React.ComponentType<{ className?: string }>; children: React.ReactNode }) {
  return (
    <li className="flex items-center gap-2 text-sm text-muted-foreground">
      <Ikon className="size-4 shrink-0" /> {children}
    </li>
  )
}

/** Kartu ujian untuk siswa: info, tombol mulai/lanjutkan, dan hasil setelah selesai. */
export function UjianSiswa({ post, jumlahSoal, totalPoin, jendela, percobaanSaya: p, diarsipkan }: Props) {
  const { confirm, confirmDialog } = useConfirm()
  const [tokenBuka, setTokenBuka] = React.useState(false)
  const [token, setToken] = React.useState("")
  const [galat, setGalat] = React.useState<string | null>(null)
  const [memproses, setMemproses] = React.useState(false)

  const peringatan = `Waktu ${post.durasiMenit} menit langsung berjalan dan tidak bisa dijeda. Jawaban tersimpan otomatis. Ujian hanya bisa dikerjakan satu kali.`

  async function mulai() {
    if (post.wajibToken) {
      setToken("")
      setGalat(null)
      setTokenBuka(true)
      return
    }
    const ok = await confirm({ title: "Mulai ujian sekarang?", description: peringatan, confirmLabel: "Mulai ujian" })
    if (ok) router.post(`/classroom/post/${post.id}/mulai`)
  }

  function kirimToken(event: React.FormEvent) {
    event.preventDefault()
    router.post(`/classroom/post/${post.id}/mulai`, { token }, {
      onStart: () => {
        setMemproses(true)
        setGalat(null)
      },
      onError: (errors) => setGalat(errors.token ?? "Token salah"),
      onFinish: () => setMemproses(false),
    })
  }

  return (
    <Card>
      {confirmDialog}
      <Dialog open={tokenBuka} onOpenChange={setTokenBuka}>
        <DialogContent className="sm:max-w-sm">
          <form onSubmit={kirimToken} className="flex flex-col gap-4">
            <DialogHeader>
              <DialogTitle>Masukkan token ujian</DialogTitle>
              <DialogDescription>
                Token diberikan guru. {peringatan}
              </DialogDescription>
            </DialogHeader>
            <Field data-invalid={!!galat}>
              <FieldLabel htmlFor="ujian-token">Token</FieldLabel>
              <Input
                id="ujian-token"
                value={token}
                onChange={(e) => setToken(e.target.value.toUpperCase())}
                autoComplete="off"
                autoCapitalize="characters"
                spellCheck={false}
                maxLength={8}
                required
                autoFocus
                aria-invalid={!!galat}
                className="h-12 text-center font-mono text-xl tracking-[0.3em]"
              />
              {galat && <p className="text-sm text-destructive">{galat}</p>}
            </Field>
            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => setTokenBuka(false)}>
                Batal
              </Button>
              <Button type="submit" disabled={memproses || token.trim() === ""}>
                {memproses ? "Memeriksa..." : "Mulai ujian"}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
      <CardHeader>
        <CardTitle>Ujian</CardTitle>
      </CardHeader>
      <CardContent className="flex flex-col gap-4">
        <ul className="flex flex-col gap-1.5">
          <Info ikon={Timer}>Durasi {post.durasiMenit} menit</Info>
          <Info ikon={ListChecks}>
            {jumlahSoal} soal · total {formatAngka(totalPoin)} poin
          </Info>
          {post.mulaiAt && <Info ikon={Clock}>Dibuka {formatWaktu(post.mulaiAt)}</Info>}
          {post.selesaiAt && <Info ikon={Clock}>Ditutup {formatWaktu(post.selesaiAt)}</Info>}
          {post.wajibToken && <Info ikon={KeyRound}>Perlu token dari guru untuk memulai</Info>}
        </ul>

        {p?.status === "BERJALAN" && (
          <div className="flex flex-col gap-3 rounded-lg border border-primary/30 bg-primary/5 p-4">
            <p className="text-sm font-medium">Kamu sedang mengerjakan ujian ini.</p>
            <Button className="w-fit" render={<Link href={`/classroom/post/${post.id}/kerjakan`} />}>
              Lanjutkan ujian
            </Button>
          </div>
        )}

        {p?.status === "SELESAI" && (
          <div className="rounded-lg border border-success/30 bg-success/10 p-4 text-sm">
            <p className="font-medium">Jawabanmu sudah terkirim</p>
            <p className="text-muted-foreground">Nilai akan tampil setelah guru selesai mengoreksi soal essay.</p>
          </div>
        )}

        {p?.status === "DINILAI" && (
          <div className="flex items-start gap-3 rounded-lg border border-success/30 bg-success/5 p-4">
            <CircleCheck className="mt-0.5 size-5 shrink-0 text-success" />
            <div className="text-sm">
              <p className="font-medium">Ujian selesai</p>
              {p.skor !== null ? (
                <p className="text-2xl font-semibold">
                  {formatAngka(p.skor)} <span className="text-base font-normal text-muted-foreground">/ {formatAngka(totalPoin)}</span>
                </p>
              ) : (
                <p className="text-muted-foreground">Guru belum menampilkan nilai.</p>
              )}
            </div>
          </div>
        )}

        {!p && (
          <>
            {diarsipkan && <p className="text-sm text-muted-foreground">Ruang kelas ini sudah diarsipkan.</p>}
            {!diarsipkan && jendela === "BELUM_DIBUKA" && (
              <p className="text-sm text-muted-foreground">Ujian belum dibuka. Kembali lagi pada {formatWaktu(post.mulaiAt)}.</p>
            )}
            {!diarsipkan && jendela === "DITUTUP" && (
              <p className="text-sm text-destructive">Ujian sudah ditutup dan kamu belum mengerjakannya.</p>
            )}
            {!diarsipkan && jendela === "BERLANGSUNG" && jumlahSoal === 0 && (
              <p className="text-sm text-muted-foreground">Guru belum menambahkan soal.</p>
            )}
            {!diarsipkan && jendela === "BERLANGSUNG" && jumlahSoal > 0 && (
              <Button className="w-fit" onClick={mulai}>
                Mulai ujian
              </Button>
            )}
          </>
        )}
      </CardContent>
    </Card>
  )
}
