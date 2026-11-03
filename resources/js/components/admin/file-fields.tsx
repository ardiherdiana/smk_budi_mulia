import * as React from "react"
import { ImagePlus, Trash2, Undo2, X } from "lucide-react"

import { cn } from "@/lib/utils"
import { Button } from "@/components/ui/button"

/** URL pratinjau untuk File lokal; dibersihkan otomatis saat file berganti/unmount. */
function useObjectUrl(file: File | null): string | null {
  const [url, setUrl] = React.useState<string | null>(null)

  React.useEffect(() => {
    if (!file) {
      setUrl(null)
      return
    }
    const objectUrl = URL.createObjectURL(file)
    setUrl(objectUrl)
    return () => URL.revokeObjectURL(objectUrl)
  }, [file])

  return url
}

interface ImageFieldProps {
  id: string
  currentUrl?: string | null
  file: File | null
  removed: boolean
  onFileChange: (file: File | null) => void
  onRemovedChange: (removed: boolean) => void
  error?: string
  /** "portrait" = bingkai 3:4 dijangkar ke atas, sama dengan tampilan foto guru/kepala sekolah di website. */
  shape?: "square" | "wide" | "circle" | "portrait"
  hint?: string
}

/** Satu gambar: pratinjau (baru/lama), pilih file, atau tandai hapus. */
export function ImageField({ id, currentUrl, file, removed, onFileChange, onRemovedChange, error, shape = "wide", hint }: ImageFieldProps) {
  const inputRef = React.useRef<HTMLInputElement>(null)
  const preview = useObjectUrl(file)
  const shown = preview ?? (removed ? null : (currentUrl ?? null))

  return (
    <div className="flex flex-col gap-2">
      <div className="flex items-center gap-3">
        <div
          className={cn(
            "flex shrink-0 items-center justify-center overflow-hidden bg-muted text-muted-foreground ring-1 ring-foreground/10",
            shape === "wide" && "h-20 w-32 rounded-lg",
            shape === "square" && "size-20 rounded-lg",
            shape === "circle" && "size-20 rounded-full",
            shape === "portrait" && "h-28 w-[5.25rem] rounded-lg"
          )}
        >
          {shown ? (
            <img src={shown} alt="" className={cn("size-full object-cover", (shape === "portrait" || shape === "circle") && "object-top")} />
          ) : (
            <ImagePlus className="size-6" />
          )}
        </div>
        <div className="flex flex-wrap gap-2">
          <Button type="button" variant="outline" size="sm" onClick={() => inputRef.current?.click()}>
            {shown ? "Ganti gambar" : "Pilih gambar"}
          </Button>
          {file && (
            <Button type="button" variant="ghost" size="sm" onClick={() => onFileChange(null)}>
              <X /> Batal
            </Button>
          )}
          {!file && currentUrl && (
            <Button type="button" variant="ghost" size="sm" onClick={() => onRemovedChange(!removed)}>
              {removed ? <Undo2 /> : <Trash2 />} {removed ? "Batalkan hapus" : "Hapus"}
            </Button>
          )}
        </div>
        <input
          ref={inputRef}
          id={id}
          type="file"
          accept="image/jpeg,image/png,image/webp"
          className="sr-only"
          onChange={(e) => {
            onFileChange(e.target.files?.[0] ?? null)
            if (file === null) onRemovedChange(false)
            e.target.value = ""
          }}
        />
      </div>
      <p className="text-xs text-muted-foreground">
        {hint ?? "JPG, PNG, atau WebP."} Otomatis dikecilkan agar situs tetap cepat.
      </p>
      {error && <p className="text-sm text-destructive">{error}</p>}
    </div>
  )
}

interface GalleryFieldProps {
  id: string
  existing: { id: string; url: string }[]
  removedIds: string[]
  files: File[]
  onRemovedChange: (ids: string[]) => void
  onFilesChange: (files: File[]) => void
  error?: string
}

function NewThumb({ file, onRemove }: { file: File; onRemove: () => void }) {
  const url = useObjectUrl(file)
  return (
    <div className="relative size-20 overflow-hidden rounded-lg ring-2 ring-primary/60">
      {url && <img src={url} alt="" className="size-full object-cover" />}
      <button
        type="button"
        onClick={onRemove}
        aria-label="Batalkan gambar baru"
        className="absolute top-0.5 right-0.5 flex size-5 items-center justify-center rounded-full bg-background/90 text-foreground shadow"
      >
        <X className="size-3" />
      </button>
    </div>
  )
}

/** Galeri banyak gambar: kelola yang sudah ada (hapus) dan tambah yang baru. */
export function GalleryField({ id, existing, removedIds, files, onRemovedChange, onFilesChange, error }: GalleryFieldProps) {
  const inputRef = React.useRef<HTMLInputElement>(null)

  function toggleRemoved(itemId: string) {
    onRemovedChange(removedIds.includes(itemId) ? removedIds.filter((x) => x !== itemId) : [...removedIds, itemId])
  }

  return (
    <div className="flex flex-col gap-2">
      <div className="flex flex-wrap gap-2">
        {existing.map((item) => {
          const removed = removedIds.includes(item.id)
          return (
            <div key={item.id} className={cn("relative size-20 overflow-hidden rounded-lg ring-1 ring-foreground/10", removed && "opacity-40")}>
              <img src={item.url} alt="" className="size-full object-cover" />
              <button
                type="button"
                onClick={() => toggleRemoved(item.id)}
                aria-label={removed ? "Batalkan hapus gambar" : "Hapus gambar"}
                className="absolute top-0.5 right-0.5 flex size-5 items-center justify-center rounded-full bg-background/90 text-foreground shadow"
              >
                {removed ? <Undo2 className="size-3" /> : <Trash2 className="size-3" />}
              </button>
            </div>
          )
        })}
        {files.map((file, index) => (
          <NewThumb key={`${file.name}-${index}`} file={file} onRemove={() => onFilesChange(files.filter((_, i) => i !== index))} />
        ))}
        <button
          type="button"
          onClick={() => inputRef.current?.click()}
          className="flex size-20 flex-col items-center justify-center gap-1 rounded-lg border border-dashed text-xs text-muted-foreground hover:bg-muted"
        >
          <ImagePlus className="size-5" /> Tambah
        </button>
      </div>
      <input
        ref={inputRef}
        id={id}
        type="file"
        multiple
        accept="image/jpeg,image/png,image/webp"
        className="sr-only"
        onChange={(e) => {
          onFilesChange([...files, ...Array.from(e.target.files ?? [])])
          e.target.value = ""
        }}
      />
      {error && <p className="text-sm text-destructive">{error}</p>}
    </div>
  )
}
