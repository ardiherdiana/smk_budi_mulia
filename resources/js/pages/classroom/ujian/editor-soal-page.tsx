import * as React from "react"
import { Link, router } from "@inertiajs/react"
import { ArrowDown, ArrowLeft, ArrowUp, Copy, Lock, Plus, Trash2, X } from "lucide-react"

import type { ItemGambar, Soal } from "@/lib/classroom/types"
import {
  HURUF,
  LABEL_TIPE_SOAL,
  PETUNJUK_TIPE_SOAL,
  URUTAN_TIPE,
  bagiPoin,
  pecahJawaban,
  type TipeSoal,
} from "@/lib/classroom/soal"
import { formatAngka } from "@/lib/classroom/format"
import { cn } from "@/lib/classroom/utils"
import { useConfirm } from "@/hooks/use-confirm"
import { ImporSoal, type DrafSoal, type HasilImpor } from "@/components/classroom/ujian/impor-soal"
import { GambarPilihanEditor, GambarSoalEditor } from "@/components/classroom/ujian/unggah-gambar"
import { Button } from "@/components/ui/button"
import { Card, CardContent } from "@/components/ui/card"
import { Checkbox } from "@/components/ui/checkbox"
import { Field, FieldDescription, FieldGroup, FieldLabel } from "@/components/ui/field"
import { Input } from "@/components/ui/input"
import { Textarea } from "@/components/ui/textarea"

const MAKS_OPSI = 8
/** Sama dengan `classroom.max_gambar_soal` di server. */
const MAKS_GAMBAR = 4
const OPSI_AWAL: Record<TipeSoal, number> = { PG: 5, PGK_MCMA: 5, PGK_KATEGORI: 3, ESSAY: 0 }

/**
 * Satu kartu soal di editor. `kunci` per tipe: PG [indeks], MCMA [indeks...], Kategori ["1"/"0"/"" per pernyataan].
 * `opsiGambar` selalu sejajar dengan `opsi`; pernyataan Kategori tidak bergambar.
 */
interface FormSoal {
  kunciUnik: string
  id: string | null
  tipe: TipeSoal
  teks: string
  gambar: ItemGambar[]
  kompetensi: string
  subKompetensi: string
  poin: string
  opsi: string[]
  opsiGambar: (ItemGambar | null)[]
  kunci: string[]
}

/** Soal dari server: gambar sudah berupa path + URL. */
type SoalServer = Omit<Soal, "urut" | "gambar" | "opsiGambar"> & { gambar: ItemGambar[]; opsiGambar: (ItemGambar | null)[] }

interface Props {
  post: { id: string; judul: string | null; kelasId: string }
  kelas: { id: string; mapel: string; rombel: string }
  /** Ujian ini juga diterbitkan ke ruang kelas lain; soal yang disimpan di sini ikut disalin ke kelas yang belum dikerjakan. */
  kelasLain: { rombel: string; terkunci: boolean }[]
  terkunci: boolean
  /** Poin soal sekarang dibagi rata ke total 100 (atau belum ada soal). Poin manual lama dibuka dalam mode manual. */
  bagiRata: boolean
  soal: SoalServer[]
}

let hitung = 0
const kunciBaru = () => `soal-${++hitung}`

const tanpaGambar = (jumlah: number): null[] => Array.from({ length: jumlah }, () => null)

function kosong(tipe: TipeSoal = "PG"): FormSoal {
  return {
    kunciUnik: kunciBaru(),
    id: null,
    tipe,
    teks: "",
    gambar: [],
    kompetensi: "",
    subKompetensi: "",
    poin: "1",
    opsi: Array.from({ length: OPSI_AWAL[tipe] }, () => ""),
    opsiGambar: tanpaGambar(OPSI_AWAL[tipe]),
    kunci: tipe === "PGK_KATEGORI" ? Array.from({ length: OPSI_AWAL[tipe] }, () => "") : [],
  }
}

function dariServer(s: SoalServer): FormSoal {
  const opsi = s.opsi ? [...s.opsi] : []

  return {
    kunciUnik: kunciBaru(),
    id: s.id,
    tipe: s.tipe,
    teks: s.teks,
    gambar: s.gambar ?? [],
    kompetensi: s.kompetensi ?? "",
    subKompetensi: s.subKompetensi ?? "",
    poin: String(s.poin),
    opsi,
    opsiGambar: opsi.map((_, i) => s.opsiGambar?.[i] ?? null),
    kunci: s.tipe === "ESSAY" ? [] : pecahJawaban(s.kunci),
  }
}

/** Draf dari impor berkas menjadi kartu baru; jumlah pilihan dilengkapi sampai minimal bawaan bentuknya. */
function dariDraf(d: DrafSoal): FormSoal {
  const opsi = d.tipe === "ESSAY" ? [] : d.opsi.slice(0, MAKS_OPSI)
  while (d.tipe !== "ESSAY" && opsi.length < Math.max(2, OPSI_AWAL[d.tipe])) opsi.push("")

  return {
    kunciUnik: kunciBaru(),
    id: null,
    tipe: d.tipe,
    teks: d.teks,
    gambar: d.gambar.slice(0, MAKS_GAMBAR),
    kompetensi: d.kompetensi,
    subKompetensi: d.subKompetensi,
    poin: "1",
    opsi,
    opsiGambar: opsi.map((_, i) => d.opsiGambar[i] ?? null),
    kunci: d.tipe === "ESSAY" ? [] : pecahJawaban(d.kunci),
  }
}

/** Kartu yang belum disentuh sama sekali (kartu awal editor kosong); impor menggantikannya, bukan menambah di bawahnya. */
function sepenuhnyaKosong(f: FormSoal): boolean {
  return (
    f.teks.trim() === "" &&
    f.kompetensi.trim() === "" &&
    f.subKompetensi.trim() === "" &&
    f.gambar.length === 0 &&
    f.opsi.every((o) => o.trim() === "") &&
    f.opsiGambar.every((g) => g === null)
  )
}

/** Bentuk yang dikirim ke server; kunci Kategori dijaga sejajar dengan pernyataan. `poin` sudah poin efektif (bagi rata atau manual). */
function kePayload(f: FormSoal, poin: string | number) {
  return {
    id: f.id,
    tipe: f.tipe,
    teks: f.teks,
    gambar: f.gambar.map((g) => g.path),
    kompetensi: f.kompetensi,
    subKompetensi: f.subKompetensi,
    poin,
    ...(f.tipe === "ESSAY"
      ? {}
      : {
          opsi: f.opsi,
          opsiGambar: f.tipe === "PGK_KATEGORI" ? [] : f.opsi.map((_, i) => f.opsiGambar[i]?.path ?? null),
          kunci: f.tipe === "PGK_KATEGORI" ? f.opsi.map((_, i) => f.kunci[i] ?? "").join(",") : f.kunci.join(","),
        }),
  }
}

/**
 * Cara skor soal dihitung dari poinnya, sama dengan `Soal::skorUntuk` di server: PG benar/salah; MCMA tiap jawaban benar
 * yang dipilih bernilai poin / jumlah kunci dan tiap jawaban salah mengurangi sebesar itu (minimal 0); Kategori tiap
 * pernyataan yang tepat bernilai poin / jumlah pernyataan; Essay dinilai guru.
 */
function rincianSkor(s: FormSoal, poin: number): string {
  if (s.tipe === "PG") return "Jawaban benar mendapat poin penuh, salah mendapat 0."
  if (s.tipe === "ESSAY") return `Dinilai guru, maksimal ${formatAngka(poin)} poin.`
  if (s.tipe === "PGK_MCMA") {
    if (s.kunci.length === 0) return "Tandai jawaban yang benar untuk melihat nilai tiap jawaban."
    const per = formatAngka(poin / s.kunci.length)
    return `Dinilai parsial: tiap jawaban benar yang dipilih = ${per} poin, tiap jawaban salah mengurangi ${per} poin (nilai soal minimal 0).`
  }
  const jumlah = s.opsi.filter((o) => o.trim() !== "").length
  if (jumlah < 2) return "Isi pernyataan untuk melihat nilai tiap pernyataan."
  return `Dinilai parsial: tiap pernyataan yang dijawab tepat = ${formatAngka(poin / jumlah)} poin.`
}

/** Seluruh soal dalam bentuk kiriman; mode bagi rata memakai poin hasil bagi, bukan isian poin tiap kartu. */
function semuaPayload(list: FormSoal[], bagiRata: boolean) {
  const rata = bagiPoin(list.length)
  return list.map((f, i) => kePayload(f, bagiRata ? rata[i] : f.poin))
}

const snapshot = (list: FormSoal[], bagiRata: boolean) => JSON.stringify({ bagiRata, soal: semuaPayload(list, bagiRata) })

export function EditorSoalPage({ post, kelas, kelasLain, terkunci, bagiRata: bagiRataAwal, soal }: Props) {
  const { confirm, confirmDialog } = useConfirm()
  const [awal] = React.useState<FormSoal[]>(() => {
    const dariDb = soal.map(dariServer)
    return dariDb.length ? dariDb : [kosong()]
  })
  const [daftar, setDaftar] = React.useState<FormSoal[]>(awal)
  const daftarTerbaru = React.useRef(daftar)
  React.useEffect(() => {
    daftarTerbaru.current = daftar
  }, [daftar])
  const [errors, setErrors] = React.useState<Record<string, string>>({})
  const [memproses, setMemproses] = React.useState(false)
  const akhir = React.useRef<HTMLDivElement>(null)

  const [bagiRata, setBagiRata] = React.useState(bagiRataAwal)
  const [snapshotAwal] = React.useState(() => snapshot(awal, bagiRataAwal))
  const berubah = snapshot(daftar, bagiRata) !== snapshotAwal && !terkunci

  // Peringatan bawaan peramban bila halaman ditutup saat ada perubahan yang belum disimpan.
  React.useEffect(() => {
    if (!berubah) return
    const cegah = (e: BeforeUnloadEvent) => e.preventDefault()
    window.addEventListener("beforeunload", cegah)
    return () => window.removeEventListener("beforeunload", cegah)
  }, [berubah])

  const poinRata = bagiPoin(daftar.length)
  /** Poin yang benar-benar berlaku untuk kartu ke-`index` pada mode sekarang. */
  const poinSoal = (index: number) => (bagiRata ? poinRata[index] : Number(daftar[index].poin) || 0)
  const totalPoin = daftar.reduce((jumlah, _, i) => jumlah + poinSoal(i), 0)
  const bukanSeratus = !bagiRata && Math.abs(totalPoin - 100) > 0.001
  const kisaranPoin =
    poinRata.length === 0 || poinRata[0] === poinRata[poinRata.length - 1]
      ? formatAngka(poinRata[0] ?? 0)
      : `${formatAngka(poinRata[0])} sampai ${formatAngka(poinRata[poinRata.length - 1])}`

  /** Mematikan bagi rata mengisi poin manual dengan pembagian saat ini, supaya angkanya tidak melompat. */
  function ubahMode(rata: boolean) {
    if (!rata) setDaftar((list) => list.map((s, i) => ({ ...s, poin: String(poinRata[i] ?? s.poin) })))
    setBagiRata(rata)
  }

  function ubah(index: number, perubahan: Partial<FormSoal>) {
    setDaftar((list) => list.map((s, i) => (i === index ? { ...s, ...perubahan } : s)))
  }

  function ubahTipe(index: number, tipe: TipeSoal) {
    const lama = daftar[index]
    if (lama.tipe === tipe) return
    // Pilihan yang sudah terisi dipertahankan antar PG dan MCMA; kunci selalu dikosongkan karena maknanya berbeda.
    const bisaDipakaiLagi = lama.tipe !== "ESSAY" && tipe !== "ESSAY" && lama.tipe !== "PGK_KATEGORI" && tipe !== "PGK_KATEGORI"
    const opsi = bisaDipakaiLagi ? lama.opsi : kosong(tipe).opsi
    ubah(index, {
      tipe,
      opsi,
      opsiGambar: bisaDipakaiLagi ? lama.opsiGambar : tanpaGambar(opsi.length),
      kunci: tipe === "PGK_KATEGORI" ? opsi.map(() => "") : [],
    })
  }

  function tambah() {
    setDaftar((list) => [...list, kosong(list.at(-1)?.tipe ?? "PG")])
    requestAnimationFrame(() => akhir.current?.scrollIntoView({ behavior: "smooth", block: "center" }))
  }

  function duplikat(index: number) {
    setDaftar((list) => {
      const asal = list[index]
      const salinan: FormSoal = {
        ...asal,
        kunciUnik: kunciBaru(),
        id: null,
        gambar: [...asal.gambar],
        opsi: [...asal.opsi],
        opsiGambar: [...asal.opsiGambar],
        kunci: [...asal.kunci],
      }
      return [...list.slice(0, index + 1), salinan, ...list.slice(index + 1)]
    })
  }

  function pindah(index: number, arah: -1 | 1) {
    setDaftar((list) => {
      const tujuan = index + arah
      if (tujuan < 0 || tujuan >= list.length) return list
      const baru = [...list]
      ;[baru[index], baru[tujuan]] = [baru[tujuan], baru[index]]
      return baru
    })
  }

  async function hapus(index: number) {
    const s = daftar[index]
    if (!sepenuhnyaKosong(s)) {
      const ok = await confirm({ title: `Hapus soal nomor ${index + 1}?`, confirmLabel: "Hapus", destructive: true })
      if (!ok) return
    }
    setDaftar((list) => (list.length === 1 ? [kosong()] : list.filter((_, i) => i !== index)))
  }

  function setOpsi(index: number, o: number, nilai: string) {
    ubah(index, { opsi: daftar[index].opsi.map((x, i) => (i === o ? nilai : x)) })
  }

  function tambahOpsi(index: number) {
    const s = daftar[index]
    ubah(index, {
      opsi: [...s.opsi, ""],
      opsiGambar: [...s.opsiGambar, null],
      kunci: s.tipe === "PGK_KATEGORI" ? [...s.opsi.map((_, i) => s.kunci[i] ?? ""), ""] : s.kunci,
    })
  }

  function hapusOpsi(index: number, o: number) {
    const s = daftar[index]
    const geser = (k: string) => (Number(k) > o ? String(Number(k) - 1) : k)
    ubah(index, {
      opsi: s.opsi.filter((_, i) => i !== o),
      opsiGambar: s.opsiGambar.filter((_, i) => i !== o),
      kunci:
        s.tipe === "PGK_KATEGORI"
          ? s.opsi.map((_, i) => s.kunci[i] ?? "").filter((_, i) => i !== o)
          : s.kunci.filter((k) => Number(k) !== o).map(geser),
    })
  }

  function setGambarOpsi(index: number, o: number, item: ItemGambar | null) {
    ubah(index, { opsiGambar: daftar[index].opsiGambar.map((g, i) => (i === o ? item : g)) })
  }

  /** Soal hasil impor ditambahkan di bawah soal yang ada; kartu awal yang masih kosong digantikan. */
  function tambahDariImpor(hasil: HasilImpor) {
    // Membaca PDF butuh beberapa detik; pakai daftar terbaru, bukan yang tertangkap saat unggahan dimulai.
    const sekarang = daftarTerbaru.current
    const baru = hasil.soal.map(dariDraf)
    const dasar = sekarang.length === 1 && sepenuhnyaKosong(sekarang[0]) ? [] : sekarang
    setDaftar([...dasar, ...baru])
    requestAnimationFrame(() => document.getElementById(`soal-${dasar.length}`)?.scrollIntoView({ behavior: "smooth", block: "start" }))
  }

  function setKunciPg(index: number, o: number) {
    ubah(index, { kunci: [String(o)] })
  }

  function togelMcma(index: number, o: number) {
    const s = daftar[index]
    const ada = s.kunci.includes(String(o))
    ubah(index, { kunci: ada ? s.kunci.filter((k) => k !== String(o)) : [...s.kunci, String(o)] })
  }

  function setKategori(index: number, o: number, nilai: "1" | "0") {
    const s = daftar[index]
    ubah(index, { kunci: s.opsi.map((_, i) => (i === o ? nilai : (s.kunci[i] ?? ""))) })
  }

  function simpan(event: React.FormEvent) {
    event.preventDefault()
    router.put(
      `/classroom/post/${post.id}/soal`,
      { bagiRata, soal: semuaPayload(daftar, bagiRata) },
      {
        preserveScroll: true,
        onStart: () => {
          setMemproses(true)
          setErrors({})
        },
        onError: (e) => {
          setErrors(e)
          const pertama = Object.keys(e).find((k) => k.startsWith("soal."))
          const nomor = pertama ? Number(pertama.split(".")[1]) : null
          if (nomor !== null) document.getElementById(`soal-${nomor}`)?.scrollIntoView({ behavior: "smooth", block: "center" })
        },
        onFinish: () => setMemproses(false),
      }
    )
  }

  const galat = (index: number, kolom: string) => errors[`soal.${index}.${kolom}`]

  return (
    <form onSubmit={simpan} className="flex w-full flex-col gap-4 pb-4">
      {confirmDialog}

      <Button type="button" variant="ghost" size="sm" className="w-fit" render={<Link href={`/classroom/post/${post.id}`} />}>
        <ArrowLeft /> {post.judul ?? "Ujian"} · {kelas.rombel}
      </Button>

      <div className="flex flex-wrap items-end justify-between gap-2">
        <div>
          <h1 className="text-xl font-semibold">Soal ujian</h1>
          <p className="text-sm text-muted-foreground">
            {kelas.mapel} · {kelas.rombel}
          </p>
        </div>
        <p className="text-sm text-muted-foreground">
          {daftar.length} soal · total {formatAngka(totalPoin)} poin
        </p>
      </div>

      {!terkunci && (
        <div className="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-lg border p-3">
          <label className="flex min-w-0 flex-1 basis-72 cursor-pointer items-start gap-3 text-sm">
            <Checkbox checked={bagiRata} onCheckedChange={(v) => ubahMode(v === true)} className="mt-0.5" />
            <span className="flex flex-col gap-0.5">
              <span className="font-medium">Bagi rata poin (total 100)</span>
              <span className="text-muted-foreground">
                {bagiRata
                  ? `Tiap soal ${kisaranPoin} poin, dihitung ulang otomatis saat soal ditambah, dihapus, atau dipindah. Matikan untuk mengatur poin tiap soal sendiri.`
                  : "Poin diatur manual per soal. Nyalakan untuk membagi rata ke total 100."}
              </span>
            </span>
          </label>
          {bukanSeratus && (
            <div className="flex flex-wrap items-center gap-2 text-sm">
              <span className="font-medium">Total {formatAngka(totalPoin)} poin, bukan 100</span>
              <Button type="button" size="sm" variant="outline" onClick={() => ubahMode(true)}>
                Bagi rata
              </Button>
            </div>
          )}
        </div>
      )}

      {!terkunci && <ImporSoal postId={post.id} nonaktif={memproses} onSelesai={tambahDariImpor} />}

      {terkunci && (
        <div className="flex items-start gap-2 rounded-lg border bg-muted/40 p-3 text-sm text-muted-foreground">
          <Lock className="mt-0.5 size-4 shrink-0" />
          Soal dikunci karena sudah ada siswa yang mengerjakan, supaya nilai yang sudah keluar tidak berubah.
        </div>
      )}

      {kelasLain.length > 0 && (
        <div className="rounded-lg border bg-muted/40 p-3 text-sm text-muted-foreground">
          Ujian ini juga diterbitkan ke {kelasLain.map((k) => k.rombel).join(", ")}. Soal yang disimpan di sini otomatis disalin ke kelas
          tersebut
          {kelasLain.some((k) => k.terkunci) &&
            `, kecuali ${kelasLain
              .filter((k) => k.terkunci)
              .map((k) => k.rombel)
              .join(", ")} yang soalnya sudah dikunci karena ada siswa yang mengerjakan`}
          .
        </div>
      )}

      <fieldset disabled={terkunci || memproses} className="flex min-w-0 flex-col gap-4">
        {daftar.map((s, index) => (
          <Card key={s.kunciUnik} id={`soal-${index}`}>
            <CardContent className="flex flex-col gap-4">
              <div className="flex flex-wrap items-center gap-2">
                <span className="flex size-7 shrink-0 items-center justify-center rounded-full bg-muted text-sm font-semibold">{index + 1}</span>
                <div className="flex flex-wrap gap-1.5" role="radiogroup" aria-label={`Bentuk soal ${index + 1}`}>
                  {URUTAN_TIPE.map((tipe) => (
                    <button
                      key={tipe}
                      type="button"
                      role="radio"
                      aria-checked={s.tipe === tipe}
                      onClick={() => ubahTipe(index, tipe)}
                      title={PETUNJUK_TIPE_SOAL[tipe]}
                      className={cn(
                        "rounded-lg border px-3 py-1.5 text-sm font-medium transition-colors max-md:py-2.5",
                        s.tipe === tipe ? "border-primary bg-primary/10 text-primary" : "text-muted-foreground hover:bg-muted"
                      )}
                    >
                      {LABEL_TIPE_SOAL[tipe]}
                    </button>
                  ))}
                </div>
                <div className="ml-auto flex gap-1">
                  <Button type="button" variant="ghost" size="icon-sm" onClick={() => pindah(index, -1)} disabled={index === 0} aria-label={`Naikkan soal ${index + 1}`}>
                    <ArrowUp />
                  </Button>
                  <Button type="button" variant="ghost" size="icon-sm" onClick={() => pindah(index, 1)} disabled={index === daftar.length - 1} aria-label={`Turunkan soal ${index + 1}`}>
                    <ArrowDown />
                  </Button>
                  <Button type="button" variant="ghost" size="icon-sm" onClick={() => duplikat(index)} aria-label={`Gandakan soal ${index + 1}`}>
                    <Copy />
                  </Button>
                  <Button type="button" variant="ghost" size="icon-sm" onClick={() => hapus(index)} aria-label={`Hapus soal ${index + 1}`}>
                    <Trash2 />
                  </Button>
                </div>
              </div>

              <FieldGroup>
                <div className="grid gap-4 sm:grid-cols-2">
                  <Field>
                    <FieldLabel htmlFor={`kompetensi-${index}`}>Kompetensi</FieldLabel>
                    <Textarea
                      id={`kompetensi-${index}`}
                      value={s.kompetensi}
                      onChange={(e) => ubah(index, { kompetensi: e.target.value })}
                      rows={3}
                      maxLength={2000}
                    />
                  </Field>
                  <Field>
                    <FieldLabel htmlFor={`sub-${index}`}>Sub kompetensi</FieldLabel>
                    <Textarea
                      id={`sub-${index}`}
                      value={s.subKompetensi}
                      onChange={(e) => ubah(index, { subKompetensi: e.target.value })}
                      rows={3}
                      maxLength={2000}
                    />
                  </Field>
                </div>
                <FieldDescription>Kompetensi dan sub kompetensi hanya terlihat oleh guru.</FieldDescription>

                <Field data-invalid={!!galat(index, "teks")}>
                  <FieldLabel htmlFor={`teks-${index}`}>Soal</FieldLabel>
                  <Textarea
                    id={`teks-${index}`}
                    value={s.teks}
                    onChange={(e) => ubah(index, { teks: e.target.value })}
                    rows={5}
                    maxLength={10000}
                    aria-invalid={!!galat(index, "teks")}
                    placeholder={s.tipe === "PGK_KATEGORI" ? "Kasus atau pengantar sebelum pernyataan" : "Tulis kasus dan pertanyaan di sini"}
                  />
                  {galat(index, "teks") && <p className="text-sm text-destructive">{galat(index, "teks")}</p>}
                </Field>

                <Field>
                  <FieldLabel>Gambar soal</FieldLabel>
                  <FieldDescription>Diagram, topologi, atau tangkapan layar yang menyertai pertanyaan (maksimal {MAKS_GAMBAR}).</FieldDescription>
                  <GambarSoalEditor
                    postId={post.id}
                    nomor={index + 1}
                    nilai={s.gambar}
                    maks={MAKS_GAMBAR}
                    onUbah={(g) => ubah(index, { gambar: g })}
                    galat={galat(index, "gambar")}
                  />
                </Field>

                {s.tipe !== "ESSAY" && (
                  <Field data-invalid={!!galat(index, "opsi") || !!galat(index, "kunci")}>
                    <FieldLabel>{s.tipe === "PGK_KATEGORI" ? "Pernyataan" : "Pilihan jawaban"}</FieldLabel>
                    <FieldDescription>
                      {s.tipe === "PG" && "Pilih bulat di sebelah kiri untuk menandai satu jawaban yang benar. Pilihan boleh berupa gambar saja."}
                      {s.tipe === "PGK_MCMA" && "Centang semua jawaban yang benar. Pilihan boleh berupa gambar saja."}
                      {s.tipe === "PGK_KATEGORI" && "Tentukan Benar atau Salah untuk setiap pernyataan."}
                    </FieldDescription>
                    <div className="flex flex-col gap-2">
                      {s.opsi.map((opsi, o) => (
                        <div key={o} className="flex flex-wrap items-center gap-2 sm:flex-nowrap">
                          {s.tipe === "PG" && (
                            <input
                              type="radio"
                              name={`kunci-${s.kunciUnik}`}
                              checked={s.kunci[0] === String(o)}
                              onChange={() => setKunciPg(index, o)}
                              className="size-4 shrink-0 accent-primary max-md:size-6"
                              aria-label={`Jawaban benar: pilihan ${HURUF[o]}`}
                            />
                          )}
                          {s.tipe === "PGK_MCMA" && (
                            <input
                              type="checkbox"
                              checked={s.kunci.includes(String(o))}
                              onChange={() => togelMcma(index, o)}
                              className="size-4 shrink-0 accent-primary max-md:size-6"
                              aria-label={`Jawaban benar: pilihan ${HURUF[o]}`}
                            />
                          )}
                          <span className="w-5 shrink-0 text-sm font-medium text-muted-foreground">{HURUF[o]}.</span>
                          <Input
                            value={opsi}
                            onChange={(e) => setOpsi(index, o, e.target.value)}
                            placeholder={s.tipe === "PGK_KATEGORI" ? `Pernyataan ${HURUF[o]}` : `Pilihan ${HURUF[o]}`}
                            maxLength={1000}
                            className="min-w-0 flex-1"
                          />
                          {s.tipe !== "PGK_KATEGORI" && (
                            <GambarPilihanEditor
                              postId={post.id}
                              huruf={HURUF[o]}
                              nilai={s.opsiGambar[o] ?? null}
                              onUbah={(item) => setGambarOpsi(index, o, item)}
                            />
                          )}
                          {s.tipe === "PGK_KATEGORI" && (
                            <div className="flex shrink-0 gap-1" role="radiogroup" aria-label={`Kunci pernyataan ${HURUF[o]}`}>
                              {(["1", "0"] as const).map((nilai) => (
                                <button
                                  key={nilai}
                                  type="button"
                                  role="radio"
                                  aria-checked={s.kunci[o] === nilai}
                                  onClick={() => setKategori(index, o, nilai)}
                                  className={cn(
                                    "min-h-9 rounded-lg border px-3 text-sm font-medium transition-colors max-md:min-h-11",
                                    s.kunci[o] === nilai ? "border-primary bg-primary/10 text-primary" : "text-muted-foreground hover:bg-muted"
                                  )}
                                >
                                  {nilai === "1" ? "Benar" : "Salah"}
                                </button>
                              ))}
                            </div>
                          )}
                          {s.opsi.length > 2 && (
                            <Button type="button" variant="ghost" size="icon-sm" onClick={() => hapusOpsi(index, o)} aria-label={`Hapus ${HURUF[o]}`}>
                              <X />
                            </Button>
                          )}
                        </div>
                      ))}
                    </div>
                    {s.opsi.length < MAKS_OPSI && (
                      <Button type="button" variant="outline" size="sm" className="w-fit" onClick={() => tambahOpsi(index)}>
                        <Plus /> {s.tipe === "PGK_KATEGORI" ? "Tambah pernyataan" : "Tambah pilihan"}
                      </Button>
                    )}
                    {(galat(index, "opsi") || galat(index, "kunci") || galat(index, "opsiGambar")) && (
                      <p className="text-sm text-destructive">{galat(index, "opsi") ?? galat(index, "kunci") ?? galat(index, "opsiGambar")}</p>
                    )}
                  </Field>
                )}

                <Field data-invalid={!!galat(index, "poin")}>
                  <FieldLabel htmlFor={`poin-${index}`}>Poin</FieldLabel>
                  {bagiRata ? (
                    <p id={`poin-${index}`} className="text-sm">
                      <span className="font-medium">{formatAngka(poinRata[index])}</span> poin <span className="text-muted-foreground">(dibagi rata)</span>
                    </p>
                  ) : (
                    <Input
                      id={`poin-${index}`}
                      type="number"
                      min={0.5}
                      max={100}
                      step="any"
                      value={s.poin}
                      onChange={(e) => ubah(index, { poin: e.target.value })}
                      aria-invalid={!!galat(index, "poin")}
                      className="sm:max-w-32"
                    />
                  )}
                  <FieldDescription>{rincianSkor(s, poinSoal(index))}</FieldDescription>
                  {galat(index, "poin") && <p className="text-sm text-destructive">{galat(index, "poin")}</p>}
                </Field>
              </FieldGroup>
            </CardContent>
          </Card>
        ))}

        <div ref={akhir}>
          <Button type="button" variant="outline" className="w-full border-dashed" onClick={tambah}>
            <Plus /> Tambah soal
          </Button>
        </div>
      </fieldset>

      {!terkunci && (
        <div className="sticky bottom-0 z-20 border-t bg-background py-3">
          <div className="flex w-full items-center justify-between gap-3">
            <p className="text-sm text-muted-foreground">{berubah ? "Ada perubahan yang belum disimpan" : "Semua perubahan tersimpan"}</p>
            <div className="flex gap-2">
              <Button type="button" variant="outline" render={<Link href={`/classroom/post/${post.id}`} />}>
                Batal
              </Button>
              <Button type="submit" disabled={memproses || !berubah}>
                {memproses ? "Menyimpan..." : "Simpan semua"}
              </Button>
            </div>
          </div>
        </div>
      )}
    </form>
  )
}
