import * as React from "react"
import { Link } from "@inertiajs/react"
import { ChevronRight } from "lucide-react"

import { StatusBadge } from "@/components/supervisi/status-badge"
import { Button } from "@/components/ui/button"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import { SENTUH, formatHari, formatNilai } from "@/lib/supervisi/format"
import type { SupervisiRingkas } from "@/lib/supervisi/types"
import { cn } from "@/lib/utils"

interface Props {
  list: SupervisiRingkas[]
  /** Kolom guru/supervisor; disembunyikan di daftar milik sendiri. */
  tampilGuru?: boolean
  tampilSupervisor?: boolean
  /** Tombol tambahan per baris (ubah, batalkan, hapus). */
  aksi?: (supervisi: SupervisiRingkas) => React.ReactNode
  kosong: string
}

function kelasMapel(s: SupervisiRingkas): string {
  return [s.mapel, s.rombel].filter(Boolean).join(" · ") || "-"
}

/** Daftar supervisi: kartu di ponsel, tabel mulai `md`. */
export function DaftarSupervisi({ list, tampilGuru = true, tampilSupervisor = true, aksi, kosong }: Props) {
  if (list.length === 0) {
    return <p className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">{kosong}</p>
  }

  return (
    <>
      <ul className="flex flex-col gap-3 md:hidden">
        {list.map((s) => {
          const tombol = aksi?.(s)
          return (
            <li key={s.id} className="rounded-lg border bg-card p-4">
              <div className="min-w-0">
                <p className="text-sm text-muted-foreground">{formatHari(s.tanggal)}</p>
                {tampilGuru && <p className="mt-0.5 font-medium">{s.guru?.nama ?? "-"}</p>}
              </div>
              <dl className="mt-3 grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-sm">
                <dt className="text-muted-foreground">Status</dt>
                <dd>
                  <StatusBadge status={s.status} />
                </dd>
                {tampilSupervisor && (
                  <>
                    <dt className="text-muted-foreground">Supervisor</dt>
                    <dd>{s.supervisor?.nama ?? "-"}</dd>
                  </>
                )}
                <dt className="text-muted-foreground">Mapel/Kelas</dt>
                <dd>{kelasMapel(s)}</dd>
                {s.status === "SELESAI" && (
                  <>
                    <dt className="text-muted-foreground">Total Score</dt>
                    <dd className="font-medium tabular-nums">{formatNilai(s.totalSkor)}</dd>
                  </>
                )}
              </dl>
              <Button variant="outline" className={cn(SENTUH, "mt-3 w-full")} render={<Link href={`/supervisi/${s.id}`} />}>
                Buka lembar <ChevronRight />
              </Button>
              {/* Tombol aksi (<TombolAksi>) berteks, dibagi sama lebar seperti kartu tabel lain. */}
              {tombol && <div className="mt-2 flex gap-2 *:min-w-0 *:flex-1">{tombol}</div>}
            </li>
          )
        })}
      </ul>

      <div className="hidden overflow-x-auto rounded-lg border md:block">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Tanggal</TableHead>
              {tampilGuru && <TableHead>Guru</TableHead>}
              {tampilSupervisor && <TableHead>Supervisor</TableHead>}
              <TableHead>Mapel/Kelas</TableHead>
              <TableHead>Status</TableHead>
              <TableHead className="text-right">Total Score</TableHead>
              <TableHead className="text-right">Aksi</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {list.map((s) => (
              <TableRow key={s.id}>
                <TableCell className="whitespace-nowrap">{formatHari(s.tanggal)}</TableCell>
                {tampilGuru && <TableCell className="font-medium">{s.guru?.nama ?? "-"}</TableCell>}
                {tampilSupervisor && <TableCell>{s.supervisor?.nama ?? "-"}</TableCell>}
                <TableCell>{kelasMapel(s)}</TableCell>
                <TableCell>
                  <StatusBadge status={s.status} />
                </TableCell>
                <TableCell className="text-right font-medium whitespace-nowrap tabular-nums">
                  {s.status === "SELESAI" ? formatNilai(s.totalSkor) : "-"}
                </TableCell>
                <TableCell>
                  <div className="flex items-center justify-end gap-1">
                    <Button variant="outline" size="sm" render={<Link href={`/supervisi/${s.id}`} />}>
                      Buka
                    </Button>
                    {aksi?.(s)}
                  </div>
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>
    </>
  )
}
