import * as React from "react"
import { router, useForm } from "@inertiajs/react"
import { Pencil, Plus, Trash2 } from "lucide-react"

import { initials } from "@/lib/utils"
import type { PrincipalItem } from "@/lib/types"
import { ImageField } from "@/components/admin/file-fields"
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import { Button } from "@/components/ui/button"
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import { Field, FieldGroup, FieldLabel } from "@/components/ui/field"
import { Input } from "@/components/ui/input"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"

interface FormState {
  nama: string
  tahun_jabatan: string
  foto: File | null
  remove_foto: boolean
}

const EMPTY: FormState = { nama: "", tahun_jabatan: "", foto: null, remove_foto: false }

function PrincipalFormDialog({ principal, open, onOpenChange }: { principal: PrincipalItem | null; open: boolean; onOpenChange: (open: boolean) => void }) {
  const form = useForm<FormState>(EMPTY)
  const { data, setData, errors, processing, reset, clearErrors } = form

  React.useEffect(() => {
    if (!open) return
    clearErrors()
    setData(principal ? { nama: principal.nama, tahun_jabatan: principal.tahun_jabatan, foto: null, remove_foto: false } : EMPTY)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, principal])

  function handleSubmit(event: React.FormEvent) {
    event.preventDefault()
    form.transform((d) => (principal ? { ...d, _method: "put" } : d))
    form.post(principal ? `/admin/kepala-sekolah/${principal.id}` : "/admin/kepala-sekolah", {
      forceFormData: true,
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
            <DialogTitle>{principal ? "Edit kepala sekolah" : "Tambah kepala sekolah"}</DialogTitle>
            <DialogDescription>Kepala sekolah terbaru (urutan teratas) ditampilkan sebagai "Saat Ini".</DialogDescription>
          </DialogHeader>
          <FieldGroup>
            <Field>
              <FieldLabel htmlFor="principal-name">Nama (beserta gelar)</FieldLabel>
              <Input id="principal-name" value={data.nama} onChange={(e) => setData("nama", e.target.value)} required />
              {errors.nama && <p className="text-sm text-destructive">{errors.nama}</p>}
            </Field>
            <Field>
              <FieldLabel htmlFor="principal-period">Periode jabatan</FieldLabel>
              <Input id="principal-period" value={data.tahun_jabatan} onChange={(e) => setData("tahun_jabatan", e.target.value)} placeholder="mis. 2022-2030" required />
              {errors.tahun_jabatan && <p className="text-sm text-destructive">{errors.tahun_jabatan}</p>}
            </Field>
            <Field>
              <FieldLabel htmlFor="principal-photo">Foto</FieldLabel>
              <ImageField
                id="principal-photo"
                shape="portrait"
                hint="Foto tegak (rasio 3:4 atau 2:3) paling cocok; bagian atas foto dijaga supaya kepala tidak terpotong."
                currentUrl={principal?.foto_url}
                file={data.foto}
                removed={data.remove_foto}
                onFileChange={(f) => setData("foto", f)}
                onRemovedChange={(v) => setData("remove_foto", v)}
                error={errors.foto}
              />
            </Field>
          </FieldGroup>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
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

export function AdminKepsekPage({ principals }: { principals: PrincipalItem[] }) {
  const [editing, setEditing] = React.useState<PrincipalItem | null>(null)
  const [open, setOpen] = React.useState(false)

  function handleDelete(principal: PrincipalItem) {
    if (!window.confirm(`Hapus "${principal.nama}"?`)) return
    router.delete(`/admin/kepala-sekolah/${principal.id}`, { preserveScroll: true })
  }

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="text-xl font-semibold">Kepala Sekolah</h1>
          <p className="text-sm text-muted-foreground">Riwayat kepala sekolah, dari yang terbaru</p>
        </div>
        <Button
          onClick={() => {
            setEditing(null)
            setOpen(true)
          }}
        >
          <Plus /> Tambah
        </Button>
      </div>

      <div className="overflow-x-auto rounded-lg border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Nama</TableHead>
              <TableHead>Periode</TableHead>
              <TableHead className="text-right">Aksi</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {principals.length === 0 ? (
              <TableRow>
                <TableCell colSpan={3} className="text-center text-muted-foreground">
                  Belum ada data
                </TableCell>
              </TableRow>
            ) : (
              principals.map((principal) => (
                <TableRow key={principal.id}>
                  <TableCell>
                    <div className="flex items-center gap-3">
                      <Avatar>
                        {principal.foto_url && <AvatarImage src={principal.foto_url} alt={principal.nama} loading="lazy" className="object-cover object-top" />}
                        <AvatarFallback>{initials(principal.nama)}</AvatarFallback>
                      </Avatar>
                      <span className="max-w-[16rem] truncate font-medium md:max-w-sm" title={principal.nama}>
                        {principal.nama}
                      </span>
                    </div>
                  </TableCell>
                  <TableCell className="text-muted-foreground">{principal.tahun_jabatan}</TableCell>
                  <TableCell className="text-right whitespace-nowrap">
                    <Button
                      variant="ghost"
                      size="icon-sm"
                      aria-label="Edit"
                      onClick={() => {
                        setEditing(principal)
                        setOpen(true)
                      }}
                    >
                      <Pencil />
                    </Button>
                    <Button variant="ghost" size="icon-sm" aria-label="Hapus" onClick={() => handleDelete(principal)}>
                      <Trash2 />
                    </Button>
                  </TableCell>
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>
      </div>

      <PrincipalFormDialog principal={editing} open={open} onOpenChange={setOpen} />
    </div>
  )
}
