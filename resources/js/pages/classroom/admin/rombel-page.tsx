import * as React from "react"
import { router } from "@inertiajs/react"
import { Pencil, Plus, Trash2 } from "lucide-react"

import { useAuth } from "@/context/classroom-auth"
import { useConfirm } from "@/hooks/use-confirm"
import { TombolAksi } from "@/components/tombol-aksi"
import { Button } from "@/components/ui/button"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import { Field, FieldGroup, FieldLabel } from "@/components/ui/field"
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

interface Rombel {
  id: string
  nama: string
  tingkat: "X" | "XI" | "XII"
  jurusan: string
  waliKelasId: string | null
  waliKelas: string | null
  jumlahSiswa: number
  jumlahKelas: number
}

interface Props {
  rombel: Rombel[]
  guru: { id: string; nama: string }[]
}

interface FormState {
  id: string | null
  nama: string
  tingkat: string
  jurusan: string
  waliKelasId: string
}

const KOSONG: FormState = { id: null, nama: "", tingkat: "X", jurusan: "", waliKelasId: "" }
const TANPA_WALI = "__tanpa"
const JURUSAN_UMUM = ["AKL", "RPL", "TKJ"]

export function RombelPage({ rombel, guru }: Props) {
  const { isAdmin } = useAuth()
  const { confirm, confirmDialog } = useConfirm()
  const [buka, setBuka] = React.useState(false)
  const [form, setForm] = React.useState<FormState>(KOSONG)
  const [errors, setErrors] = React.useState<Record<string, string>>({})
  const [memproses, setMemproses] = React.useState(false)

  function tambah() {
    setForm(KOSONG)
    setErrors({})
    setBuka(true)
  }

  function ubah(r: Rombel) {
    setForm({ id: r.id, nama: r.nama, tingkat: r.tingkat, jurusan: r.jurusan, waliKelasId: r.waliKelasId ?? "" })
    setErrors({})
    setBuka(true)
  }

  function simpan(event: React.FormEvent) {
    event.preventDefault()
    const payload = { nama: form.nama, tingkat: form.tingkat, jurusan: form.jurusan, waliKelasId: form.waliKelasId || null }
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
    if (form.id) router.patch(`/classroom/rombel/${form.id}`, payload, opsi)
    else router.post("/classroom/rombel", payload, opsi)
  }

  async function hapus(r: Rombel) {
    const ok = await confirm({
      title: `Hapus rombel "${r.nama}"?`,
      description: r.jumlahKelas > 0 ? `${r.jumlahKelas} ruang kelas di rombel ini ikut terhapus.` : undefined,
      confirmLabel: "Hapus",
      destructive: true,
    })
    if (ok) router.delete(`/classroom/rombel/${r.id}`, { preserveScroll: true })
  }

  // Jurusan umum ditambah jurusan yang sudah terpakai, supaya rombel lama dengan jurusan lain tetap bisa diubah.
  const daftarJurusan = [...new Set([...JURUSAN_UMUM, ...rombel.map((r) => r.jurusan)])].sort()
  const guruItems = { [TANPA_WALI]: "Tanpa wali kelas", ...Object.fromEntries(guru.map((g) => [g.id, g.nama])) }

  return (
    <div className="flex flex-col gap-4">
      {confirmDialog}
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-xl font-semibold">Rombel</h1>
          <p className="text-sm text-muted-foreground">
            {isAdmin ? "Kelola rombongan belajar dan wali kelasnya" : "Daftar rombongan belajar dan wali kelasnya"}
          </p>
        </div>
        {isAdmin && (
          <Button onClick={tambah}>
            <Plus /> Tambah Rombel
          </Button>
        )}
      </div>

      <div className="overflow-x-auto rounded-lg border">
        <Table kartu>
          <TableHeader>
            <TableRow>
              <TableHead className="w-10">No</TableHead>
              <TableHead>Rombel</TableHead>
              <TableHead>Jurusan</TableHead>
              <TableHead>Wali kelas</TableHead>
              <TableHead className="text-right">Siswa</TableHead>
              <TableHead className="text-right">Ruang kelas</TableHead>
              {isAdmin && <TableHead className="text-right">Aksi</TableHead>}
            </TableRow>
          </TableHeader>
          <TableBody>
            {rombel.length === 0 ? (
              <TableRow>
                <TableCell colSpan={7} className="text-center text-muted-foreground">
                  Belum ada rombel
                </TableCell>
              </TableRow>
            ) : (
              rombel.map((r, i) => (
                <TableRow key={r.id}>
                  <TableCell kartu="sembunyi" className="text-muted-foreground">{i + 1}</TableCell>
                  <TableCell kartu="judul" className="font-medium">{r.nama}</TableCell>
                  <TableCell>{r.jurusan}</TableCell>
                  <TableCell>{r.waliKelas ?? <span className="text-muted-foreground">-</span>}</TableCell>
                  <TableCell className="text-right">{r.jumlahSiswa}</TableCell>
                  <TableCell className="text-right">{r.jumlahKelas}</TableCell>
                  {isAdmin && (
                    <TableCell kartu="aksi" className="text-right">
                      <div className="flex justify-end gap-1">
                        <TombolAksi jenis="edit" onClick={() => ubah(r)} aria-label={`Edit ${r.nama}`}>
                          <Pencil />
                        </TombolAksi>
                        <TombolAksi jenis="hapus" onClick={() => hapus(r)} aria-label={`Hapus ${r.nama}`}>
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

      <Dialog open={buka} onOpenChange={setBuka}>
        <DialogContent className="sm:max-w-md">
          <form onSubmit={simpan} className="flex flex-col gap-4">
            <DialogHeader>
              <DialogTitle>{form.id ? "Ubah Rombel" : "Tambah Rombel"}</DialogTitle>
              <DialogDescription>Contoh nama: XI TKJ 1, X AKL.</DialogDescription>
            </DialogHeader>
            <FieldGroup>
              <Field data-invalid={!!errors.nama}>
                <FieldLabel htmlFor="rombel-nama">Nama rombel</FieldLabel>
                <Input
                  id="rombel-nama"
                  value={form.nama}
                  onChange={(e) => setForm({ ...form, nama: e.target.value })}
                  maxLength={50}
                  required
                  aria-invalid={!!errors.nama}
                />
                {errors.nama && <p className="text-sm text-destructive">{errors.nama}</p>}
              </Field>
              <div className="grid gap-4 sm:grid-cols-2">
                <Field>
                  <FieldLabel htmlFor="rombel-tingkat">Tingkat</FieldLabel>
                  <Select
                    items={{ X: "X", XI: "XI", XII: "XII" }}
                    value={form.tingkat}
                    onValueChange={(v) => v && setForm({ ...form, tingkat: v })}
                  >
                    <SelectTrigger id="rombel-tingkat" className="w-full">
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      {["X", "XI", "XII"].map((t) => (
                        <SelectItem key={t} value={t}>
                          {t}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </Field>
                <Field data-invalid={!!errors.jurusan}>
                  <FieldLabel htmlFor="rombel-jurusan">Jurusan</FieldLabel>
                  <Select
                    items={Object.fromEntries(daftarJurusan.map((j) => [j, j]))}
                    value={form.jurusan || null}
                    onValueChange={(v) => v && setForm({ ...form, jurusan: v })}
                  >
                    <SelectTrigger id="rombel-jurusan" className="w-full" aria-invalid={!!errors.jurusan}>
                      <SelectValue placeholder="Pilih jurusan" />
                    </SelectTrigger>
                    <SelectContent>
                      {daftarJurusan.map((j) => (
                        <SelectItem key={j} value={j}>
                          {j}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  {errors.jurusan && <p className="text-sm text-destructive">{errors.jurusan}</p>}
                </Field>
              </div>
              <Field>
                <FieldLabel htmlFor="rombel-wali">Wali kelas</FieldLabel>
                <Select
                  items={guruItems}
                  value={form.waliKelasId || TANPA_WALI}
                  onValueChange={(v) => setForm({ ...form, waliKelasId: v === TANPA_WALI || !v ? "" : v })}
                >
                  <SelectTrigger id="rombel-wali" className="w-full">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value={TANPA_WALI}>Tanpa wali kelas</SelectItem>
                    {guru.map((g) => (
                      <SelectItem key={g.id} value={g.id}>
                        {g.nama}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </Field>
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
