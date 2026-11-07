import * as React from "react"
import { router, useForm } from "@inertiajs/react"
import { toast } from "sonner"
import { Download, Pencil, X } from "lucide-react"

import { ApiError, downloadFile } from "@/lib/absensi/api"
import type { Guru, RekapRow, StatusKehadiran } from "@/lib/absensi/types"
import {
  STATUS_OPTIONS,
  formatJam,
  statusBadgeVariant,
  statusLabel,
  toTimeInputValue,
} from "@/lib/absensi/attendance-format"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { DataPagination } from "@/components/data-pagination"
import { DatePicker } from "@/components/absensi/date-picker"
import { GuruCombobox } from "@/components/absensi/guru-combobox"
import { TimePicker } from "@/components/absensi/time-picker"
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import { Field, FieldGroup, FieldLabel } from "@/components/ui/field"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { Textarea } from "@/components/ui/textarea"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"

interface Props {
  rows: RekapRow[]
  from: string
  to: string
  guruId: string
  guruList: Guru[]
}

export function RekapPage({ rows, from, to, guruId, guruList }: Props) {
  const [exporting, setExporting] = React.useState(false)
  const [editing, setEditing] = React.useState<RekapRow | null>(null)
  const [page, setPage] = React.useState(1)
  const [pageSize, setPageSize] = React.useState(50)

  const { data, setData, post, processing, errors, reset, clearErrors } = useForm({
    guruId: "",
    tanggal: "",
    status: "HADIR" as StatusKehadiran,
    jamMasuk: "",
    jamPulang: "",
    catatan: "",
  })

  React.useEffect(() => {
    setPage(1)
  }, [from, to, guruId])

  function reload(next: Partial<{ from: string; to: string; guruId: string }>) {
    const merged = { from, to, guruId, ...next }
    const params: Record<string, string> = { from: merged.from, to: merged.to }
    if (merged.guruId !== "all") params.guruId = merged.guruId
    router.get("/rekap", params, { preserveState: true, replace: true, only: ["rows", "from", "to", "guruId"] })
  }

  const paginatedRows = rows.slice((page - 1) * pageSize, page * pageSize)

  function openEdit(row: RekapRow) {
    setEditing(row)
    clearErrors()
    setData({
      guruId: row.guruId,
      tanggal: row.tanggal,
      status: row.status ?? "HADIR",
      jamMasuk: toTimeInputValue(row.jamMasuk),
      jamPulang: toTimeInputValue(row.jamPulang),
      catatan: "",
    })
  }

  function handleSaveEdit(event: React.FormEvent) {
    event.preventDefault()
    post("/rekap/manual", {
      preserveScroll: true,
      onSuccess: () => {
        setEditing(null)
        reset()
        toast.success("Koreksi kehadiran berhasil disimpan")
      },
    })
  }

  async function handleExport() {
    setExporting(true)
    try {
      await downloadFile(
        "/rekap/export",
        { from, to, guruId: guruId === "all" ? undefined : guruId },
        `rekap-absensi-${from}-${to}.xlsx`
      )
      toast.success("Excel berhasil diunduh")
    } catch (err) {
      toast.error(err instanceof ApiError ? err.message : "Gagal mengunduh Excel")
    } finally {
      setExporting(false)
    }
  }

  const summary = React.useMemo(() => {
    const counts = { HADIR: 0, TELAT: 0, IZIN: 0, SAKIT: 0, ALPA: 0 }
    for (const row of rows) {
      if (row.status) counts[row.status] += 1
    }
    return counts
  }, [rows])

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-xl font-semibold">Rekap Absensi</h1>
          <p className="text-sm text-muted-foreground">
            Hadir: {summary.HADIR} · Telat: {summary.TELAT} · Izin: {summary.IZIN} · Sakit:{" "}
            {summary.SAKIT} · Alpa: {summary.ALPA}
          </p>
        </div>
        <Button variant="outline" onClick={handleExport} disabled={exporting}>
          <Download /> {exporting ? "Mengunduh..." : "Export Excel"}
        </Button>
      </div>

      <div className="flex flex-wrap items-end gap-3">
        <Field className="w-auto">
          <FieldLabel htmlFor="from">Dari</FieldLabel>
          <DatePicker id="from" value={from} onChange={(v) => reload({ from: v })} />
        </Field>
        <Field className="w-auto">
          <FieldLabel htmlFor="to">Sampai</FieldLabel>
          <DatePicker id="to" value={to} onChange={(v) => reload({ to: v })} />
        </Field>
        <Field className="w-auto">
          <FieldLabel htmlFor="guru">Guru</FieldLabel>
          <GuruCombobox
            id="guru"
            guruList={guruList}
            value={guruId}
            onChange={(v) => reload({ guruId: v })}
            className="w-48"
          />
        </Field>
      </div>

      <div className="overflow-x-auto rounded-lg border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead className="w-10">No</TableHead>
              <TableHead>Tanggal</TableHead>
              <TableHead>Nama</TableHead>
              <TableHead>Jam Masuk</TableHead>
              <TableHead>Jam Pulang</TableHead>
              <TableHead>Status</TableHead>
              <TableHead className="text-right">Aksi</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {rows.length === 0 ? (
              <TableRow>
                <TableCell colSpan={7} className="text-center text-muted-foreground">
                  Tidak ada data pada rentang ini
                </TableCell>
              </TableRow>
            ) : (
              paginatedRows.map((row, index) => (
                <TableRow key={`${row.guruId}-${row.tanggal}`}>
                  <TableCell className="text-muted-foreground">
                    {(page - 1) * pageSize + index + 1}
                  </TableCell>
                  <TableCell>{row.tanggal}</TableCell>
                  <TableCell>{row.nama}</TableCell>
                  <TableCell>{formatJam(row.jamMasuk)}</TableCell>
                  <TableCell>{formatJam(row.jamPulang)}</TableCell>
                  <TableCell>
                    {row.status && (
                      <Badge variant={statusBadgeVariant(row.status)}>
                        {statusLabel(row.status)}
                      </Badge>
                    )}
                  </TableCell>
                  <TableCell className="text-right">
                    {row.status && (
                      <Button variant="ghost" size="icon-sm" onClick={() => openEdit(row)}>
                        <Pencil />
                      </Button>
                    )}
                  </TableCell>
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>
      </div>

      <DataPagination
        page={page}
        pageSize={pageSize}
        total={rows.length}
        onPageChange={setPage}
        onPageSizeChange={(size) => {
          setPageSize(size)
          setPage(1)
        }}
      />

      <Dialog open={editing !== null} onOpenChange={(open) => !open && setEditing(null)}>
        <DialogContent>
          {editing && (
            <>
              <DialogHeader>
                <DialogTitle>Koreksi Kehadiran</DialogTitle>
              </DialogHeader>
              <form onSubmit={handleSaveEdit}>
                <FieldGroup>
                  <div>
                    <div className="font-medium">{editing.nama}</div>
                    <div className="text-xs text-muted-foreground">{editing.tanggal}</div>
                  </div>
                  <div className="grid grid-cols-2 gap-3">
                    <Field>
                      <FieldLabel htmlFor="editJamMasuk">Jam Masuk</FieldLabel>
                      <div className="flex items-center gap-2">
                        <TimePicker
                          id="editJamMasuk"
                          value={data.jamMasuk}
                          onChange={(v) => setData("jamMasuk", v)}
                          className="w-full"
                        />
                        {data.jamMasuk && (
                          <Button
                            type="button"
                            variant="ghost"
                            size="icon-sm"
                            onClick={() => setData("jamMasuk", "")}
                          >
                            <X />
                          </Button>
                        )}
                      </div>
                    </Field>
                    <Field>
                      <FieldLabel htmlFor="editJamPulang">Jam Pulang</FieldLabel>
                      <div className="flex items-center gap-2">
                        <TimePicker
                          id="editJamPulang"
                          value={data.jamPulang}
                          onChange={(v) => setData("jamPulang", v)}
                          className="w-full"
                        />
                        {data.jamPulang && (
                          <Button
                            type="button"
                            variant="ghost"
                            size="icon-sm"
                            onClick={() => setData("jamPulang", "")}
                          >
                            <X />
                          </Button>
                        )}
                      </div>
                    </Field>
                  </div>
                  <Field>
                    <FieldLabel htmlFor="editStatus">Status</FieldLabel>
                    <Select
                      items={Object.fromEntries(STATUS_OPTIONS.map((s) => [s, statusLabel(s)]))}
                      value={data.status}
                      onValueChange={(v) => v && setData("status", v as StatusKehadiran)}
                    >
                      <SelectTrigger id="editStatus" className="w-full">
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        {STATUS_OPTIONS.map((s) => (
                          <SelectItem key={s} value={s}>
                            {statusLabel(s)}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </Field>
                  <Field>
                    <FieldLabel htmlFor="editCatatan">Catatan Koreksi (opsional)</FieldLabel>
                    <Textarea
                      id="editCatatan"
                      value={data.catatan}
                      onChange={(e) => setData("catatan", e.target.value)}
                      placeholder="Alasan koreksi, misal: lupa absen pulang, konfirmasi hadir seharian"
                    />
                  </Field>
                  {(errors.status ??
                    errors.jamMasuk ??
                    errors.jamPulang ??
                    errors.catatan ??
                    (errors as Record<string, string>).message) && (
                    <p className="text-sm text-destructive" role="alert">
                      {errors.status ??
                        errors.jamMasuk ??
                        errors.jamPulang ??
                        errors.catatan ??
                        (errors as Record<string, string>).message}
                    </p>
                  )}
                  <DialogFooter>
                    <Button type="submit" disabled={processing}>
                      {processing ? "Menyimpan..." : "Simpan Koreksi"}
                    </Button>
                  </DialogFooter>
                </FieldGroup>
              </form>
            </>
          )}
        </DialogContent>
      </Dialog>
    </div>
  )
}
