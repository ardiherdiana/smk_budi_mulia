import * as React from "react"

import { cn } from "@/lib/utils"

/**
 * `kartu`: di bawah 768px tiap baris tampil sebagai blok label + nilai (CSS di `index.css`), bukan tabel yang harus
 * digeser. Label diambil otomatis dari teks <TableHead> pada kolom yang sama. Atur per sel lewat prop `kartu` di
 * <TableCell>: "judul" (paling atas, tanpa label), "aksi" (tombol berteks di bawah kartu, dibagi sama lebar; isi
 * dengan <TombolAksi>), "penuh" (tanpa label), "sembunyi" (tidak tampil di ponsel). Kotak centang pilih baris tampil
 * di pojok kanan atas. Tampilan layar lebar tidak berubah.
 */
function Table({ className, kartu = false, ...props }: React.ComponentProps<"table"> & { kartu?: boolean }) {
  const ref = React.useRef<HTMLTableElement>(null)

  React.useLayoutEffect(() => {
    const table = ref.current
    if (!kartu || !table) return

    const beriLabel = () => {
      const label: string[] = []
      table.querySelectorAll<HTMLTableCellElement>(":scope > thead > tr:last-child > th").forEach((th) => {
        for (let i = 0; i < (th.colSpan || 1); i++) label.push(th.textContent?.trim() ?? "")
      })
      table.querySelectorAll<HTMLTableRowElement>(":scope > tbody > tr").forEach((tr) => {
        let kolom = 0
        for (const td of Array.from(tr.cells)) {
          const teks = td.colSpan > 1 ? "" : (label[kolom] ?? "")
          if (td.getAttribute("data-label") !== teks) td.setAttribute("data-label", teks)
          kolom += td.colSpan || 1
        }
      })
    }

    beriLabel()
    // Baris berubah saat paginasi/filter; atribut tidak diamati sehingga setAttribute tidak memicu ulang.
    const pengamat = new MutationObserver(beriLabel)
    pengamat.observe(table, { childList: true, subtree: true, characterData: true })
    return () => pengamat.disconnect()
  }, [kartu])

  return (
    <div
      data-slot="table-container"
      data-kartu={kartu || undefined}
      className="relative w-full overflow-x-auto"
    >
      <table
        ref={ref}
        data-slot="table"
        className={cn("w-full caption-bottom text-sm", className)}
        {...props}
      />
    </div>
  )
}

function TableHeader({ className, ...props }: React.ComponentProps<"thead">) {
  return (
    <thead
      data-slot="table-header"
      className={cn("[&_tr]:border-b", className)}
      {...props}
    />
  )
}

function TableBody({ className, ...props }: React.ComponentProps<"tbody">) {
  return (
    <tbody
      data-slot="table-body"
      className={cn("[&_tr:last-child]:border-0", className)}
      {...props}
    />
  )
}

function TableFooter({ className, ...props }: React.ComponentProps<"tfoot">) {
  return (
    <tfoot
      data-slot="table-footer"
      className={cn(
        "border-t bg-muted/50 font-medium [&>tr]:last:border-b-0",
        className
      )}
      {...props}
    />
  )
}

function TableRow({ className, ...props }: React.ComponentProps<"tr">) {
  return (
    <tr
      data-slot="table-row"
      className={cn(
        "border-b transition-colors hover:bg-muted/50 has-aria-expanded:bg-muted/50 data-[state=selected]:bg-muted",
        className
      )}
      {...props}
    />
  )
}

function TableHead({ className, ...props }: React.ComponentProps<"th">) {
  return (
    <th
      data-slot="table-head"
      className={cn(
        "h-10 px-2 text-left align-middle font-medium whitespace-nowrap text-foreground [&:has([role=checkbox])]:pr-0",
        className
      )}
      {...props}
    />
  )
}

function TableCell({
  className,
  kartu,
  ...props
}: React.ComponentProps<"td"> & { kartu?: "judul" | "aksi" | "penuh" | "sembunyi" }) {
  return (
    <td
      data-slot="table-cell"
      data-kartu={kartu}
      className={cn(
        "p-2 align-middle whitespace-nowrap [&:has([role=checkbox])]:pr-0",
        className
      )}
      {...props}
    />
  )
}

function TableCaption({
  className,
  ...props
}: React.ComponentProps<"caption">) {
  return (
    <caption
      data-slot="table-caption"
      className={cn("mt-4 text-sm text-muted-foreground", className)}
      {...props}
    />
  )
}

export {
  Table,
  TableHeader,
  TableBody,
  TableFooter,
  TableHead,
  TableRow,
  TableCell,
  TableCaption,
}
