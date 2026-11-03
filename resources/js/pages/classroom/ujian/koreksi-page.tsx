import * as React from "react"
import { Link, router } from "@inertiajs/react"
import { ArrowLeft } from "lucide-react"

import type { KelasHeader } from "@/lib/classroom/types"
import { formatAngka, formatWaktu } from "@/lib/classroom/format"
import { HURUF, LABEL_TIPE_SOAL, pecahJawaban, type TipeSoal } from "@/lib/classroom/soal"
import { cn } from "@/lib/classroom/utils"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { GambarPilihan, GambarSoal } from "@/components/classroom/ujian/gambar-soal"
import { Card, CardContent } from "@/components/ui/card"
import { Input } from "@/components/ui/input"

interface SoalKoreksi {
  id: string
  urut: number
  tipe: TipeSoal
  teks: string
  poin: number
  opsi: string[] | null
  gambar?: string[]
  opsiGambar?: (string | null)[]
  kunci: string | null
  kompetensi: string | null
  subKompetensi: string | null
  jawabanId: string | null
  jawaban: string | null
  skor: number | null
}

interface Props {
  kelas: KelasHeader
  post: { id: string; judul: string | null }
  siswa: { nama: string; nis: string | null }
  percobaan: {
    id: string
    status: "BERJALAN" | "SELESAI" | "DINILAI"
    mulaiAt: string
    selesaiAt: string | null
    skorOtomatis: number
    skorManual: number | null
    skorTotal: number
  }
  totalPoin: number
  soal: SoalKoreksi[]
}

function SkorEssay({ soal }: { soal: SoalKoreksi }) {
  const [skor, setSkor] = React.useState(soal.skor !== null ? String(soal.skor) : "")
  const [error, setError] = React.useState<string | undefined>()
  const [memproses, setMemproses] = React.useState(false)

  function simpan(event: React.FormEvent) {
    event.preventDefault()
    if (!soal.jawabanId) return
    router.patch(`/classroom/jawaban/${soal.jawabanId}/skor`, { skor }, {
      preserveScroll: true,
      onStart: () => {
        setMemproses(true)
        setError(undefined)
      },
      onError: (e) => setError(e.skor),
      onFinish: () => setMemproses(false),
    })
  }

  return (
    <form onSubmit={simpan} className="flex flex-wrap items-end gap-2">
      <div className="flex flex-col gap-1">
        <label htmlFor={`skor-${soal.id}`} className="text-xs font-medium text-muted-foreground">
          Skor (maksimal {formatAngka(soal.poin)})
        </label>
        <Input
          id={`skor-${soal.id}`}
          type="number"
          min={0}
          max={soal.poin}
          step="0.5"
          value={skor}
          onChange={(e) => setSkor(e.target.value)}
          className="w-28"
          required
          aria-invalid={!!error}
        />
      </div>
      <Button type="submit" size="sm" disabled={memproses}>
        {memproses ? "Menyimpan..." : "Simpan skor"}
      </Button>
      {error && <p className="w-full text-sm text-destructive">{error}</p>}
    </form>
  )
}

export function KoreksiPage({ kelas, post, siswa, percobaan, totalPoin, soal }: Props) {
  return (
    <div className="flex w-full flex-col gap-4">
      <Button variant="ghost" size="sm" className="w-fit" render={<Link href={`/classroom/post/${post.id}`} />}>
        <ArrowLeft /> {post.judul} · {kelas.rombel}
      </Button>

      <Card>
        <CardContent className="flex flex-wrap items-start justify-between gap-4">
          <div>
            <h1 className="text-xl font-semibold">{siswa.nama}</h1>
            <p className="text-sm text-muted-foreground">
              {siswa.nis ? `NIS ${siswa.nis} · ` : ""}Selesai {formatWaktu(percobaan.selesaiAt)}
            </p>
            <div className="mt-2">
              {percobaan.status === "DINILAI" ? (
                <Badge variant="success">Sudah dinilai</Badge>
              ) : (
                <Badge variant="warning">Ada essay yang belum dinilai</Badge>
              )}
            </div>
          </div>
          <div className="text-right">
            <p className="text-3xl font-semibold">
              {formatAngka(percobaan.skorTotal)}
              <span className="text-base font-normal text-muted-foreground"> / {formatAngka(totalPoin)}</span>
            </p>
            <p className="text-xs text-muted-foreground">
              Otomatis {formatAngka(percobaan.skorOtomatis)} · Essay {formatAngka(percobaan.skorManual ?? 0)}
            </p>
          </div>
        </CardContent>
      </Card>

      {soal.map((s) => (
        <Card key={s.id}>
          <CardContent className="flex flex-col gap-3">
            <div className="flex items-start gap-3">
              <span className="flex size-7 shrink-0 items-center justify-center rounded-full bg-muted text-sm font-semibold">
                {s.urut}
              </span>
              <p className="min-w-0 flex-1 pt-0.5 text-sm break-words whitespace-pre-wrap">{s.teks}</p>
              <Badge variant="outline" className="shrink-0">
                {LABEL_TIPE_SOAL[s.tipe]} · {formatAngka(s.poin)} poin
              </Badge>
            </div>
            <GambarSoal urls={s.gambar} nomor={s.urut} className="pl-10" />

            {s.tipe === "PG" && s.opsi && (
              <ul className="flex flex-col gap-1.5 pl-10">
                {s.opsi.map((opsi, i) => {
                  const dipilih = s.jawaban === String(i)
                  const benar = s.kunci === String(i)
                  return (
                    <li
                      key={i}
                      className={cn(
                        "flex items-start gap-2 rounded-lg border px-3 py-1.5 text-sm",
                        benar && "border-success/50 bg-success/10",
                        dipilih && !benar && "border-destructive/50 bg-destructive/10"
                      )}
                    >
                      <span className="w-5 shrink-0 font-medium">{HURUF[i]}.</span>
                      <span className="flex min-w-0 flex-1 flex-col gap-2">
                        {opsi !== "" && <span className="break-words">{opsi}</span>}
                        <GambarPilihan url={s.opsiGambar?.[i]} label={`Gambar pilihan ${HURUF[i]} soal ${s.urut}`} className="max-h-32" />
                      </span>
                      {dipilih && <span className="shrink-0 text-xs text-muted-foreground">jawaban siswa</span>}
                      {benar && <span className="shrink-0 text-xs font-medium text-success">kunci</span>}
                    </li>
                  )
                })}
                {s.jawaban === null || s.jawaban === "" ? (
                  <li className="text-sm text-muted-foreground">Tidak dijawab</li>
                ) : null}
                <li className="text-sm text-muted-foreground">
                  Skor: {formatAngka(s.skor ?? 0)} / {formatAngka(s.poin)}
                </li>
              </ul>
            )}

            {(s.tipe === "PGK_MCMA" || s.tipe === "PGK_KATEGORI") && s.opsi && (
              <ul className="flex flex-col gap-1.5 pl-10">
                {s.opsi.map((opsi, i) => {
                  const kunci = pecahJawaban(s.kunci)
                  const jawab = pecahJawaban(s.jawaban)
                  const kategori = s.tipe === "PGK_KATEGORI"
                  const benar = kategori ? kunci[i] === "1" : kunci.includes(String(i))
                  const dipilih = kategori ? jawab[i] === "1" : jawab.includes(String(i))
                  const sesuai = kategori ? (jawab[i] ?? "") === (kunci[i] ?? "") : benar === dipilih
                  return (
                    <li
                      key={i}
                      className={cn(
                        "flex flex-wrap items-start gap-2 rounded-lg border px-3 py-1.5 text-sm",
                        sesuai ? "border-success/50 bg-success/10" : "border-destructive/50 bg-destructive/10"
                      )}
                    >
                      <span className="w-5 shrink-0 font-medium">{HURUF[i]}.</span>
                      <span className="flex min-w-0 flex-1 flex-col gap-2">
                        {opsi !== "" && <span className="break-words">{opsi}</span>}
                        <GambarPilihan url={s.opsiGambar?.[i]} label={`Gambar pilihan ${HURUF[i]} soal ${s.urut}`} className="max-h-32" />
                      </span>
                      {kategori ? (
                        <span className="shrink-0 text-xs text-muted-foreground">
                          siswa: {jawab[i] === "1" ? "Benar" : jawab[i] === "0" ? "Salah" : "kosong"} · kunci: {benar ? "Benar" : "Salah"}
                        </span>
                      ) : (
                        <span className="shrink-0 text-xs text-muted-foreground">
                          {dipilih ? "dipilih siswa" : "tidak dipilih"}
                          {benar && " · kunci"}
                        </span>
                      )}
                    </li>
                  )
                })}
                {!s.jawaban?.replace(/,/g, "").trim() && <li className="text-sm text-muted-foreground">Tidak dijawab</li>}
                <li className="text-sm text-muted-foreground">
                  Skor: {formatAngka(s.skor ?? 0)} / {formatAngka(s.poin)}
                </li>
                {(s.kompetensi || s.subKompetensi) && (
                  <li className="text-xs text-muted-foreground">
                    {s.kompetensi}
                    {s.kompetensi && s.subKompetensi ? " · " : ""}
                    {s.subKompetensi}
                  </li>
                )}
              </ul>
            )}

            {s.tipe === "ESSAY" && (
              <div className="flex flex-col gap-3 pl-10">
                <div className="rounded-lg border bg-muted/40 p-3 text-sm break-words whitespace-pre-wrap">
                  {s.jawaban?.trim() ? s.jawaban : <span className="text-muted-foreground">Tidak dijawab</span>}
                </div>
                <SkorEssay key={String(s.skor)} soal={s} />
              </div>
            )}
          </CardContent>
        </Card>
      ))}
    </div>
  )
}
