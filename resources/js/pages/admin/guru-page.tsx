import * as React from "react"
import { router, useForm } from "@inertiajs/react"
import { Pencil, Plus, Search, Trash2 } from "lucide-react"

import { initials } from "@/lib/utils"
import type { StaffItem, StaffRole } from "@/lib/types"
import { ImageField } from "@/components/admin/file-fields"
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import { Badge } from "@/components/ui/badge"
import { TombolAksi } from "@/components/tombol-aksi"
import { Button } from "@/components/ui/button"
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import { Field, FieldGroup, FieldLabel } from "@/components/ui/field"
import { Input } from "@/components/ui/input"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs"

const ROLE_LABEL: Record<StaffRole, string> = {
  principal: "Kepala Sekolah",
  vice_principal: "Wakil Kepala Sekolah",
  program_head: "Kepala Program",
  teacher: "Guru",
  staff: "Tenaga Kependidikan",
}

interface FormState {
  name: string
  email: string
  role: StaffRole
  type: string
  subject: string
  expertise: string
  position: string
  image: File | null
  remove_image: boolean
}

const EMPTY: FormState = { name: "", email: "", role: "teacher", type: "teacher", subject: "", expertise: "", position: "", image: null, remove_image: false }

function StaffFormDialog({ member, open, onOpenChange }: { member: StaffItem | null; open: boolean; onOpenChange: (open: boolean) => void }) {
  const form = useForm<FormState>(EMPTY)
  const { data, setData, errors, processing, reset, clearErrors } = form

  React.useEffect(() => {
    if (!open) return
    clearErrors()
    setData(
      member
        ? {
            name: member.name,
            email: member.email ?? "",
            role: member.role,
            type: member.type ?? "",
            subject: member.subject ?? "",
            expertise: member.expertise ?? "",
            position: member.position ?? "",
            image: null,
            remove_image: false,
          }
        : EMPTY
    )
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, member])

  function handleSubmit(event: React.FormEvent) {
    event.preventDefault()
    form.transform((d) => (member ? { ...d, _method: "put" } : d))
    form.post(member ? `/admin/guru-staff/${member.id}` : "/admin/guru-staff", {
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
      <DialogContent className="max-h-[92svh] overflow-y-auto sm:max-w-xl">
        <form onSubmit={handleSubmit} className="grid gap-4">
          <DialogHeader>
            <DialogTitle>{member ? "Edit data" : "Tambah guru / staf"}</DialogTitle>
            <DialogDescription>Ditampilkan pada bagian Guru & Tenaga Kependidikan di halaman utama.</DialogDescription>
          </DialogHeader>
          <FieldGroup>
            <Field>
              <FieldLabel htmlFor="staff-name">Nama lengkap (beserta gelar)</FieldLabel>
              <Input id="staff-name" value={data.name} onChange={(e) => setData("name", e.target.value)} required />
              {errors.name && <p className="text-sm text-destructive">{errors.name}</p>}
            </Field>
            <Field>
              <FieldLabel>Kategori</FieldLabel>
              <Select items={ROLE_LABEL} value={data.role} onValueChange={(v) => v && setData("role", v as StaffRole)}>
                <SelectTrigger className="w-full">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {(Object.keys(ROLE_LABEL) as StaffRole[]).map((role) => (
                    <SelectItem key={role} value={role}>
                      {ROLE_LABEL[role]}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              {errors.role && <p className="text-sm text-destructive">{errors.role}</p>}
            </Field>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <Field>
                <FieldLabel htmlFor="staff-expertise">Bidang / mata pelajaran / keahlian</FieldLabel>
                <Input id="staff-expertise" value={data.expertise} onChange={(e) => setData("expertise", e.target.value)} placeholder="mis. Kurikulum, Matematika" />
                {errors.expertise && <p className="text-sm text-destructive">{errors.expertise}</p>}
              </Field>
              <Field>
                <FieldLabel htmlFor="staff-position">Jabatan (opsional)</FieldLabel>
                <Input id="staff-position" value={data.position} onChange={(e) => setData("position", e.target.value)} placeholder="mis. Kepala TU" />
              </Field>
              <Field>
                <FieldLabel htmlFor="staff-subject">Mata pelajaran lain (opsional)</FieldLabel>
                <Input id="staff-subject" value={data.subject} onChange={(e) => setData("subject", e.target.value)} />
              </Field>
              <Field>
                <FieldLabel htmlFor="staff-email">Email/kontak (maks 20 karakter)</FieldLabel>
                <Input id="staff-email" maxLength={20} value={data.email} onChange={(e) => setData("email", e.target.value)} />
                {errors.email && <p className="text-sm text-destructive">{errors.email}</p>}
              </Field>
            </div>
            <Field>
              <FieldLabel htmlFor="staff-image">Foto</FieldLabel>
              <ImageField
                id="staff-image"
                shape="portrait"
                hint="Foto tegak (rasio 3:4 atau 2:3) paling cocok; bagian atas foto dijaga supaya kepala tidak terpotong."
                currentUrl={member?.image_url}
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

export function AdminGuruPage({ staff, filters }: { staff: StaffItem[]; filters: { q: string; role: string } }) {
  const [editing, setEditing] = React.useState<StaffItem | null>(null)
  const [open, setOpen] = React.useState(false)
  const [search, setSearch] = React.useState(filters.q)
  const timer = React.useRef<number | undefined>(undefined)

  function visit(params: { q?: string; role?: string }) {
    const query: Record<string, string> = {}
    if (params.q) query.q = params.q
    if (params.role && params.role !== "all") query.role = params.role
    router.get("/admin/guru-staff", query, { preserveScroll: true, preserveState: true, replace: true })
  }

  function onSearch(value: string) {
    setSearch(value)
    window.clearTimeout(timer.current)
    timer.current = window.setTimeout(() => visit({ q: value.trim(), role: filters.role }), 350)
  }

  function handleDelete(member: StaffItem) {
    if (!window.confirm(`Hapus "${member.name}"?`)) return
    router.delete(`/admin/guru-staff/${member.id}`, { preserveScroll: true })
  }

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="text-xl font-semibold">Guru & Staf</h1>
          <p className="text-sm text-muted-foreground">{staff.length} data ditampilkan</p>
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

      <div className="flex flex-wrap items-center gap-3">
        <Tabs value={filters.role} onValueChange={(v) => visit({ q: filters.q, role: String(v) })} className="w-full sm:w-auto">
          {/* Ponsel: grid 3 kolom dua baris, garis bawah per sel supaya tiap baris punya garisnya sendiri. */}
          <TabsList className="grid w-full grid-cols-3 gap-0 border-b-0 *:border-b sm:flex sm:w-fit sm:flex-wrap sm:gap-1 sm:border-b sm:*:border-b-0">
            <TabsTrigger value="all">Semua</TabsTrigger>
            <TabsTrigger value="vice_principal">Wakasek</TabsTrigger>
            <TabsTrigger value="program_head">Kaprog</TabsTrigger>
            <TabsTrigger value="teacher">Guru</TabsTrigger>
            <TabsTrigger value="staff">Staf</TabsTrigger>
            <TabsTrigger value="principal">Kepsek</TabsTrigger>
          </TabsList>
        </Tabs>
        <div className="relative w-full sm:w-64">
          <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
          <Input value={search} onChange={(e) => onSearch(e.target.value)} placeholder="Cari nama..." className="pl-8" />
        </div>
      </div>

      <div className="overflow-x-auto rounded-lg border">
        <Table kartu>
          <TableHeader>
            <TableRow>
              <TableHead>Nama</TableHead>
              <TableHead>Kategori</TableHead>
              <TableHead>Bidang / keahlian</TableHead>
              <TableHead className="text-right">Aksi</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {staff.length === 0 ? (
              <TableRow>
                <TableCell colSpan={4} className="text-center text-muted-foreground">
                  Tidak ada data
                </TableCell>
              </TableRow>
            ) : (
              staff.map((member) => (
                <TableRow key={member.id}>
                  <TableCell kartu="judul">
                    <div className="flex items-center gap-3">
                      <Avatar>
                        {member.image_url && <AvatarImage src={member.image_url} alt={member.name} loading="lazy" className="object-cover object-top" />}
                        <AvatarFallback>{initials(member.name)}</AvatarFallback>
                      </Avatar>
                      <span className="max-w-[16rem] truncate font-medium md:max-w-sm" title={member.name}>
                        {member.name}
                      </span>
                    </div>
                  </TableCell>
                  <TableCell>
                    <Badge variant={member.role === "teacher" ? "secondary" : "outline"}>{ROLE_LABEL[member.role]}</Badge>
                  </TableCell>
                  <TableCell className="max-w-xs truncate text-muted-foreground" title={member.expertise || member.subject || member.position || undefined}>
                    {member.expertise || member.subject || member.position || "-"}
                  </TableCell>
                  <TableCell kartu="aksi" className="text-right whitespace-nowrap">
                    <TombolAksi
                      jenis="edit"
                      onClick={() => {
                        setEditing(member)
                        setOpen(true)
                      }}
                    >
                      <Pencil />
                    </TombolAksi>
                    <TombolAksi jenis="hapus" onClick={() => handleDelete(member)}>
                      <Trash2 />
                    </TombolAksi>
                  </TableCell>
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>
      </div>

      <StaffFormDialog member={editing} open={open} onOpenChange={setOpen} />
    </div>
  )
}
