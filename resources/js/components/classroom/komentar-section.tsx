import * as React from "react"
import { router } from "@inertiajs/react"
import { Send, Trash2 } from "lucide-react"

import type { Komentar } from "@/lib/classroom/types"
import { useAuth } from "@/context/classroom-auth"
import { useConfirm } from "@/hooks/use-confirm"
import { formatRelatif } from "@/lib/classroom/format"
import { initials } from "@/lib/classroom/utils"
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"

interface KomentarSectionProps {
  postId: string
  komentar: Komentar[]
}

export function KomentarSection({ postId, komentar }: KomentarSectionProps) {
  const { user } = useAuth()
  const { confirm, confirmDialog } = useConfirm()
  const [isi, setIsi] = React.useState("")
  const [mengirim, setMengirim] = React.useState(false)
  // Kepala sekolah hanya memantau, tidak ikut berkomentar.
  const bisaKomentar = user?.role !== "KEPSEK"

  function kirim(event: React.FormEvent) {
    event.preventDefault()
    if (!isi.trim()) return
    router.post(`/classroom/post/${postId}/komentar`, { isi }, {
      preserveScroll: true,
      onStart: () => setMengirim(true),
      onSuccess: () => setIsi(""),
      onFinish: () => setMengirim(false),
    })
  }

  async function hapus(k: Komentar) {
    const ok = await confirm({ title: "Hapus komentar ini?", confirmLabel: "Hapus", destructive: true })
    if (!ok) return
    router.delete(`/classroom/komentar/${k.id}`, { preserveScroll: true })
  }

  return (
    <div className="flex flex-col gap-3">
      {confirmDialog}
      {komentar.length > 0 && (
        <ul className="flex flex-col gap-3">
          {komentar.map((k) => (
            <li key={k.id} className="flex gap-2.5">
              <Avatar size="sm">
                {k.user.fotoUrl && <AvatarImage src={k.user.fotoUrl} alt="" className="object-top" />}
                <AvatarFallback>{initials(k.user.name)}</AvatarFallback>
              </Avatar>
              <div className="min-w-0 flex-1">
                <div className="flex flex-wrap items-center gap-x-2 text-sm">
                  <span className="font-medium">{k.user.name}</span>
                  {k.user.role !== "SISWA" && <Badge variant="secondary">Guru</Badge>}
                  <span className="text-xs text-muted-foreground">{formatRelatif(k.createdAt)}</span>
                </div>
                <p className="text-sm break-words whitespace-pre-wrap">{k.isi}</p>
              </div>
              {k.dapatDihapus && (
                <Button variant="ghost" size="icon-xs" onClick={() => hapus(k)} aria-label="Hapus komentar">
                  <Trash2 />
                </Button>
              )}
            </li>
          ))}
        </ul>
      )}

      {bisaKomentar && (
        <form onSubmit={kirim} className="flex items-center gap-2">
          <Input
            value={isi}
            onChange={(e) => setIsi(e.target.value)}
            placeholder="Tambahkan komentar kelas..."
            maxLength={2000}
          />
          <Button type="submit" size="icon" disabled={mengirim || !isi.trim()} aria-label="Kirim komentar">
            <Send />
          </Button>
        </form>
      )}
    </div>
  )
}
