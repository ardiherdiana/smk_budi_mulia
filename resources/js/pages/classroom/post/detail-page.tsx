import * as React from "react"
import { Link } from "@inertiajs/react"
import { ArrowLeft, Pencil, Timer, Trash2, Users } from "lucide-react"

import type { JendelaUjian, KelasHeader, KelasUjian, Lampiran, Post, RingkasPengumpulan, Soal, StatusPercobaan } from "@/lib/classroom/types"
import { useAuth } from "@/context/classroom-auth"
import { useHapusPost } from "@/hooks/use-hapus-post"
import { format } from "date-fns"
import { id as localeId } from "date-fns/locale"
import { formatWaktu } from "@/lib/classroom/format"
import { cn } from "@/lib/classroom/utils"
import { KomentarSection } from "@/components/classroom/komentar-section"
import { LampiranList } from "@/components/classroom/lampiran-list"
import { IKON_TIPE, LABEL_TIPE } from "@/components/classroom/post-card"
import { PostEditDialog } from "@/components/classroom/post/post-edit-dialog"
import { TugasGuru, type BarisPengumpulan } from "@/components/classroom/post/tugas-guru"
import { TugasSiswa } from "@/components/classroom/post/tugas-siswa"
import { UjianGuru, type HasilSiswa } from "@/components/classroom/post/ujian-guru"
import { UjianSiswa } from "@/components/classroom/post/ujian-siswa"
import { Button } from "@/components/ui/button"

interface Props {
  kelas: KelasHeader
  post: Post
  dapatDikelola: boolean
  // TUGAS
  pengumpulanSaya?: (RingkasPengumpulan & { lampiran: Lampiran[] }) | null
  bisaMengumpulkan?: boolean
  pengumpulan?: BarisPengumpulan[]
  // UJIAN
  jumlahSoal?: number
  totalPoin?: number
  jendela?: JendelaUjian
  percobaanSaya?: { status: StatusPercobaan; selesaiAt: string | null; skor: number | null; menungguKoreksi: boolean } | null
  sudahDikerjakan?: boolean
  soal?: Soal[]
  hasil?: HasilSiswa[]
  kelasUjian?: KelasUjian[]
}

export function PostDetailPage(props: Props) {
  const { kelas, post, dapatDikelola } = props
  const { isSiswa } = useAuth()
  const [ubahBuka, setUbahBuka] = React.useState(false)
  const Ikon = IKON_TIPE[post.tipe]
  const { hapus, confirmDialog } = useHapusPost(post, {
    title: `Hapus ${LABEL_TIPE[post.tipe].toLowerCase()} "${post.judul ?? ""}"?`,
    description: "Lampiran, komentar, dan semua pengumpulan atau hasil ujian siswa ikut terhapus.",
  })

  const diedit = new Date(post.updatedAt).getTime() - new Date(post.createdAt).getTime() > 60_000

  return (
    <div className="flex w-full flex-col gap-4">
      {confirmDialog}

      <Button variant="ghost" size="sm" className="w-fit" render={<Link href={`/classroom/kelas/${kelas.id}`} />}>
        <ArrowLeft /> {kelas.mapel} · {kelas.rombel}
      </Button>

      <div className={cn("grid w-full items-start gap-6", isSiswa && "lg:grid-cols-[minmax(0,1fr)_22rem]")}>
        <div className="flex min-w-0 flex-col gap-5">
          <div className="flex items-start gap-3">
            <div className="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
              <Ikon className="size-5" />
            </div>
            <h1 className="min-w-0 flex-1 text-xl font-semibold break-words">{post.judul ?? LABEL_TIPE[post.tipe]}</h1>
            {dapatDikelola && !kelas.diarsipkan && (
              <div className="flex shrink-0 gap-1">
                <Button variant="outline" size="sm" onClick={() => setUbahBuka(true)}>
                  <Pencil /> Ubah
                </Button>
                <Button variant="ghost" size="icon-sm" onClick={hapus} aria-label="Hapus">
                  <Trash2 />
                </Button>
              </div>
            )}
          </div>

          <div className="flex flex-col gap-1 border-b pb-4 text-sm">
            <p className="text-muted-foreground">
              {post.pembuat} · {format(new Date(post.createdAt), "d MMM yyyy", { locale: localeId })}
              {diedit && ` (Diedit ${format(new Date(post.updatedAt), "d MMM yyyy", { locale: localeId })})`}
            </p>
            {post.tipe === "TUGAS" && (
              <p>
                {post.poinMaks ?? 100} poin <span className="mx-1 text-muted-foreground">|</span>{" "}
                {post.deadline ? `Tenggat: ${formatWaktu(post.deadline)}` : "Tanpa tenggat"}
                {!post.izinTerlambat && <span className="text-muted-foreground"> · Tidak menerima pengumpulan terlambat</span>}
              </p>
            )}
            {post.tipe === "UJIAN" && !isSiswa && (
              <p className="flex flex-wrap items-center gap-x-4 gap-y-1">
                <span className="flex items-center gap-1.5">
                  <Timer className="size-4" /> {post.durasiMenit} menit
                </span>
                <span>Dibuka {post.mulaiAt ? formatWaktu(post.mulaiAt) : "langsung"}</span>
                <span>Ditutup {post.selesaiAt ? formatWaktu(post.selesaiAt) : "tanpa batas"}</span>
                {post.acakSoal && <span>Soal diacak</span>}
                {!post.tampilkanNilai && <span>Nilai tidak ditampilkan ke siswa</span>}
              </p>
            )}
          </div>

          {post.isi && <p className="text-sm break-words whitespace-pre-wrap">{post.isi}</p>}
          <LampiranList lampiran={post.lampiran} />

          {post.tipe === "TUGAS" && !isSiswa && <TugasGuru post={post} baris={props.pengumpulan ?? []} />}
          {post.tipe === "UJIAN" && !isSiswa && (
            <UjianGuru
              postId={post.id}
              tokenAkses={post.tokenAkses}
              soal={props.soal ?? []}
              totalPoin={props.totalPoin ?? 0}
              sudahDikerjakan={props.sudahDikerjakan ?? false}
              hasil={props.hasil ?? []}
              kelasUjian={props.kelasUjian ?? []}
            />
          )}

          <section className="flex flex-col gap-3 border-t pt-4">
            <h2 className="flex items-center gap-3 text-sm font-medium">
              <Users className="size-5 text-muted-foreground" /> Komentar kelas
            </h2>
            <KomentarSection postId={post.id} komentar={post.komentar ?? []} />
          </section>
        </div>

        {isSiswa && post.tipe === "TUGAS" && (
          <div className="lg:sticky lg:top-4">
            <TugasSiswa
              post={post}
              pengumpulanSaya={props.pengumpulanSaya ?? null}
              bisaMengumpulkan={props.bisaMengumpulkan ?? false}
              diarsipkan={kelas.diarsipkan}
            />
          </div>
        )}
        {isSiswa && post.tipe === "UJIAN" && (
          <div className="lg:sticky lg:top-4">
            <UjianSiswa
              post={post}
              jumlahSoal={props.jumlahSoal ?? 0}
              totalPoin={props.totalPoin ?? 0}
              jendela={props.jendela ?? "BERLANGSUNG"}
              percobaanSaya={props.percobaanSaya ?? null}
              diarsipkan={kelas.diarsipkan}
            />
          </div>
        )}
      </div>

      {dapatDikelola && post.tipe !== "PENGUMUMAN" && (
        <PostEditDialog post={post} buka={ubahBuka} onBukaChange={setUbahBuka} />
      )}
    </div>
  )
}
