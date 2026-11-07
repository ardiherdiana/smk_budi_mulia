import * as React from "react"

import type { Guru } from "@/lib/absensi/types"
import {
  Combobox,
  ComboboxContent,
  ComboboxEmpty,
  ComboboxInput,
  ComboboxItem,
  ComboboxList,
} from "@/components/ui/combobox"

interface GuruComboItem {
  value: string
  label: string
}

interface GuruComboboxProps {
  id?: string
  guruList: Guru[]
  value: string
  onChange: (value: string) => void
  className?: string
  allLabel?: string
}

export function GuruCombobox({
  id,
  guruList,
  value,
  onChange,
  className,
  allLabel = "Semua Guru",
}: GuruComboboxProps) {
  const items = React.useMemo<GuruComboItem[]>(
    () => [{ value: "all", label: allLabel }, ...guruList.map((g) => ({ value: g.id, label: g.nama }))],
    [guruList, allLabel]
  )
  const selected = items.find((i) => i.value === value) ?? null

  return (
    <Combobox items={items} value={selected} onValueChange={(item) => onChange(item?.value ?? "all")}>
      <ComboboxInput id={id} placeholder="Cari guru..." className={className} showClear />
      <ComboboxContent>
        <ComboboxEmpty>Guru tidak ditemukan</ComboboxEmpty>
        <ComboboxList>
          {(item: GuruComboItem) => (
            <ComboboxItem key={item.value} value={item}>
              {item.label}
            </ComboboxItem>
          )}
        </ComboboxList>
      </ComboboxContent>
    </Combobox>
  )
}
