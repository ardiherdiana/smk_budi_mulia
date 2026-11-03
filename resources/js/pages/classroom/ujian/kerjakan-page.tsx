import * as React from "react"
import { router } from "@inertiajs/react"
import { ArrowLeft, ArrowRight, CircleCheck, Flag, LayoutGrid, Loader2, TriangleAlert } from "lucide-react"
import { toast } from "sonner"

import { api, ApiError } from "@/lib/api"
import { useConfirm } from "@/hooks/use-confirm"
import { sudahDijawab } from "@/lib/classroom/soal"
import {
  adalahBilangan,
  adalahDaftarTeks,
  formatSisa,
  UKURAN_FONT,
  UKURAN_FONT_AWAL,
  useSimpanLokal,
  type SoalUjian,
} from "@/lib/classroom/ujian"
import { cn } from "@/lib/classroom/utils"
import { DaftarSoal } from "@/components/classroom/ujian/daftar-soal"
import { InformasiSoal } from "@/components/classroom/ujian/informasi-soal"
import { GambarSoal } from "@/components/classroom/ujian/gambar-soal"
import { SoalInput } from "@/components/classroom/ujian/soal-input"
import { ThemeToggle } from "@/components/theme-toggle"
import { Button } from "@/components/ui/button"
import { Checkbox } from "@/components/ui/checkbox"

interface Props {
  post: { id: string; judul: string | null; isi: string | null; kelasId: string; durasiMenit: number | null }
  kelas: { mapel: string; rombel: string }
  totalPoin: number
  percobaanId: string
  sisaDetik: number
  soal: SoalUjian[]
}

type StatusSimpan = "tersimpan" | "menyimpan" | "gagal"

export function KerjakanUjianPage({ post, kelas, totalPoin, percobaanId, sisaDetik, soal }: Props) {
  const { confirm, confirmDialog } = useConfirm()
  const [jawaban, setJawaban] = React.useState<Record<string, string>>(
    () => Object.fromEntries(soal.map((s) => [s.id, s.jawaban ?? ""]))
  )
  const [sisa, setSisa] = React.useState(sisaDetik)
  const [status, setStatus] = React.useState<StatusSimpan>("tersimpan")
  const [mengirim, setMengirim] = React.useState(false)

  // Posisi soal, tanda ragu-ragu, dan ukuran huruf bertahan walau halaman dimuat ulang (hanya di perangkat ini).
  const [indeksTersimpan, setIndeks] = useSimpanLokal(`ujian:${percobaanId}:soal`, 0, adalahBilangan)
  const [raguTersimpan, setRagu] = useSimpanLokal<string[]>(`ujian:${percobaanId}:ragu`, [], adalahDaftarTeks)
  const [ukuranTersimpan, setUkuran] = useSimpanLokal("ujian:ukuran-font", UKURAN_FONT_AWAL, adalahBilangan)
  const [daftarBuka, setDaftarBuka] = React.useState(false)
  const [infoBuka, setInfoBuka] = React.useState(false)

  const indeks = Math.min(Math.max(indeksTersimpan, 0), soal.length - 1)
  const ukuran = UKURAN_FONT[Math.min(Math.max(ukuranTersimpan, 0), UKURAN_FONT.length - 1)]
  const ragu = React.useMemo(() => raguTersimpan.filter((id) => soal.some((s) => s.id === id)), [raguTersimpan, soal])
  const aktif = soal[indeks]
  const nomorTerakhir = indeks === soal.length - 1

  // Waktu dihitung dari jam server (sisaDetik) + selisih performance.now(), jadi mengubah jam perangkat tidak berpengaruh.
  const mulai = React.useRef(performance.now())
  // Jawaban yang belum sampai ke server; dicoba ulang sampai berhasil.
  const antrean = React.useRef<Map<string, string>>(new Map())
  const timerEssay = React.useRef<Map<string, ReturnType<typeof setTimeout>>>(new Map())
  const sedangKirim = React.useRef(false)
  const selesai = React.useRef(false)

  const dikirimOtomatis = React.useRef(false)

  const kirimSelesai = React.useCallback(async () => {
    if (selesai.current) return
    selesai.current = true
    setMengirim(true)
    await flush()
    router.post(`/classroom/percobaan/${percobaanId}/selesai`)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [percobaanId])

  async function flush(): Promise<void> {
    if (sedangKirim.current) return
    sedangKirim.current = true
    setStatus("menyimpan")
    try {
      while (antrean.current.size > 0) {
        const [soalId, isi] = antrean.current.entries().next().value as [string, string]
        try {
          await api.patch(`/classroom/percobaan/${percobaanId}/jawab`, { soalId, jawaban: isi })
          // Hanya hapus bila belum diubah lagi selama permintaan berjalan.
          if (antrean.current.get(soalId) === isi) antrean.current.delete(soalId)
        } catch (err) {
          if (err instanceof ApiError && err.status === 409) {
            // Waktu habis di server: percobaan sudah ditutup, tidak ada gunanya mencoba lagi.
            antrean.current.clear()
            selesai.current = true
            toast.error("Waktu ujian sudah habis")
            router.visit(`/classroom/post/${post.id}`)
            return
          }
          setStatus("gagal")
          return
        }
      }
      setStatus("tersimpan")
    } finally {
      sedangKirim.current = false
    }
  }

  function simpan(soalId: string, isi: string, segera: boolean) {
    antrean.current.set(soalId, isi)
    setStatus("menyimpan")

    const lama = timerEssay.current.get(soalId)
    if (lama) clearTimeout(lama)

    if (segera) {
      void flush()
    } else {
      timerEssay.current.set(soalId, setTimeout(() => void flush(), 800))
    }
  }

  function ubah(s: SoalUjian, isi: string) {
    setJawaban((j) => ({ ...j, [s.id]: isi }))
    simpan(s.id, isi, s.tipe !== "ESSAY")
  }

  // Hitung mundur + kirim otomatis saat waktu habis.
  React.useEffect(() => {
    const id = setInterval(() => {
      const tersisa = Math.max(0, Math.ceil(sisaDetik - (performance.now() - mulai.current) / 1000))
      setSisa(tersisa)
      if (tersisa === 0 && !dikirimOtomatis.current) {
        dikirimOtomatis.current = true
        toast.info("Waktu habis, jawabanmu dikirim otomatis")
        void kirimSelesai()
      }
    }, 500)
    return () => clearInterval(id)
  }, [sisaDetik, kirimSelesai])

  // Coba ulang jawaban yang gagal terkirim (mis. sinyal putus-nyambung).
  React.useEffect(() => {
    const id = setInterval(() => {
      if (antrean.current.size > 0 && !sedangKirim.current) void flush()
    }, 5000)
    return () => clearInterval(id)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  // Cegah tertutup tak sengaja selama ujian.
  React.useEffect(() => {
    function peringatan(event: BeforeUnloadEvent) {
      if (selesai.current) return
      event.preventDefault()
    }
    window.addEventListener("beforeunload", peringatan)
    return () => window.removeEventListener("beforeunload", peringatan)
  }, [])

  const terisi = (s: SoalUjian) => sudahDijawab(s.tipe, jawaban[s.id], s.opsi?.length ?? 0)
  const terjawab = soal.filter(terisi).length

  function pindah(ke: number) {
    setIndeks(Math.min(Math.max(ke, 0), soal.length - 1))
    window.scrollTo({ top: 0, behavior: "smooth" })
  }

  function togelRagu() {
    setRagu((r) => (r.includes(aktif.id) ? r.filter((id) => id !== aktif.id) : [...r, aktif.id]))
  }

  async function kumpulkan() {
    setDaftarBuka(false)
    const belum = soal.length - terjawab
    const masihRagu = ragu.length
    const catatan = [
      belum > 0 ? `${belum} soal belum dijawab` : null,
      masihRagu > 0 ? `${masihRagu} soal masih ditandai ragu-ragu` : null,
    ]
      .filter(Boolean)
      .join(" dan ")
    const ok = await confirm({
      title: "Kumpulkan ujian sekarang?",
      description: `${catatan ? `${catatan}. ` : ""}Setelah dikumpulkan, jawaban tidak bisa diubah lagi.`,
      confirmLabel: "Kumpulkan",
    })
    if (ok) void kirimSelesai()
  }

  // Server menolak memulai ujian tanpa soal; ini hanya jaring pengaman bila soalnya terhapus saat ujian berjalan.
  if (!aktif) {
    return (
      <div className="flex min-h-svh flex-col items-center justify-center gap-3 p-6 text-center">
        <p className="text-sm text-muted-foreground">Ujian ini belum memiliki soal.</p>
        <Button onClick={() => router.visit(`/classroom/post/${post.id}`)}>Kembali</Button>
      </div>
    )
  }

  const hampirHabis = sisa <= 300
  const tandaiRagu = ragu.includes(aktif.id)

  return (
    <div className="flex min-h-svh flex-col bg-muted/30">
      {confirmDialog}
      <header className="sticky top-0 z-20 flex h-14 shrink-0 items-center gap-3 border-b bg-background/95 px-4 backdrop-blur md:px-6">
        <div className="flex min-w-0 flex-1 items-center gap-2">
          <img src="/logo_smk.png" alt="Logo SMK Budi Mulia" className="size-7 shrink-0 object-contain" />
          <h1 className="truncate font-medium">{post.judul}</h1>
        </div>
        <span className="flex items-center gap-1.5 text-xs text-muted-foreground" aria-live="polite">
          {status === "menyimpan" && (
            <>
              <Loader2 className="size-3.5 animate-spin" /> <span className="hidden sm:inline">Menyimpan...</span>
            </>
          )}
          {status === "tersimpan" && (
            <>
              <CircleCheck className="size-3.5 text-success" /> <span className="hidden sm:inline">Tersimpan</span>
            </>
          )}
          {status === "gagal" && (
            <>
              <TriangleAlert className="size-3.5 text-destructive" /> <span className="hidden sm:inline">Gagal menyimpan, mencoba lagi</span>
            </>
          )}
        </span>
        <ThemeToggle />
      </header>

      <main className="mx-auto w-full max-w-5xl flex-1 p-4 md:p-6">
        <div className="rounded-xl border bg-card text-card-foreground shadow-sm">
          <div className="flex flex-wrap items-start justify-between gap-4 border-b p-4 md:p-6">
            <div className="flex flex-col gap-2">
              <h2 className="text-2xl font-medium">Soal nomor {indeks + 1}</h2>
              <div className="flex items-center gap-1 text-sm">
                <span className="mr-2 text-muted-foreground">Ukuran font soal:</span>
                {UKURAN_FONT.map((u, i) => (
                  <button
                    key={u.nama}
                    type="button"
                    onClick={() => setUkuran(i)}
                    aria-label={`Ukuran huruf ${u.nama.toLowerCase()}`}
                    aria-pressed={u === ukuran}
                    className={cn(
                      "flex size-11 items-center justify-center rounded-md transition-colors hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none",
                      u.tombol,
                      u === ukuran ? "bg-muted font-semibold" : "text-muted-foreground"
                    )}
                  >
                    A
                  </button>
                ))}
              </div>
            </div>

            <div className="flex flex-col items-end gap-2">
              <div className="flex flex-wrap justify-end gap-2">
                <Button variant="outline" size="lg" className="rounded-full px-4" onClick={() => setInfoBuka(true)}>
                  Informasi Soal
                </Button>
                <div
                  className={cn(
                    "flex h-9 items-center gap-1.5 rounded-full border px-4 text-sm tabular-nums",
                    hampirHabis && "border-destructive/40 bg-destructive/10 text-destructive"
                  )}
                  role="timer"
                  aria-label="Sisa waktu"
                >
                  Sisa Waktu : <span className="font-mono font-semibold">{formatSisa(sisa)}</span>
                </div>
                <Button size="lg" className="rounded-full px-4" onClick={() => setDaftarBuka(true)}>
                  Daftar Soal <LayoutGrid />
                </Button>
              </div>
              <p className="text-right text-sm text-muted-foreground">
                {kelas.mapel} - {kelas.rombel}
              </p>
            </div>
          </div>

          <div className="p-4 md:p-6">
            <div className="overflow-hidden rounded-md border">
              <div className="h-3 bg-muted" />
              <div key={aktif.id} className="flex min-h-64 flex-col gap-5 p-4 md:p-6">
                <p className={cn("break-words whitespace-pre-wrap", ukuran.teks)}>{aktif.teks}</p>
                <GambarSoal urls={aktif.gambar} nomor={indeks + 1} />
                <SoalInput
                  soal={aktif}
                  nomor={indeks + 1}
                  jawaban={jawaban[aktif.id] ?? ""}
                  teksClass={ukuran.teks}
                  onUbah={(isi) => ubah(aktif, isi)}
                />
              </div>
            </div>
          </div>

          <div className="flex flex-col gap-3 border-t p-4 sm:flex-row sm:items-center sm:justify-between md:p-6">
            <Button
              size="lg"
              className="rounded-full bg-destructive px-6 text-(--destructive-foreground) hover:bg-destructive/90"
              disabled={indeks === 0}
              onClick={() => pindah(indeks - 1)}
            >
              <ArrowLeft /> Soal sebelumnya
            </Button>

            <label className="inline-flex h-9 cursor-pointer items-center justify-center gap-2 rounded-full bg-warning px-6 text-sm font-medium text-(--warning-foreground) transition-colors select-none hover:bg-warning/90 sm:h-10">
              <Checkbox checked={tandaiRagu} onCheckedChange={togelRagu} className="bg-background" />
              Ragu-ragu
            </label>

            {nomorTerakhir ? (
              <Button size="lg" className="rounded-full px-6" onClick={kumpulkan} disabled={mengirim}>
                {mengirim ? "Mengirim..." : "Selesai"} <Flag />
              </Button>
            ) : (
              <Button size="lg" className="rounded-full px-6" onClick={() => pindah(indeks + 1)}>
                Soal berikutnya <ArrowRight />
              </Button>
            )}
          </div>
        </div>
      </main>

      <DaftarSoal
        buka={daftarBuka}
        onBukaChange={setDaftarBuka}
        soal={soal}
        terisi={terisi}
        ragu={ragu}
        aktif={indeks}
        onPilih={pindah}
        onKumpulkan={kumpulkan}
        mengirim={mengirim}
      />
      <InformasiSoal
        buka={infoBuka}
        onBukaChange={setInfoBuka}
        judul={post.judul}
        isi={post.isi}
        mapel={kelas.mapel}
        rombel={kelas.rombel}
        durasiMenit={post.durasiMenit}
        jumlahSoal={soal.length}
        totalPoin={totalPoin}
        nomor={indeks + 1}
        soal={aktif}
      />
    </div>
  )
}
