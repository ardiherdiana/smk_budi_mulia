import * as React from "react"
import { Head, router, usePage } from "@inertiajs/react"
import { Eye, Search, X } from "lucide-react"

import { DatePicker } from "@/components/sppd/date-picker"
import { HeaderMandiri } from "@/components/header-mandiri"
import { DataPagination } from "@/components/sppd/data-pagination"
import { TombolAksi } from "@/components/tombol-aksi"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import { Input } from "@/components/ui/input"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import type { PaginatedData } from "@/types/sppd"

type Aksi = "tambah" | "ubah" | "hapus" | "masuk" | "keluar" | "gagal_masuk" | "lain"

interface BarisLog {
  id: number
  waktu: string
  pengguna: string | null
  modul: string
  aksi: Aksi
  entitas: string | null
  deskripsi: string
  /** kolom -> [sebelum, sesudah], atau "diubah"/"diisi" untuk kolom rahasia (sandi). */
  perubahan: Record<string, [unknown, unknown] | string> | null
  ip: string | null
  userAgent: string | null
}

interface Filter {
  modul: string | null
  aksi: Aksi | null
  cari: string
  dari: string | null
  sampai: string | null
}

interface Props {
  log: PaginatedData<BarisLog>
  filter: Filter
  opsi: { modul: string[]; aksi: Aksi[] }
  masaSimpanHari: number
  [key: string]: unknown
}

const SEMUA = "__semua__"

const LABEL_AKSI: Record<Aksi, string> = {
  tambah: "Tambah",
  ubah: "Ubah",
  hapus: "Hapus",
  masuk: "Masuk",
  keluar: "Keluar",
  gagal_masuk: "Gagal masuk",
  lain: "Lainnya",
}

const VARIAN_AKSI: Record<Aksi, "success" | "secondary" | "destructive" | "warning" | "outline"> = {
  tambah: "success",
  ubah: "secondary",
  hapus: "destructive",
  gagal_masuk: "warning",
  masuk: "outline",
  keluar: "outline",
  lain: "outline",
}

const FORMAT_WAKTU = new Intl.DateTimeFormat("id-ID", { dateStyle: "medium", timeStyle: "short" })

function formatWaktu(iso: string): string {
  return FORMAT_WAKTU.format(new Date(iso))
}

/** "tanggalMulai" -> "tanggal mulai", "nama_file" -> "nama file". */
function namaKolom(kolom: string): string {
  return kolom.replace(/_/g, " ").replace(/([a-z])([A-Z])/g, "$1 $2").toLowerCase()
}

function tampilNilai(nilai: unknown): string {
  if (nilai === null || nilai === undefined || nilai === "") return "-"
  if (typeof nilai === "boolean") return nilai ? "Ya" : "Tidak"
  return String(nilai)
}

function DetailLog({ baris, onTutup }: { baris: BarisLog | null; onTutup: () => void }) {
  const perubahan = Object.entries(baris?.perubahan ?? {})

  return (
    <Dialog open={baris !== null} onOpenChange={(buka) => !buka && onTutup()}>
      <DialogContent className="sm:max-w-2xl">
        {baris && (
          <>
            <DialogHeader>
              <DialogTitle>Detail aktivitas</DialogTitle>
              <DialogDescription>{baris.deskripsi}</DialogDescription>
            </DialogHeader>
            <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm">
              <dt className="text-muted-foreground">Waktu</dt>
              <dd>{formatWaktu(baris.waktu)}</dd>
              <dt className="text-muted-foreground">Pengguna</dt>
              <dd>{baris.pengguna ?? "-"}</dd>
              <dt className="text-muted-foreground">Modul</dt>
              <dd>{baris.modul}</dd>
              <dt className="text-muted-foreground">Alamat IP</dt>
              <dd>{baris.ip ?? "-"}</dd>
              <dt className="text-muted-foreground">Perangkat</dt>
              <dd className="break-words">{baris.userAgent || "-"}</dd>
            </dl>
            {perubahan.length > 0 ? (
              <div className="overflow-x-auto rounded-lg border">
                <Table kartu>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Kolom</TableHead>
                      <TableHead>Sebelum</TableHead>
                      <TableHead>Sesudah</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {perubahan.map(([kolom, nilai]) => (
                      <TableRow key={kolom}>
                        <TableCell kartu="judul">{namaKolom(kolom)}</TableCell>
                        {typeof nilai === "string" ? (
                          <TableCell colSpan={2} className="text-muted-foreground">
                            Nilai disembunyikan ({nilai})
                          </TableCell>
                        ) : (
                          <>
                            <TableCell className="whitespace-normal break-words">{tampilNilai(nilai[0])}</TableCell>
                            <TableCell className="whitespace-normal break-words">{tampilNilai(nilai[1])}</TableCell>
                          </>
                        )}
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </div>
            ) : (
              <p className="text-sm text-muted-foreground">Tidak ada rincian perubahan data.</p>
            )}
          </>
        )}
      </DialogContent>
    </Dialog>
  )
}

export function LogAktivitasPage() {
  const { log, filter, opsi, masaSimpanHari } = usePage<Props>().props
  const [cari, setCari] = React.useState(filter.cari)
  const [detail, setDetail] = React.useState<BarisLog | null>(null)

  function saring(ubah: Partial<Filter>) {
    const query = { ...filter, cari, ...ubah }
    router.get(
      "/log-aktivitas",
      Object.fromEntries(Object.entries(query).filter(([, v]) => v !== null && v !== "")),
      { preserveState: true, preserveScroll: true, replace: true }
    )
  }

  const adaSaringan = Boolean(filter.modul || filter.aksi || filter.cari || filter.dari || filter.sampai)

  return (
    <div className="flex min-h-svh flex-col">
      <Head title="Log Aktivitas - SMK Budi Mulia" />
      <HeaderMandiri judul="Log Aktivitas" />

      <main className="mx-auto flex w-full max-w-7xl flex-col gap-4 p-4 md:p-6">
        <div>
          <h1 className="text-xl font-semibold">Log Aktivitas</h1>
          <p className="text-sm text-muted-foreground">
            Tambah, ubah, dan hapus data di semua modul, serta masuk dan keluar akun. Log disimpan {masaSimpanHari} hari,
            lalu terhapus otomatis.
          </p>
        </div>

        <div className="flex flex-col gap-2 lg:flex-row lg:flex-wrap lg:items-center">
          <form
            className="flex gap-2 lg:w-72"
            onSubmit={(e) => {
              e.preventDefault()
              saring({ cari })
            }}
          >
            <Input
              aria-label="Cari pengguna, keterangan, atau IP"
              placeholder="Cari pengguna, keterangan, atau IP"
              value={cari}
              onChange={(e) => setCari(e.target.value)}
            />
            <Button type="submit" variant="outline" size="icon" aria-label="Cari">
              <Search />
            </Button>
          </form>

          <div className="grid grid-cols-2 gap-2 lg:flex">
            <Select
              items={{ [SEMUA]: "Semua modul", ...Object.fromEntries(opsi.modul.map((m) => [m, m])) }}
              value={filter.modul ?? SEMUA}
              onValueChange={(v) => saring({ modul: v === SEMUA ? null : (v as string) })}
            >
              <SelectTrigger className="w-full lg:w-44" aria-label="Modul">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value={SEMUA}>Semua modul</SelectItem>
                {opsi.modul.map((m) => (
                  <SelectItem key={m} value={m}>
                    {m}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>

            <Select
              items={{ [SEMUA]: "Semua aksi", ...LABEL_AKSI }}
              value={filter.aksi ?? SEMUA}
              onValueChange={(v) => saring({ aksi: v === SEMUA ? null : (v as Aksi) })}
            >
              <SelectTrigger className="w-full lg:w-40" aria-label="Aksi">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value={SEMUA}>Semua aksi</SelectItem>
                {opsi.aksi.map((a) => (
                  <SelectItem key={a} value={a}>
                    {LABEL_AKSI[a]}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          <div className="grid grid-cols-2 gap-2 lg:flex lg:items-center">
            <DatePicker
              id="dari"
              placeholder="Dari tanggal"
              value={filter.dari ?? ""}
              onChange={(v) => saring({ dari: v || null })}
              className="lg:w-40"
            />
            <DatePicker
              id="sampai"
              placeholder="Sampai tanggal"
              value={filter.sampai ?? ""}
              onChange={(v) => saring({ sampai: v || null })}
              className="lg:w-40"
            />
          </div>

          {adaSaringan && (
            <Button
              variant="ghost"
              onClick={() => {
                setCari("")
                router.get("/log-aktivitas", {}, { preserveScroll: true, replace: true })
              }}
            >
              <X /> Hapus saringan
            </Button>
          )}
        </div>

        <div className="overflow-x-auto rounded-lg border">
          <Table kartu>
            <TableHeader>
              <TableRow>
                <TableHead>Waktu</TableHead>
                <TableHead>Keterangan</TableHead>
                <TableHead>Pengguna</TableHead>
                <TableHead>Modul</TableHead>
                <TableHead>Aksi</TableHead>
                <TableHead>IP</TableHead>
                <TableHead className="text-right">Detail</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {log.data.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={7} className="text-center text-muted-foreground">
                    {adaSaringan ? "Tidak ada aktivitas yang cocok dengan saringan ini." : "Belum ada aktivitas tercatat."}
                  </TableCell>
                </TableRow>
              ) : (
                log.data.map((baris) => (
                  <TableRow key={baris.id}>
                    <TableCell className="text-muted-foreground tabular-nums">{formatWaktu(baris.waktu)}</TableCell>
                    <TableCell kartu="judul" className="max-w-md whitespace-normal">
                      {baris.deskripsi}
                    </TableCell>
                    <TableCell>{baris.pengguna ?? "-"}</TableCell>
                    <TableCell>{baris.modul}</TableCell>
                    <TableCell>
                      <Badge variant={VARIAN_AKSI[baris.aksi]}>{LABEL_AKSI[baris.aksi]}</Badge>
                    </TableCell>
                    <TableCell className="text-muted-foreground tabular-nums">{baris.ip ?? "-"}</TableCell>
                    <TableCell kartu="aksi" className="text-right">
                      <TombolAksi jenis="lihat" aria-label={`Lihat detail: ${baris.deskripsi}`} onClick={() => setDetail(baris)}>
                        <Eye />
                      </TombolAksi>
                    </TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
        </div>

        <DataPagination paginator={log} />
      </main>

      <DetailLog baris={detail} onTutup={() => setDetail(null)} />
    </div>
  )
}
