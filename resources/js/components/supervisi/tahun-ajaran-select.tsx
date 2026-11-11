import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { SELECT_SENTUH } from "@/lib/supervisi/format"
import type { TahunAjaranOpsi } from "@/lib/supervisi/types"
import { cn } from "@/lib/utils"

interface Props {
  id?: string
  list: TahunAjaranOpsi[]
  value: string | null
  onChange: (id: string) => void
  className?: string
}

export function TahunAjaranSelect({ id, list, value, onChange, className }: Props) {
  if (list.length === 0) {
    return <p className="text-sm text-muted-foreground">Belum ada tahun ajaran. Tambahkan dulu di Classroom.</p>
  }

  return (
    <Select
      items={Object.fromEntries(list.map((t) => [t.id, t.aktif ? `${t.label} (aktif)` : t.label]))}
      value={value ?? undefined}
      onValueChange={(v) => v && onChange(v as string)}
    >
      <SelectTrigger id={id} className={cn(SELECT_SENTUH, "w-full sm:w-56", className)} aria-label="Tahun ajaran">
        <SelectValue placeholder="Pilih tahun ajaran" />
      </SelectTrigger>
      <SelectContent>
        {list.map((t) => (
          <SelectItem key={t.id} value={t.id}>
            {t.aktif ? `${t.label} (aktif)` : t.label}
          </SelectItem>
        ))}
      </SelectContent>
    </Select>
  )
}
