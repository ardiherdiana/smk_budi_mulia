import * as React from "react"
import { Link, router } from "@inertiajs/react"
import { Clock, FileText, Megaphone, MessageSquare, NotebookPen, Timer, Trash2 } from "lucide-react"

import type { Post, TipePost } from "@/lib/classroom/types"
import { useAuth } from "@/context/classroom-auth"
import { useHapusPost } from "@/hooks/use-hapus-post"
import { formatRelatif, formatWaktu } from "@/lib/classroom/format"
import { format } from "date-fns"
import { id as localeId } from "date-fns/locale"
import { cn } from "@/lib/classroom/utils"
import { KomentarSection } from "@/components/classroom/komentar-section"
import { LampiranList } from "@/components/classroom/lampiran-list"
import { PostStatus } from "@/components/classroom/post-status"
import { Button } from "@/components/ui/button"
import { Card, CardContent } from "@/components/ui/card"

export const IKON_TIPE: Record<TipePost, React.ComponentType<{ className?: string }>> = {
  PENGUMUMAN: Megaphone,
  MATERI: FileText,
  TUGAS: NotebookPen,
  UJIAN: Timer,
}

export const LABEL_TIPE: Record<TipePost, string> = {
  PENGUMUMAN: "Pengumuman",
  MATERI: "Materi",
  TUGAS: "Tugas",
  UJIAN: "Ujian",
}

interface PostCardProps {
  post: Post
  dapatDikelola: boolean
  /** Tampilkan komentar langsung di kartu (stream) atau sembunyikan (daftar tugas). */
  komentar?: boolean
}

export function PostCard({ post, dapatDikelola, komentar = true }: PostCardProps) {
  const { isSiswa } = useAuth()
  const [bukaKomentar, setBukaKomentar] = React.useState(false)
  const Ikon = IKON_TIPE[post.tipe]
  const punyaDetail = post.tipe !== "PENGUMUMAN"
  const judul = post.judul ?? LABEL_TIPE[post.tipe]
  const jumlahKomentar = post.komentar?.length ?? 0
  const { hapus, confirmDialog } = useHapusPost(post, {
    title: `Hapus ${LABEL_TIPE[post.tipe].toLowerCase()} ini?`,
    description:
      post.tipe === "PENGUMUMAN" ? undefined : "Lampiran, komentar, dan semua pengumpulan atau hasil ujian siswa ikut terhapus.",
    preserveScroll: true,
  })

  // Seluruh kartu membuka detail, kecuali klik pada tautan, tombol, atau isian di dalamnya (komentar, lampiran, hapus).
  function bukaDetail(event: React.MouseEvent) {
    if ((event.target as HTMLElement).closest("a, button, input, textarea, select, [role=button]")) return
    if (window.getSelection()?.toString()) return
    router.visit(`/classroom/post/${post.id}`)
  }

  return (
    <Card
      onClick={punyaDetail ? bukaDetail : undefined}
      className={cn(punyaDetail && "cursor-pointer transition-shadow hover:shadow-md")}
    >
      {confirmDialog}
      <CardContent className="flex flex-col gap-3">
        <div className="flex items-start gap-3">
          <div
            className={cn(
              "flex size-10 shrink-0 items-center justify-center rounded-full",
              post.tipe === "PENGUMUMAN" ? "bg-muted text-muted-foreground" : "bg-primary/10 text-primary"
            )}
          >
            <Ikon className="size-5" />
          </div>
          <div className="min-w-0 flex-1">
            {punyaDetail ? (
              <Link href={`/classroom/post/${post.id}`} className="font-medium hover:underline">
                {judul}
              </Link>
            ) : (
              <span className="font-medium">{post.pembuat ?? "Guru"}</span>
            )}
            <p className="text-xs text-muted-foreground">
              {punyaDetail && post.pembuat ? `${post.pembuat} · ` : ""}
              {LABEL_TIPE[post.tipe]} · {formatRelatif(post.createdAt)}
            </p>
          </div>
          {dapatDikelola && (
            <Button variant="ghost" size="icon-sm" onClick={hapus} aria-label="Hapus postingan">
              <Trash2 />
            </Button>
          )}
        </div>

        {post.isi && (
          <p className={cn("text-sm break-words whitespace-pre-wrap", punyaDetail && "line-clamp-4")}>{post.isi}</p>
        )}

        <LampiranList lampiran={post.lampiran} />

        {(post.tipe === "TUGAS" || post.tipe === "UJIAN") && (
          <div className="flex flex-wrap items-center gap-x-4 gap-y-1.5 text-sm">
            <PostStatus post={post} siswa={isSiswa} />
            {post.tipe === "TUGAS" && post.deadline && (
              <span className="flex items-center gap-1.5 text-muted-foreground">
                <Clock className="size-4" /> Tenggat {formatWaktu(post.deadline)}
              </span>
            )}
            {post.tipe === "UJIAN" && (
              <span className="flex items-center gap-1.5 text-muted-foreground">
                <Timer className="size-4" /> {post.durasiMenit} menit
                {post.selesaiAt ? ` · sampai ${formatWaktu(post.selesaiAt)}` : ""}
              </span>
            )}
          </div>
        )}

        {komentar && post.komentar && (
          <div className="border-t pt-3">
            <button
              type="button"
              className="flex items-center gap-1.5 text-sm font-medium text-muted-foreground hover:text-foreground"
              onClick={() => setBukaKomentar((v) => !v)}
              aria-expanded={bukaKomentar}
            >
              <MessageSquare className="size-4" />
              {jumlahKomentar > 0 ? `${jumlahKomentar} komentar kelas` : "Tambahkan komentar kelas"}
            </button>
            {bukaKomentar && (
              <div className="mt-3">
                <KomentarSection postId={post.id} komentar={post.komentar} />
              </div>
            )}
          </div>
        )}
      </CardContent>
    </Card>
  )
}

const KATA_TIPE: Record<TipePost, string> = {
  PENGUMUMAN: "pengumuman",
  MATERI: "materi",
  TUGAS: "tugas",
  UJIAN: "ujian",
}

/** Baris ringkas di forum untuk materi/tugas/ujian: "Guru memposting tugas baru: Judul". Pengumuman tetap memakai PostCard. */
export function PostRingkas({ post, dapatDikelola }: { post: Post; dapatDikelola: boolean }) {
  const Ikon = IKON_TIPE[post.tipe]
  const { hapus, confirmDialog } = useHapusPost(post, {
    title: `Hapus ${LABEL_TIPE[post.tipe].toLowerCase()} ini?`,
    description: "Lampiran, komentar, dan semua pengumpulan atau hasil ujian siswa ikut terhapus.",
    preserveScroll: true,
  })

  return (
    <Card className="transition-shadow hover:shadow-md">
      {confirmDialog}
      <CardContent className="flex items-center gap-3">
        <span className="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
          <Ikon className="size-5" />
        </span>
        <Link href={`/classroom/post/${post.id}`} className="min-w-0 flex-1">
          <span className="block truncate text-sm font-medium">
            {post.pembuat ?? "Guru"} memposting {KATA_TIPE[post.tipe]} baru: {post.judul ?? LABEL_TIPE[post.tipe]}
          </span>
          <span className="block text-xs text-muted-foreground">
            {format(new Date(post.createdAt), "d MMM", { locale: localeId })}
          </span>
        </Link>
        {dapatDikelola && (
          <Button variant="ghost" size="icon-sm" onClick={hapus} aria-label="Hapus postingan">
            <Trash2 />
          </Button>
        )}
      </CardContent>
    </Card>
  )
}
