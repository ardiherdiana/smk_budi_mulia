import * as React from "react"
import { router, useForm } from "@inertiajs/react"
import { Download, Search, Send, Trash2, Upload } from "lucide-react"

import type { Paginated } from "@/lib/types"
import { formatTanggalWaktu } from "@/lib/format"
import { DataPagination } from "@/components/data-pagination"
import { Badge } from "@/components/ui/badge"
import { TombolAksi } from "@/components/tombol-aksi"
import { Button } from "@/components/ui/button"
import { Checkbox } from "@/components/ui/checkbox"
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import { Field, FieldGroup, FieldLabel } from "@/components/ui/field"
import { Input } from "@/components/ui/input"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs"

interface PortalFile {
  id: number
  nama_file: string
  uploaded_at: string | null
  owner_email: string | null
  available: boolean
  download_url: string
}

interface Props {
  files: Paginated<PortalFile>
  students: { id: number; email: string }[]
  filters: { q: string; scope: string }
}

const GUDANG = "gudang"

function StudentSelect({ students, value, onChange }: { students: Props["students"]; value: string; onChange: (value: string) => void }) {
  const items = { [GUDANG]: "Gudang (belum dibagikan)", ...Object.fromEntries(students.map((s) => [String(s.id), s.email])) }

  return (
    <Select items={items} value={value} onValueChange={(v) => v && onChange(v)}>
      <SelectTrigger className="w-full">
        <SelectValue />
      </SelectTrigger>
      <SelectContent className="max-h-72">
        {Object.entries(items).map(([id, label]) => (
          <SelectItem key={id} value={id}>
            {label}
          </SelectItem>
        ))}
      </SelectContent>
    </Select>
  )
}

function UploadDialog({ students, open, onOpenChange }: { students: Props["students"]; open: boolean; onOpenChange: (open: boolean) => void }) {
  const { data, setData, post, processing, errors, reset, transform } = useForm<{ files: File[]; owner: string }>({ files: [], owner: GUDANG })
  const input = React.useRef<HTMLInputElement>(null)

  function handleSubmit(event: React.FormEvent) {
    event.preventDefault()
    transform((d) => ({ files: d.files, user_id: d.owner === GUDANG ? "" : d.owner }))
    post("/skl/berkas", {
      forceFormData: true,
      preserveScroll: true,
      onSuccess: () => {
        reset()
        onOpenChange(false)
      },
    })
  }

  const fileError = errors.files ?? Object.entries(errors).find(([key]) => key.startsWith("files."))?.[1]

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <form onSubmit={handleSubmit} className="grid gap-4">
          <DialogHeader>
            <DialogTitle>Unggah berkas PDF</DialogTitle>
            <DialogDescription>Berkas disimpan privat dan hanya bisa diunduh oleh pemiliknya dan admin.</DialogDescription>
          </DialogHeader>
          <FieldGroup>
            <Field>
              <FieldLabel>Pemilik</FieldLabel>
              <StudentSelect students={students} value={data.owner} onChange={(v) => setData("owner", v)} />
            </Field>
            <Field>
              <FieldLabel htmlFor="upload-files">File PDF (maks. 10 MB per berkas)</FieldLabel>
              <div className="flex items-center gap-3">
                <Button type="button" variant="outline" onClick={() => input.current?.click()}>
                  <Upload /> Pilih file
                </Button>
                <span className="text-sm text-muted-foreground">{data.files.length > 0 ? `${data.files.length} file dipilih` : "Belum ada file"}</span>
              </div>
              <input
                ref={input}
                id="upload-files"
                type="file"
                multiple
                accept="application/pdf,.pdf"
                className="sr-only"
                onChange={(e) => setData("files", Array.from(e.target.files ?? []))}
              />
              {fileError && <p className="text-sm text-destructive">{fileError}</p>}
              {(errors as Record<string, string>).user_id && <p className="text-sm text-destructive">{(errors as Record<string, string>).user_id}</p>}
            </Field>
          </FieldGroup>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
              Batal
            </Button>
            <Button type="submit" disabled={processing || data.files.length === 0}>
              {processing ? "Mengunggah..." : "Unggah"}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}

function AssignDialog({
  students,
  ids,
  onClose,
}: {
  students: Props["students"]
  ids: number[] | null
  onClose: () => void
}) {
  const [owner, setOwner] = React.useState(GUDANG)

  function handleSubmit(event: React.FormEvent) {
    event.preventDefault()
    router.patch(
      "/skl/berkas/bagikan",
      { ids: ids ?? [], user_id: owner === GUDANG ? null : Number(owner) },
      { preserveScroll: true, onSuccess: onClose }
    )
  }

  return (
    <Dialog open={ids !== null} onOpenChange={(open) => !open && onClose()}>
      <DialogContent>
        <form onSubmit={handleSubmit} className="grid gap-4">
          <DialogHeader>
            <DialogTitle>Bagikan berkas</DialogTitle>
            <DialogDescription>{ids?.length} berkas terpilih akan dipindahkan ke pemilik berikut.</DialogDescription>
          </DialogHeader>
          <Field>
            <FieldLabel>Siswa penerima</FieldLabel>
            <StudentSelect students={students} value={owner} onChange={setOwner} />
          </Field>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={onClose}>
              Batal
            </Button>
            <Button type="submit">
              <Send /> Bagikan
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}

export function AdminBerkasPage({ files, students, filters }: Props) {
  const [uploadOpen, setUploadOpen] = React.useState(false)
  const [assignIds, setAssignIds] = React.useState<number[] | null>(null)
  const [selected, setSelected] = React.useState<number[]>([])
  const [search, setSearch] = React.useState(filters.q)
  const timer = React.useRef<number | undefined>(undefined)

  React.useEffect(() => setSelected([]), [files.current_page, filters.q, filters.scope])

  function visit(params: { q?: string; scope?: string; page?: number; per_page?: number }) {
    const query: Record<string, string | number> = {}
    if (params.q) query.q = params.q
    if (params.scope === GUDANG) query.scope = GUDANG
    if (params.page && params.page > 1) query.page = params.page
    if (params.per_page && params.per_page !== 25) query.per_page = params.per_page
    router.get("/skl/berkas", query, { preserveScroll: true, preserveState: true, replace: true })
  }

  function onSearch(value: string) {
    setSearch(value)
    window.clearTimeout(timer.current)
    timer.current = window.setTimeout(() => visit({ q: value.trim(), scope: filters.scope, per_page: files.per_page }), 350)
  }

  function handleDelete(file: PortalFile) {
    if (!window.confirm(`Hapus berkas "${file.nama_file}"? File PDF-nya juga akan dihapus.`)) return
    router.delete(`/skl/berkas/${file.id}`, { preserveScroll: true })
  }

  const allSelected = files.data.length > 0 && selected.length === files.data.length

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="text-xl font-semibold">Berkas Portal (SKL)</h1>
          <p className="text-sm text-muted-foreground">Unggah PDF ke gudang, lalu bagikan ke akun siswa</p>
        </div>
        <Button onClick={() => setUploadOpen(true)}>
          <Upload /> Unggah PDF
        </Button>
      </div>

      <div className="flex flex-wrap items-center gap-3">
        <Tabs value={filters.scope} onValueChange={(v) => visit({ q: filters.q, scope: String(v) })}>
          <TabsList>
            <TabsTrigger value="all">Semua</TabsTrigger>
            <TabsTrigger value={GUDANG}>Gudang</TabsTrigger>
          </TabsList>
        </Tabs>
        <div className="relative w-full sm:w-72">
          <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
          <Input value={search} onChange={(e) => onSearch(e.target.value)} placeholder="Cari nama file atau email..." className="pl-8" />
        </div>
        {selected.length > 0 && (
          <Button variant="secondary" onClick={() => setAssignIds(selected)}>
            <Send /> Bagikan ({selected.length})
          </Button>
        )}
      </div>

      {/* Di ponsel kepala tabel tidak tampil, jadi "pilih semua" dipindah ke atas daftar. */}
      {files.data.length > 0 && (
        <label className="flex min-h-11 items-center gap-3 text-sm md:hidden">
          <Checkbox checked={allSelected} onCheckedChange={(checked) => setSelected(checked === true ? files.data.map((f) => f.id) : [])} />
          Pilih semua di halaman ini
        </label>
      )}

      <div className="overflow-x-auto rounded-lg border">
        <Table kartu>
          <TableHeader>
            <TableRow>
              <TableHead className="w-10">
                <Checkbox
                  aria-label="Pilih semua"
                  checked={allSelected}
                  onCheckedChange={(checked) => setSelected(checked === true ? files.data.map((f) => f.id) : [])}
                />
              </TableHead>
              <TableHead>Nama berkas</TableHead>
              <TableHead>Pemilik</TableHead>
              <TableHead>Diunggah</TableHead>
              <TableHead className="text-right">Aksi</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {files.data.length === 0 ? (
              <TableRow>
                <TableCell colSpan={5} className="text-center text-muted-foreground">
                  Belum ada berkas
                </TableCell>
              </TableRow>
            ) : (
              files.data.map((file) => (
                <TableRow key={file.id}>
                  <TableCell>
                    <Checkbox
                      aria-label={`Pilih ${file.nama_file}`}
                      checked={selected.includes(file.id)}
                      onCheckedChange={(checked) => setSelected((prev) => (checked === true ? [...prev, file.id] : prev.filter((id) => id !== file.id)))}
                    />
                  </TableCell>
                  <TableCell kartu="judul">
                    <div className="flex max-w-xs items-center gap-2 md:max-w-md">
                      <span className="truncate font-medium" title={file.nama_file}>
                        {file.nama_file}
                      </span>
                      {!file.available && (
                        <Badge variant="destructive" className="shrink-0">
                          File hilang
                        </Badge>
                      )}
                    </div>
                  </TableCell>
                  <TableCell className="max-w-[14rem] truncate" title={file.owner_email ?? undefined}>
                    {file.owner_email ?? <Badge variant="warning">Gudang</Badge>}
                  </TableCell>
                  <TableCell className="whitespace-nowrap text-muted-foreground">{formatTanggalWaktu(file.uploaded_at)}</TableCell>
                  <TableCell kartu="aksi" className="text-right whitespace-nowrap">
                    <TombolAksi label="Unduh" disabled={!file.available} render={<a href={file.download_url} />}>
                      <Download />
                    </TombolAksi>
                    <TombolAksi label="Bagikan" onClick={() => setAssignIds([file.id])}>
                      <Send />
                    </TombolAksi>
                    <TombolAksi jenis="hapus" onClick={() => handleDelete(file)}>
                      <Trash2 />
                    </TombolAksi>
                  </TableCell>
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>
      </div>

      <DataPagination
        page={files.current_page}
        pageSize={files.per_page}
        total={files.total}
        pageSizeOptions={[25, 50, 100]}
        onPageChange={(page) => visit({ q: filters.q, scope: filters.scope, page, per_page: files.per_page })}
        onPageSizeChange={(size) => visit({ q: filters.q, scope: filters.scope, per_page: size })}
      />

      <UploadDialog students={students} open={uploadOpen} onOpenChange={setUploadOpen} />
      <AssignDialog students={students} ids={assignIds} onClose={() => setAssignIds(null)} />
    </div>
  )
}
