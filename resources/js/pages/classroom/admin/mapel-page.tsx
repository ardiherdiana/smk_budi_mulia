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
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import { Field, FieldLabel } from "@/components/ui/field"
import { Input } from "@/components/ui/input"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"

interface Mapel {
  id: string
  nama: string
  jumlahKelas: number
}

export function MapelPage({ mapel }: { mapel: Mapel[] }) {
  const { isAdmin } = useAuth()
  const { confirm, confirmDialog } = useConfirm()
  const [buka, setBuka] = React.useState(false)
  const [id, setId] = React.useState<string | null>(null)
  const [nama, setNama] = React.useState("")
  const [error, setError] = React.useState<string | undefined>()
  const [memproses, setMemproses] = React.useState(false)

  function tambah() {
    setId(null)
    setNama("")
    setError(undefined)
    setBuka(true)
  }

  function ubah(m: Mapel) {
    setId(m.id)
    setNama(m.nama)
    setError(undefined)
    setBuka(true)
  }

  function simpan(event: React.FormEvent) {
    event.preventDefault()
    const opsi = {
      preserveScroll: true,
      onStart: () => {
        setMemproses(true)
        setError(undefined)
      },
      onSuccess: () => setBuka(false),
      onError: (e: Record<string, string>) => setError(e.nama),
      onFinish: () => setMemproses(false),
    }
    if (id) router.patch(`/classroom/mapel/${id}`, { nama }, opsi)
    else router.post("/classroom/mapel", { nama }, opsi)
  }

  async function hapus(m: Mapel) {
    const ok = await confirm({ title: `Hapus mata pelajaran "${m.nama}"?`, confirmLabel: "Hapus", destructive: true })
    if (ok) router.delete(`/classroom/mapel/${m.id}`, { preserveScroll: true })
  }

  return (
    <div className="flex flex-col gap-4">
      {confirmDialog}
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-xl font-semibold">Mata Pelajaran</h1>
          <p className="text-sm text-muted-foreground">Daftar mata pelajaran untuk ruang kelas</p>
        </div>
        {isAdmin && (
          <Button onClick={tambah}>
            <Plus /> Tambah Mapel
          </Button>
        )}
      </div>

      <div className="overflow-x-auto rounded-lg border">
        <Table kartu>
          <TableHeader>
            <TableRow>
              <TableHead className="w-10">No</TableHead>
              <TableHead>Mata pelajaran</TableHead>
              <TableHead className="text-right">Ruang kelas</TableHead>
              {isAdmin && <TableHead className="text-right">Aksi</TableHead>}
            </TableRow>
          </TableHeader>
          <TableBody>
            {mapel.length === 0 ? (
              <TableRow>
                <TableCell colSpan={4} className="text-center text-muted-foreground">
                  Belum ada mata pelajaran
                </TableCell>
              </TableRow>
            ) : (
              mapel.map((m, i) => (
                <TableRow key={m.id}>
                  <TableCell kartu="sembunyi" className="text-muted-foreground">{i + 1}</TableCell>
                  <TableCell kartu="judul" className="font-medium">{m.nama}</TableCell>
                  <TableCell className="text-right">{m.jumlahKelas}</TableCell>
                  {isAdmin && (
                    <TableCell kartu="aksi" className="text-right">
                      <div className="flex justify-end gap-1">
                        <TombolAksi jenis="edit" onClick={() => ubah(m)} aria-label={`Edit ${m.nama}`}>
                          <Pencil />
                        </TombolAksi>
                        <TombolAksi jenis="hapus" onClick={() => hapus(m)} aria-label={`Hapus ${m.nama}`}>
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
              <DialogTitle>{id ? "Ubah Mata Pelajaran" : "Tambah Mata Pelajaran"}</DialogTitle>
            </DialogHeader>
            <Field data-invalid={!!error}>
              <FieldLabel htmlFor="mapel-nama">Nama</FieldLabel>
              <Input
                id="mapel-nama"
                value={nama}
                onChange={(e) => setNama(e.target.value)}
                maxLength={100}
                required
                aria-invalid={!!error}
              />
              {error && <p className="text-sm text-destructive">{error}</p>}
            </Field>
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
