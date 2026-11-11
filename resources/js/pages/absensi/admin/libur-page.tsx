import { router, useForm } from "@inertiajs/react"
import { toast } from "sonner"
import { Plus, Trash2 } from "lucide-react"

import type { Holiday } from "@/lib/absensi/types"
import { useConfirm } from "@/hooks/use-confirm"
import { formatTanggal, todayIso } from "@/lib/absensi/attendance-format"
import { TombolAksi } from "@/components/tombol-aksi"
import { Button } from "@/components/ui/button"
import { DatePicker } from "@/components/absensi/date-picker"
import { Field, FieldLabel } from "@/components/ui/field"
import { Input } from "@/components/ui/input"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"

export function LiburPage({ list }: { list: Holiday[] }) {
  const { confirm, confirmDialog } = useConfirm()
  const { data, setData, post, processing, errors, reset } = useForm({
    tanggal: todayIso(),
    keterangan: "",
  })

  function handleSubmit(event: React.FormEvent) {
    event.preventDefault()
    post("/hari-libur", {
      preserveScroll: true,
      onSuccess: () => {
        reset("keterangan")
        toast.success("Hari libur berhasil ditambahkan")
      },
    })
  }

  async function handleDelete(holiday: Holiday) {
    const ok = await confirm({
      title: `Hapus hari libur "${holiday.keterangan}"?`,
      confirmLabel: "Hapus",
      destructive: true,
    })
    if (!ok) return
    router.delete(`/hari-libur/${holiday.id}`, {
      preserveScroll: true,
      onSuccess: () => toast.success("Hari libur berhasil dihapus"),
    })
  }

  return (
    <div className="flex flex-col gap-4">
      {confirmDialog}
      <div>
        <h1 className="text-xl font-semibold">Hari Libur</h1>
        <p className="text-sm text-muted-foreground">
          Tanggal di sini otomatis dikecualikan dari rekap (tidak dihitung Alpa)
        </p>
      </div>

      <form onSubmit={handleSubmit} className="flex flex-wrap items-end gap-3">
        <Field className="w-auto">
          <FieldLabel htmlFor="tanggal">Tanggal</FieldLabel>
          <DatePicker id="tanggal" value={data.tanggal} onChange={(v) => setData("tanggal", v)} />
        </Field>
        <Field className="w-64">
          <FieldLabel htmlFor="keterangan">Keterangan</FieldLabel>
          <Input
            id="keterangan"
            required
            placeholder="mis. Libur Nasional Idul Fitri"
            value={data.keterangan}
            onChange={(e) => setData("keterangan", e.target.value)}
          />
        </Field>
        <Button type="submit" disabled={processing}>
          <Plus /> {processing ? "Menyimpan..." : "Tambah"}
        </Button>
      </form>

      {(errors.tanggal ?? errors.keterangan ?? (errors as Record<string, string>).message) && (
        <p className="text-sm text-destructive" role="alert">
          {errors.tanggal ?? errors.keterangan ?? (errors as Record<string, string>).message}
        </p>
      )}

      <div className="overflow-x-auto rounded-lg border">
        <Table kartu>
          <TableHeader>
            <TableRow>
              <TableHead className="w-10">No</TableHead>
              <TableHead>Tanggal</TableHead>
              <TableHead>Keterangan</TableHead>
              <TableHead className="text-right">Aksi</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {list.length === 0 ? (
              <TableRow>
                <TableCell colSpan={4} className="text-center text-muted-foreground">
                  Belum ada hari libur yang ditambahkan
                </TableCell>
              </TableRow>
            ) : (
              list.map((holiday, index) => (
                <TableRow key={holiday.id}>
                  <TableCell kartu="sembunyi" className="text-muted-foreground">{index + 1}</TableCell>
                  <TableCell kartu="judul">{formatTanggal(holiday.tanggal)}</TableCell>
                  <TableCell>{holiday.keterangan}</TableCell>
                  <TableCell kartu="aksi" className="text-right">
                    <TombolAksi jenis="hapus" onClick={() => handleDelete(holiday)}>
                      <Trash2 />
                    </TombolAksi>
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
