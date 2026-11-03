import { Download } from "lucide-react"

import type { KelasHeader as KelasHeaderData } from "@/lib/classroom/types"
import { formatAngka } from "@/lib/classroom/format"
import { KelasHeader } from "@/components/classroom/kelas-header"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Card, CardContent } from "@/components/ui/card"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"

interface Kolom {
  id: string
  judul: string
  tipe: string
  maks: number
}

interface Baris {
  siswaId: string
  nama: string
  nis: string | null
  nilai: Record<string, number | null>
  rata: number | null
}

interface Props {
  kelas: KelasHeaderData
  kolom: Kolom[]
  baris: Baris[]
  saya: boolean
}

function badgeRata(rata: number | null) {
  if (rata === null) return <span className="text-muted-foreground">-</span>
  return <Badge variant={rata >= 75 ? "success" : rata >= 60 ? "warning" : "destructive"}>{formatAngka(rata)}</Badge>
}

export function NilaiPage({ kelas, kolom, baris, saya }: Props) {
  return (
    <div className="flex flex-col gap-4">
      <KelasHeader kelas={kelas} />

      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 className="text-lg font-semibold">{saya ? "Nilai Saya" : "Rekap Nilai"}</h2>
          <p className="text-sm text-muted-foreground">
            {saya
              ? "Nilai tugas dan ujian di mata pelajaran ini"
              : "Nilai tugas dan ujian semua siswa. Rata-rata dihitung dari persentase tiap penilaian."}
          </p>
        </div>
        {!saya && kolom.length > 0 && (
          <Button variant="outline" render={<a href={`/classroom/kelas/${kelas.id}/nilai/export`} />}>
            <Download /> Unduh Excel
          </Button>
        )}
      </div>

      {kolom.length === 0 ? (
        <Card>
          <CardContent className="py-12 text-center text-sm text-muted-foreground">
            Belum ada tugas atau ujian, jadi belum ada nilai.
          </CardContent>
        </Card>
      ) : saya ? (
        <div className="overflow-x-auto rounded-lg border">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Penilaian</TableHead>
                <TableHead>Jenis</TableHead>
                <TableHead className="text-right">Nilai</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {kolom.map((k) => {
                const nilai = baris[0]?.nilai[k.id] ?? null
                return (
                  <TableRow key={k.id}>
                    <TableCell className="font-medium">{k.judul}</TableCell>
                    <TableCell>
                      <Badge variant="outline">{k.tipe === "TUGAS" ? "Tugas" : "Ujian"}</Badge>
                    </TableCell>
                    <TableCell className="text-right">
                      {nilai === null ? (
                        <span className="text-muted-foreground">Belum ada</span>
                      ) : (
                        <>
                          {formatAngka(nilai)} <span className="text-muted-foreground">/ {formatAngka(k.maks)}</span>
                        </>
                      )}
                    </TableCell>
                  </TableRow>
                )
              })}
              <TableRow>
                <TableCell className="font-semibold" colSpan={2}>
                  Rata-rata
                </TableCell>
                <TableCell className="text-right">{badgeRata(baris[0]?.rata ?? null)}</TableCell>
              </TableRow>
            </TableBody>
          </Table>
        </div>
      ) : (
        <div className="overflow-x-auto rounded-lg border">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead className="w-10">No</TableHead>
                <TableHead>Nama</TableHead>
                {kolom.map((k) => (
                  <TableHead key={k.id} className="text-right">
                    <span className="block max-w-40 truncate" title={k.judul}>
                      {k.judul}
                    </span>
                    <span className="text-xs font-normal text-muted-foreground">
                      {k.tipe === "TUGAS" ? "Tugas" : "Ujian"} · /{formatAngka(k.maks)}
                    </span>
                  </TableHead>
                ))}
                <TableHead className="text-right">Rata-rata (%)</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {baris.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={kolom.length + 3} className="text-center text-muted-foreground">
                    Belum ada siswa di rombel {kelas.rombel}
                  </TableCell>
                </TableRow>
              ) : (
                baris.map((b, i) => (
                  <TableRow key={b.siswaId}>
                    <TableCell className="text-muted-foreground">{i + 1}</TableCell>
                    <TableCell className="font-medium">{b.nama}</TableCell>
                    {kolom.map((k) => (
                      <TableCell key={k.id} className="text-right">
                        {b.nilai[k.id] === null || b.nilai[k.id] === undefined ? (
                          <span className="text-muted-foreground">-</span>
                        ) : (
                          formatAngka(b.nilai[k.id])
                        )}
                      </TableCell>
                    ))}
                    <TableCell className="text-right">{badgeRata(b.rata)}</TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
        </div>
      )}
    </div>
  )
}
