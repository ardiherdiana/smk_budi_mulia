import * as React from "react"
import { router, useForm, usePage } from "@inertiajs/react"
import { Copy, Download, FileUp, KeyRound, Search, Trash2, Upload, UserPlus } from "lucide-react"
import { toast } from "sonner"

import type { Credential, Paginated } from "@/lib/types"
import { DataPagination } from "@/components/data-pagination"
import { Badge } from "@/components/ui/badge"
import { TombolAksi } from "@/components/tombol-aksi"
import { Button } from "@/components/ui/button"
import { Checkbox } from "@/components/ui/checkbox"
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import { Field, FieldGroup, FieldLabel } from "@/components/ui/field"
import { Input } from "@/components/ui/input"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { Textarea } from "@/components/ui/textarea"

interface Student {
  id: number
  email: string
  files_count: number
}

interface Props {
  users: Paginated<Student>
  filters: { q: string }
}

function csvEscape(value: string): string {
  return /[",\n]/.test(value) ? `"${value.replace(/"/g, '""')}"` : value
}

/** Password yang baru dibuat ditampilkan SEKALI (tidak disimpan di server); unduh sebelum menutup. */
function CredentialsDialog({ credentials, onClose }: { credentials: Credential[] | null; onClose: () => void }) {
  function download() {
    if (!credentials) return
    const csv = ["Email,Password", ...credentials.map((c) => `${csvEscape(c.email)},${csvEscape(c.password)}`)].join("\n")
    const url = URL.createObjectURL(new Blob(["﻿" + csv], { type: "text/csv;charset=utf-8" }))
    const link = document.createElement("a")
    link.href = url
    link.download = `akun-siswa-${new Date().toISOString().slice(0, 10)}.csv`
    document.body.appendChild(link)
    link.click()
    link.remove()
    URL.revokeObjectURL(url)
  }

  async function copy() {
    if (!credentials) return
    try {
      await navigator.clipboard.writeText(credentials.map((c) => `${c.email}\t${c.password}`).join("\n"))
      toast.success("Disalin ke clipboard")
    } catch {
      toast.error("Gagal menyalin")
    }
  }

  return (
    <Dialog open={credentials !== null} onOpenChange={(open) => !open && onClose()}>
      <DialogContent className="max-h-[90svh] overflow-y-auto sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>Kredensial akun</DialogTitle>
          <DialogDescription>
            Password hanya ditampilkan sekali dan tidak disimpan dalam bentuk asli. Unduh atau salin sekarang, lalu bagikan ke siswa.
          </DialogDescription>
        </DialogHeader>
        <div className="max-h-72 overflow-y-auto rounded-lg border">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Email</TableHead>
                <TableHead>Password</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {credentials?.map((c) => (
                <TableRow key={c.email}>
                  <TableCell>{c.email}</TableCell>
                  <TableCell className="font-mono">{c.password}</TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </div>
        <DialogFooter>
          <Button variant="outline" onClick={copy}>
            <Copy /> Salin
          </Button>
          <Button onClick={download}>
            <Download /> Unduh CSV
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}

function BulkAddDialog({ open, onOpenChange }: { open: boolean; onOpenChange: (open: boolean) => void }) {
  const { data, setData, post, processing, errors, reset } = useForm({ emails: "", password: "" })

  function handleSubmit(event: React.FormEvent) {
    event.preventDefault()
    post("/skl/siswa", {
      preserveScroll: true,
      onSuccess: () => {
        onOpenChange(false)
        reset()
      },
    })
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <form onSubmit={handleSubmit} className="grid gap-4">
          <DialogHeader>
            <DialogTitle>Tambah akun siswa</DialogTitle>
            <DialogDescription>Satu email per baris. Email yang sudah terdaftar dilewati.</DialogDescription>
          </DialogHeader>
          <FieldGroup>
            <Field>
              <FieldLabel htmlFor="bulk-emails">Daftar email</FieldLabel>
              <Textarea id="bulk-emails" rows={7} value={data.emails} onChange={(e) => setData("emails", e.target.value)} placeholder={"siswa1@gmail.com\nsiswa2@gmail.com"} required />
              {errors.emails && <p className="text-sm text-destructive">{errors.emails}</p>}
            </Field>
            <Field>
              <FieldLabel htmlFor="bulk-password">Password (opsional)</FieldLabel>
              <Input id="bulk-password" value={data.password} onChange={(e) => setData("password", e.target.value)} placeholder="Kosongkan = acak per akun" />
              <p className="text-xs text-muted-foreground">Minimal 8 karakter. Password acak lebih aman karena berbeda untuk tiap siswa.</p>
              {errors.password && <p className="text-sm text-destructive">{errors.password}</p>}
            </Field>
          </FieldGroup>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
              Batal
            </Button>
            <Button type="submit" disabled={processing}>
              {processing ? "Membuat..." : "Buat akun"}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}

function PasswordResetDialog({
  target,
  onClose,
}: {
  target: { ids: number[]; label: string } | null
  onClose: () => void
}) {
  const { data, setData, patch, processing, errors, reset, transform } = useForm({ password: "", ids: [] as number[] })

  function handleSubmit(event: React.FormEvent) {
    event.preventDefault()
    if (!target) return
    const single = target.ids.length === 1
    transform((d) => (single ? { password: d.password } : { ...d, ids: target.ids }))
    patch(single ? `/skl/siswa/${target.ids[0]}/password` : "/skl/siswa/password", {
      preserveScroll: true,
      onSuccess: () => {
        reset()
        onClose()
      },
    })
  }

  const single = target?.ids.length === 1

  return (
    <Dialog open={target !== null} onOpenChange={(open) => !open && onClose()}>
      <DialogContent>
        <form onSubmit={handleSubmit} className="grid gap-4">
          <DialogHeader>
            <DialogTitle>Ubah password</DialogTitle>
            <DialogDescription>{target?.label}</DialogDescription>
          </DialogHeader>
          <Field>
            <FieldLabel htmlFor="reset-password">Password baru{single ? "" : " (opsional)"}</FieldLabel>
            <Input
              id="reset-password"
              value={data.password}
              onChange={(e) => setData("password", e.target.value)}
              required={single}
              placeholder={single ? "Minimal 8 karakter" : "Kosongkan = acak per akun"}
            />
            {errors.password && <p className="text-sm text-destructive">{errors.password}</p>}
          </Field>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={onClose}>
              Batal
            </Button>
            <Button type="submit" disabled={processing}>
              {processing ? "Menyimpan..." : "Simpan"}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}

export function AdminSiswaPage({ users, filters }: Props) {
  const { flash } = usePage<{ flash: { credentials?: Credential[] | null } }>().props
  const [credentials, setCredentials] = React.useState<Credential[] | null>(null)
  const [addOpen, setAddOpen] = React.useState(false)
  const [resetTarget, setResetTarget] = React.useState<{ ids: number[]; label: string } | null>(null)
  const [selected, setSelected] = React.useState<number[]>([])
  const [search, setSearch] = React.useState(filters.q)
  const timer = React.useRef<number | undefined>(undefined)
  const importInput = React.useRef<HTMLInputElement>(null)

  React.useEffect(() => {
    if (flash.credentials && flash.credentials.length > 0) setCredentials(flash.credentials)
  }, [flash.credentials])

  React.useEffect(() => setSelected([]), [users.current_page, filters.q])

  function visit(params: { q?: string; page?: number; per_page?: number }) {
    const query: Record<string, string | number> = {}
    if (params.q) query.q = params.q
    if (params.page && params.page > 1) query.page = params.page
    if (params.per_page && params.per_page !== 25) query.per_page = params.per_page
    router.get("/skl/siswa", query, { preserveScroll: true, preserveState: true, replace: true })
  }

  function onSearch(value: string) {
    setSearch(value)
    window.clearTimeout(timer.current)
    timer.current = window.setTimeout(() => visit({ q: value.trim(), per_page: users.per_page }), 350)
  }

  function handleImport(file: File | undefined) {
    if (!file) return
    router.post("/skl/siswa/impor", { csv: file }, { forceFormData: true, preserveScroll: true })
  }

  function handleDelete(student: Student) {
    const warning = student.files_count > 0 ? ` ${student.files_count} berkas milik akun ini juga akan dihapus.` : ""
    if (!window.confirm(`Hapus akun ${student.email}?${warning}`)) return
    router.delete(`/skl/siswa/${student.id}`, { preserveScroll: true })
  }

  const allSelected = users.data.length > 0 && selected.length === users.data.length

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="text-xl font-semibold">Akun Siswa</h1>
          <p className="text-sm text-muted-foreground">Akun untuk mengunduh berkas kelulusan di Portal Siswa. Password disimpan ter-hash.</p>
        </div>
        <div className="flex flex-wrap gap-2">
          <Button variant="outline" render={<a href="/skl/siswa/ekspor" />}>
            <Download /> Ekspor CSV
          </Button>
          <Button variant="outline" onClick={() => importInput.current?.click()}>
            <FileUp /> Impor password
          </Button>
          <input
            ref={importInput}
            type="file"
            accept=".csv,text/csv"
            className="sr-only"
            onChange={(e) => {
              handleImport(e.target.files?.[0])
              e.target.value = ""
            }}
          />
          <Button onClick={() => setAddOpen(true)}>
            <UserPlus /> Tambah akun
          </Button>
        </div>
      </div>

      <div className="flex flex-wrap items-center gap-3">
        <div className="relative w-full sm:w-72">
          <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
          <Input value={search} onChange={(e) => onSearch(e.target.value)} placeholder="Cari email..." className="pl-8" />
        </div>
        {selected.length > 0 && (
          <Button variant="secondary" onClick={() => setResetTarget({ ids: selected, label: `${selected.length} akun terpilih` })}>
            <KeyRound /> Ubah password ({selected.length})
          </Button>
        )}
      </div>

      {/* Di ponsel kepala tabel tidak tampil, jadi "pilih semua" dipindah ke atas daftar. */}
      {users.data.length > 0 && (
        <label className="flex min-h-11 items-center gap-3 text-sm md:hidden">
          <Checkbox checked={allSelected} onCheckedChange={(checked) => setSelected(checked === true ? users.data.map((u) => u.id) : [])} />
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
                  onCheckedChange={(checked) => setSelected(checked === true ? users.data.map((u) => u.id) : [])}
                />
              </TableHead>
              <TableHead>Email</TableHead>
              <TableHead>Berkas</TableHead>
              <TableHead className="text-right">Aksi</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {users.data.length === 0 ? (
              <TableRow>
                <TableCell colSpan={4} className="text-center text-muted-foreground">
                  Belum ada akun siswa
                </TableCell>
              </TableRow>
            ) : (
              users.data.map((student) => (
                <TableRow key={student.id}>
                  <TableCell>
                    <Checkbox
                      aria-label={`Pilih ${student.email}`}
                      checked={selected.includes(student.id)}
                      onCheckedChange={(checked) =>
                        setSelected((prev) => (checked === true ? [...prev, student.id] : prev.filter((id) => id !== student.id)))
                      }
                    />
                  </TableCell>
                  <TableCell kartu="judul" className="max-w-xs truncate font-medium md:max-w-md" title={student.email}>
                    {student.email}
                  </TableCell>
                  <TableCell>
                    {student.files_count > 0 ? <Badge variant="success">{student.files_count} berkas</Badge> : <Badge variant="warning">Belum ada</Badge>}
                  </TableCell>
                  <TableCell kartu="aksi" className="text-right whitespace-nowrap">
                    <TombolAksi label="Ubah password" onClick={() => setResetTarget({ ids: [student.id], label: student.email })}>
                      <KeyRound />
                    </TombolAksi>
                    <TombolAksi jenis="hapus" aria-label="Hapus akun" onClick={() => handleDelete(student)}>
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
        page={users.current_page}
        pageSize={users.per_page}
        total={users.total}
        pageSizeOptions={[25, 50, 100]}
        onPageChange={(page) => visit({ q: filters.q, page, per_page: users.per_page })}
        onPageSizeChange={(size) => visit({ q: filters.q, per_page: size })}
      />

      <BulkAddDialog open={addOpen} onOpenChange={setAddOpen} />
      <PasswordResetDialog target={resetTarget} onClose={() => setResetTarget(null)} />
      <CredentialsDialog credentials={credentials} onClose={() => setCredentials(null)} />
      <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
        <Upload className="size-3.5" /> Impor CSV: kolom ID, Email, Password Baru (gunakan berkas hasil Ekspor CSV sebagai templat).
      </p>
    </div>
  )
}
