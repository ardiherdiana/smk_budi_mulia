import * as React from "react"
import { router } from "@inertiajs/react"

import type { Lampiran, Post } from "@/lib/classroom/types"
import { bentukPayload, formDariPost, PostFormFields, type PostFormState } from "@/components/classroom/post-form"
import { LampiranList } from "@/components/classroom/lampiran-list"
import { Button } from "@/components/ui/button"
import { Checkbox } from "@/components/ui/checkbox"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import { Field, FieldDescription, FieldLabel } from "@/components/ui/field"

interface Props {
  post: Post
  buka: boolean
  onBukaChange: (buka: boolean) => void
}

export function PostEditDialog({ post, buka, onBukaChange }: Props) {
  return (
    <Dialog open={buka} onOpenChange={onBukaChange}>
      <DialogContent className="max-h-[90svh] overflow-y-auto sm:max-w-xl">
        {/* Dipasang ulang tiap dialog dibuka, jadi formulir selalu mulai dari isi post terbaru. */}
        {buka && <PostEditForm post={post} onSelesai={() => onBukaChange(false)} />}
      </DialogContent>
    </Dialog>
  )
}

function PostEditForm({ post, onSelesai }: { post: Post; onSelesai: () => void }) {
  const [form, setForm] = React.useState<PostFormState>(() => formDariPost(post))
  const [hapusId, setHapusId] = React.useState<string[]>([])
  const [semuaKelas, setSemuaKelas] = React.useState(false)
  const [errors, setErrors] = React.useState<Record<string, string>>({})
  const [memproses, setMemproses] = React.useState(false)

  const lampiranAda: Lampiran[] = post.lampiran.filter((l) => !hapusId.includes(l.id))
  const punyaLampiran = post.tipe === "MATERI" || post.tipe === "TUGAS"
  const lainnya = post.jumlahGrup - 1

  function simpan(event: React.FormEvent) {
    event.preventDefault()
    // Berkas tidak didukung PATCH multipart, jadi POST dengan _method=PATCH.
    router.post(
      `/classroom/post/${post.id}`,
      { _method: "patch", ...bentukPayload(form), hapusLampiran: hapusId, semuaKelas: semuaKelas ? 1 : 0 } as never,
      {
        forceFormData: true,
        preserveScroll: true,
        onStart: () => {
          setMemproses(true)
          setErrors({})
        },
        onSuccess: onSelesai,
        onError: (e) => setErrors(e),
        onFinish: () => setMemproses(false),
      }
    )
  }

  return (
        <form onSubmit={simpan} className="flex flex-col gap-4">
          <DialogHeader>
            <DialogTitle>Ubah {post.tipe === "TUGAS" ? "tugas" : post.tipe === "UJIAN" ? "ujian" : "materi"}</DialogTitle>
            <DialogDescription>Perubahan langsung terlihat oleh siswa.</DialogDescription>
          </DialogHeader>

          <PostFormFields form={form} onChange={setForm} errors={errors} />

          {punyaLampiran && lampiranAda.length > 0 && (
            <Field>
              <FieldLabel>Lampiran saat ini</FieldLabel>
              <LampiranList lampiran={lampiranAda} onHapus={(l) => setHapusId([...hapusId, l.id])} />
            </Field>
          )}

          {lainnya > 0 && (
            <Field orientation="horizontal">
              <Checkbox id="post-semua-kelas" checked={semuaKelas} onCheckedChange={(v) => setSemuaKelas(v === true)} />
              <div className="flex flex-col gap-0.5">
                <FieldLabel htmlFor="post-semua-kelas">Terapkan juga ke {lainnya} ruang kelas lain</FieldLabel>
                <FieldDescription>
                  Postingan ini diterbitkan ke {post.jumlahGrup} ruang kelas. Isi di kelas lain akan diganti dengan versi ini.
                </FieldDescription>
              </div>
            </Field>
          )}

          <DialogFooter>
            <Button type="button" variant="outline" onClick={onSelesai}>
              Batal
            </Button>
            <Button type="submit" disabled={memproses}>
              {memproses ? "Menyimpan..." : "Simpan perubahan"}
            </Button>
          </DialogFooter>
        </form>
  )
}
