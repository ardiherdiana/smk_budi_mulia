import * as React from "react"
import { Link } from "@inertiajs/react"
import { ChevronDown, ClipboardCheck, FileText, ListChecks } from "lucide-react"

import type { ItemAgenda } from "@/lib/classroom/types"
import { useAuth } from "@/context/classroom-auth"
import { formatWaktu } from "@/lib/classroom/format"
import { cn } from "@/lib/classroom/utils"
import { Badge } from "@/components/ui/badge"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { format } from "date-fns"
import { id as localeId } from "date-fns/locale"

interface Props {
  items: ItemAgenda[]
}

interface Tab {
  kunci: string
  label: string
  saring: (item: ItemAgenda, sekarang: number) => boolean
  /** Bagi per rentang tenggat; jika tidak, satu bagian bernama `judul`. */
  perTenggat?: boolean
  judul?: string
}

const HARI = 24 * 60 * 60 * 1000
const SEMUA = "semua"
const BATAS_TAMPIL = 5

function lewat(item: ItemAgenda, sekarang: number): boolean {
  return item.tenggat !== null && new Date(item.tenggat).getTime() < sekarang
}

const TAB_SISWA: Tab[] = [
  {
    kunci: "ditugaskan",
    label: "Ditugaskan",
    perTenggat: true,
    saring: (i, s) => i.status === "BELUM" && !lewat(i, s),
  },
  {
    kunci: "belum",
    label: "Belum Diserahkan",
    judul: "Terlambat",
    saring: (i, s) => i.status === "BELUM" && lewat(i, s),
  },
  { kunci: "selesai", label: "Selesai", judul: "Sudah diserahkan", saring: (i) => i.status !== "BELUM" },
]

const TAB_GURU: Tab[] = [
  { kunci: "menunggu", label: "Perlu dinilai", judul: "Menunggu penilaian", saring: (i) => (i.menunggu ?? 0) > 0 },
  { kunci: "mendatang", label: "Mendatang", perTenggat: true, saring: (i, s) => !lewat(i, s) },
  { kunci: "lewat", label: "Sudah lewat", judul: "Tenggat terlewat", saring: (i, s) => lewat(i, s) },
]

const URUTAN_BAGIAN = ["Tak ada batas waktu", "Minggu ini", "Minggu berikutnya", "Nanti"]

function bagianTenggat(item: ItemAgenda, sekarang: number): string {
  if (item.tenggat === null) return URUTAN_BAGIAN[0]
  const selisih = new Date(item.tenggat).getTime() - sekarang
  if (selisih < 7 * HARI) return URUTAN_BAGIAN[1]
  if (selisih < 14 * HARI) return URUTAN_BAGIAN[2]
  return URUTAN_BAGIAN[3]
}

function BarisItem({ item, siswa }: { item: ItemAgenda; siswa: boolean }) {
  const Ikon = item.tipe === "UJIAN" ? ClipboardCheck : FileText
  return (
    <Link href={`/classroom/post/${item.id}`} className="flex items-start gap-4 border-b px-1 py-3 transition-colors hover:bg-muted/50">
      <span className="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
        <Ikon className="size-5" />
      </span>
      <div className="flex min-w-0 flex-1 flex-col gap-0.5">
        <span className="truncate text-sm font-medium">{item.judul}</span>
        <span className="truncate text-xs text-muted-foreground">
          {item.mapel} · {item.rombel}
        </span>
        <span className="mt-1 text-xs text-muted-foreground">
          Diposting {format(new Date(item.diposting), "EEEE, d MMM yyyy", { locale: localeId })}
          {item.tenggat && ` · Tenggat ${formatWaktu(item.tenggat)}`}
        </span>
      </div>
      <div className="flex shrink-0 flex-col items-end gap-1">
        {siswa && item.status === "DINILAI" && <Badge variant="secondary">Dinilai</Badge>}
        {siswa && item.status === "DIKUMPULKAN" && <Badge variant="outline">Diserahkan</Badge>}
        {!siswa && (item.menunggu ?? 0) > 0 && <Badge>{item.menunggu} perlu dinilai</Badge>}
      </div>
    </Link>
  )
}

function Bagian({ judul, daftar, siswa }: { judul: string; daftar: ItemAgenda[]; siswa: boolean }) {
  const [buka, setBuka] = React.useState(true)
  const [semua, setSemua] = React.useState(false)
  const tampil = semua ? daftar : daftar.slice(0, BATAS_TAMPIL)

  return (
    <section className="flex flex-col">
      <button
        type="button"
        onClick={() => setBuka((v) => !v)}
        aria-expanded={buka}
        className="flex items-center justify-between gap-3 py-3 text-left"
      >
        <h2 className="text-base font-medium">{judul}</h2>
        <span className="flex items-center gap-3 text-sm text-primary">
          {daftar.length}
          <ChevronDown className={cn("size-5 text-muted-foreground transition-transform", !buka && "-rotate-90")} />
        </span>
      </button>
      {buka && daftar.length > 0 && (
        <>
          <div className="flex flex-col border-t">
            {tampil.map((item) => (
              <BarisItem key={item.id} item={item} siswa={siswa} />
            ))}
          </div>
          {!semua && daftar.length > BATAS_TAMPIL && (
            <button type="button" onClick={() => setSemua(true)} className="py-4 text-sm text-primary hover:underline">
              Lihat semua
            </button>
          )}
        </>
      )}
    </section>
  )
}

export function DaftarTugasPage({ items }: Props) {
  const { isSiswa } = useAuth()
  const tabs = isSiswa ? TAB_SISWA : TAB_GURU
  const [aktif, setAktif] = React.useState(tabs[0].kunci)
  const [kelasId, setKelasId] = React.useState(SEMUA)
  const [sekarang] = React.useState(() => Date.now())
  const tab = tabs.find((t) => t.kunci === aktif) ?? tabs[0]

  const daftarKelas = React.useMemo(() => {
    const peta = new Map<string, string>()
    for (const i of items) peta.set(i.kelasId, `${i.mapel} · ${i.rombel}`)
    return [...peta.entries()].sort((a, b) => a[1].localeCompare(b[1]))
  }, [items])
  const petaPilihan = { [SEMUA]: "Semua kelas", ...Object.fromEntries(daftarKelas) }

  const terpilih = items
    .filter((i) => (kelasId === SEMUA || i.kelasId === kelasId) && tab.saring(i, sekarang))
    .sort((a, b) => {
      const wa = new Date(a.tenggat ?? a.diposting).getTime()
      const wb = new Date(b.tenggat ?? b.diposting).getTime()
      return wa - wb
    })

  const bagian = tab.perTenggat
    ? URUTAN_BAGIAN.map((nama) => ({ nama, daftar: terpilih.filter((i) => bagianTenggat(i, sekarang) === nama) }))
    : [{ nama: tab.judul ?? tab.label, daftar: terpilih }]

  return (
    <div className="flex flex-col gap-4">
      <div>
        <h1 className="text-xl font-semibold">Daftar tugas</h1>
        <p className="text-sm text-muted-foreground">Tugas dan ujian dari semua ruang kelas aktif</p>
      </div>

      <Tabs value={aktif} onValueChange={(v) => setAktif(String(v))}>
        <TabsList variant="line" className="h-10 border-b">
          {tabs.map((t) => (
            <TabsTrigger key={t.kunci} value={t.kunci} className="px-4">
              {t.label}
            </TabsTrigger>
          ))}
        </TabsList>
      </Tabs>

      <div className="mx-auto flex w-full max-w-3xl flex-col gap-4 pt-2">
        <Select items={petaPilihan} value={kelasId} onValueChange={(v) => setKelasId(v ?? SEMUA)}>
          <SelectTrigger className="w-full max-w-sm">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value={SEMUA}>Semua kelas</SelectItem>
            {daftarKelas.map(([id, nama]) => (
              <SelectItem key={id} value={id}>
                {nama}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>

        {terpilih.length === 0 && !tab.perTenggat ? (
          <div className="flex flex-col items-center gap-2 py-16 text-center">
            <ListChecks className="size-10 text-muted-foreground" />
            <p className="font-medium">Tidak ada tugas</p>
          </div>
        ) : (
          bagian.map((b) => <Bagian key={b.nama} judul={b.nama} daftar={b.daftar} siswa={isSiswa} />)
        )}
      </div>
    </div>
  )
}
