import * as React from "react"
import { Head, Link, router, useForm, usePage } from "@inertiajs/react"
import { toast } from "sonner"
import { ArrowLeft, Download, LockOpen, MessageSquarePlus } from "lucide-react"

import { StatusBadge } from "@/components/supervisi/status-badge"
import { Button } from "@/components/ui/button"
import { Field, FieldLabel } from "@/components/ui/field"
import { Input } from "@/components/ui/input"
import { Textarea } from "@/components/ui/textarea"
import { useConfirm } from "@/hooks/use-confirm"
import { SENTUH, formatHariPanjang, formatNilai, jumlahSkor, pesanGalat, totalSkor } from "@/lib/supervisi/format"
import type { MenuSupervisi, SkorButir, SupervisiLembar } from "@/lib/supervisi/types"
import type { AuthUser } from "@/lib/types"
import { cn } from "@/lib/utils"

interface Props {
  supervisi: SupervisiLembar
  boleh: { isi: boolean; bukaKunci: boolean; pdf: boolean }
  pemilik: boolean
  skala: { maks: number; label: Record<string, string> }
}

interface IsianSkor {
  id: string
  skor: number | null
  catatan: string
}

type KunciTeks = "kelebihan" | "halDitingkatkan" | "rekomendasi"

interface FormLembar {
  kelebihan: string
  halDitingkatkan: string
  rekomendasi: string
  skor: IsianSkor[]
}

/** Baris 5-7 dokumen "Instrumen Observasi Pembelajaran Mendalam". */
const TEKS: { nomor: number; kunci: KunciTeks; label: string }[] = [
  { nomor: 5, kunci: "kelebihan", label: "Kelebihan Perencanaan" },
  { nomor: 6, kunci: "halDitingkatkan", label: "Hal yang perlu ditingkatkan" },
  { nomor: 7, kunci: "rekomendasi", label: "Rekomendasi Perbaikan" },
]

function TeksBaca({ isi }: { isi: string | null | undefined }) {
  return isi ? <p className="whitespace-pre-line">{isi}</p> : <p className="text-muted-foreground">Tidak diisi.</p>
}

/** Satu butir: uraian, tombol skor 1..maks, dan catatan opsional (kolom Catatan di dokumen). */
function KartuButir({
  butir,
  isian,
  maks,
  label,
  bisaIsi,
  onSkor,
  onCatatan,
}: {
  butir: SkorButir
  isian: IsianSkor | undefined
  maks: number
  label: Record<string, string>
  bisaIsi: boolean
  onSkor: (skor: number) => void
  onCatatan: (catatan: string) => void
}) {
  const skor = bisaIsi ? (isian?.skor ?? null) : butir.skor
  const catatan = bisaIsi ? (isian?.catatan ?? "") : (butir.catatan ?? "")
  const [tampilCatatan, setTampilCatatan] = React.useState(catatan !== "")
  const idUraian = `butir-${butir.id}`

  return (
    <li id={`kartu-${butir.id}`} className="flex scroll-mt-4 flex-col gap-3 rounded-lg border bg-card p-4">
      <p id={idUraian}>{butir.uraian}</p>

      {bisaIsi && (
        <div role="group" aria-labelledby={idUraian} className="flex gap-2">
          {Array.from({ length: maks }, (_, i) => i + 1).map((nilai) => {
            const dipilih = skor === nilai
            return (
              <Button
                key={nilai}
                type="button"
                variant="outline"
                aria-pressed={dipilih}
                aria-label={`Skor ${nilai}, ${label[nilai] ?? ""}`}
                title={label[nilai]}
                onClick={() => onSkor(nilai)}
                className={cn(
                  "h-11 flex-1 text-base tabular-nums sm:h-9 sm:max-w-16",
                  dipilih &&
                    "border-sidebar-primary bg-sidebar-primary text-sidebar-primary-foreground hover:bg-sidebar-primary/90 hover:text-sidebar-primary-foreground dark:bg-sidebar-primary"
                )}
              >
                {nilai}
              </Button>
            )
          })}
        </div>
      )}

      <div className="flex flex-wrap items-center justify-between gap-2 text-sm">
        <span className={cn(skor ? "font-medium" : "text-muted-foreground")}>
          {skor ? `Skor ${skor}: ${label[skor] ?? ""}` : "Belum diberi skor"}
        </span>
        {bisaIsi && !tampilCatatan && (
          <Button type="button" variant="ghost" size="sm" className={SENTUH} onClick={() => setTampilCatatan(true)}>
            <MessageSquarePlus /> Catatan
          </Button>
        )}
      </div>

      {bisaIsi && tampilCatatan && (
        <Input
          aria-label="Catatan butir"
          placeholder="Catatan untuk butir ini (opsional)"
          maxLength={500}
          value={catatan}
          onChange={(e) => onCatatan(e.target.value)}
          className={SENTUH}
        />
      )}
      {!bisaIsi && catatan && <p className="text-sm text-muted-foreground">Catatan: {catatan}</p>}
    </li>
  )
}

export function LembarPage({ supervisi, boleh, pemilik, skala }: Props) {
  const { supervisiMenu } = usePage<{ auth: { user: AuthUser | null }; supervisiMenu?: MenuSupervisi }>().props
  const { confirm, confirmDialog } = useConfirm()
  const p = supervisi.penilaian
  const bisaIsi = boleh.isi && p !== null

  const form = useForm<FormLembar>({
    kelebihan: p?.kelebihan ?? "",
    halDitingkatkan: p?.halDitingkatkan ?? "",
    rekomendasi: p?.rekomendasi ?? "",
    skor: (p?.skor ?? []).map((s) => ({ id: s.id, skor: s.skor, catatan: s.catatan ?? "" })),
  })

  const isianById = React.useMemo(() => new Map(form.data.skor.map((s) => [s.id, s])), [form.data.skor])
  const butir = React.useMemo(() => p?.skor ?? [], [p])
  const kategori = React.useMemo(() => [...new Set(butir.map((b) => b.kategori))], [butir])
  const pembagi = butir.length * skala.maks

  const skorSekarang = butir.map((b) => (bisaIsi ? (isianById.get(b.id)?.skor ?? null) : b.skor))
  const jumlah = bisaIsi ? jumlahSkor(skorSekarang) : supervisi.jumlahSkor
  const total = bisaIsi ? totalSkor(jumlah ?? 0, butir.length, skala.maks) : supervisi.totalSkor
  const terisi = skorSekarang.filter((s) => s !== null).length

  // Updater fungsional: ketukan beruntun sebelum render ulang tidak saling menimpa.
  function ubahSkor(id: string, ubah: Partial<IsianSkor>) {
    form.setData((d) => ({ ...d, skor: d.skor.map((s) => (s.id === id ? { ...s, ...ubah } : s)) }))
  }

  function kirimData() {
    form.transform((d) => ({
      ...d,
      skor: d.skor.map((s) => ({ id: s.id, skor: s.skor, catatan: s.catatan || null })),
    }))
  }

  function simpanDraf() {
    kirimData()
    form.put(`/supervisi/${supervisi.id}/lembar`, {
      preserveScroll: true,
      onError: (e) => toast.error(pesanGalat(e)),
    })
  }

  async function selesaikan() {
    const kosong = butir.find((b) => (isianById.get(b.id)?.skor ?? null) === null)
    if (kosong) {
      document.getElementById(`kartu-${kosong.id}`)?.scrollIntoView({ behavior: "smooth", block: "start" })
      toast.error(`Masih ada ${butir.length - terisi} butir yang belum diberi skor.`)
      return
    }
    const ok = await confirm({
      title: "Selesaikan supervisi?",
      description: `Jumlah Score ${jumlah}, Total Score ${formatNilai(total)}. Lembar dikunci dan ${supervisi.guru?.nama ?? "guru"} diberi tahu bahwa hasilnya bisa dibaca.`,
      confirmLabel: "Selesaikan",
    })
    if (!ok) return
    kirimData()
    form.post(`/supervisi/${supervisi.id}/selesai`, {
      preserveScroll: true,
      onError: (e) => toast.error(pesanGalat(e)),
    })
  }

  async function bukaKunci() {
    const ok = await confirm({
      title: "Buka kunci lembar?",
      description: "Jumlah dan Total Score dihapus sampai lembar diselesaikan lagi. Skor dan catatan tetap ada untuk dikoreksi.",
      confirmLabel: "Buka kunci",
    })
    if (!ok) return
    router.post(`/supervisi/${supervisi.id}/buka-kunci`, {}, { preserveScroll: true, onError: (e) => toast.error(pesanGalat(e)) })
  }

  const kembali = supervisiMenu?.kelola ? "/supervisi/jadwal" : pemilik ? "/supervisi/saya" : "/supervisi/tugas"

  const identitas: [string, string][] = [
    ["Penyusun Perencanaan", supervisi.guru?.nama ?? "-"],
    ["Mata Pelajaran", supervisi.mapel ?? "-"],
    ["Kelas", supervisi.rombel ?? "-"],
    ["Tanggal Supervisi", formatHariPanjang(supervisi.tanggal)],
    ["Pemberi Umpan Balik", supervisi.supervisor?.nama ?? "-"],
  ]

  return (
    <div className="flex flex-col gap-4">
      <Head title={`Supervisi ${supervisi.guru?.nama ?? ""}`} />
      {confirmDialog}

      <div>
        <Button variant="ghost" size="sm" className={cn(SENTUH, "-ml-2")} render={<Link href={kembali} />}>
          <ArrowLeft /> Kembali
        </Button>
      </div>

      <div className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
        <div className="min-w-0">
          <div className="flex flex-wrap items-center gap-2">
            <h1 className="text-xl font-semibold">Instrumen Observasi Pembelajaran Mendalam</h1>
            <StatusBadge status={supervisi.status} />
          </div>
          <dl className="mt-2 grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-sm">
            {identitas.map(([label, isi]) => (
              <React.Fragment key={label}>
                <dt className="font-medium">{label}</dt>
                <dd>: {isi}</dd>
              </React.Fragment>
            ))}
          </dl>
        </div>
        <div className="flex flex-wrap gap-2">
          {boleh.pdf && (
            <Button variant="outline" className={SENTUH} render={<a href={`/supervisi/${supervisi.id}/lembar.pdf`} />}>
              <Download /> {supervisi.status === "SELESAI" ? "Unduh PDF" : "Cetak lembar kosong"}
            </Button>
          )}
          {boleh.bukaKunci && (
            <Button variant="outline" className={SENTUH} onClick={bukaKunci}>
              <LockOpen /> Buka kunci
            </Button>
          )}
        </div>
      </div>

      {supervisi.status === "DIBATALKAN" && (
        <p className="rounded-lg border p-4 text-sm text-muted-foreground">Supervisi ini dibatalkan.</p>
      )}

      {p === null && supervisi.status === "DIJADWALKAN" && (
        <div className="rounded-lg border p-4 text-sm">
          <p className="font-medium">Penilaian tampil setelah Pemberi Umpan Balik menyelesaikan lembar.</p>
          <p className="mt-1 text-muted-foreground">
            Siapkan perencanaan pembelajaran (tujuan, langkah, dan asesmen pembelajaran) untuk ditelaah pada tanggal di atas.
          </p>
        </div>
      )}

      {supervisi.status === "SELESAI" && (
        <div className="grid grid-cols-2 gap-3 sm:max-w-md">
          {[
            { label: "Jumlah Score", nilai: `${supervisi.jumlahSkor ?? "-"} / ${pembagi || "-"}` },
            { label: "Total Score", nilai: formatNilai(supervisi.totalSkor) },
          ].map((t) => (
            <div key={t.label} className="rounded-lg border bg-card p-4">
              <p className="text-sm text-muted-foreground">{t.label}</p>
              <p className="mt-1 text-xl font-semibold tabular-nums">{t.nilai}</p>
            </div>
          ))}
        </div>
      )}

      {p && (
        <>
          <section className="rounded-lg border p-4 text-sm">
            <h2 className="font-medium">Petunjuk pengisian :</h2>
            <p className="mt-1 flex flex-wrap gap-x-6 gap-y-1">
              {Object.entries(skala.label).map(([nilai, label]) => (
                <span key={nilai}>
                  {nilai} : {label}
                </span>
              ))}
            </p>
          </section>

          {kategori.map((k, i) => (
            <section key={k} className="flex flex-col gap-2">
              <h2 className="font-semibold">
                {i + 1}. {k}
              </h2>
              <ul className="flex flex-col gap-2">
                {butir
                  .filter((b) => b.kategori === k)
                  .map((b) => (
                    <KartuButir
                      key={b.id}
                      butir={b}
                      isian={isianById.get(b.id)}
                      maks={skala.maks}
                      label={skala.label}
                      bisaIsi={bisaIsi}
                      onSkor={(skor) => ubahSkor(b.id, { skor })}
                      onCatatan={(catatan) => ubahSkor(b.id, { catatan })}
                    />
                  ))}
              </ul>
            </section>
          ))}

          <div className="flex items-center justify-between gap-3 rounded-lg border bg-card p-4">
            <span className="font-semibold">JUMLAH SCORE</span>
            <span className="font-semibold tabular-nums">
              {jumlah ?? "-"} / {pembagi}
            </span>
          </div>

          {TEKS.map((t) => (
            <Field key={t.kunci}>
              <FieldLabel htmlFor={t.kunci} className="font-semibold">
                {t.nomor}. {t.label}
              </FieldLabel>
              {bisaIsi ? (
                <Textarea
                  id={t.kunci}
                  rows={4}
                  maxLength={5000}
                  value={form.data[t.kunci]}
                  onChange={(e) => form.setData(t.kunci, e.target.value)}
                />
              ) : (
                <TeksBaca isi={p[t.kunci]} />
              )}
            </Field>
          ))}

          <div className="rounded-lg border bg-card p-4 text-sm">
            <p>
              <span className="font-semibold">TOTAL SCORE</span> = Jumlah Score / {pembagi} x 100
            </p>
            <p className="mt-1 text-lg font-semibold tabular-nums">= {formatNilai(total)}</p>
          </div>
        </>
      )}

      {bisaIsi && (
        <div className="sticky bottom-0 z-10 -mx-4 mt-2 flex flex-col gap-3 border-t bg-background px-4 py-3 sm:flex-row sm:items-center sm:justify-between md:-mx-6 md:px-6">
          <p className="text-sm">
            <span className="text-muted-foreground">Jumlah </span>
            <span className="font-medium tabular-nums">
              {jumlah}/{pembagi}
            </span>
            <span className="text-muted-foreground"> · Total </span>
            <span className="font-medium tabular-nums">{formatNilai(total)}</span>
            <span className="text-muted-foreground">
              {" "}
              · {terisi}/{butir.length} butir
            </span>
          </p>
          <div className="grid grid-cols-2 gap-2 sm:flex">
            <Button variant="outline" className={SENTUH} disabled={form.processing} onClick={simpanDraf}>
              Simpan draf
            </Button>
            <Button className={SENTUH} disabled={form.processing} onClick={selesaikan}>
              Selesaikan
            </Button>
          </div>
        </div>
      )}
    </div>
  )
}
