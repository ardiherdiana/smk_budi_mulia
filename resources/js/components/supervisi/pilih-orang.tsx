import * as React from "react"

import {
  Combobox,
  ComboboxContent,
  ComboboxEmpty,
  ComboboxInput,
  ComboboxItem,
  ComboboxList,
} from "@/components/ui/combobox"
import { SENTUH } from "@/lib/supervisi/format"
import { cn } from "@/lib/utils"

export interface OpsiOrang {
  id: number
  nama: string
  peran?: string
}

interface Item {
  value: number
  label: string
}

interface Props {
  id?: string
  list: OpsiOrang[]
  value: number | null
  onChange: (id: number | null) => void
  placeholder?: string
  /** Sembunyikan satu orang (mis. guru yang disupervisi dari daftar supervisor). */
  kecuali?: number | null
  invalid?: boolean
}

/** Combobox pencarian nama guru/kepsek. */
export function PilihOrang({ id, list, value, onChange, placeholder = "Cari nama...", kecuali, invalid }: Props) {
  const items = React.useMemo<Item[]>(
    () =>
      list
        .filter((o) => o.id !== kecuali)
        .map((o) => ({ value: o.id, label: o.peran === "KEPSEK" ? `${o.nama} (Kepala Sekolah)` : o.nama })),
    [list, kecuali]
  )
  const selected = items.find((i) => i.value === value) ?? null

  return (
    <Combobox items={items} value={selected} onValueChange={(item) => onChange(item?.value ?? null)}>
      <ComboboxInput id={id} placeholder={placeholder} className={cn(SENTUH, "w-full")} aria-invalid={invalid || undefined} />
      <ComboboxContent>
        <ComboboxEmpty>Nama tidak ditemukan</ComboboxEmpty>
        <ComboboxList>
          {(item: Item) => (
            <ComboboxItem key={item.value} value={item}>
              {item.label}
            </ComboboxItem>
          )}
        </ComboboxList>
      </ComboboxContent>
    </Combobox>
  )
}
