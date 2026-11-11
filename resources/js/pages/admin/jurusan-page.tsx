import * as React from "react"
import { router, useForm } from "@inertiajs/react"
import { Pencil, Plus, Trash2 } from "lucide-react"

import type { Major } from "@/lib/types"
import { ImageField } from "@/components/admin/file-fields"
import { iconFor } from "@/components/landing/icon-map"
import { TombolAksi } from "@/components/tombol-aksi"
import { Button } from "@/components/ui/button"
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import { Field, FieldGroup, FieldLabel } from "@/components/ui/field"
import { Input } from "@/components/ui/input"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { Textarea } from "@/components/ui/textarea"

interface FormState {
  name: string
  description: string
  icon: string
  video_url: string
  image: File | null
  remove_image: boolean
}

const EMPTY: FormState = { name: "", description: "", icon: "none", video_url: "", image: null, remove_image: false }

function MajorFormDialog({ major, icons, open, onOpenChange }: { major: Major | null; icons: string[]; open: boolean; onOpenChange: (open: boolean) => void }) {
  const form = useForm<FormState>(EMPTY)
  const { data, setData, errors, processing, reset, clearErrors } = form

  React.useEffect(() => {
    if (!open) return
    clearErrors()
    setData(
      major
        ? { name: major.name, description: major.description ?? "", icon: major.icon ?? "none", video_url: major.video_url ?? "", image: null, remove_image: false }
        : EMPTY
    )
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, major])

  function handleSubmit(event: React.FormEvent) {
    event.preventDefault()
    form.transform((d) => ({ ...d, icon: d.icon === "none" ? "" : d.icon, ...(major ? { _method: "put" } : {}) }))
    form.post(major ? `/admin/jurusan/${major.id}` : "/admin/jurusan", {
      forceFormData: true,
      preserveScroll: true,
      onSuccess: () => {
        onOpenChange(false)
        reset()
      },
    })
  }

  const iconItems = { none: "Tanpa ikon", ...Object.fromEntries(icons.map((i) => [i, i])) }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[92svh] overflow-y-auto sm:max-w-xl">
        <form onSubmit={handleSubmit} className="grid gap-4">
          <DialogHeader>
            <DialogTitle>{major ? "Edit jurusan" : "Jurusan baru"}</DialogTitle>
            <DialogDescription>Tampil pada bagian Jurusan Unggulan di halaman utama.</DialogDescription>
          </DialogHeader>
          <FieldGroup>
            <Field>
              <FieldLabel htmlFor="major-name">Nama jurusan</FieldLabel>
              <Input id="major-name" value={data.name} onChange={(e) => setData("name", e.target.value)} required />
              {errors.name && <p className="text-sm text-destructive">{errors.name}</p>}
            </Field>
            <Field>
              <FieldLabel htmlFor="major-desc">Deskripsi</FieldLabel>
              <Textarea id="major-desc" rows={5} value={data.description} onChange={(e) => setData("description", e.target.value)} />
              {errors.description && <p className="text-sm text-destructive">{errors.description}</p>}
            </Field>
            <Field>
              <FieldLabel>Ikon</FieldLabel>
              <Select items={iconItems} value={data.icon} onValueChange={(v) => v && setData("icon", v)}>
                <SelectTrigger className="w-full">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {Object.entries(iconItems).map(([value, label]) => (
                    <SelectItem key={value} value={value}>
                      {label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </Field>
            <Field>
              <FieldLabel htmlFor="major-video">Link video YouTube (opsional)</FieldLabel>
              <Input id="major-video" value={data.video_url} onChange={(e) => setData("video_url", e.target.value)} placeholder="https://www.youtube.com/watch?v=..." />
              {errors.video_url && <p className="text-sm text-destructive">{errors.video_url}</p>}
            </Field>
            <Field>
              <FieldLabel htmlFor="major-image">Gambar / logo jurusan</FieldLabel>
              <ImageField
                id="major-image"
                currentUrl={major?.image_url}
                file={data.image}
                removed={data.remove_image}
                onFileChange={(f) => setData("image", f)}
                onRemovedChange={(v) => setData("remove_image", v)}
                error={errors.image}
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

export function AdminJurusanPage({ majors, icons }: { majors: Major[]; icons: string[] }) {
  const [editing, setEditing] = React.useState<Major | null>(null)
  const [open, setOpen] = React.useState(false)

  function handleDelete(major: Major) {
    if (!window.confirm(`Hapus jurusan "${major.name}"?`)) return
    router.delete(`/admin/jurusan/${major.id}`, { preserveScroll: true })
  }

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="text-xl font-semibold">Jurusan</h1>
          <p className="text-sm text-muted-foreground">Program keahlian yang ditampilkan di halaman utama</p>
        </div>
        <Button
          onClick={() => {
            setEditing(null)
            setOpen(true)
          }}
        >
          <Plus /> Jurusan baru
        </Button>
      </div>

      <div className="overflow-x-auto rounded-lg border">
        <Table kartu>
          <TableHeader>
            <TableRow>
              <TableHead className="w-24">Gambar</TableHead>
              <TableHead>Nama</TableHead>
              <TableHead>Video</TableHead>
              <TableHead className="text-right">Aksi</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {majors.length === 0 ? (
              <TableRow>
                <TableCell colSpan={4} className="text-center text-muted-foreground">
                  Belum ada jurusan yang ditambahkan
                </TableCell>
              </TableRow>
            ) : (
              majors.map((major) => {
                const Icon = iconFor(major.icon)
                return (
                  <TableRow key={major.id}>
                    <TableCell>
                      <div className="flex h-14 w-20 items-center justify-center overflow-hidden rounded-md bg-muted text-muted-foreground">
                        {major.image_url ? <img src={major.image_url} alt="" loading="lazy" className="size-full object-contain" /> : <Icon className="size-5" />}
                      </div>
                    </TableCell>
                    <TableCell kartu="judul" className="max-w-xs md:max-w-md lg:max-w-xl">
                      <p className="truncate font-medium" title={major.name}>
                        {major.name}
                      </p>
                      <p className="truncate text-xs text-muted-foreground" title={major.description ?? undefined}>
                        {major.description}
                      </p>
                    </TableCell>
                    <TableCell className="text-muted-foreground">{major.youtube_id ? "YouTube" : "-"}</TableCell>
                    <TableCell kartu="aksi" className="text-right whitespace-nowrap">
                      <TombolAksi
                        jenis="edit"
                        onClick={() => {
                          setEditing(major)
                          setOpen(true)
                        }}
                      >
                        <Pencil />
                      </TombolAksi>
                      <TombolAksi jenis="hapus" onClick={() => handleDelete(major)}>
                        <Trash2 />
                      </TombolAksi>
                    </TableCell>
                  </TableRow>
                )
              })
            )}
          </TableBody>
        </Table>
      </div>

      <MajorFormDialog major={editing} icons={icons} open={open} onOpenChange={setOpen} />
    </div>
  )
}
