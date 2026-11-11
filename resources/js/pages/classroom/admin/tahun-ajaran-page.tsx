import * as React from "react"
import { router } from "@inertiajs/react"
import { Pencil, Plus, Trash2 } from "lucide-react"

import { useAuth } from "@/context/classroom-auth"
import { useConfirm } from "@/hooks/use-confirm"
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

interface TahunAjaran {
  id: string
  nama: string
  semester: "GANJIL" | "GENAP"
  aktif: boolean
  jumlahKelas: number
}

interface FormState {
  id: string | null
  nama: string
  semester: string
  aktif: boolean
}

const KOSONG: FormState = { id: null, nama: "", semester: "GANJIL", aktif: false }

export function TahunAjaranPage({ tahunAjaran }: { tahunAjaran: TahunAjaran[] }) {
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

  function ubah(t: TahunAjaran) {
    setForm({ id: t.id, nama: t.nama, semester: t.semester, aktif: t.aktif })
    setErrors({})
    setBuka(true)
  }

  function simpan(event: React.FormEvent) {
    event.preventDefault()
    const payload = { nama: form.nama, semester: form.semester, aktif: form.aktif ? 1 : 0 }
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
    if (form.id) router.patch(`/classroom/tahun-ajaran/${form.id}`, payload, opsi)
    else router.post("/classroom/tahun-ajaran", payload, opsi)
  }

  async function hapus(t: TahunAjaran) {
    const ok = await confirm({
      title: `Hapus tahun ajaran ${t.nama} ${t.semester === "GANJIL" ? "Ganjil" : "Genap"}?`,
      confirmLabel: "Hapus",
      destructive: true,
    })
    if (ok) router.delete(`/classroom/tahun-ajaran/${t.id}`, { preserveScroll: true })
  }

  return (
    <div className="flex flex-col gap-4">
      {confirmDialog}
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-xl font-semibold">Tahun Ajaran</h1>
          <p className="text-sm text-muted-foreground">Ruang kelas baru selalu dibuat di tahun ajaran yang aktif</p>
        </div>
        {isAdmin && (
          <Button onClick={tambah}>
            <Plus /> Tambah Tahun Ajaran
          </Button>
        )}
      </div>

      <div className="overflow-x-auto rounded-lg border">
        <Table kartu>
          <TableHeader>
            <TableRow>
              <TableHead className="w-10">No</TableHead>
              <TableHead>Tahun ajaran</TableHead>
              <TableHead>Semester</TableHead>
              <TableHead>Status</TableHead>
              <TableHead className="text-right">Ruang kelas</TableHead>
              {isAdmin && <TableHead className="text-right">Aksi</TableHead>}
            </TableRow>
          </TableHeader>
          <TableBody>
            {tahunAjaran.length === 0 ? (
              <TableRow>
                <TableCell colSpan={6} className="text-center text-muted-foreground">
                  Belum ada tahun ajaran
                </TableCell>
              </TableRow>
            ) : (
              tahunAjaran.map((t, i) => (
                <TableRow key={t.id}>
                  <TableCell kartu="sembunyi" className="text-muted-foreground">{i + 1}</TableCell>
                  <TableCell kartu="judul" className="font-medium">{t.nama}</TableCell>
                  <TableCell>{t.semester === "GANJIL" ? "Ganjil" : "Genap"}</TableCell>
                  <TableCell>
                    <Badge variant={t.aktif ? "success" : "outline"}>{t.aktif ? "Aktif" : "Tidak aktif"}</Badge>
                  </TableCell>
                  <TableCell className="text-right">{t.jumlahKelas}</TableCell>
                  {isAdmin && (
                    <TableCell kartu="aksi" className="text-right">
                      <div className="flex justify-end gap-1">
                        <TombolAksi jenis="edit" onClick={() => ubah(t)} aria-label={`Edit ${t.nama}`}>
                          <Pencil />
                        </TombolAksi>
                        <TombolAksi jenis="hapus" onClick={() => hapus(t)} aria-label={`Hapus ${t.nama}`}>
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
        <DialogContent className="sm:max-w-sm">
          <form onSubmit={simpan} className="flex flex-col gap-4">
            <DialogHeader>
              <DialogTitle>{form.id ? "Ubah Tahun Ajaran" : "Tambah Tahun Ajaran"}</DialogTitle>
              <DialogDescription>Mengaktifkan satu tahun ajaran menonaktifkan yang lain.</DialogDescription>
            </DialogHeader>
            <FieldGroup>
              <Field data-invalid={!!errors.nama}>
                <FieldLabel htmlFor="ta-nama">Tahun ajaran</FieldLabel>
                <Input
                  id="ta-nama"
                  value={form.nama}
                  onChange={(e) => setForm({ ...form, nama: e.target.value })}
                  placeholder="2026/2027"
                  maxLength={9}
                  required
                  aria-invalid={!!errors.nama}
                />
                {errors.nama && <p className="text-sm text-destructive">{errors.nama}</p>}
              </Field>
              <Field data-invalid={!!errors.semester}>
                <FieldLabel htmlFor="ta-semester">Semester</FieldLabel>
                <Select
                  items={{ GANJIL: "Ganjil", GENAP: "Genap" }}
                  value={form.semester}
                  onValueChange={(v) => v && setForm({ ...form, semester: v })}
                >
                  <SelectTrigger id="ta-semester" className="w-full">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="GANJIL">Ganjil</SelectItem>
                    <SelectItem value="GENAP">Genap</SelectItem>
                  </SelectContent>
                </Select>
                {errors.semester && <p className="text-sm text-destructive">{errors.semester}</p>}
              </Field>
              <Field orientation="horizontal">
                <Checkbox id="ta-aktif" checked={form.aktif} onCheckedChange={(v) => setForm({ ...form, aktif: v === true })} />
                <FieldLabel htmlFor="ta-aktif">Jadikan tahun ajaran aktif</FieldLabel>
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
