import { Head, Link, router } from "@inertiajs/react"
import { Download } from "lucide-react"

import { StatusBadge } from "@/components/supervisi/status-badge"
import { TahunAjaranSelect } from "@/components/supervisi/tahun-ajaran-select"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import { SENTUH, formatHari, formatNilai } from "@/lib/supervisi/format"
import type { StatusSupervisi, TahunAjaranOpsi } from "@/lib/supervisi/types"

interface BarisRekap {
  id: string | null
  guruId: number
  guru: string
  tanggal: string | null
  supervisor: string | null
  mapel: string | null
  rombel: string | null
  jumlahSkor: number | null
  totalSkor: number | null
  status: StatusSupervisi | null
}

interface Props {
  baris: BarisRekap[]
  tahunAjaran: TahunAjaranOpsi[]
  tahunAjaranId: string | null
}

function Status({ b }: { b: BarisRekap }) {
  return b.status ? <StatusBadge status={b.status} /> : <Badge variant="outline">Belum dijadwalkan</Badge>
}

function NamaGuru({ b }: { b: BarisRekap }) {
  return b.id ? (
    <Link href={`/supervisi/${b.id}`} className="font-medium underline-offset-4 hover:underline">
      {b.guru}
    </Link>
  ) : (
    <span className="font-medium">{b.guru}</span>
  )
}

export function RekapPage({ baris, tahunAjaran, tahunAjaranId }: Props) {
  const selesai = baris.filter((b) => b.status === "SELESAI").length

  return (
    <div className="flex flex-col gap-4">
      <Head title="Rekap - Supervisi" />
      <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <h1 className="text-xl font-semibold">Rekap Supervisi</h1>
          <p className="text-sm text-muted-foreground">
            {selesai} supervisi selesai. Guru aktif yang belum punya jadwal ikut ditampilkan.
          </p>
        </div>
        <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
          <TahunAjaranSelect
            list={tahunAjaran}
            value={tahunAjaranId}
            onChange={(id) => router.get("/supervisi/rekap", { tahunAjaranId: id }, { preserveScroll: true })}
          />
          {tahunAjaranId && (
            <Button variant="outline" className={SENTUH} render={<a href={`/supervisi/rekap/export?tahunAjaranId=${tahunAjaranId}`} />}>
              <Download /> Ekspor Excel
            </Button>
          )}
        </div>
      </div>

      {baris.length === 0 ? (
        <p className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">Belum ada guru aktif.</p>
      ) : (
        <>
          <ul className="flex flex-col gap-3 md:hidden">
            {baris.map((b) => (
              <li key={b.id ?? `guru-${b.guruId}`} className="rounded-lg border bg-card p-4">
                <div className="flex items-start justify-between gap-3">
                  <div className="min-w-0">
                    <NamaGuru b={b} />
                    {b.tanggal && <p className="text-sm text-muted-foreground">{formatHari(b.tanggal)}</p>}
                  </div>
                  <Status b={b} />
                </div>
                {b.id && (
                  <dl className="mt-3 grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-sm">
                    <dt className="text-muted-foreground">Supervisor</dt>
                    <dd>{b.supervisor ?? "-"}</dd>
                    <dt className="text-muted-foreground">Mapel/Kelas</dt>
                    <dd>{[b.mapel, b.rombel].filter(Boolean).join(" · ") || "-"}</dd>
                    {b.status === "SELESAI" && (
                      <>
                        <dt className="text-muted-foreground">Jumlah Score</dt>
                        <dd className="tabular-nums">{b.jumlahSkor ?? "-"}</dd>
                        <dt className="text-muted-foreground">Total Score</dt>
                        <dd className="font-medium tabular-nums">{formatNilai(b.totalSkor)}</dd>
                      </>
                    )}
                  </dl>
                )}
              </li>
            ))}
          </ul>

          <div className="hidden overflow-x-auto rounded-lg border md:block">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead className="w-10">No</TableHead>
                  <TableHead>Guru</TableHead>
                  <TableHead>Tanggal</TableHead>
                  <TableHead>Supervisor</TableHead>
                  <TableHead>Mapel/Kelas</TableHead>
                  <TableHead className="text-right">Jumlah Score</TableHead>
                  <TableHead className="text-right">Total Score</TableHead>
                  <TableHead>Status</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {baris.map((b, i) => (
                  <TableRow key={b.id ?? `guru-${b.guruId}`}>
                    <TableCell className="text-muted-foreground">{i + 1}</TableCell>
                    <TableCell>
                      <NamaGuru b={b} />
                    </TableCell>
                    <TableCell className="whitespace-nowrap">{b.tanggal ? formatHari(b.tanggal) : "-"}</TableCell>
                    <TableCell>{b.supervisor ?? "-"}</TableCell>
                    <TableCell>{[b.mapel, b.rombel].filter(Boolean).join(" · ") || "-"}</TableCell>
                    <TableCell className="text-right tabular-nums">{b.jumlahSkor ?? "-"}</TableCell>
                    <TableCell className="text-right font-medium tabular-nums">{formatNilai(b.totalSkor)}</TableCell>
                    <TableCell>
                      <Status b={b} />
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </div>
        </>
      )}
    </div>
  )
}
