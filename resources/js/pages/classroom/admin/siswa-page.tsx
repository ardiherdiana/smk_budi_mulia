import * as React from "react"
import { router } from "@inertiajs/react"
import { Pencil, Plus, Trash2 } from "lucide-react"

import type { Paginasi } from "@/lib/classroom/types"
import { useAuth } from "@/context/classroom-auth"
import { useConfirm } from "@/hooks/use-confirm"
import { DataPagination } from "@/components/data-pagination"
import { Badge } from "@/components/ui/badge"
import { TombolAksi } from "@/components/tombol-aksi"
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

interface Siswa {
  id: string
  nama: string
  nis: string | null
  username: string
  aktif: boolean
  rombelId: string
  rombel: string
}

interface Props {
  siswa: Paginasi<Siswa>
  rombel: { id: string; nama: string }[]
  filter: { q: string; rombelId: string | null }
}

interface FormState {
  id: string | null
  nama: string
  username: string
  password: string
  nis: string
  rombelId: string
  aktif: boolean
}

const KOSONG: FormState = { id: null, nama: "", username: "", password: "", nis: "", rombelId: "", aktif: true }
const SEMUA = "__semua"

export function SiswaPage({ siswa, rombel, filter }: Props) {
  const { isAdmin } = useAuth()
  const { confirm, confirmDialog } = useConfirm()
  const [q, setQ] = React.useState(filter.q)
  const [buka, setBuka] = React.useState(false)
  const [form, setForm] = React.useState<FormState>(KOSONG)
  const [errors, setErrors] = React.useState<Record<string, string>>({})
  const [memproses, setMemproses] = React.useState(false)

  function muat(params: { q?: string; rombelId?: string | null; page?: number; per_page?: number }) {
    router.get(
      "/classroom/siswa",
      {
        q: (params.q ?? q) || undefined,
        rombelId: (params.rombelId === undefined ? filter.rombelId : params.rombelId) || undefined,
        page: params.page,
        per_page: params.per_page ?? siswa.per_page,
      },
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
    setForm({ ...KOSONG, rombelId: filter.rombelId ?? "" })
    setErrors({})
    setBuka(true)
  }

  function ubah(s: Siswa) {
    setForm({ id: s.id, nama: s.nama, username: s.username, password: "", nis: s.nis ?? "", rombelId: s.rombelId, aktif: s.aktif })
    setErrors({})
    setBuka(true)
  }

  function simpan(event: React.FormEvent) {
    event.preventDefault()
    const payload = {
      nama: form.nama,
      username: form.username,
      password: form.password || undefined,
      nis: form.nis || null,
      rombelId: form.rombelId,
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
    if (form.id) router.patch(`/classroom/siswa/${form.id}`, payload, opsi)
    else router.post("/classroom/siswa", payload, opsi)
  }

  async function hapus(s: Siswa) {
    const ok = await confirm({
      title: `Hapus siswa "${s.nama}"?`,
      description: "Akun, pengumpulan tugas, dan hasil ujian siswa ini ikut terhapus dan tidak bisa dikembalikan.",
      confirmLabel: "Hapus",
      destructive: true,
    })
    if (ok) router.delete(`/classroom/siswa/${s.id}`, { preserveScroll: true })
  }

  const rombelItems = Object.fromEntries(rombel.map((r) => [r.id, r.nama]))

  return (
    <div className="flex flex-col gap-4">
      {confirmDialog}
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-xl font-semibold">Siswa</h1>
          <p className="text-sm text-muted-foreground">
            {isAdmin ? "Kelola akun siswa dan rombelnya" : "Daftar akun siswa dan rombelnya"}
          </p>
        </div>
        {isAdmin && (
          <Button onClick={tambah}>
            <Plus /> Tambah Siswa
          </Button>
        )}
      </div>

      <div className="flex flex-wrap items-center gap-3">
        <Input placeholder="Cari nama, NIS, atau username..." value={q} onChange={(e) => setQ(e.target.value)} className="max-w-sm" />
        <Select
          items={{ [SEMUA]: "Semua rombel", ...rombelItems }}
          value={filter.rombelId ?? SEMUA}
          onValueChange={(v) => muat({ rombelId: v === SEMUA ? null : v, page: 1 })}
        >
          <SelectTrigger className="w-44" aria-label="Filter rombel">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value={SEMUA}>Semua rombel</SelectItem>
            {rombel.map((r) => (
              <SelectItem key={r.id} value={r.id}>
                {r.nama}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>

      <div className="overflow-x-auto rounded-lg border">
        <Table kartu>
          <TableHeader>
            <TableRow>
              <TableHead className="w-10">No</TableHead>
              <TableHead>Nama</TableHead>
              <TableHead>NIS</TableHead>
              <TableHead>Username</TableHead>
              <TableHead>Rombel</TableHead>
              <TableHead>Status</TableHead>
              {isAdmin && <TableHead className="text-right">Aksi</TableHead>}
            </TableRow>
          </TableHeader>
          <TableBody>
            {siswa.data.length === 0 ? (
              <TableRow>
                <TableCell colSpan={7} className="text-center text-muted-foreground">
                  Tidak ada siswa yang cocok
                </TableCell>
              </TableRow>
            ) : (
              siswa.data.map((s, i) => (
                <TableRow key={s.id}>
                  <TableCell kartu="sembunyi" className="text-muted-foreground">{(siswa.current_page - 1) * siswa.per_page + i + 1}</TableCell>
                  <TableCell kartu="judul" className="font-medium">{s.nama}</TableCell>
                  <TableCell className="font-mono text-xs">{s.nis ?? "-"}</TableCell>
                  <TableCell className="font-mono text-xs">{s.username}</TableCell>
                  <TableCell>{s.rombel}</TableCell>
                  <TableCell>
                    <Badge variant={s.aktif ? "success" : "destructive"}>{s.aktif ? "Aktif" : "Nonaktif"}</Badge>
                  </TableCell>
                  {isAdmin && (
                    <TableCell kartu="aksi" className="text-right">
                      <div className="flex justify-end gap-1">
                        <TombolAksi jenis="edit" onClick={() => ubah(s)} aria-label={`Edit ${s.nama}`}>
                          <Pencil />
                        </TombolAksi>
                        <TombolAksi jenis="hapus" onClick={() => hapus(s)} aria-label={`Hapus ${s.nama}`}>
                          <Trash2 />
                        </TombolAksi>
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
        page={siswa.current_page}
        pageSize={siswa.per_page}
        total={siswa.total}
        onPageChange={(page) => muat({ page })}
        onPageSizeChange={(size) => muat({ page: 1, per_page: size })}
      />

      <Dialog open={buka} onOpenChange={setBuka}>
        <DialogContent className="max-h-[90svh] overflow-y-auto sm:max-w-md">
          <form onSubmit={simpan} className="flex flex-col gap-4">
            <DialogHeader>
              <DialogTitle>{form.id ? "Ubah Siswa" : "Tambah Siswa"}</DialogTitle>
              <DialogDescription>Siswa otomatis masuk ke semua ruang kelas di rombelnya.</DialogDescription>
            </DialogHeader>
            <FieldGroup>
              <Field data-invalid={!!errors.nama}>
                <FieldLabel htmlFor="siswa-nama">Nama lengkap</FieldLabel>
                <Input
                  id="siswa-nama"
                  value={form.nama}
                  onChange={(e) => setForm({ ...form, nama: e.target.value })}
                  maxLength={255}
                  required
                  aria-invalid={!!errors.nama}
                />
                {errors.nama && <p className="text-sm text-destructive">{errors.nama}</p>}
              </Field>
              <div className="grid gap-4 sm:grid-cols-2">
                <Field data-invalid={!!errors.username}>
                  <FieldLabel htmlFor="siswa-username">Username</FieldLabel>
                  <Input
                    id="siswa-username"
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
                <Field data-invalid={!!errors.nis}>
                  <FieldLabel htmlFor="siswa-nis">NIS (opsional)</FieldLabel>
                  <Input
                    id="siswa-nis"
                    value={form.nis}
                    onChange={(e) => setForm({ ...form, nis: e.target.value })}
                    maxLength={20}
                    aria-invalid={!!errors.nis}
                  />
                  {errors.nis && <p className="text-sm text-destructive">{errors.nis}</p>}
                </Field>
              </div>
              <Field data-invalid={!!errors.password}>
                <FieldLabel htmlFor="siswa-password">{form.id ? "Password baru (opsional)" : "Password"}</FieldLabel>
                <Input
                  id="siswa-password"
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
              <Field data-invalid={!!errors.rombelId}>
                <FieldLabel htmlFor="siswa-rombel">Rombel</FieldLabel>
                <Select
                  items={rombelItems}
                  value={form.rombelId || null}
                  onValueChange={(v) => v && setForm({ ...form, rombelId: v })}
                >
                  <SelectTrigger id="siswa-rombel" className="w-full">
                    <SelectValue placeholder="Pilih rombel" />
                  </SelectTrigger>
                  <SelectContent>
                    {rombel.map((r) => (
                      <SelectItem key={r.id} value={r.id}>
                        {r.nama}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                {errors.rombelId && <p className="text-sm text-destructive">{errors.rombelId}</p>}
              </Field>
              {form.id && (
                <Field orientation="horizontal">
                  <Checkbox id="siswa-aktif" checked={form.aktif} onCheckedChange={(v) => setForm({ ...form, aktif: v === true })} />
                  <FieldLabel htmlFor="siswa-aktif">Akun aktif</FieldLabel>
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
