import type { ReactNode } from "react"
import { Head, Link, router } from "@inertiajs/react"
import { ChevronRight } from "lucide-react"

import { DaftarSupervisi } from "@/components/supervisi/daftar-supervisi"
import { StatusBadge } from "@/components/supervisi/status-badge"
import { TahunAjaranSelect } from "@/components/supervisi/tahun-ajaran-select"
import { Button } from "@/components/ui/button"
import { SENTUH, formatHari, formatNilai } from "@/lib/supervisi/format"
import type { Orang, SupervisiRingkas, TahunAjaranOpsi } from "@/lib/supervisi/types"
import { cn } from "@/lib/utils"

interface Ringkasan {
  jumlahGuru: number
  sudah: number
  dijadwalkan: number
  belum: number
  akanDatang: SupervisiRingkas[]
  belumDijadwalkan: Orang[]
}

interface Props {
  tahunAjaran: TahunAjaranOpsi[]
  tahunAjaranId: string | null
  kelola: boolean
  tugas: SupervisiRingkas[]
  saya: SupervisiRingkas[]
  ringkasan: Ringkasan | null
}

function StatTile({ label, value }: { label: string; value: number }) {
  return (
    <div className="rounded-lg border bg-card p-4">
      <p className="text-sm text-muted-foreground">{label}</p>
      <p className="mt-1 text-2xl font-semibold tabular-nums">{value}</p>
    </div>
  )
}

function Bagian({ judul, children, aksi }: { judul: string; children: ReactNode; aksi?: ReactNode }) {
  return (
    <section className="flex min-w-0 flex-col gap-3">
      <div className="flex items-center justify-between gap-2">
        <h2 className="font-medium">{judul}</h2>
        {aksi}
      </div>
      {children}
    </section>
  )
}

/** Daftar ringkas satu baris per supervisi, dipakai untuk jadwal terdekat dan hasil terbaru. */
function DaftarSingkat({ list, kosong, tampil }: { list: SupervisiRingkas[]; kosong: string; tampil: "guru" | "supervisor" }) {
  if (list.length === 0) {
    return <p className="rounded-lg border border-dashed p-4 text-sm text-muted-foreground">{kosong}</p>
  }

  return (
    <ul className="divide-y rounded-lg border">
      {list.map((s) => (
        <li key={s.id}>
          <Link
            href={`/supervisi/${s.id}`}
            className="flex min-h-14 items-center gap-3 px-4 py-3 transition-colors hover:bg-muted focus-visible:bg-muted focus-visible:outline-none"
          >
            <div className="min-w-0 flex-1">
              <p className="truncate font-medium">{tampil === "guru" ? s.guru?.nama : `Supervisor: ${s.supervisor?.nama ?? "-"}`}</p>
              <p className="truncate text-sm text-muted-foreground">
                {formatHari(s.tanggal)}
                {s.mapel ? ` · ${s.mapel}` : ""}
                {s.rombel ? ` · ${s.rombel}` : ""}
              </p>
            </div>
            {s.status === "SELESAI" ? (
              <span className="shrink-0 text-right text-sm">
                <span className="font-medium tabular-nums">{formatNilai(s.totalSkor)}</span>
                <span className="block text-muted-foreground">Total Score</span>
              </span>
            ) : (
              <StatusBadge status={s.status} />
            )}
            <ChevronRight className="size-4 shrink-0 text-muted-foreground" />
          </Link>
        </li>
      ))}
    </ul>
  )
}

export function DashboardPage({ tahunAjaran, tahunAjaranId, kelola, tugas, saya, ringkasan }: Props) {
  const jadwalSaya = saya.filter((s) => s.status === "DIJADWALKAN")
  const hasilSaya = saya.filter((s) => s.status === "SELESAI")

  return (
    <div className="flex flex-col gap-6">
      <Head title="Dashboard - Supervisi" />
      <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <h1 className="text-xl font-semibold">Dashboard Supervisi</h1>
          <p className="text-sm text-muted-foreground">
            {kelola ? "Progres supervisi akademik guru di tahun ajaran terpilih." : "Jadwal dan hasil supervisi Anda."}
          </p>
        </div>
        <TahunAjaranSelect
          list={tahunAjaran}
          value={tahunAjaranId}
          onChange={(id) => router.get("/supervisi/dashboard", { tahunAjaranId: id }, { preserveScroll: true })}
        />
      </div>

      {ringkasan && (
        <>
          <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <StatTile label="Guru aktif" value={ringkasan.jumlahGuru} />
            <StatTile label="Sudah disupervisi" value={ringkasan.sudah} />
            <StatTile label="Dijadwalkan" value={ringkasan.dijadwalkan} />
            <StatTile label="Belum dijadwalkan" value={ringkasan.belum} />
          </div>

          <Bagian
            judul="Jadwal terdekat"
            aksi={
              <Button variant="ghost" size="sm" render={<Link href="/supervisi/jadwal" />}>
                Semua jadwal <ChevronRight />
              </Button>
            }
          >
            <DaftarSingkat list={ringkasan.akanDatang} tampil="guru" kosong="Tidak ada jadwal supervisi mendatang." />
          </Bagian>

          <Bagian judul={`Belum dijadwalkan (${ringkasan.belumDijadwalkan.length})`}>
            {ringkasan.belumDijadwalkan.length === 0 ? (
              <p className="rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
                Semua guru aktif sudah punya jadwal di tahun ajaran ini.
              </p>
            ) : (
              <ul className="grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-3">
                {ringkasan.belumDijadwalkan.map((g) => (
                  <li key={g.id} className="flex min-w-0 items-center justify-between gap-2 rounded-lg border px-4 py-2">
                    <span className="min-w-0 truncate text-sm">{g.nama}</span>
                    <Button
                      size="sm"
                      className={cn(SENTUH, "shrink-0")}
                      render={<Link href={`/supervisi/jadwal?guru=${g.id}${tahunAjaranId ? `&tahunAjaranId=${tahunAjaranId}` : ""}`} />}
                    >
                      Jadwalkan
                    </Button>
                  </li>
                ))}
              </ul>
            )}
          </Bagian>
        </>
      )}

      {tugas.length > 0 && (
        <Bagian judul="Tugas supervisi Anda">
          <DaftarSupervisi list={tugas} tampilSupervisor={false} kosong="" />
        </Bagian>
      )}

      {!kelola && (
        <div className="grid gap-6 lg:grid-cols-2">
          <Bagian judul="Jadwal mendatang">
            <DaftarSingkat list={jadwalSaya} tampil="supervisor" kosong="Belum ada jadwal supervisi untuk Anda." />
          </Bagian>
          <Bagian judul="Hasil supervisi">
            <DaftarSingkat list={hasilSaya} tampil="supervisor" kosong="Belum ada hasil supervisi." />
          </Bagian>
        </div>
      )}
    </div>
  )
}
