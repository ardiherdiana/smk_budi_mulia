import type * as React from "react"

import { Button } from "@/components/ui/button"

type Jenis = "edit" | "lihat" | "hapus"

const LABEL: Record<Jenis, string> = { edit: "Edit", lihat: "Lihat", hapus: "Hapus" }

type Props = Omit<React.ComponentProps<typeof Button>, "variant" | "size"> &
  ({ jenis: Jenis; label?: string } | { jenis?: undefined; label: string })

/**
 * Tombol aksi baris tabel/kartu. Layar lebar: ikon saja (ghost, icon-sm) dengan tooltip.
 * Ponsel (di bawah 768px): tombol berteks tanpa ikon, warnanya menurut `jenis` (edit = oranye, hapus = merah,
 * lainnya putih berbingkai; CSS `[data-tombol-aksi]` di index.css). Lebarnya dibagi rata oleh wadahnya: sel
 * `kartu="aksi"`, atau `flex *:flex-1` di kartu buatan sendiri.
 *
 * Urutan di setiap kartu: Edit, Lihat, tombol lain (QR, Unduh, ...), Hapus.
 * `jenis` mengisi label baku (Edit/Lihat/Hapus); tombol lain wajib memberi `label`. `aria-label` boleh diganti yang
 * lebih lengkap asal memuat labelnya.
 */
export function TombolAksi({ jenis, label, children, ...props }: Props) {
  const teks = label ?? LABEL[jenis as Jenis]
  return (
    <Button variant="ghost" size="icon-sm" data-tombol-aksi={jenis ?? "lain"} aria-label={teks} title={teks} {...props}>
      {children}
      <span className="md:hidden">{teks}</span>
    </Button>
  )
}
