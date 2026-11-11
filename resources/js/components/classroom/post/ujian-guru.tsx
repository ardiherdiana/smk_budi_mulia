import { Link, router } from "@inertiajs/react"
import { ClipboardCheck, Copy, KeyRound, Lock, Pencil, RefreshCw } from "lucide-react"
import { toast } from "sonner"

import type { KelasUjian, Soal, StatusPercobaan } from "@/lib/classroom/types"
import { useConfirm } from "@/hooks/use-confirm"
import { formatAngka } from "@/lib/classroom/format"
import { HURUF, LABEL_TIPE_SOAL, pecahJawaban } from "@/lib/classroom/soal"
import { EksporNilai } from "@/components/classroom/post/ekspor-nilai"
import { GambarPilihan, GambarSoal } from "@/components/classroom/ujian/gambar-soal"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"

export interface HasilSiswa {
  siswaId: string
  nama: string
  nis: string | null
  percobaanId: string | null
  status: StatusPercobaan | null
  skor: number | null
}

interface Props {
  postId: string
  /** Token ujian; null/kosong bila ujian tidak memakai token. */
  tokenAkses?: string | null
  soal: Soal[]
  totalPoin: number
  sudahDikerjakan: boolean
  hasil: HasilSiswa[]
  /** Ruang kelas ujian ini (kelas ini dan kelas lain satu grup) untuk dialog ekspor nilai. */
  kelasUjian: KelasUjian[]
}

/** Token besar supaya bisa diproyeksikan ke kelas; guru bisa menyalin atau menggantinya (mis. setelah bocor). */
function TokenUjian({ postId, token }: { postId: string; token: string }) {
  const { confirm, confirmDialog } = useConfirm()

  async function salin() {
    try {
      await navigator.clipboard.writeText(token)
      toast.success("Token disalin")
    } catch {
      toast.error("Token tidak bisa disalin, salin manual")
    }
  }

  async function ganti() {
    const ok = await confirm({
      title: "Buat token baru?",
      description: "Token lama langsung tidak berlaku. Siswa yang sudah mulai mengerjakan tetap bisa melanjutkan.",
      confirmLabel: "Buat token baru",
    })
    if (ok) router.post(`/classroom/post/${postId}/token`, {}, { preserveScroll: true })
  }

  return (
    <section className="flex flex-col gap-3 rounded-lg border p-4">
      {confirmDialog}
      <h2 className="flex items-center gap-2 text-sm font-medium text-muted-foreground">
        <KeyRound className="size-4" /> Token ujian
      </h2>
      <p className="font-mono text-4xl font-semibold tracking-[0.3em] select-all" aria-label={`Token ujian ${token.split("").join(" ")}`}>
        {token}
      </p>
      <div className="flex flex-wrap gap-2">
        <Button size="sm" variant="outline" onClick={salin}>
          <Copy /> Salin
        </Button>
        <Button size="sm" variant="ghost" onClick={ganti}>
          <RefreshCw /> Buat token baru
        </Button>
      </div>
      <p className="text-xs text-muted-foreground">Sebutkan atau tampilkan token ini saat ujian dimulai. Siswa hanya memasukkannya sekali, saat menekan Mulai.</p>
    </section>
  )
}

function StatusHasil({ status }: { status: StatusPercobaan | null }) {
  if (!status) return <Badge variant="outline">Belum mulai</Badge>
  if (status === "BERJALAN") return <Badge variant="warning">Mengerjakan</Badge>
  if (status === "SELESAI") return <Badge variant="secondary">Perlu koreksi</Badge>
  return <Badge variant="success">Dinilai</Badge>
}

/** Sisi guru untuk ujian: ringkasan soal (diedit di halaman editor) dan hasil tiap siswa. */
export function UjianGuru({ postId, tokenAkses, soal, totalPoin, sudahDikerjakan, hasil, kelasUjian }: Props) {
  return (
    <>
      {tokenAkses && <TokenUjian postId={postId} token={tokenAkses} />}

      <section className="flex flex-col gap-3">
        <h2 className="flex flex-wrap items-center justify-between gap-2 text-lg font-medium">
          <span>
            Soal ({soal.length}) <span className="text-sm font-normal text-muted-foreground">· total {formatAngka(totalPoin)} poin</span>
          </span>
          <Button size="sm" variant={sudahDikerjakan ? "outline" : "default"} render={<Link href={`/classroom/post/${postId}/soal`} />}>
            <Pencil /> {sudahDikerjakan ? "Lihat soal" : soal.length === 0 ? "Tulis soal" : "Kelola soal"}
          </Button>
        </h2>
        {sudahDikerjakan && (
          <div className="flex items-start gap-2 rounded-lg border bg-muted/40 p-3 text-sm text-muted-foreground">
            <Lock className="mt-0.5 size-4 shrink-0" />
            Soal dikunci karena sudah ada siswa yang mengerjakan, supaya nilai yang sudah keluar tidak berubah.
          </div>
        )}

        {soal.length === 0 ? (
          <p className="py-6 text-center text-sm text-muted-foreground">
            Belum ada soal. Siswa baru bisa mengerjakan setelah ada minimal satu soal.
          </p>
        ) : (
          <ol className="grid items-start gap-3 xl:grid-cols-2">
            {soal.map((s) => (
              <li key={s.id} className="rounded-lg border p-3">
                <div className="flex items-start gap-3">
                  <span className="flex size-6 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-medium">
                    {s.urut}
                  </span>
                  <div className="min-w-0 flex-1">
                    <div className="mb-1 flex flex-wrap items-center gap-2">
                      <Badge variant="outline">{LABEL_TIPE_SOAL[s.tipe]}</Badge>
                      <span className="text-xs text-muted-foreground">{formatAngka(s.poin)} poin</span>
                    </div>
                    <p className="text-sm break-words whitespace-pre-wrap">{s.teks}</p>
                    <GambarSoal urls={s.gambar} nomor={s.urut} className="mt-2" />
                    {s.tipe !== "ESSAY" && s.opsi && (
                      <ul className="mt-2 flex flex-col gap-1 text-sm">
                        {s.opsi.map((o, i) => {
                          const kunci = pecahJawaban(s.kunci)
                          const benar = s.tipe === "PGK_KATEGORI" ? kunci[i] === "1" : kunci.includes(String(i))
                          return (
                            <li key={i} className={benar ? "font-medium text-success" : "text-muted-foreground"}>
                              {HURUF[i]}. {o}
                              {s.tipe === "PGK_KATEGORI" ? (kunci[i] === "1" ? " · Benar" : " · Salah") : benar && " ✓"}
                              <GambarPilihan url={s.opsiGambar?.[i]} label={`Gambar pilihan ${HURUF[i]} soal ${s.urut}`} className="mt-1 max-h-32" />
                            </li>
                          )
                        })}
                      </ul>
                    )}
                    {(s.kompetensi || s.subKompetensi) && (
                      <p className="mt-2 text-xs text-muted-foreground">
                        {s.kompetensi}
                        {s.kompetensi && s.subKompetensi ? " · " : ""}
                        {s.subKompetensi}
                      </p>
                    )}
                  </div>
                </div>
              </li>
            ))}
          </ol>
        )}
      </section>

      <section className="flex flex-col gap-3">
        <h2 className="flex flex-wrap items-center justify-between gap-2 text-lg font-medium">
          <span>Hasil Siswa</span>
          <EksporNilai postId={postId} kelas={kelasUjian} />
        </h2>
        <div className="overflow-x-auto rounded-lg border">
          <Table kartu>
            <TableHeader>
              <TableRow>
                <TableHead className="w-10">No</TableHead>
                <TableHead>Nama</TableHead>
                <TableHead>Status</TableHead>
                <TableHead className="text-right">Skor</TableHead>
                <TableHead className="text-right">Aksi</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {hasil.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={5} className="text-center text-muted-foreground">
                    Belum ada siswa di rombel ini
                  </TableCell>
                </TableRow>
              ) : (
                hasil.map((h, i) => (
                  <TableRow key={h.siswaId}>
                    <TableCell kartu="sembunyi" className="text-muted-foreground">{i + 1}</TableCell>
                    <TableCell kartu="judul" className="font-medium">{h.nama}</TableCell>
                    <TableCell>
                      <StatusHasil status={h.status} />
                    </TableCell>
                    <TableCell className="text-right">
                      {h.skor !== null ? (
                        <>
                          {formatAngka(h.skor)} <span className="text-muted-foreground">/ {formatAngka(totalPoin)}</span>
                        </>
                      ) : (
                        <span className="text-muted-foreground">-</span>
                      )}
                    </TableCell>
                    <TableCell kartu="aksi" className="text-right">
                      {h.percobaanId && h.status !== "BERJALAN" && (
                        <Button
                          variant="ghost"
                          size="sm"
                          data-tombol-aksi="lain"
                          render={<Link href={`/classroom/percobaan/${h.percobaanId}`} />}
                        >
                          <ClipboardCheck /> {h.status === "SELESAI" ? "Koreksi" : "Lihat"}
                        </Button>
                      )}
                    </TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
        </div>
      </section>

    </>
  )
}
