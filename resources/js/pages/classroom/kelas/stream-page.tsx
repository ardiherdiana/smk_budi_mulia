import { Link } from "@inertiajs/react"
import { Megaphone } from "lucide-react"

import type { KelasHeader as KelasHeaderData, KelasTujuan, Mendatang, Post } from "@/lib/classroom/types"
import { formatRelatif } from "@/lib/classroom/format"
import { warnaKelas } from "@/lib/classroom/kelas-warna"
import { cn } from "@/lib/classroom/utils"
import { IKON_TIPE, LABEL_TIPE, PostCard, PostRingkas } from "@/components/classroom/post-card"
import { KelasHeader } from "@/components/classroom/kelas-header"
import { PostComposer } from "@/components/classroom/post-composer"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"

interface Props {
  kelas: KelasHeaderData
  posts: Post[]
  mendatang: Mendatang[]
  tujuan: KelasTujuan[]
}

export function StreamPage({ kelas, posts, mendatang, tujuan }: Props) {
  return (
    <div className="flex flex-col gap-4">
      <KelasHeader kelas={kelas} />

      <div className="flex w-full flex-col gap-4 pt-2">
        <div className={cn("flex min-h-40 flex-col justify-end gap-1 rounded-xl p-6 md:min-h-56", warnaKelas(kelas.id))}>
          <h2 className="text-2xl font-semibold">{kelas.mapel}</h2>
          <p className="text-sm opacity-90">{kelas.rombel}</p>
        </div>

        <div className="grid items-start gap-4 lg:grid-cols-[14rem_minmax(0,1fr)]">
          <Card size="sm" className="lg:sticky lg:top-4">
            <CardHeader>
              <CardTitle className="text-base font-medium">Mendatang</CardTitle>
            </CardHeader>
            <CardContent className="flex flex-col gap-3">
              {mendatang.length === 0 ? (
                <p className="text-xs text-muted-foreground">Hore, tidak ada tugas yang perlu segera diselesaikan!</p>
              ) : (
                mendatang.map((item) => {
                  const Ikon = IKON_TIPE[item.tipe]
                  return (
                    <Link key={item.id} href={`/classroom/post/${item.id}`} className="group flex items-start gap-2 text-sm">
                      <Ikon className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                      <span className="min-w-0">
                        <span className="line-clamp-2 font-medium group-hover:underline">{item.judul ?? LABEL_TIPE[item.tipe]}</span>
                        <span className="block text-xs text-muted-foreground">
                          {item.batas ? formatRelatif(item.batas) : "Tanpa batas waktu"}
                        </span>
                      </span>
                    </Link>
                  )
                })
              )}
              <Link href={`/classroom/kelas/${kelas.id}/tugas`} className="self-end text-sm font-medium text-primary hover:underline">
                Lihat semua
              </Link>
            </CardContent>
          </Card>

          <div className="flex min-w-0 flex-col gap-4">
            {kelas.dapatDikelola && !kelas.diarsipkan && <PostComposer kelasId={kelas.id} tujuan={tujuan} />}

            {posts.length === 0 ? (
              <Card>
                <CardContent className="flex flex-col items-center gap-2 py-12 text-center">
                  <Megaphone className="size-10 text-muted-foreground" />
                  <p className="font-medium">Belum ada postingan</p>
                  <p className="max-w-sm text-sm text-muted-foreground">
                    {kelas.dapatDikelola
                      ? "Bagikan pengumuman, materi, tugas, atau ujian pertama untuk kelas ini."
                      : "Pengumuman, materi, tugas, dan ujian dari guru akan muncul di sini."}
                  </p>
                </CardContent>
              </Card>
            ) : (
              posts.map((post) =>
                post.tipe === "PENGUMUMAN" ? (
                  <PostCard key={post.id} post={post} dapatDikelola={kelas.dapatDikelola} />
                ) : (
                  <PostRingkas key={post.id} post={post} dapatDikelola={kelas.dapatDikelola} />
                ),
              )
            )}
          </div>
        </div>
      </div>
    </div>
  )
}
