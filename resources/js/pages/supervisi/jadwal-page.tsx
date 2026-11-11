import * as React from "react"
import { Head, router, useForm } from "@inertiajs/react"
import { toast } from "sonner"
import { Ban, Pencil, Plus, Search, Trash2 } from "lucide-react"

import { DatePicker } from "@/components/absensi/date-picker"
import { DaftarSupervisi } from "@/components/supervisi/daftar-supervisi"
import { PilihOrang, type OpsiOrang } from "@/components/supervisi/pilih-orang"
import { TahunAjaranSelect } from "@/components/supervisi/tahun-ajaran-select"
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
import { Field, FieldError, FieldGroup, FieldLabel } from "@/components/ui/field"
import { Input } from "@/components/ui/input"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { useConfirm } from "@/hooks/use-confirm"
import { todayIso } from "@/lib/absensi/attendance-format"
import { SELECT_SENTUH, SENTUH, STATUS_LABEL, formatHariPanjang, pesanGalat } from "@/lib/supervisi/format"
import type { StatusSupervisi, SupervisiRingkas, TahunAjaranOpsi } from "@/lib/supervisi/types"
import { cn } from "@/lib/utils"

interface OpsiNama {
  id: string
  nama: string
}

interface SaranKelas {
  mapelId: string
  rombelId: string
  label: string
}

interface Props {
  list: SupervisiRingkas[]
  tahunAjaran: TahunAjaranOpsi[]
  filter: { tahunAjaranId: string | null; status: StatusSupervisi | null; cari: string }
  bukaUntukGuru: number | null
  opsi: {
    guru: OpsiOrang[]
    supervisor: OpsiOrang[]
    mapel: OpsiNama[]
    rombel: OpsiNama[]
    saranKelas: Record<string, SaranKelas[]>
  }
}

interface FormJadwal {
  tahunAjaranId: string
  guruId: number | null
  supervisorId: number | null
  mapelId: string | null
  rombelId: string | null
  tanggal: string
}

const KOSONG = "__kosong__"

function PilihNama({
  id,
  list,
  value,
  onChange,
  placeholder,
}: {
  id: string
  list: OpsiNama[]
  value: string | null
  onChange: (v: string | null) => void
  placeholder: string
}) {
  const items = { [KOSONG]: placeholder, ...Object.fromEntries(list.map((o) => [o.id, o.nama])) }
  return (
    <Select items={items} value={value ?? KOSONG} onValueChange={(v) => onChange(!v || v === KOSONG ? null : (v as string))}>
      <SelectTrigger id={id} className={cn(SELECT_SENTUH, "w-full")}>
        <SelectValue />
      </SelectTrigger>
      <SelectContent>
        <SelectItem value={KOSONG}>{placeholder}</SelectItem>
        {list.map((o) => (
          <SelectItem key={o.id} value={o.id}>
            {o.nama}
          </SelectItem>
        ))}
      </SelectContent>
    </Select>
  )
}

export function JadwalPage({ list, tahunAjaran, filter, bukaUntukGuru, opsi }: Props) {
  const { confirm, confirmDialog } = useConfirm()
  const [buka, setBuka] = React.useState(false)
  const [sedangDiubah, setSedangDiubah] = React.useState<SupervisiRingkas | null>(null)
  const [cari, setCari] = React.useState(filter.cari)
  const tahunAktif = filter.tahunAjaranId ?? tahunAjaran.find((t) => t.aktif)?.id ?? tahunAjaran[0]?.id ?? ""

  const form = useForm<FormJadwal>({
    tahunAjaranId: tahunAktif,
    guruId: null,
    supervisorId: null,
    mapelId: null,
    rombelId: null,
    tanggal: todayIso(),
  })
  const { data, setData, processing, errors, clearErrors } = form

  function bukaBaru(guruId: number | null = null) {
    setSedangDiubah(null)
    clearErrors()
    form.setData({
      tahunAjaranId: tahunAktif,
      guruId,
      supervisorId: null,
      mapelId: null,
      rombelId: null,
      tanggal: todayIso(),
    })
    setBuka(true)
  }

  // Dari tombol "Jadwalkan" di dashboard: buka formulir sekali dengan guru itu terpilih.
  const sudahBukaOtomatis = React.useRef(false)
  React.useEffect(() => {
    if (bukaUntukGuru && !sudahBukaOtomatis.current) {
      sudahBukaOtomatis.current = true
      bukaBaru(bukaUntukGuru)
      // Buang `?guru=` dari alamat supaya muat ulang tidak membuka formulir lagi.
      const url = new URL(window.location.href)
      url.searchParams.delete("guru")
      window.history.replaceState(window.history.state, "", url)
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [bukaUntukGuru])

  function bukaUbah(s: SupervisiRingkas) {
    setSedangDiubah(s)
    clearErrors()
    form.setData({
      tahunAjaranId: s.tahunAjaranId,
      guruId: s.guru?.id ?? null,
      supervisorId: s.supervisor?.id ?? null,
      mapelId: s.mapelId,
      rombelId: s.rombelId,
      tanggal: s.tanggal,
    })
    setBuka(true)
  }

  function simpan(event: React.FormEvent) {
    event.preventDefault()
    const opsiKirim = {
      preserveScroll: true,
      onSuccess: () => setBuka(false),
      onError: (e: Record<string, string>) => e.message && toast.error(e.message),
    }
    if (sedangDiubah) form.patch(`/supervisi/jadwal/${sedangDiubah.id}`, opsiKirim)
    else form.post("/supervisi/jadwal", opsiKirim)
  }

  function saring(ubah: Partial<{ tahunAjaranId: string; status: string | null; cari: string }>) {
    const query = { tahunAjaranId: filter.tahunAjaranId, status: filter.status, cari: filter.cari, ...ubah }
    router.get(
      "/supervisi/jadwal",
      Object.fromEntries(Object.entries(query).filter(([, v]) => v)),
      { preserveScroll: true, preserveState: true }
    )
  }

  async function batalkan(s: SupervisiRingkas) {
    const ok = await confirm({
      title: `Batalkan supervisi ${s.guru?.nama ?? ""}?`,
      description: `Jadwal ${formatHariPanjang(s.tanggal)} ditandai dibatalkan dan guru serta supervisor diberi tahu.`,
      confirmLabel: "Batalkan jadwal",
      destructive: true,
    })
    if (!ok) return
    router.post(`/supervisi/jadwal/${s.id}/batal`, {}, { preserveScroll: true, onError: (e) => toast.error(pesanGalat(e)) })
  }

  async function hapus(s: SupervisiRingkas) {
    const ok = await confirm({
      title: `Hapus jadwal ${s.guru?.nama ?? ""}?`,
      description: "Jadwal dan isian lembarnya dihapus permanen.",
      confirmLabel: "Hapus",
      destructive: true,
    })
    if (!ok) return
    router.delete(`/supervisi/jadwal/${s.id}`, { preserveScroll: true, onError: (e) => toast.error(pesanGalat(e)) })
  }

  const saran = data.guruId ? (opsi.saranKelas[String(data.guruId)] ?? []) : []

  return (
    <div className="flex flex-col gap-4">
      <Head title="Jadwal Supervisi" />
      {confirmDialog}

      <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <h1 className="text-xl font-semibold">Jadwal Supervisi</h1>
          <p className="text-sm text-muted-foreground">
            Tentukan guru, supervisor, dan waktu supervisi. Guru dan supervisor mendapat notifikasi.
          </p>
        </div>
        <Button className={SENTUH} onClick={() => bukaBaru()} disabled={tahunAjaran.length === 0}>
          <Plus /> Buat jadwal
        </Button>
      </div>

      <div className="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
        <TahunAjaranSelect list={tahunAjaran} value={filter.tahunAjaranId} onChange={(id) => saring({ tahunAjaranId: id })} />
        <Select
          items={{ [KOSONG]: "Semua status", ...STATUS_LABEL }}
          value={filter.status ?? KOSONG}
          onValueChange={(v) => saring({ status: !v || v === KOSONG ? null : (v as string) })}
        >
          <SelectTrigger className={cn(SELECT_SENTUH, "w-full sm:w-44")} aria-label="Status">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value={KOSONG}>Semua status</SelectItem>
            {(Object.keys(STATUS_LABEL) as StatusSupervisi[]).map((s) => (
              <SelectItem key={s} value={s}>
                {STATUS_LABEL[s]}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
        <form
          className="flex gap-2 sm:w-72"
          onSubmit={(e) => {
            e.preventDefault()
            saring({ cari })
          }}
        >
          <Input
            type="search"
            placeholder="Cari nama guru"
            aria-label="Cari nama guru"
            value={cari}
            onChange={(e) => setCari(e.target.value)}
            className={SENTUH}
          />
          <Button type="submit" variant="outline" className={cn(SENTUH, "shrink-0")} aria-label="Cari">
            <Search />
          </Button>
        </form>
      </div>

      <DaftarSupervisi
        list={list}
        kosong="Belum ada jadwal supervisi yang cocok dengan saringan ini."
        aksi={(s) =>
          s.status === "SELESAI" ? null : (
            <>
              {s.status === "DIJADWALKAN" && (
                <>
                  <TombolAksi jenis="edit" aria-label="Edit jadwal" onClick={() => bukaUbah(s)}>
                    <Pencil />
                  </TombolAksi>
                  <TombolAksi label="Batalkan" aria-label="Batalkan jadwal" onClick={() => batalkan(s)}>
                    <Ban />
                  </TombolAksi>
                </>
              )}
              <TombolAksi jenis="hapus" aria-label="Hapus jadwal" onClick={() => hapus(s)}>
                <Trash2 />
              </TombolAksi>
            </>
          )
        }
      />

      <Dialog open={buka} onOpenChange={setBuka}>
        <DialogContent className="max-h-[90svh] overflow-y-auto sm:max-w-lg">
          <DialogHeader>
            <DialogTitle>{sedangDiubah ? "Ubah jadwal supervisi" : "Buat jadwal supervisi"}</DialogTitle>
            <DialogDescription>Mapel dan kelas boleh dikosongkan lalu dilengkapi kemudian.</DialogDescription>
          </DialogHeader>
          <form id="form-jadwal" onSubmit={simpan}>
            <FieldGroup className="gap-4">
              <Field data-invalid={!!errors.tahunAjaranId || undefined}>
                <FieldLabel htmlFor="tahunAjaranId">Tahun ajaran</FieldLabel>
                <TahunAjaranSelect
                  id="tahunAjaranId"
                  list={tahunAjaran}
                  value={data.tahunAjaranId}
                  onChange={(v) => setData("tahunAjaranId", v)}
                  className="sm:w-full"
                />
                <FieldError>{errors.tahunAjaranId}</FieldError>
              </Field>
              <Field data-invalid={!!errors.guruId || undefined}>
                <FieldLabel htmlFor="guruId">Guru yang disupervisi</FieldLabel>
                <PilihOrang
                  id="guruId"
                  list={opsi.guru}
                  value={data.guruId}
                  invalid={!!errors.guruId}
                  onChange={(v) => setData((d) => ({ ...d, guruId: v, supervisorId: d.supervisorId === v ? null : d.supervisorId }))}
                />
                <FieldError>{errors.guruId}</FieldError>
              </Field>
              <Field data-invalid={!!errors.supervisorId || undefined}>
                <FieldLabel htmlFor="supervisorId">Supervisor</FieldLabel>
                <PilihOrang
                  id="supervisorId"
                  list={opsi.supervisor}
                  value={data.supervisorId}
                  kecuali={data.guruId}
                  invalid={!!errors.supervisorId}
                  onChange={(v) => setData("supervisorId", v)}
                />
                <FieldError>{errors.supervisorId}</FieldError>
              </Field>

              {saran.length > 0 && (
                <div className="flex flex-col gap-2">
                  <p className="text-sm text-muted-foreground">Kelas yang diajar guru ini:</p>
                  <div className="flex flex-wrap gap-2">
                    {saran.map((k) => {
                      const dipilih = data.mapelId === k.mapelId && data.rombelId === k.rombelId
                      return (
                        <Button
                          key={`${k.mapelId}-${k.rombelId}`}
                          type="button"
                          size="sm"
                          variant={dipilih ? "secondary" : "outline"}
                          aria-pressed={dipilih}
                          className={SENTUH}
                          onClick={() => setData((d) => ({ ...d, mapelId: k.mapelId, rombelId: k.rombelId }))}
                        >
                          {k.label}
                        </Button>
                      )
                    })}
                  </div>
                </div>
              )}

              <div className="grid gap-4 sm:grid-cols-2">
                <Field data-invalid={!!errors.mapelId || undefined}>
                  <FieldLabel htmlFor="mapelId">Mapel</FieldLabel>
                  <PilihNama id="mapelId" list={opsi.mapel} value={data.mapelId} placeholder="Tidak diisi" onChange={(v) => setData("mapelId", v)} />
                  <FieldError>{errors.mapelId}</FieldError>
                </Field>
                <Field data-invalid={!!errors.rombelId || undefined}>
                  <FieldLabel htmlFor="rombelId">Kelas</FieldLabel>
                  <PilihNama id="rombelId" list={opsi.rombel} value={data.rombelId} placeholder="Tidak diisi" onChange={(v) => setData("rombelId", v)} />
                  <FieldError>{errors.rombelId}</FieldError>
                </Field>
                <Field data-invalid={!!errors.tanggal || undefined}>
                  <FieldLabel htmlFor="tanggal">Tanggal</FieldLabel>
                  <DatePicker id="tanggal" value={data.tanggal} onChange={(v) => setData("tanggal", v)} className={cn(SENTUH, "w-full")} />
                  <FieldError>{errors.tanggal}</FieldError>
                </Field>
              </div>
            </FieldGroup>
          </form>
          <DialogFooter>
            <Button type="button" variant="outline" className={SENTUH} onClick={() => setBuka(false)}>
              Batal
            </Button>
            <Button type="submit" form="form-jadwal" className={SENTUH} disabled={processing}>
              {processing ? "Menyimpan..." : sedangDiubah ? "Simpan perubahan" : "Buat jadwal"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
