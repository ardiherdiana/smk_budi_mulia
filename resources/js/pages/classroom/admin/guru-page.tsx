import * as React from "react"
import { router } from "@inertiajs/react"
import { Pencil, Plus, Trash2 } from "lucide-react"

import type { Paginasi, Role } from "@/lib/classroom/types"
import { useAuth } from "@/context/classroom-auth"
import { useConfirm } from "@/hooks/use-confirm"
import { initials } from "@/lib/classroom/utils"
import { ImageField } from "@/components/admin/file-fields"
import { DataPagination } from "@/components/data-pagination"
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Checkbox } from "@/components/ui/checkbox"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import { Field, FieldDescription, FieldGroup, FieldLabel } from "@/components/ui/field"
import { Input } from "@/components/ui/input"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"

interface Guru {
  id: string
  nama: string
  username: string
  role: Role
  aktif: boolean
  jumlahKelas: number
  waliKelas: boolean
  fotoUrl: string | null
  tampilDiWebsite: boolean
}

interface Props {
  guru: Paginasi<Guru>
  filter: { q: string }
}

interface FormState {
  id: string | null
  nama: string
  username: string
  password: string
  role: Role
  aktif: boolean
  fotoUrl: string | null
  foto: File | null
  hapusFoto: boolean
}

const LABEL_ROLE: Partial<Record<Role, string>> = { ADMIN: "Admin", KEPSEK: "Kepsek", GURU: "Guru" }
const PILIHAN_ROLE: Partial<Record<Role, string>> = { GURU: "Guru", KEPSEK: "Kepala sekolah" }
const KOSONG: FormState = { id: null, nama: "", username: "", password: "", role: "GURU", aktif: true, fotoUrl: null, foto: null, hapusFoto: false }

export function GuruPage({ guru, filter }: Props) {
  const { isAdmin } = useAuth()
  const { confirm, confirmDialog } = useConfirm()
  const [q, setQ] = React.useState(filter.q)
  const [buka, setBuka] = React.useState(false)
  const [form, setForm] = React.useState<FormState>(KOSONG)
  const [errors, setErrors] = React.useState<Record<string, string>>({})
  const [memproses, setMemproses] = React.useState(false)

  function muat(params: { q?: string; page?: number; per_page?: number }) {
    router.get(
      "/classroom/data-guru",
      { q: (params.q ?? q) || undefined, page: params.page, per_page: params.per_page ?? guru.per_page },
      { preserveState: true, preserveScroll: true, replace: true }
    )
  }

  const pertama = React.useRef(true)
  React.useEffect(() => {
    if (pertama.current) {
      pertama.current = false
      return
    }
    const timer = setTimeout(() => muat({ q, page: 1 }), 300)
    return () => clearTimeout(timer)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [q])

  function tambah() {
    setForm(KOSONG)
    setErrors({})
    setBuka(true)
  }

  function ubah(g: Guru) {
    setForm({ id: g.id, nama: g.nama, username: g.username, password: "", role: g.role, aktif: g.aktif, fotoUrl: g.fotoUrl, foto: null, hapusFoto: false })
    setErrors({})
    setBuka(true)
  }

  function simpan(event: React.FormEvent) {
    event.preventDefault()
    const payload = {
      nama: form.nama,
      username: form.username,
      password: form.password || undefined,
      role: form.role === "ADMIN" ? undefined : form.role,
      foto: form.role === "GURU" ? (form.foto ?? undefined) : undefined,
      hapusFoto: form.hapusFoto ? 1 : undefined,
      ...(form.id ? { aktif: form.aktif ? 1 : 0 } : {}),
    }
    const opsi = {
      preserveScroll: true,
      onStart: () => {
        setMemproses(true)
        setErrors({})
      },
      onSuccess: () => setBuka(false),
      onError: (e: Record<string, string>) => setErrors(e),
      onFinish: () => setMemproses(false),
    }
    // Berkas hanya terbaca lewat POST multipart, jadi ubah memakai POST + _method=patch.
    if (form.id) router.post(`/classroom/data-guru/${form.id}`, { ...payload, _method: "patch" }, { ...opsi, forceFormData: true })
    else router.post("/classroom/data-guru", payload, { ...opsi, forceFormData: true })
  }

  async function hapus(g: Guru) {
    const ok = await confirm({
      title: `Hapus akun "${g.nama}"?`,
      description: "Akun dan sandinya dihapus dan tidak bisa dikembalikan. Bila menjadi wali kelas, rombelnya jadi tanpa wali.",
      confirmLabel: "Hapus",
      destructive: true,
    })
    if (ok) router.delete(`/classroom/data-guru/${g.id}`, { preserveScroll: true })
  }

  return (
    <div className="flex flex-col gap-4">
      {confirmDialog}
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-xl font-semibold">Guru</h1>
          <p className="text-sm text-muted-foreground">
            {isAdmin ? "Kelola akun guru dan kepala sekolah" : "Daftar akun guru, admin, dan kepala sekolah"}
          </p>
        </div>
        {isAdmin && (
          <Button onClick={tambah}>
            <Plus /> Tambah Guru
          </Button>
        )}
      </div>

      <Input placeholder="Cari nama atau username..." value={q} onChange={(e) => setQ(e.target.value)} className="max-w-sm" />

      <div className="overflow-x-auto rounded-lg border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead className="w-10">No</TableHead>
              <TableHead className="w-10"></TableHead>
              <TableHead>Nama</TableHead>
              <TableHead>Username</TableHead>
              <TableHead>Peran</TableHead>
              <TableHead>Status</TableHead>
              <TableHead className="text-right">Ruang kelas</TableHead>
              {isAdmin && <TableHead className="text-right">Aksi</TableHead>}
            </TableRow>
          </TableHeader>
          <TableBody>
            {guru.data.length === 0 ? (
              <TableRow>
                <TableCell colSpan={8} className="text-center text-muted-foreground">
                  Tidak ada akun yang cocok
                </TableCell>
              </TableRow>
            ) : (
              guru.data.map((g, i) => (
                <TableRow key={g.id}>
                  <TableCell className="text-muted-foreground">{(guru.current_page - 1) * guru.per_page + i + 1}</TableCell>
                  <TableCell>
                    <Avatar>
                      {g.fotoUrl && <AvatarImage src={g.fotoUrl} alt="" className="object-top" />}
                      <AvatarFallback>{initials(g.nama)}</AvatarFallback>
                    </Avatar>
                  </TableCell>
                  <TableCell className="font-medium">
                    <div className="flex items-center gap-2">
                      {g.nama}
                      {g.waliKelas && <Badge variant="secondary">Wali kelas</Badge>}
                    </div>
                  </TableCell>
                  <TableCell className="font-mono text-xs">{g.username}</TableCell>
                  <TableCell>{LABEL_ROLE[g.role] ?? g.role}</TableCell>
                  <TableCell>
                    <Badge variant={g.aktif ? "success" : "destructive"}>{g.aktif ? "Aktif" : "Nonaktif"}</Badge>
                  </TableCell>
                  <TableCell className="text-right">{g.role === "ADMIN" ? "-" : g.jumlahKelas}</TableCell>
                  {isAdmin && (
                    <TableCell className="text-right">
                      <div className="flex justify-end gap-1">
                        <Button variant="ghost" size="icon-sm" onClick={() => ubah(g)} aria-label={`Ubah ${g.nama}`}>
                          <Pencil />
                        </Button>
                        {g.role !== "ADMIN" && (
                          <Button variant="ghost" size="icon-sm" onClick={() => hapus(g)} aria-label={`Hapus ${g.nama}`}>
                            <Trash2 />
                          </Button>
                        )}
                      </div>
                    </TableCell>
                  )}
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>
      </div>

      <DataPagination
        page={guru.current_page}
        pageSize={guru.per_page}
        total={guru.total}
        onPageChange={(page) => muat({ page })}
        onPageSizeChange={(size) => muat({ page: 1, per_page: size })}
      />

      <Dialog open={buka} onOpenChange={setBuka}>
        <DialogContent className="max-h-[90svh] overflow-y-auto sm:max-w-md">
          <form onSubmit={simpan} className="flex flex-col gap-4">
            <DialogHeader>
              <DialogTitle>{form.id ? "Ubah Akun" : "Tambah Guru"}</DialogTitle>
              <DialogDescription>Username dan password dipakai untuk masuk di halaman login.</DialogDescription>
            </DialogHeader>
            <FieldGroup>
              <Field data-invalid={!!errors.nama}>
                <FieldLabel htmlFor="guru-nama">Nama lengkap</FieldLabel>
                <Input
                  id="guru-nama"
                  value={form.nama}
                  onChange={(e) => setForm({ ...form, nama: e.target.value })}
                  maxLength={255}
                  required
                  aria-invalid={!!errors.nama}
                />
                {errors.nama && <p className="text-sm text-destructive">{errors.nama}</p>}
              </Field>
              <Field data-invalid={!!errors.username}>
                <FieldLabel htmlFor="guru-username">Username</FieldLabel>
                <Input
                  id="guru-username"
                  value={form.username}
                  onChange={(e) => setForm({ ...form, username: e.target.value })}
                  autoCapitalize="none"
                  autoComplete="off"
                  spellCheck={false}
                  maxLength={100}
                  required
                  aria-invalid={!!errors.username}
                />
                {errors.username && <p className="text-sm text-destructive">{errors.username}</p>}
              </Field>
              <Field data-invalid={!!errors.password}>
                <FieldLabel htmlFor="guru-password">{form.id ? "Password baru (opsional)" : "Password"}</FieldLabel>
                <Input
                  id="guru-password"
                  type="password"
                  value={form.password}
                  onChange={(e) => setForm({ ...form, password: e.target.value })}
                  autoComplete="new-password"
                  minLength={6}
                  maxLength={100}
                  required={!form.id}
                  aria-invalid={!!errors.password}
                />
                {form.id && <FieldDescription>Kosongkan bila password tidak diganti.</FieldDescription>}
                {errors.password && <p className="text-sm text-destructive">{errors.password}</p>}
              </Field>
              {form.role !== "ADMIN" && (
                <Field data-invalid={!!errors.role}>
                  <FieldLabel htmlFor="guru-role">Peran</FieldLabel>
                  <Select items={PILIHAN_ROLE} value={form.role} onValueChange={(v) => v && setForm({ ...form, role: v as Role })}>
                    <SelectTrigger id="guru-role" className="w-full">
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      {Object.entries(PILIHAN_ROLE).map(([nilai, label]) => (
                        <SelectItem key={nilai} value={nilai}>
                          {label}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  {errors.role && <p className="text-sm text-destructive">{errors.role}</p>}
                </Field>
              )}
              {form.role === "GURU" && (
                <Field data-invalid={!!errors.foto}>
                  <FieldLabel htmlFor="guru-foto">Foto</FieldLabel>
                  <ImageField
                    id="guru-foto"
                    shape="portrait"
                    hint="Foto yang sama tampil di bagian Guru & Staf di website. Foto tegak paling cocok."
                    currentUrl={form.fotoUrl}
                    file={form.foto}
                    removed={form.hapusFoto}
                    onFileChange={(f) => setForm((s) => ({ ...s, foto: f }))}
                    onRemovedChange={(v) => setForm((s) => ({ ...s, hapusFoto: v }))}
                    error={errors.foto}
                  />
                </Field>
              )}
              {form.id && (
                <Field orientation="horizontal">
                  <Checkbox id="guru-aktif" checked={form.aktif} onCheckedChange={(v) => setForm({ ...form, aktif: v === true })} />
                  <FieldLabel htmlFor="guru-aktif">Akun aktif</FieldLabel>
                </Field>
              )}
            </FieldGroup>
            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => setBuka(false)}>
                Batal
              </Button>
              <Button type="submit" disabled={memproses}>
                {memproses ? "Menyimpan..." : "Simpan"}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </div>
  )
}
