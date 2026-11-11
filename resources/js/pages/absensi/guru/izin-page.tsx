import * as React from "react"
import { useForm } from "@inertiajs/react"
import { Paperclip } from "lucide-react"

import { assetUrl } from "@/lib/absensi/api"
import { todayIso } from "@/lib/absensi/attendance-format"
import type { JenisIzin, LeaveRequest, StatusIzin } from "@/lib/absensi/types"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { DatePicker } from "@/components/absensi/date-picker"
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

const ALLOWED_LAMPIRAN_TYPES = "image/jpeg,image/png,image/webp,application/pdf"

function statusBadge(status: StatusIzin) {
  if (status === "APPROVED") return { label: "Disetujui", variant: "success" as const }
  if (status === "REJECTED") return { label: "Ditolak", variant: "destructive" as const }
  return { label: "Menunggu", variant: "warning" as const }
}

export function GuruIzinPage({ list }: { list: LeaveRequest[] }) {
  const lampiranInputRef = React.useRef<HTMLInputElement>(null)
  const { data, setData, post, processing, errors, reset } = useForm<{
    jenis: JenisIzin
    tanggalMulai: string
    tanggalSelesai: string
    alasan: string
    lampiran: File | null
  }>({
    jenis: "IZIN",
    tanggalMulai: todayIso(),
    tanggalSelesai: todayIso(),
    alasan: "",
    lampiran: null,
  })

  function handleSubmit(event: React.FormEvent) {
    event.preventDefault()
    post("/izin", {
      forceFormData: true,
      onSuccess: () => {
        reset()
        if (lampiranInputRef.current) lampiranInputRef.current.value = ""
      },
    })
  }

  return (
    <div className="flex flex-col gap-6">
      <div>
        <h1 className="text-xl font-semibold">Izin & Sakit</h1>
        <p className="text-sm text-muted-foreground">Ajukan izin atau sakit ke admin</p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">Ajukan Baru</CardTitle>
        </CardHeader>
        <CardContent>
          <form onSubmit={handleSubmit}>
            <FieldGroup>
              <div className="grid gap-4 sm:grid-cols-3">
                <Field>
                  <FieldLabel htmlFor="jenis">Jenis</FieldLabel>
                  <Select
                    items={{ IZIN: "Izin", SAKIT: "Sakit" }}
                    value={data.jenis}
                    onValueChange={(v) => v && setData("jenis", v as JenisIzin)}
                  >
                    <SelectTrigger id="jenis" className="w-full">
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="IZIN">Izin</SelectItem>
                      <SelectItem value="SAKIT">Sakit</SelectItem>
                    </SelectContent>
                  </Select>
                </Field>
                <Field>
                  <FieldLabel htmlFor="mulai">Tanggal Mulai</FieldLabel>
                  <DatePicker id="mulai" value={data.tanggalMulai} onChange={(v) => setData("tanggalMulai", v)} />
                </Field>
                <Field>
                  <FieldLabel htmlFor="selesai">Tanggal Selesai</FieldLabel>
                  <DatePicker id="selesai" value={data.tanggalSelesai} onChange={(v) => setData("tanggalSelesai", v)} />
                </Field>
              </div>
              <Field>
                <FieldLabel htmlFor="alasan">Alasan</FieldLabel>
                <Textarea
                  id="alasan"
                  required
                  value={data.alasan}
                  onChange={(e) => setData("alasan", e.target.value)}
                  placeholder="Jelaskan alasan izin/sakit"
                />
              </Field>
              <Field>
                <FieldLabel htmlFor="lampiran">Lampiran (wajib)</FieldLabel>
                <input
                  ref={lampiranInputRef}
                  id="lampiran"
                  type="file"
                  accept={ALLOWED_LAMPIRAN_TYPES}
                  className="hidden"
                  onChange={(e) => setData("lampiran", e.target.files?.[0] ?? null)}
                />
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  className="w-fit"
                  onClick={() => lampiranInputRef.current?.click()}
                >
                  <Paperclip /> {data.lampiran ? data.lampiran.name : "Lampirkan bukti (foto/PDF)"}
                </Button>
              </Field>
              {(errors.lampiran ?? errors.alasan) && (
                <p className="text-sm text-destructive" role="alert">
                  {errors.lampiran ?? errors.alasan}
                </p>
              )}
              <Field>
                <Button type="submit" disabled={processing} className="w-fit">
                  {processing ? "Mengirim..." : "Kirim Pengajuan"}
                </Button>
              </Field>
            </FieldGroup>
          </form>
        </CardContent>
      </Card>

      <div className="overflow-x-auto rounded-lg border">
        <Table kartu>
          <TableHeader>
            <TableRow>
              <TableHead className="w-10">No</TableHead>
              <TableHead>Jenis</TableHead>
              <TableHead>Periode</TableHead>
              <TableHead>Alasan</TableHead>
              <TableHead>Lampiran</TableHead>
              <TableHead>Status</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {list.length === 0 ? (
              <TableRow>
                <TableCell colSpan={6} className="text-center text-muted-foreground">
                  Belum ada pengajuan
                </TableCell>
              </TableRow>
            ) : (
              list.map((item, index) => {
                const badge = statusBadge(item.status)
                return (
                  <TableRow key={item.id}>
                    <TableCell kartu="sembunyi" className="text-muted-foreground">{index + 1}</TableCell>
                    <TableCell>{item.jenis === "IZIN" ? "Izin" : "Sakit"}</TableCell>
                    <TableCell kartu="judul">
                      {item.tanggalMulai.slice(0, 10)} s/d {item.tanggalSelesai.slice(0, 10)}
                    </TableCell>
                    <TableCell className="max-w-xs truncate">{item.alasan}</TableCell>
                    <TableCell>
                      {item.lampiranUrl ? (
                        <a
                          href={assetUrl(item.lampiranUrl)}
                          target="_blank"
                          rel="noreferrer"
                          className="inline-flex items-center gap-1 text-primary hover:underline"
                        >
                          <Paperclip className="size-3.5" /> Lihat
                        </a>
                      ) : (
                        <span className="text-muted-foreground">-</span>
                      )}
                    </TableCell>
                    <TableCell>
                      <Badge variant={badge.variant}>{badge.label}</Badge>
                    </TableCell>
                  </TableRow>
                )
              })
            )}
          </TableBody>
        </Table>
      </div>
    </div>
  )
}
