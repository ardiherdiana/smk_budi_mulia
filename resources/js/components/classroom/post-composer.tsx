import * as React from "react"
import { router } from "@inertiajs/react"
import { toast } from "sonner"

import type { KelasTujuan, TipePost } from "@/lib/classroom/types"
import { cn } from "@/lib/classroom/utils"
import { IKON_TIPE, LABEL_TIPE } from "@/components/classroom/post-card"
import { bentukPayload, formKosong, PostFormFields, type PostFormState } from "@/components/classroom/post-form"
import { TargetKelas } from "@/components/classroom/target-kelas"
import { Button } from "@/components/ui/button"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"

const TIPE: TipePost[] = ["PENGUMUMAN", "MATERI", "TUGAS", "UJIAN"]

interface Props {
  kelasId: string
  /** Ruang kelas lain milik guru yang sama yang bisa ikut menerima postingan. */
  tujuan: KelasTujuan[]
}

/** Tombol pembuat postingan di atas forum. Hanya untuk guru pengajar dan admin. */
export function PostComposer({ kelasId, tujuan }: Props) {
  const [buka, setBuka] = React.useState(false)
  const [form, setForm] = React.useState<PostFormState>(formKosong())
  // Terpisah dari `form` supaya pilihan kelas tidak ikut terhapus saat jenis postingan diganti.
  const [kelasTujuan, setKelasTujuan] = React.useState<string[]>([kelasId])
  const [errors, setErrors] = React.useState<Record<string, string>>({})
  const [memproses, setMemproses] = React.useState(false)

  function bukaDialog(tipe: TipePost) {
    setForm(formKosong(tipe))
    setKelasTujuan([kelasId])
    setErrors({})
    setBuka(true)
  }

  function kirim(event: React.FormEvent) {
    event.preventDefault()
    router.post(`/classroom/kelas/${kelasId}/post`, { tipe: form.tipe, ...bentukPayload(form), kelasTujuan } as never, {
      forceFormData: true,
      preserveScroll: true,
      onStart: () => {
        setMemproses(true)
        setErrors({})
      },
      onSuccess: () => setBuka(false),
      onError: (e) => {
        setErrors(e)
        if (!Object.keys(e).some((k) => k !== "message")) return
        toast.error("Periksa isian yang berwarna merah")
      },
      onFinish: () => setMemproses(false),
    })
  }

  return (
    <>
      <div className="flex flex-wrap gap-2">
        {TIPE.map((tipe) => {
          const Ikon = IKON_TIPE[tipe]
          return (
            <Button
              key={tipe}
              variant={tipe === "PENGUMUMAN" ? "secondary" : "outline"}
              onClick={() => bukaDialog(tipe)}
            >
              <Ikon /> {LABEL_TIPE[tipe]} baru
            </Button>
          )
        })}
      </div>

      <Dialog open={buka} onOpenChange={setBuka}>
        <DialogContent className="max-h-[90svh] overflow-y-auto sm:max-w-xl">
          <form onSubmit={kirim} className="flex flex-col gap-4">
            <DialogHeader>
              <DialogTitle>Buat postingan</DialogTitle>
              <DialogDescription>
                {kelasTujuan.length > 1
                  ? `Terlihat oleh siswa di ${kelasTujuan.length} ruang kelas yang dipilih.`
                  : "Terlihat oleh semua siswa di ruang kelas ini."}
              </DialogDescription>
            </DialogHeader>

            <div className="flex flex-wrap gap-1.5" role="radiogroup" aria-label="Jenis postingan">
              {TIPE.map((tipe) => {
                const Ikon = IKON_TIPE[tipe]
                const aktif = form.tipe === tipe
                return (
                  <button
                    key={tipe}
                    type="button"
                    role="radio"
                    aria-checked={aktif}
                    onClick={() => {
                      setForm({ ...formKosong(tipe), isi: form.isi, judul: form.judul })
                      setErrors({})
                    }}
                    className={cn(
                      "flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-sm font-medium transition-colors",
                      aktif ? "border-primary bg-primary/10 text-primary" : "text-muted-foreground hover:bg-muted"
                    )}
                  >
                    <Ikon className="size-4" /> {LABEL_TIPE[tipe]}
                  </button>
                )
              })}
            </div>

            <PostFormFields form={form} onChange={setForm} errors={errors} />

            <TargetKelas tujuan={tujuan} asalId={kelasId} value={kelasTujuan} onChange={setKelasTujuan} />
            {errors.kelasTujuan && <p className="text-sm text-destructive">{errors.kelasTujuan}</p>}

            {form.tipe === "UJIAN" && (
              <p className="text-sm text-muted-foreground">
                Soal ditambahkan setelah ujian dibuat
                {kelasTujuan.length > 1 ? " dan otomatis disalin ke semua ruang kelas yang dipilih." : "."}
              </p>
            )}

            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => setBuka(false)}>
                Batal
              </Button>
              <Button type="submit" disabled={memproses}>
                {memproses ? "Memproses..." : form.tipe === "UJIAN" ? "Buat & tambah soal" : "Publikasikan"}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </>
  )
}
