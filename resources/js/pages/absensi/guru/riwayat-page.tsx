import { router } from "@inertiajs/react"

import type { Attendance } from "@/lib/absensi/types"
import {
  formatJam,
  formatTanggal,
  statusBadgeVariant,
  statusLabel,
} from "@/lib/absensi/attendance-format"
import { Badge } from "@/components/ui/badge"
import { DatePicker } from "@/components/absensi/date-picker"
import { Field, FieldLabel } from "@/components/ui/field"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"

interface Props {
  rows: Attendance[]
  from: string
  to: string
}

export function RiwayatPage({ rows, from, to }: Props) {
  function reload(next: { from: string; to: string }) {
    router.get("/riwayat", next, { preserveState: true, replace: true, only: ["rows", "from", "to"] })
  }

  return (
    <div className="flex flex-col gap-4">
      <div>
        <h1 className="text-xl font-semibold">Riwayat Absensi</h1>
        <p className="text-sm text-muted-foreground">Rekam kehadiran Anda</p>
      </div>

      <div className="flex flex-wrap items-end gap-3">
        <Field className="w-auto">
          <FieldLabel htmlFor="from">Dari</FieldLabel>
          <DatePicker id="from" value={from} onChange={(value) => reload({ from: value, to })} />
        </Field>
        <Field className="w-auto">
          <FieldLabel htmlFor="to">Sampai</FieldLabel>
          <DatePicker id="to" value={to} onChange={(value) => reload({ from, to: value })} />
        </Field>
      </div>

      <div className="overflow-x-auto rounded-lg border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead className="w-10">No</TableHead>
              <TableHead>Tanggal</TableHead>
              <TableHead>Jam Masuk</TableHead>
              <TableHead>Jam Pulang</TableHead>
              <TableHead>Status</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {rows.length === 0 ? (
              <TableRow>
                <TableCell colSpan={5} className="text-center text-muted-foreground">
                  Belum ada data
                </TableCell>
              </TableRow>
            ) : (
              rows.map((row, index) => (
                <TableRow key={row.id}>
                  <TableCell className="text-muted-foreground">{index + 1}</TableCell>
                  <TableCell>{formatTanggal(row.tanggal)}</TableCell>
                  <TableCell>{formatJam(row.jamMasuk)}</TableCell>
                  <TableCell>{formatJam(row.jamPulang)}</TableCell>
                  <TableCell>
                    {row.statusMasuk && (
                      <Badge variant={statusBadgeVariant(row.statusMasuk)}>
                        {statusLabel(row.statusMasuk)}
                      </Badge>
                    )}
                  </TableCell>
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>
      </div>
    </div>
  )
}
