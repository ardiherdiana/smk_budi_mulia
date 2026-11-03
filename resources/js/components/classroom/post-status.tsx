import type { Post } from "@/lib/classroom/types"
import { formatAngka } from "@/lib/classroom/format"
import { Badge } from "@/components/ui/badge"

/** Status satu tugas/ujian: untuk siswa berdasarkan pengerjaannya, untuk guru berupa ringkasan kelas. */
export function PostStatus({ post, siswa }: { post: Post; siswa: boolean }) {
  if (post.tipe === "TUGAS") {
    if (!siswa) {
      const r = post.ringkasan as { dikumpulkan: number; dinilai: number } | undefined
      return (
        <span className="text-sm text-muted-foreground">
          {r?.dikumpulkan ?? 0} dikumpulkan · {r?.dinilai ?? 0} dinilai
        </span>
      )
    }

    const p = post.pengumpulanSaya
    if (!p) {
      const lewat = post.deadline && new Date(post.deadline) < new Date()
      if (lewat && !post.izinTerlambat) return <Badge variant="destructive">Ditutup</Badge>
      return <Badge variant={lewat ? "destructive" : "warning"}>{lewat ? "Terlambat, belum dikumpulkan" : "Belum dikumpulkan"}</Badge>
    }
    if (p.status === "DINILAI") {
      return <Badge variant="success">Nilai {formatAngka(p.nilai)}/{post.poinMaks ?? 100}</Badge>
    }
    return <Badge variant={p.terlambat ? "warning" : "secondary"}>{p.terlambat ? "Dikumpulkan terlambat" : "Dikumpulkan"}</Badge>
  }

  if (post.tipe === "UJIAN") {
    if (!siswa) {
      const r = post.ringkasan as { mulai: number; selesai: number } | undefined
      return (
        <span className="text-sm text-muted-foreground">
          {r?.mulai ?? 0} mulai · {r?.selesai ?? 0} selesai
        </span>
      )
    }

    const p = post.percobaanSaya
    if (p?.status === "BERJALAN") return <Badge variant="warning">Sedang dikerjakan</Badge>
    if (p?.status === "SELESAI") return <Badge variant="secondary">Menunggu koreksi</Badge>
    if (p?.status === "DINILAI") return <Badge variant="success">Selesai</Badge>
    if (post.jendela === "BELUM_DIBUKA") return <Badge variant="outline">Belum dibuka</Badge>
    if (post.jendela === "DITUTUP") return <Badge variant="destructive">Ditutup</Badge>
    return <Badge variant="warning">Belum dikerjakan</Badge>
  }

  return null
}
