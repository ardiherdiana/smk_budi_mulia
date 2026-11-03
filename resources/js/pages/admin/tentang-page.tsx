import * as React from "react"
import { router, useForm } from "@inertiajs/react"
import { Eye, EyeOff, Pencil, Plus, Trash2 } from "lucide-react"

import type { AboutItem } from "@/lib/types"
import { GalleryField } from "@/components/admin/file-fields"
import { iconFor } from "@/components/landing/icon-map"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Checkbox } from "@/components/ui/checkbox"
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import { Field, FieldGroup, FieldLabel } from "@/components/ui/field"
import { Input } from "@/components/ui/input"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { Textarea } from "@/components/ui/textarea"

interface FormState {
  title: string
  description: string
  icon_type: string
  display_order: number
  is_active: boolean
  media: File[]
  remove_media: string[]
}

const EMPTY: FormState = { title: "", description: "", icon_type: "none", display_order: 0, is_active: true, media: [], remove_media: [] }

function AboutFormDialog({
  item,
  icons,
  open,
  onOpenChange,
}: {
  item: AboutItem | null
  icons: string[]
  open: boolean
  onOpenChange: (open: boolean) => void
}) {
  const form = useForm<FormState>(EMPTY)
  const { data, setData, errors, processing, reset, clearErrors } = form

  React.useEffect(() => {
    if (!open) return
    clearErrors()
    setData(
      item
        ? {
            title: item.title,
            description: item.description,
            icon_type: item.icon_type ?? "none",
            display_order: item.display_order,
            is_active: item.is_active,
            media: [],
            remove_media: [],
          }
        : EMPTY
    )
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, item])

  function handleSubmit(event: React.FormEvent) {
    event.preventDefault()
    const options = {
      forceFormData: true,
      preserveScroll: true,
      onSuccess: () => {
        onOpenChange(false)
        reset()
      },
    }
    form.transform((d) => ({ ...d, icon_type: d.icon_type === "none" ? "" : d.icon_type, ...(item ? { _method: "put" } : {}) }))
    form.post(item ? `/admin/tentang-kami/${item.id}` : "/admin/tentang-kami", options)
  }

  const iconItems = { none: "Tanpa ikon", ...Object.fromEntries(icons.map((i) => [i, i])) }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[92svh] overflow-y-auto sm:max-w-xl">
        <form onSubmit={handleSubmit} className="grid gap-4">
          <DialogHeader>
            <DialogTitle>{item ? "Edit blok tentang kami" : "Blok tentang kami baru"}</DialogTitle>
            <DialogDescription>Tampil sebagai tab pada bagian Tentang Kami di halaman utama.</DialogDescription>
          </DialogHeader>
          <FieldGroup>
            <Field>
              <FieldLabel htmlFor="about-title">Judul</FieldLabel>
              <Input id="about-title" value={data.title} onChange={(e) => setData("title", e.target.value)} required />
              {errors.title && <p className="text-sm text-destructive">{errors.title}</p>}
            </Field>
            <Field>
              <FieldLabel htmlFor="about-desc">Deskripsi</FieldLabel>
              <Textarea id="about-desc" rows={6} value={data.description} onChange={(e) => setData("description", e.target.value)} required />
              {errors.description && <p className="text-sm text-destructive">{errors.description}</p>}
            </Field>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <Field>
                <FieldLabel>Ikon</FieldLabel>
                <Select items={iconItems} value={data.icon_type} onValueChange={(v) => v && setData("icon_type", v)}>
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
                <FieldLabel htmlFor="about-order">Urutan tampil (kecil = lebih dulu)</FieldLabel>
                <Input
                  id="about-order"
                  type="number"
                  min={0}
                  value={data.display_order}
                  onChange={(e) => setData("display_order", Number(e.target.value))}
                />
                {errors.display_order && <p className="text-sm text-destructive">{errors.display_order}</p>}
              </Field>
            </div>
            <label className="flex items-center gap-2 text-sm">
              <Checkbox checked={data.is_active} onCheckedChange={(checked) => setData("is_active", checked === true)} />
              Tampilkan di website
            </label>
            <Field>
              <FieldLabel htmlFor="about-media">Foto</FieldLabel>
              <GalleryField
                id="about-media"
                existing={(item?.media ?? []).map((m) => ({ id: m.id, url: m.url }))}
                removedIds={data.remove_media}
                files={data.media}
                onRemovedChange={(ids) => setData("remove_media", ids)}
                onFilesChange={(files) => setData("media", files)}
                error={errors.media ?? errors["media.0"]}
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

export function AdminTentangPage({ items, icons }: { items: AboutItem[]; icons: string[] }) {
  const [editing, setEditing] = React.useState<AboutItem | null>(null)
  const [open, setOpen] = React.useState(false)

  function handleDelete(item: AboutItem) {
    if (!window.confirm(`Hapus "${item.title}"?`)) return
    router.delete(`/admin/tentang-kami/${item.id}`, { preserveScroll: true })
  }

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="text-xl font-semibold">Tentang Kami</h1>
          <p className="text-sm text-muted-foreground">Blok konten profil sekolah (sejarah, fasilitas, prestasi, dll.)</p>
        </div>
        <Button
          onClick={() => {
            setEditing(null)
            setOpen(true)
          }}
        >
          <Plus /> Blok baru
        </Button>
      </div>

      <div className="overflow-x-auto rounded-lg border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead className="w-16">Urutan</TableHead>
              <TableHead>Judul</TableHead>
              <TableHead>Foto</TableHead>
              <TableHead>Status</TableHead>
              <TableHead className="text-right">Aksi</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {items.length === 0 ? (
              <TableRow>
                <TableCell colSpan={5} className="text-center text-muted-foreground">
                  Belum ada data
                </TableCell>
              </TableRow>
            ) : (
              items.map((item) => {
                const Icon = iconFor(item.icon_type)
                return (
                  <TableRow key={item.id}>
                    <TableCell className="text-muted-foreground">{item.display_order}</TableCell>
                    {/* Teks panjang dipotong dengan "..." supaya tabel tidak melebar dan memunculkan scroll horizontal. */}
                    <TableCell className="max-w-xs md:max-w-md lg:max-w-xl">
                      <div className="flex min-w-0 items-center gap-2 font-medium">
                        <Icon className="size-4 shrink-0 text-muted-foreground" />
                        <span className="truncate" title={item.title}>
                          {item.title}
                        </span>
                      </div>
                      <p className="truncate text-xs text-muted-foreground" title={item.description}>
                        {item.description}
                      </p>
                    </TableCell>
                    <TableCell className="text-muted-foreground">{item.media.length}</TableCell>
                    <TableCell>
                      {item.is_active ? (
                        <Badge variant="success">
                          <Eye /> Tampil
                        </Badge>
                      ) : (
                        <Badge variant="outline">
                          <EyeOff /> Disembunyikan
                        </Badge>
                      )}
                    </TableCell>
                    <TableCell className="text-right whitespace-nowrap">
                      <Button
                        variant="ghost"
                        size="icon-sm"
                        aria-label="Edit"
                        onClick={() => {
                          setEditing(item)
                          setOpen(true)
                        }}
                      >
                        <Pencil />
                      </Button>
                      <Button variant="ghost" size="icon-sm" aria-label="Hapus" onClick={() => handleDelete(item)}>
                        <Trash2 />
                      </Button>
                    </TableCell>
                  </TableRow>
                )
              })
            )}
          </TableBody>
        </Table>
      </div>

      <AboutFormDialog item={editing} icons={icons} open={open} onOpenChange={setOpen} />
    </div>
  )
}
