import { Check } from "lucide-react"

import { HURUF, pecahJawaban } from "@/lib/classroom/soal"
import type { SoalUjian } from "@/lib/classroom/ujian"
import { cn } from "@/lib/classroom/utils"
import { GambarPilihan } from "@/components/classroom/ujian/gambar-soal"
import { Textarea } from "@/components/ui/textarea"

interface Props {
  soal: SoalUjian
  nomor: number
  jawaban: string
  /** Kelas ukuran huruf untuk teks pilihan. */
  teksClass: string
  onUbah: (isi: string) => void
}

const BARIS = "flex min-h-11 w-full items-center gap-3 rounded-md px-2 py-2 text-left transition-colors hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"

/** Teks pilihan dan gambarnya (pilihan boleh hanya berupa gambar). */
function IsiPilihan({ teks, url, huruf, nomor, teksClass }: { teks: string; url?: string | null; huruf: string; nomor: number; teksClass: string }) {
  return (
    <span className="flex min-w-0 flex-col gap-2">
      {teks !== "" && <span className={cn("break-words", teksClass)}>{teks}</span>}
      <GambarPilihan url={url} label={`Gambar pilihan ${huruf} soal ${nomor}`} />
    </span>
  )
}

/** Kontrol jawaban satu soal: PG (radio), PGK MCMA (kotak centang), PGK Kategori (Benar/Salah), atau Essay. */
export function SoalInput({ soal: s, nomor, jawaban, teksClass, onUbah }: Props) {
  const opsi = s.opsi ?? []

  if (s.tipe === "PG") {
    return (
      <div className="flex flex-col gap-1" role="radiogroup" aria-label={`Pilihan soal ${nomor}`}>
        {opsi.map((teks, i) => {
          const dipilih = jawaban === String(i)
          return (
            <button key={i} type="button" role="radio" aria-checked={dipilih} onClick={() => onUbah(String(i))} className={BARIS}>
              <span
                className={cn(
                  "flex size-6 shrink-0 items-center justify-center rounded-full border-2",
                  dipilih ? "border-primary" : "border-muted-foreground/60"
                )}
              >
                {dipilih && <span className="size-3 rounded-full bg-primary" />}
              </span>
              <IsiPilihan teks={teks} url={s.opsiGambar?.[i]} huruf={HURUF[i]} nomor={nomor} teksClass={teksClass} />
            </button>
          )
        })}
      </div>
    )
  }

  if (s.tipe === "PGK_MCMA") {
    const dipilih = pecahJawaban(jawaban).filter((j) => j !== "")
    const togel = (i: number) => {
      const baru = dipilih.includes(String(i)) ? dipilih.filter((j) => j !== String(i)) : [...dipilih, String(i)]
      onUbah(baru.sort((a, b) => Number(a) - Number(b)).join(","))
    }

    return (
      <div className="flex flex-col gap-1" role="group" aria-label={`Pilihan soal ${nomor}`}>
        <p className="px-2 text-sm text-muted-foreground">Pilih semua jawaban yang benar.</p>
        {opsi.map((teks, i) => {
          const aktif = dipilih.includes(String(i))
          return (
            <button key={i} type="button" role="checkbox" aria-checked={aktif} onClick={() => togel(i)} className={BARIS}>
              <span
                className={cn(
                  "flex size-6 shrink-0 items-center justify-center rounded border-2",
                  aktif ? "border-primary bg-primary text-primary-foreground" : "border-muted-foreground/60"
                )}
              >
                {aktif && <Check className="size-4" />}
              </span>
              <IsiPilihan teks={teks} url={s.opsiGambar?.[i]} huruf={HURUF[i]} nomor={nomor} teksClass={teksClass} />
            </button>
          )
        })}
      </div>
    )
  }

  if (s.tipe === "PGK_KATEGORI") {
    const sekarang = pecahJawaban(jawaban)
    const set = (i: number, nilai: "1" | "0") => onUbah(opsi.map((_, k) => (k === i ? nilai : (sekarang[k] ?? ""))).join(","))

    return (
      <div className="flex flex-col gap-2" role="group" aria-label={`Pernyataan soal ${nomor}`}>
        <p className="text-sm text-muted-foreground">Tentukan Benar atau Salah untuk setiap pernyataan.</p>
        {opsi.map((teks, i) => {
          const nilai = sekarang[i] ?? ""
          return (
            <div key={i} className="flex flex-wrap items-center gap-3 rounded-md border px-3 py-2">
              <span className="w-5 shrink-0 font-medium text-muted-foreground">{HURUF[i]}.</span>
              <span className={cn("min-w-0 flex-1 break-words", teksClass)}>{teks}</span>
              <div className="flex shrink-0 gap-1" role="radiogroup" aria-label={`Pernyataan ${HURUF[i]}`}>
                {(["1", "0"] as const).map((pilihan) => (
                  <button
                    key={pilihan}
                    type="button"
                    role="radio"
                    aria-checked={nilai === pilihan}
                    onClick={() => set(i, pilihan)}
                    className={cn(
                      "min-h-11 min-w-20 rounded-md border px-3 text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none",
                      nilai === pilihan ? "border-primary bg-primary text-primary-foreground" : "hover:bg-muted"
                    )}
                  >
                    {pilihan === "1" ? "Benar" : "Salah"}
                  </button>
                ))}
              </div>
            </div>
          )
        })}
      </div>
    )
  }

  return (
    <Textarea
      value={jawaban}
      onChange={(e) => onUbah(e.target.value)}
      rows={8}
      maxLength={20000}
      placeholder="Tulis jawabanmu di sini..."
      aria-label={`Jawaban soal ${nomor}`}
      className={teksClass}
    />
  )
}
