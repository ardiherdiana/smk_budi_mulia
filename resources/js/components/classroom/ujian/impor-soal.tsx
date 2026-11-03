import * as React from "react"
import { Download, FileUp } from "lucide-react"

import { ApiError, uploadFile } from "@/lib/api"
import type { ItemGambar } from "@/lib/classroom/types"
import type { TipeSoal } from "@/lib/classroom/soal"
import { Button } from "@/components/ui/button"

/** Satu soal hasil baca dokumen. `kunci` memakai format penyimpanan ("2", "0,2", "1,0,1"); kosong bila tak terbaca. */
export interface DrafSoal {
  nomor: number
  tipe: TipeSoal
  teks: string
  kompetensi: string
  subKompetensi: string
  opsi: string[]
  kunci: string
  gambar: ItemGambar[]
  opsiGambar: (ItemGambar | null)[]
}

export interface HasilImpor {
  soal: DrafSoal[]
  peringatan: string[]
}

interface Props {
  postId: string
  nonaktif: boolean
  onSelesai: (hasil: HasilImpor) => void
}

const MAKS_PERINGATAN = 8

/**
 * Impor soal dari dokumen Word (.docx) atau PDF hasil ketik. Hasilnya draf di editor; tidak ada yang tersimpan
 * sebelum guru menyimpan. Template Word dari sistem membuat dokumen terbaca persis seperti yang ditulis guru.
 */
export function ImporSoal({ postId, nonaktif, onSelesai }: Props) {
  const input = React.useRef<HTMLInputElement>(null)
  const [membaca, setMembaca] = React.useState(false)
  const [galat, setGalat] = React.useState<string | null>(null)
  const [laporan, setLaporan] = React.useState<{ jumlah: number; peringatan: string[] } | null>(null)

  async function pilih(e: React.ChangeEvent<HTMLInputElement>) {
    const berkas = e.target.files?.[0]
    e.target.value = ""
    if (!berkas) return

    setMembaca(true)
    setGalat(null)
    setLaporan(null)
    try {
      const hasil = await uploadFile<HasilImpor>(`/classroom/post/${postId}/soal/impor`, "berkas", berkas)
      if (hasil.soal.length === 0) {
        setGalat(hasil.peringatan[0] ?? "Tidak ada soal yang dikenali di berkas ini.")
        return
      }
      onSelesai(hasil)
      setLaporan({ jumlah: hasil.soal.length, peringatan: hasil.peringatan })
    } catch (err) {
      setGalat(err instanceof ApiError ? err.message : "Berkas gagal dibaca")
    } finally {
      setMembaca(false)
    }
  }

  return (
    <div className="flex flex-col gap-3 rounded-lg border p-3">
      <div className="flex flex-wrap items-center gap-3">
        <Button type="button" variant="outline" disabled={nonaktif || membaca} onClick={() => input.current?.click()}>
          <FileUp /> {membaca ? "Membaca berkas..." : "Impor soal dari file"}
        </Button>
        <Button type="button" variant="ghost" render={<a href="/classroom/template-soal" download />}>
          <Download /> Unduh template Word
        </Button>
      </div>
      <p className="text-sm text-muted-foreground">
        Isi template di Word, lalu unggah berkas .docx-nya. PDF hasil ketik juga bisa. Soal, pilihan, kunci, dan gambar di dalamnya menjadi draf di bawah,
        jadi periksa kunci jawaban dan gambarnya sebelum menyimpan.
      </p>
      <input
        ref={input}
        type="file"
        accept=".docx,.pdf,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
        className="sr-only"
        tabIndex={-1}
        onChange={pilih}
        aria-hidden="true"
      />

      {galat && (
        <p role="alert" className="text-sm text-destructive">
          {galat}
        </p>
      )}

      {laporan && (
        <div role="status" className="flex flex-col gap-2 text-sm">
          <p className="font-medium">{laporan.jumlah} soal dari berkas ditambahkan di bawah.</p>
          {laporan.peringatan.length > 0 && (
            <ul className="list-disc space-y-1 pl-5 text-muted-foreground">
              {laporan.peringatan.slice(0, MAKS_PERINGATAN).map((p) => (
                <li key={p}>{p}</li>
              ))}
              {laporan.peringatan.length > MAKS_PERINGATAN && <li>Dan {laporan.peringatan.length - MAKS_PERINGATAN} catatan lain.</li>}
            </ul>
          )}
        </div>
      )}
    </div>
  )
}
