import * as React from "react"
import { ImagePlus, X } from "lucide-react"

import { ApiError, uploadFile } from "@/lib/api"
import type { ItemGambar } from "@/lib/classroom/types"
import { Button } from "@/components/ui/button"

const TIPE_GAMBAR = "image/png,image/jpeg,image/webp"

/** Unggah satu gambar soal ke media sementara; path-nya baru dipindah permanen saat soal disimpan. */
function useUnggahGambar(postId: string) {
  const [sibuk, setSibuk] = React.useState(false)
  const [galat, setGalat] = React.useState<string | null>(null)

  const unggah = React.useCallback(
    async (berkas: File): Promise<ItemGambar | null> => {
      setSibuk(true)
      setGalat(null)
      try {
        return await uploadFile<ItemGambar>(`/classroom/post/${postId}/soal/gambar`, "gambar", berkas)
      } catch (e) {
        setGalat(e instanceof ApiError ? e.message : "Gambar gagal diunggah")
        return null
      } finally {
        setSibuk(false)
      }
    },
    [postId]
  )

  return { unggah, sibuk, galat }
}

interface GambarSoalEditorProps {
  postId: string
  nomor: number
  nilai: ItemGambar[]
  maks: number
  onUbah: (daftar: ItemGambar[]) => void
  /** Pesan galat dari server untuk gambar soal ini. */
  galat?: string
}

/** Gambar di bawah teks soal: deret gambar mini dengan tombol hapus, dan tombol tambah. */
export function GambarSoalEditor({ postId, nomor, nilai, maks, onUbah, galat: galatServer }: GambarSoalEditorProps) {
  const input = React.useRef<HTMLInputElement>(null)
  const { unggah, sibuk, galat } = useUnggahGambar(postId)

  async function pilih(e: React.ChangeEvent<HTMLInputElement>) {
    const berkas = Array.from(e.target.files ?? []).slice(0, maks - nilai.length)
    e.target.value = ""

    let daftar = nilai
    for (const f of berkas) {
      const item = await unggah(f)
      if (!item) break
      daftar = [...daftar, item]
      onUbah(daftar)
    }
  }

  const pesan = galatServer ?? galat

  return (
    <div className="flex flex-col gap-2">
      {nilai.length > 0 && (
        <div className="flex flex-wrap gap-2">
          {nilai.map((g, i) => (
            <div key={g.path} className="relative">
              <img src={g.url} alt={`Gambar soal ${nomor}, ${i + 1}`} className="h-28 w-auto max-w-56 rounded-md border object-contain" />
              <Button
                type="button"
                variant="secondary"
                size="icon-sm"
                className="absolute top-1 right-1"
                onClick={() => onUbah(nilai.filter((x) => x.path !== g.path))}
                aria-label={`Hapus gambar ${i + 1} soal ${nomor}`}
              >
                <X />
              </Button>
            </div>
          ))}
        </div>
      )}
      {nilai.length < maks && (
        <Button type="button" variant="outline" size="sm" className="w-fit" disabled={sibuk} onClick={() => input.current?.click()}>
          <ImagePlus /> {sibuk ? "Mengunggah..." : "Tambah gambar"}
        </Button>
      )}
      <input ref={input} type="file" accept={TIPE_GAMBAR} multiple className="sr-only" tabIndex={-1} onChange={pilih} aria-hidden="true" />
      {pesan && <p className="text-sm text-destructive">{pesan}</p>}
    </div>
  )
}

interface GambarPilihanEditorProps {
  postId: string
  /** Huruf pilihan, untuk label aksesibilitas. */
  huruf: string
  nilai: ItemGambar | null
  onUbah: (item: ItemGambar | null) => void
}

/** Gambar satu pilihan jawaban: tombol kecil bila kosong, gambar mini dengan tombol hapus bila ada. */
export function GambarPilihanEditor({ postId, huruf, nilai, onUbah }: GambarPilihanEditorProps) {
  const input = React.useRef<HTMLInputElement>(null)
  const { unggah, sibuk, galat } = useUnggahGambar(postId)

  async function pilih(e: React.ChangeEvent<HTMLInputElement>) {
    const berkas = e.target.files?.[0]
    e.target.value = ""
    if (!berkas) return
    const item = await unggah(berkas)
    if (item) onUbah(item)
  }

  return (
    <>
      {nilai ? (
        <div className="relative shrink-0">
          <img src={nilai.url} alt={`Gambar pilihan ${huruf}`} className="h-16 w-auto max-w-32 rounded-md border object-contain" />
          <Button
            type="button"
            variant="secondary"
            size="icon-sm"
            className="absolute -top-2 -right-2"
            onClick={() => onUbah(null)}
            aria-label={`Hapus gambar pilihan ${huruf}`}
          >
            <X />
          </Button>
        </div>
      ) : (
        <Button
          type="button"
          variant="ghost"
          size="icon-sm"
          disabled={sibuk}
          onClick={() => input.current?.click()}
          aria-label={`Tambah gambar pilihan ${huruf}`}
          title={galat ?? `Tambah gambar pilihan ${huruf}`}
        >
          <ImagePlus />
        </Button>
      )}
      <input ref={input} type="file" accept={TIPE_GAMBAR} className="sr-only" tabIndex={-1} onChange={pilih} aria-hidden="true" />
    </>
  )
}
