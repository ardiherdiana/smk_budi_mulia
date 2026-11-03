import * as React from "react"
import { ChevronDown } from "lucide-react"

import type { KelasTujuan } from "@/lib/classroom/types"
import { cn } from "@/lib/classroom/utils"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Checkbox } from "@/components/ui/checkbox"
import { Field, FieldLabel } from "@/components/ui/field"

const URUTAN_TINGKAT = ["X", "XI", "XII"]

interface Props {
  /** Semua ruang kelas yang boleh dituju, termasuk kelas yang sedang dibuka. */
  tujuan: KelasTujuan[]
  /** Kelas yang sedang dibuka: selalu ikut dan tidak bisa dilepas. */
  asalId: string
  value: string[]
  onChange: (ids: string[]) => void
}

function toggle(daftar: string[], nilai: string) {
  return daftar.includes(nilai) ? daftar.filter((v) => v !== nilai) : [...daftar, nilai]
}

/**
 * Pemilih ruang kelas tujuan untuk satu postingan. Tingkat dan jurusan menyaring daftar, lalu kelas dicentang
 * satu per satu atau sekaligus lewat "Pilih semua yang tampil". Pilihan yang tersembunyi filter tetap terhitung.
 */
export function TargetKelas({ tujuan, asalId, value, onChange }: Props) {
  const [buka, setBuka] = React.useState(false)
  const [tingkat, setTingkat] = React.useState<string[]>([])
  const [jurusan, setJurusan] = React.useState<string[]>([])

  const daftarTingkat = React.useMemo(
    () => URUTAN_TINGKAT.filter((t) => tujuan.some((k) => k.tingkat === t)),
    [tujuan]
  )
  const daftarJurusan = React.useMemo(
    () => [...new Set(tujuan.map((k) => k.jurusan))].sort((a, b) => a.localeCompare(b)),
    [tujuan]
  )

  const tampil = tujuan.filter(
    (k) => (tingkat.length === 0 || tingkat.includes(k.tingkat)) && (jurusan.length === 0 || jurusan.includes(k.jurusan))
  )

  // Tidak ada pilihan lain selain kelas ini, jadi tidak ada yang perlu dipilih.
  if (tujuan.length <= 1) return null

  function pilihSemuaTampil() {
    onChange([...new Set([...value, ...tampil.map((k) => k.id)])])
  }

  return (
    <div className="rounded-lg border">
      <button
        type="button"
        aria-expanded={buka}
        onClick={() => setBuka(!buka)}
        className="flex w-full items-center justify-between gap-2 px-3 py-2 text-left"
      >
        <span className="flex flex-col">
          <span className="text-sm font-medium">Kirim ke ruang kelas lain</span>
          <span className="text-xs text-muted-foreground">
            {value.length > 1 ? `${value.length} ruang kelas dipilih` : "Hanya ruang kelas ini"}
          </span>
        </span>
        <ChevronDown className={cn("size-4 shrink-0 transition-transform", buka && "rotate-180")} />
      </button>

      {buka && (
        <div className="flex flex-col gap-3 border-t p-3">
          <FilterChip label="Tingkat" pilihan={daftarTingkat} aktif={tingkat} onToggle={(v) => setTingkat(toggle(tingkat, v))} />
          <FilterChip label="Jurusan" pilihan={daftarJurusan} aktif={jurusan} onToggle={(v) => setJurusan(toggle(jurusan, v))} />

          <div className="flex flex-wrap gap-2">
            <Button type="button" variant="outline" size="sm" onClick={pilihSemuaTampil} disabled={tampil.length === 0}>
              Pilih semua yang tampil ({tampil.length})
            </Button>
            <Button type="button" variant="ghost" size="sm" onClick={() => onChange([asalId])}>
              Kosongkan
            </Button>
          </div>

          <div className="flex max-h-48 flex-col gap-2 overflow-y-auto" role="group" aria-label="Ruang kelas tujuan">
            {tampil.length === 0 && <p className="text-sm text-muted-foreground">Tidak ada ruang kelas yang cocok dengan filter.</p>}
            {tampil.map((k) => {
              const id = `tujuan-${k.id}`
              const asal = k.id === asalId
              return (
                <Field key={k.id} orientation="horizontal">
                  <Checkbox
                    id={id}
                    checked={value.includes(k.id)}
                    disabled={asal}
                    onCheckedChange={() => onChange(toggle(value, k.id))}
                  />
                  <FieldLabel htmlFor={id} className="flex-wrap gap-x-2 gap-y-0.5 font-normal">
                    <span className="font-medium">{k.rombel}</span>
                    <span className="text-muted-foreground">{k.mapel}</span>
                    {asal && <Badge variant="secondary">Kelas ini</Badge>}
                  </FieldLabel>
                </Field>
              )
            })}
          </div>
        </div>
      )}
    </div>
  )
}

interface FilterChipProps {
  label: string
  pilihan: string[]
  aktif: string[]
  onToggle: (nilai: string) => void
}

function FilterChip({ label, pilihan, aktif, onToggle }: FilterChipProps) {
  if (pilihan.length === 0) return null

  return (
    <div className="flex flex-col gap-1.5">
      <span className="text-xs font-medium text-muted-foreground">{label}</span>
      <div className="flex flex-wrap gap-1.5" role="group" aria-label={`Saring menurut ${label.toLowerCase()}`}>
        {pilihan.map((nilai) => {
          const dipilih = aktif.includes(nilai)
          return (
            <button
              key={nilai}
              type="button"
              aria-pressed={dipilih}
              onClick={() => onToggle(nilai)}
              className={cn(
                "rounded-lg border px-3 py-1 text-sm font-medium transition-colors",
                dipilih ? "border-primary bg-primary/10 text-primary" : "text-muted-foreground hover:bg-muted"
              )}
            >
              {nilai}
            </button>
          )
        })}
      </div>
    </div>
  )
}
