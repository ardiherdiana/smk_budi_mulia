import { Link } from "@inertiajs/react"
import { BookOpen, ClipboardCheck, FileText, NotebookPen, UserRound } from "lucide-react"
import { format } from "date-fns"
import { id as localeId } from "date-fns/locale"

import type { KelasHeader as KelasHeaderData, Post } from "@/lib/classroom/types"
import { useAuth } from "@/context/classroom-auth"
import { KelasHeader } from "@/components/classroom/kelas-header"
import { cn } from "@/lib/classroom/utils"
import { Button } from "@/components/ui/button"

interface Props {
  kelas: KelasHeaderData
  posts: Post[]
}

const IKON = { TUGAS: FileText, UJIAN: ClipboardCheck, MATERI: BookOpen, PENGUMUMAN: FileText } as const

/** Siswa: tugas/ujian yang sudah dikumpulkan (ujian: sudah diselesaikan). */
function sudahSelesai(post: Post): boolean {
  if (post.tipe === "TUGAS") return !!post.pengumpulanSaya
  if (post.tipe === "UJIAN") return !!post.percobaanSaya && post.percobaanSaya.status !== "BERJALAN"
  return false
}

function keterangan(post: Post): string {
  if (post.tipe === "MATERI") return `Diposting ${format(new Date(post.createdAt), "d MMM", { locale: localeId })}`
  const tenggat = post.tipe === "UJIAN" ? (post.selesaiAt ?? post.mulaiAt) : post.deadline
  return tenggat ? `Tenggat: ${format(new Date(tenggat), "d MMM, HH.mm", { locale: localeId })}` : "Tak ada batas waktu"
}

export function TugasKelasPage({ kelas, posts }: Props) {
  const { isSiswa } = useAuth()

  return (
    <div className="flex flex-col gap-4">
      <KelasHeader kelas={kelas} />

      <div className="flex w-full flex-col gap-4 pt-2">
        {isSiswa && (
          <div className="flex justify-end">
            <Button variant="outline" className="rounded-full" render={<Link href="/classroom/tugas" />}>
              <UserRound /> Lihat tugas Anda
            </Button>
          </div>
        )}

        {posts.length === 0 ? (
          <div className="flex flex-col items-center gap-2 py-16 text-center">
            <NotebookPen className="size-10 text-muted-foreground" />
            <p className="font-medium">Belum ada tugas atau materi</p>
            <p className="max-w-sm text-sm text-muted-foreground">
              {kelas.dapatDikelola
                ? "Buat tugas, ujian, atau materi dari tab Forum."
                : "Tugas, ujian, dan materi dari guru akan muncul di sini."}
            </p>
          </div>
        ) : (
          <div className="flex flex-col border-t">
            {posts.map((post) => {
              const Ikon = IKON[post.tipe]
              const perluDikerjakan = isSiswa && post.tipe !== "MATERI" && !sudahSelesai(post)
              return (
                <Link
                  key={post.id}
                  href={`/classroom/post/${post.id}`}
                  className="flex items-center gap-4 border-b px-2 py-3 transition-colors hover:bg-muted/50"
                >
                  <span
                    className={cn(
                      "flex size-10 shrink-0 items-center justify-center rounded-full",
                      perluDikerjakan ? "bg-primary text-primary-foreground" : "bg-muted text-muted-foreground",
                    )}
                  >
                    <Ikon className="size-5" />
                  </span>
                  <span className="min-w-0 flex-1 truncate text-sm">{post.judul}</span>
                  <span className="shrink-0 text-sm text-muted-foreground">{keterangan(post)}</span>
                </Link>
              )
            })}
          </div>
        )}
      </div>
    </div>
  )
}
