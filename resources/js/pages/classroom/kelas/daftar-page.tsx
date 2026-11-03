import * as React from "react"
import { Link, router } from "@inertiajs/react"
import { BookOpen, Plus, Users } from "lucide-react"
import { toast } from "sonner"

import type { KelasCard } from "@/lib/classroom/types"
import { useAuth } from "@/context/classroom-auth"
import { warnaKelas } from "@/lib/classroom/kelas-warna"
import { cn } from "@/lib/classroom/utils"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Card, CardContent } from "@/components/ui/card"
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

interface Opsi {
  rombel: { id: string; nama: string }[]
  mapel: { id: string; nama: string }[]
  guru: { id: string; nama: string }[] | null
  tahunAjaran: string | null
}

interface Props {
  kelas: KelasCard[]
  arsip: boolean
  bisaMembuat: boolean
  opsi: Opsi | null
}

const FORM_KOSONG = { rombelId: "", mapelId: "", mapelBaru: "", guruId: "", deskripsi: "" }

function petaItem(daftar: { id: string; nama: string }[]) {
  return Object.fromEntries(daftar.map((d) => [d.id, d.nama]))
}

export function DaftarKelasPage({ kelas, arsip, bisaMembuat, opsi }: Props) {
  const { isSiswa } = useAuth()
  const [buka, setBuka] = React.useState(false)
  const [form, setForm] = React.useState(FORM_KOSONG)
  const [errors, setErrors] = React.useState<Record<string, string>>({})
  const [memproses, setMemproses] = React.useState(false)

  function kirim(event: React.FormEvent) {
    event.preventDefault()
    router.post("/classroom/kelas", { ...form, mapelBaru: form.mapelId ? "" : form.mapelBaru }, {
      onStart: () => {
        setMemproses(true)
        setErrors({})
      },
      onSuccess: () => {
        setBuka(false)
        setForm(FORM_KOSONG)
      },
      onError: (e) => {
        setErrors(e)
        if (e.message) return
        toast.error("Periksa isian yang berwarna merah")
      },
      onFinish: () => setMemproses(false),
    })
  }

  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-xl font-semibold">{arsip ? "Ruang Kelas Diarsipkan" : "Ruang Kelas"}</h1>
          <p className="text-sm text-muted-foreground">
            {arsip
              ? "Ruang kelas yang sudah selesai dan disembunyikan dari daftar utama"
              : opsi?.tahunAjaran
                ? `Tahun ajaran ${opsi.tahunAjaran}`
                : isSiswa
                  ? "Mata pelajaran yang kamu ikuti"
                  : "Mata pelajaran yang diajar"}
          </p>
        </div>
        {bisaMembuat && !arsip && (
          <Button
            onClick={() => {
              setErrors({})
              setBuka(true)
            }}
          >
            <Plus /> Buat Ruang Kelas
          </Button>
        )}
      </div>

      {kelas.length === 0 ? (
        <Card>
          <CardContent className="flex flex-col items-center gap-2 py-12 text-center">
            <BookOpen className="size-10 text-muted-foreground" />
            <p className="font-medium">{arsip ? "Belum ada ruang kelas yang diarsipkan" : "Belum ada ruang kelas"}</p>
            <p className="max-w-sm text-sm text-muted-foreground">
              {isSiswa
                ? "Ruang kelas muncul otomatis saat guru membuatnya untuk rombelmu."
                : bisaMembuat
                  ? "Buat ruang kelas pertamamu untuk mulai membagikan materi, tugas, dan ujian."
                  : "Belum ada data untuk ditampilkan."}
            </p>
          </CardContent>
        </Card>
      ) : (
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
          {kelas.map((k) => (
            <Link
              key={k.id}
              href={`/classroom/kelas/${k.id}`}
              className="group overflow-hidden rounded-xl bg-card ring-1 ring-foreground/10 transition-shadow hover:shadow-md focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
            >
              <div className={cn("flex h-24 flex-col justify-between p-4", warnaKelas(k.id))}>
                <h2 className="line-clamp-2 text-lg leading-tight font-semibold">{k.mapel}</h2>
                <p className="text-sm opacity-90">{k.rombel}</p>
              </div>
              <div className="flex items-center justify-between gap-2 p-4 text-sm">
                <div className="flex min-w-0 flex-col gap-1">
                  <span className="truncate text-muted-foreground">{k.guru}</span>
                  <span className="flex items-center gap-1.5 text-xs text-muted-foreground">
                    <Users className="size-3.5" /> {k.jumlahSiswa} siswa
                  </span>
                </div>
                {k.penanda > 0 && (
                  <Badge variant={isSiswa ? "warning" : "secondary"} className="shrink-0">
                    {isSiswa ? `${k.penanda} belum dikumpulkan` : `${k.penanda} perlu dinilai`}
                  </Badge>
                )}
              </div>
            </Link>
          ))}
        </div>
      )}

      {opsi && (
        <Dialog open={buka} onOpenChange={setBuka}>
          <DialogContent className="sm:max-w-md">
            <form onSubmit={kirim} className="flex flex-col gap-4">
              <DialogHeader>
                <DialogTitle>Buat Ruang Kelas</DialogTitle>
                <DialogDescription>
                  Siswa di rombel yang dipilih otomatis masuk ke ruang kelas ini.
                  {opsi.tahunAjaran ? ` Tahun ajaran ${opsi.tahunAjaran}.` : ""}
                </DialogDescription>
              </DialogHeader>

              <FieldGroup>
                <Field data-invalid={!!errors.rombelId}>
                  <FieldLabel htmlFor="kelas-rombel">Rombel</FieldLabel>
                  <Select
                    items={petaItem(opsi.rombel)}
                    value={form.rombelId || null}
                    onValueChange={(v) => v && setForm({ ...form, rombelId: v })}
                  >
                    <SelectTrigger id="kelas-rombel" className="w-full">
                      <SelectValue placeholder="Pilih rombel" />
                    </SelectTrigger>
                    <SelectContent>
                      {opsi.rombel.map((r) => (
                        <SelectItem key={r.id} value={r.id}>
                          {r.nama}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  {errors.rombelId && <p className="text-sm text-destructive">{errors.rombelId}</p>}
                </Field>

                <Field>
                  <FieldLabel htmlFor="kelas-mapel">Mata pelajaran</FieldLabel>
                  <Select
                    items={petaItem(opsi.mapel)}
                    value={form.mapelId || null}
                    onValueChange={(v) => setForm({ ...form, mapelId: v ?? "", mapelBaru: "" })}
                  >
                    <SelectTrigger id="kelas-mapel" className="w-full">
                      <SelectValue placeholder="Pilih dari daftar" />
                    </SelectTrigger>
                    <SelectContent>
                      {opsi.mapel.map((m) => (
                        <SelectItem key={m.id} value={m.id}>
                          {m.nama}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <Input
                    value={form.mapelBaru}
                    onChange={(e) => setForm({ ...form, mapelBaru: e.target.value, mapelId: "" })}
                    placeholder="...atau ketik nama mapel baru"
                    maxLength={100}
                  />
                  <FieldDescription>Mapel baru otomatis masuk ke daftar mata pelajaran.</FieldDescription>
                </Field>

                {opsi.guru && (
                  <Field data-invalid={!!errors.guruId}>
                    <FieldLabel htmlFor="kelas-guru">Guru pengajar</FieldLabel>
                    <Select
                      items={petaItem(opsi.guru)}
                      value={form.guruId || null}
                      onValueChange={(v) => v && setForm({ ...form, guruId: v })}
                    >
                      <SelectTrigger id="kelas-guru" className="w-full">
                        <SelectValue placeholder="Pilih guru" />
                      </SelectTrigger>
                      <SelectContent>
                        {opsi.guru.map((g) => (
                          <SelectItem key={g.id} value={g.id}>
                            {g.nama}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </Field>
                )}

                <Field>
                  <FieldLabel htmlFor="kelas-deskripsi">Deskripsi (opsional)</FieldLabel>
                  <Input
                    id="kelas-deskripsi"
                    value={form.deskripsi}
                    onChange={(e) => setForm({ ...form, deskripsi: e.target.value })}
                    maxLength={500}
                  />
                </Field>
              </FieldGroup>

              <DialogFooter>
                <Button type="button" variant="outline" onClick={() => setBuka(false)}>
                  Batal
                </Button>
                <Button type="submit" disabled={memproses}>
                  {memproses ? "Memproses..." : "Buat"}
                </Button>
              </DialogFooter>
            </form>
          </DialogContent>
        </Dialog>
      )}
    </div>
  )
}
