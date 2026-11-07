import * as React from "react"
import { toast } from "sonner"
import { Download, Pencil } from "lucide-react"

import { api, ApiError, downloadFile } from "@/lib/absensi/api"
import type { BriefingRekapRow, Guru, StatusKehadiran } from "@/lib/absensi/types"
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

const REFRESH_INTERVAL_MS = 10_000

interface Props {
  guruList: Guru[]
  rows: BriefingRekapRow[]
  from: string
  to: string
}

export function BriefingPage({
  guruList,
  rows: initialRows,
  from: initialFrom,
  to: initialTo,
}: Props) {
  const [guruId, setGuruId] = React.useState<string>("all")
  const [from, setFrom] = React.useState(initialFrom)
  const [to, setTo] = React.useState(initialTo)
  const [rows, setRows] = React.useState<BriefingRekapRow[]>(initialRows)
  const [loading, setLoading] = React.useState(false)
  const [exporting, setExporting] = React.useState(false)
  const [page, setPage] = React.useState(1)
  const [pageSize, setPageSize] = React.useState(50)

  const [editing, setEditing] = React.useState<BriefingRekapRow | null>(null)
  const [editForm, setEditForm] = React.useState({
    status: "HADIR" as StatusKehadiran,
    waktu: "",
    catatan: "",
  })
  const [editSaving, setEditSaving] = React.useState(false)
  const [editError, setEditError] = React.useState<string | null>(null)

  // Hanya jawaban permintaan terbaru yang dipakai: penyegaran otomatis yang terlambat
  // tiba tidak boleh menimpa hasil filter yang baru diganti.
  const latestRequestRef = React.useRef(0)
  const loadRows = React.useCallback(
    (silent = false) => {
      const requestId = ++latestRequestRef.current
      if (!silent) setLoading(true)
      api
        .get<BriefingRekapRow[]>("/briefing/rekap", {
          from,
          to,
          guruId: guruId === "all" ? undefined : guruId,
        })
        .then((result) => {
          if (requestId === latestRequestRef.current) setRows(result)
        })
        .catch((err) => {
          if (!silent) throw err
        })
        .finally(() => {
          if (requestId === latestRequestRef.current) setLoading(false)
        })
    },
    [from, to, guruId]
  )

  // Scan di kiosk masuk ke tabel yang sama dari layar lain, jadi tabel disegarkan sendiri
  // selama halaman ini terlihat.
  React.useEffect(() => {
    const timer = setInterval(() => {
      if (document.visibilityState === "visible") loadRows(true)
    }, REFRESH_INTERVAL_MS)
    return () => clearInterval(timer)
  }, [loadRows])

  const isFirstLoad = React.useRef(true)
  React.useEffect(() => {
    if (isFirstLoad.current) {
      isFirstLoad.current = false
      return
    }
    loadRows()
    setPage(1)
  }, [loadRows])

  async function handleExport() {
    setExporting(true)
    try {
      await downloadFile(
        "/briefing/rekap/export",
        { from, to, guruId: guruId === "all" ? undefined : guruId },
        `rekap-briefing-${from}-${to}.xlsx`
      )
      toast.success("Excel berhasil diunduh")
    } catch (err) {
      toast.error(err instanceof ApiError ? err.message : "Gagal mengunduh Excel")
    } finally {
      setExporting(false)
    }
  }

  function openEdit(row: BriefingRekapRow) {
    setEditing(row)
    setEditError(null)
    setEditForm({
      status: row.status ?? "HADIR",
      waktu: toTimeInputValue(row.waktu),
      catatan: "",
    })
  }

  async function handleSaveEdit(event: React.FormEvent) {
    event.preventDefault()
    if (!editing) return

    setEditSaving(true)
    setEditError(null)
    try {
      await api.post("/briefing/manual", {
        guruId: editing.guruId,
        tanggal: editing.tanggal,
        status: editForm.status,
        waktu: editForm.waktu,
        catatan: editForm.catatan,
      })
      toast.success("Koreksi absen briefing berhasil disimpan")
      setEditing(null)
      loadRows()
    } catch (err) {
      setEditError(err instanceof ApiError ? err.message : "Gagal menyimpan koreksi")
    } finally {
      setEditSaving(false)
    }
  }

  const paginatedRows = rows.slice((page - 1) * pageSize, page * pageSize)
  const hadirCount = rows.filter((r) => r.status === "HADIR").length
  const alpaCount = rows.filter((r) => r.status === "ALPA").length

  return (
    <div className="flex flex-col gap-4">
      <div>
        <h1 className="text-xl font-semibold">Absen Briefing</h1>
        <p className="text-sm text-muted-foreground">
          Rekap absen briefing guru/kepsek - absen dibuka otomatis mulai jam
          briefing yang diatur di Pengaturan
        </p>
      </div>

      <div className="flex flex-wrap items-center justify-between gap-3">
        <p className="text-sm text-muted-foreground">
          Hadir: {hadirCount} · Alpa: {alpaCount}
        </p>
        <Button variant="outline" onClick={handleExport} disabled={exporting}>
          <Download /> {exporting ? "Mengunduh..." : "Export Excel"}
        </Button>
      </div>

      <div className="flex flex-wrap items-end gap-3">
        <Field className="w-auto">
          <FieldLabel htmlFor="from">Dari</FieldLabel>
          <DatePicker id="from" value={from} onChange={setFrom} />
        </Field>
        <Field className="w-auto">
          <FieldLabel htmlFor="to">Sampai</FieldLabel>
          <DatePicker id="to" value={to} onChange={setTo} />
        </Field>
        <Field className="w-auto">
          <FieldLabel htmlFor="guru">Guru</FieldLabel>
          <GuruCombobox id="guru" guruList={guruList} value={guruId} onChange={setGuruId} className="w-48" />
        </Field>
      </div>

      <div className="overflow-x-auto rounded-lg border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead className="w-10">No</TableHead>
              <TableHead>Tanggal</TableHead>
              <TableHead>Nama</TableHead>
              <TableHead>Waktu</TableHead>
              <TableHead>Status</TableHead>
              <TableHead className="text-right">Aksi</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {loading ? (
              <TableRow>
                <TableCell colSpan={6} className="text-center text-muted-foreground">
                  Memuat...
                </TableCell>
              </TableRow>
            ) : paginatedRows.filter((r) => r.status !== null).length === 0 ? (
              <TableRow>
                <TableCell colSpan={6} className="text-center text-muted-foreground">
                  Tidak ada jadwal briefing pada rentang ini
                </TableCell>
              </TableRow>
            ) : (
              paginatedRows
                .filter((r) => r.status !== null)
                .map((row, index) => (
                  <TableRow key={`${row.guruId}-${row.tanggal}`}>
                    <TableCell className="text-muted-foreground">
                      {(page - 1) * pageSize + index + 1}
                    </TableCell>
                    <TableCell>{row.tanggal}</TableCell>
                    <TableCell>{row.nama}</TableCell>
                    <TableCell>{formatJam(row.waktu)}</TableCell>
                    <TableCell>
                      {row.status && (
                        <Badge variant={statusBadgeVariant(row.status)}>{statusLabel(row.status)}</Badge>
                      )}
                    </TableCell>
                    <TableCell className="text-right">
                      <Button variant="ghost" size="icon-sm" onClick={() => openEdit(row)}>
                        <Pencil />
                      </Button>
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
                <DialogTitle>Koreksi Absen Briefing</DialogTitle>
              </DialogHeader>
              <form onSubmit={handleSaveEdit}>
                <FieldGroup>
                  <div>
                    <div className="font-medium">{editing.nama}</div>
                    <div className="text-xs text-muted-foreground">{editing.tanggal}</div>
                  </div>
                  <Field>
                    <FieldLabel htmlFor="editWaktu">Jam Masuk</FieldLabel>
                    <div className="flex items-center gap-2">
                      <TimePicker
                        id="editWaktu"
                        value={editForm.waktu}
                        onChange={(v) => setEditForm((f) => ({ ...f, waktu: v }))}
                      />
                      {editForm.waktu && (
                        <Button
                          type="button"
                          variant="ghost"
                          size="sm"
                          onClick={() => setEditForm((f) => ({ ...f, waktu: "" }))}
                        >
                          Kosongkan
                        </Button>
                      )}
                    </div>
                  </Field>
                  <Field>
                    <FieldLabel htmlFor="editStatus">Status</FieldLabel>
                    <Select
                      items={Object.fromEntries(STATUS_OPTIONS.map((s) => [s, statusLabel(s)]))}
                      value={editForm.status}
                      onValueChange={(v) => v && setEditForm((f) => ({ ...f, status: v as StatusKehadiran }))}
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
                    <FieldLabel htmlFor="editCatatan">Alasan (opsional)</FieldLabel>
                    <Textarea
                      id="editCatatan"
                      value={editForm.catatan}
                      onChange={(e) => setEditForm((f) => ({ ...f, catatan: e.target.value }))}
                      placeholder="Alasan koreksi, misal: lupa scan, konfirmasi hadir briefing"
                    />
                  </Field>
                  {editError && (
                    <p className="text-sm text-destructive" role="alert">
                      {editError}
                    </p>
                  )}
                  <DialogFooter>
                    <Button type="submit" disabled={editSaving}>
                      {editSaving ? "Menyimpan..." : "Simpan Koreksi"}
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
