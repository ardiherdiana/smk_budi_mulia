import { cn } from "@/lib/classroom/utils"

interface GambarSoalProps {
  urls?: string[] | null
  nomor: number
  className?: string
}

/** Gambar yang menyertai teks soal (diagram, topologi, tangkapan layar). */
export function GambarSoal({ urls, nomor, className }: GambarSoalProps) {
  if (!urls?.length) return null

  return (
    <div className={cn("flex flex-col gap-2", className)}>
      {urls.map((url, i) => (
        <img
          key={url}
          src={url}
          alt={`Gambar soal ${nomor}${urls.length > 1 ? `, ${i + 1}` : ""}`}
          loading="lazy"
          className="h-auto max-h-96 w-auto max-w-full self-start rounded-md border object-contain"
        />
      ))}
    </div>
  )
}

interface GambarPilihanProps {
  url?: string | null
  /** Contoh: "Gambar pilihan A soal 3". */
  label: string
  className?: string
}

/** Gambar sebuah pilihan jawaban. */
export function GambarPilihan({ url, label, className }: GambarPilihanProps) {
  if (!url) return null

  return (
    <img
      src={url}
      alt={label}
      loading="lazy"
      className={cn("h-auto max-h-48 w-auto max-w-full self-start rounded-md border object-contain", className)}
    />
  )
}
