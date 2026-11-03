import { FileText, Image as ImageIcon, Link2, Paperclip, X } from "lucide-react"

import type { Lampiran } from "@/lib/classroom/types"
import { formatUkuran } from "@/lib/classroom/format"
import { cn } from "@/lib/classroom/utils"

function ikon(lampiran: Lampiran) {
  if (lampiran.tipe === "LINK") return Link2
  if (lampiran.mime?.startsWith("image/")) return ImageIcon
  if (lampiran.mime === "application/pdf") return FileText
  return Paperclip
}

interface LampiranListProps {
  lampiran: Lampiran[]
  className?: string
  /** Bila diisi, tiap lampiran punya tombol hapus. */
  onHapus?: (lampiran: Lampiran) => void
}

export function LampiranList({ lampiran, className, onHapus }: LampiranListProps) {
  if (lampiran.length === 0) return null

  return (
    <ul className={cn("flex flex-wrap gap-2", className)}>
      {lampiran.map((item) => {
        const Icon = ikon(item)
        return (
          <li
            key={item.id}
            className="flex max-w-full items-center gap-2 rounded-lg border bg-muted/40 py-1.5 pr-2 pl-2.5 text-sm"
          >
            <Icon className="size-4 shrink-0 text-muted-foreground" />
            <a
              href={item.url}
              target="_blank"
              rel="noreferrer noopener"
              className="max-w-56 truncate font-medium hover:underline"
              title={item.nama}
            >
              {item.nama}
            </a>
            {item.ukuran ? <span className="shrink-0 text-xs text-muted-foreground">{formatUkuran(item.ukuran)}</span> : null}
            {onHapus && (
              <button
                type="button"
                onClick={() => onHapus(item)}
                className="shrink-0 rounded-sm p-0.5 text-muted-foreground transition-colors hover:bg-muted hover:text-destructive"
                aria-label={`Hapus ${item.nama}`}
              >
                <X className="size-3.5" />
              </button>
            )}
          </li>
        )
      })}
    </ul>
  )
}
