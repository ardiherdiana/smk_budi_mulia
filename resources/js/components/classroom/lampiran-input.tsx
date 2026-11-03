import * as React from "react"
import { Link2, Paperclip, Plus, X } from "lucide-react"
import { toast } from "sonner"

import { formatUkuran } from "@/lib/classroom/format"
import { Button } from "@/components/ui/button"
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from "@/components/ui/dropdown-menu"
import { Input } from "@/components/ui/input"

export interface LampiranBaru {
  berkas: File[]
  tautan: string[]
}

export const LAMPIRAN_KOSONG: LampiranBaru = { berkas: [], tautan: [] }

const MAKS_BERKAS = 10
const MAKS_MB = 20
const FORMAT = ".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt,.csv,.jpg,.jpeg,.png,.webp,.gif,.mp4,.mp3,.zip,.rar"

interface LampiranInputProps {
  value: LampiranBaru
  onChange: (value: LampiranBaru) => void
  /** Sembunyikan tautan untuk form yang hanya menerima berkas. */
  tanpaTautan?: boolean
  error?: string
}

/** Tombol "Tambah" dengan dua pilihan, File atau Link, untuk materi, tugas, dan hasil kerja siswa. */
export function LampiranInput({ value, onChange, tanpaTautan, error }: LampiranInputProps) {
  const inputRef = React.useRef<HTMLInputElement>(null)
  const [tautan, setTautan] = React.useState("")
  const [tautanTerbuka, setTautanTerbuka] = React.useState(false)

  function tambahBerkas(event: React.ChangeEvent<HTMLInputElement>) {
    const dipilih = Array.from(event.target.files ?? [])
    event.target.value = ""

    const terlaluBesar = dipilih.filter((f) => f.size > MAKS_MB * 1024 * 1024)
    if (terlaluBesar.length > 0) {
      toast.error(`Ukuran berkas maksimal ${MAKS_MB} MB: ${terlaluBesar.map((f) => f.name).join(", ")}`)
    }

    const diterima = dipilih.filter((f) => f.size <= MAKS_MB * 1024 * 1024)
    const gabungan = [...value.berkas, ...diterima].slice(0, MAKS_BERKAS)
    onChange({ ...value, berkas: gabungan })
  }

  function tambahTautan(teks = tautan) {
    const url = teks.trim()
    if (!url) {
      tutupTautan()
      return
    }
    try {
      const parsed = new URL(url)
      if (parsed.protocol !== "http:" && parsed.protocol !== "https:") throw new Error()
    } catch {
      toast.error("Tautan harus diawali http:// atau https://")
      return
    }
    onChange({ ...value, tautan: [...value.tautan, url].slice(0, MAKS_BERKAS) })
    tutupTautan()
  }

  function tutupTautan() {
    setTautan("")
    setTautanTerbuka(false)
  }

  return (
    <div className="flex flex-col gap-2">
      {(value.berkas.length > 0 || value.tautan.length > 0) && (
        <ul className="flex flex-col gap-1.5">
          {value.berkas.map((file, i) => (
            <li key={`${file.name}-${i}`} className="flex items-center gap-2 rounded-lg border bg-muted/40 px-2.5 py-1.5 text-sm">
              <Paperclip className="size-4 shrink-0 text-muted-foreground" />
              <span className="min-w-0 flex-1 truncate">{file.name}</span>
              <span className="shrink-0 text-xs text-muted-foreground">{formatUkuran(file.size)}</span>
              <button
                type="button"
                className="shrink-0 rounded-sm p-0.5 text-muted-foreground hover:bg-muted hover:text-destructive"
                onClick={() => onChange({ ...value, berkas: value.berkas.filter((_, j) => j !== i) })}
                aria-label={`Buang ${file.name}`}
              >
                <X className="size-3.5" />
              </button>
            </li>
          ))}
          {value.tautan.map((url, i) => (
            <li key={`${url}-${i}`} className="flex items-center gap-2 rounded-lg border bg-muted/40 px-2.5 py-1.5 text-sm">
              <Link2 className="size-4 shrink-0 text-muted-foreground" />
              <span className="min-w-0 flex-1 truncate">{url}</span>
              <button
                type="button"
                className="shrink-0 rounded-sm p-0.5 text-muted-foreground hover:bg-muted hover:text-destructive"
                onClick={() => onChange({ ...value, tautan: value.tautan.filter((_, j) => j !== i) })}
                aria-label={`Buang ${url}`}
              >
                <X className="size-3.5" />
              </button>
            </li>
          ))}
        </ul>
      )}

      <input ref={inputRef} type="file" multiple accept={FORMAT} className="hidden" onChange={tambahBerkas} />
      <DropdownMenu>
        <DropdownMenuTrigger render={<Button type="button" variant="outline" className="w-full" />}>
          <Plus /> Tambah
        </DropdownMenuTrigger>
        <DropdownMenuContent className="w-56">
          {!tanpaTautan && (
            <DropdownMenuItem onClick={() => setTautanTerbuka(true)}>
              <Link2 /> Link
            </DropdownMenuItem>
          )}
          <DropdownMenuItem onClick={() => inputRef.current?.click()}>
            <Paperclip /> File
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>

      {tautanTerbuka && (
        // Bukan <form>: komponen ini dipakai di dalam form lain.
        <div className="flex items-center gap-2">
          <Input
            autoFocus
            value={tautan}
            onChange={(e) => setTautan(e.target.value)}
            onKeyDown={(e) => {
              if (e.key === "Enter") {
                e.preventDefault()
                tambahTautan()
              }
              if (e.key === "Escape") {
                e.stopPropagation()
                tutupTautan()
              }
            }}
            onPaste={(e) => {
              // Tempel tautan langsung masuk ke daftar tanpa menekan tombol.
              e.preventDefault()
              tambahTautan(e.clipboardData.getData("text"))
            }}
            onBlur={() => tambahTautan()}
            placeholder="Tempel tautan (https://...)"
            inputMode="url"
            aria-label="Tautan Drive, YouTube, atau situs lain"
          />
          {/* mouseDown dicegah agar input tidak blur dan menambahkan tautan saat dibatalkan. */}
          <Button type="button" variant="outline" size="icon" onMouseDown={(e) => e.preventDefault()} onClick={tutupTautan} aria-label="Batal">
            <X />
          </Button>
        </div>
      )}
      <p className="text-xs text-muted-foreground">
        Maksimal {MAKS_BERKAS} lampiran, {MAKS_MB} MB per berkas (PDF, Word, Excel, PowerPoint, gambar, video, ZIP).
      </p>
      {error && (
        <p className="text-sm text-destructive" role="alert">
          {error}
        </p>
      )}
    </div>
  )
}
