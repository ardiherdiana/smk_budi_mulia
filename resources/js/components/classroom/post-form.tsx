import type { Post, TipePost } from "@/lib/classroom/types"
import { keInputWaktu } from "@/lib/classroom/format"
import { LAMPIRAN_KOSONG, LampiranInput, type LampiranBaru } from "@/components/classroom/lampiran-input"
import { Checkbox } from "@/components/ui/checkbox"
import { Field, FieldDescription, FieldGroup, FieldLabel } from "@/components/ui/field"
import { Input } from "@/components/ui/input"
import { Textarea } from "@/components/ui/textarea"

export interface PostFormState {
  tipe: TipePost
  judul: string
  isi: string
  deadline: string
  poinMaks: string
  izinTerlambat: boolean
  durasiMenit: string
  mulaiAt: string
  selesaiAt: string
  acakSoal: boolean
  tampilkanNilai: boolean
  wajibToken: boolean
  lampiran: LampiranBaru
}

export function formKosong(tipe: TipePost = "PENGUMUMAN"): PostFormState {
  return {
    tipe,
    judul: "",
    isi: "",
    deadline: "",
    poinMaks: "100",
    izinTerlambat: true,
    durasiMenit: "60",
    mulaiAt: "",
    selesaiAt: "",
    acakSoal: false,
    tampilkanNilai: true,
    wajibToken: false,
    lampiran: LAMPIRAN_KOSONG,
  }
}

export function formDariPost(post: Post): PostFormState {
  return {
    tipe: post.tipe,
    judul: post.judul ?? "",
    isi: post.isi ?? "",
    deadline: keInputWaktu(post.deadline),
    poinMaks: String(post.poinMaks ?? 100),
    izinTerlambat: post.izinTerlambat,
    durasiMenit: String(post.durasiMenit ?? 60),
    mulaiAt: keInputWaktu(post.mulaiAt),
    selesaiAt: keInputWaktu(post.selesaiAt),
    acakSoal: post.acakSoal,
    tampilkanNilai: post.tampilkanNilai,
    wajibToken: post.wajibToken,
    lampiran: LAMPIRAN_KOSONG,
  }
}

/** Hanya kolom yang relevan untuk tipenya; boolean dikirim 1/0 supaya lolos aturan `boolean` Laravel di multipart. */
export function bentukPayload(form: PostFormState): Record<string, unknown> {
  const dasar: Record<string, unknown> = { isi: form.isi, berkas: form.lampiran.berkas, tautan: form.lampiran.tautan }

  switch (form.tipe) {
    case "PENGUMUMAN":
      return { ...dasar, berkas: [], tautan: [] }
    case "MATERI":
      return { ...dasar, judul: form.judul }
    case "TUGAS":
      return {
        ...dasar,
        judul: form.judul,
        deadline: form.deadline,
        poinMaks: form.poinMaks,
        izinTerlambat: form.izinTerlambat ? 1 : 0,
      }
    case "UJIAN":
      return {
        ...dasar,
        berkas: [],
        tautan: [],
        judul: form.judul,
        durasiMenit: form.durasiMenit,
        mulaiAt: form.mulaiAt,
        selesaiAt: form.selesaiAt,
        acakSoal: form.acakSoal ? 1 : 0,
        tampilkanNilai: form.tampilkanNilai ? 1 : 0,
        wajibToken: form.wajibToken ? 1 : 0,
      }
  }
}

interface PostFormFieldsProps {
  form: PostFormState
  onChange: (form: PostFormState) => void
  errors: Record<string, string>
}

export function PostFormFields({ form, onChange, errors }: PostFormFieldsProps) {
  const set = <K extends keyof PostFormState>(key: K, value: PostFormState[K]) => onChange({ ...form, [key]: value })
  const punyaJudul = form.tipe !== "PENGUMUMAN"
  const punyaLampiran = form.tipe === "MATERI" || form.tipe === "TUGAS"

  return (
    <FieldGroup>
      {punyaJudul && (
        <Field data-invalid={!!errors.judul}>
          <FieldLabel htmlFor="post-judul">Judul</FieldLabel>
          <Input
            id="post-judul"
            value={form.judul}
            onChange={(e) => set("judul", e.target.value)}
            placeholder={form.tipe === "UJIAN" ? "mis. Kuis 1: Dasar Jaringan" : "mis. Pengantar Jaringan Komputer"}
            aria-invalid={!!errors.judul}
            maxLength={255}
          />
          {errors.judul && <p className="text-sm text-destructive">{errors.judul}</p>}
        </Field>
      )}

      <Field data-invalid={!!errors.isi}>
        <FieldLabel htmlFor="post-isi">
          {form.tipe === "PENGUMUMAN" ? "Pengumuman" : form.tipe === "TUGAS" ? "Instruksi (opsional)" : "Deskripsi (opsional)"}
        </FieldLabel>
        <Textarea
          id="post-isi"
          value={form.isi}
          onChange={(e) => set("isi", e.target.value)}
          placeholder={form.tipe === "PENGUMUMAN" ? "Umumkan sesuatu ke kelas..." : undefined}
          rows={form.tipe === "PENGUMUMAN" ? 4 : 3}
          aria-invalid={!!errors.isi}
          maxLength={10000}
        />
        {errors.isi && <p className="text-sm text-destructive">{errors.isi}</p>}
      </Field>

      {form.tipe === "TUGAS" && (
        <>
          <div className="grid gap-4 sm:grid-cols-2">
            <Field data-invalid={!!errors.deadline}>
              <FieldLabel htmlFor="post-deadline">Tenggat (opsional)</FieldLabel>
              <Input
                id="post-deadline"
                type="datetime-local"
                value={form.deadline}
                onChange={(e) => set("deadline", e.target.value)}
                aria-invalid={!!errors.deadline}
              />
              {errors.deadline && <p className="text-sm text-destructive">{errors.deadline}</p>}
            </Field>
            <Field data-invalid={!!errors.poinMaks}>
              <FieldLabel htmlFor="post-poin">Poin maksimal</FieldLabel>
              <Input
                id="post-poin"
                type="number"
                min={1}
                max={1000}
                value={form.poinMaks}
                onChange={(e) => set("poinMaks", e.target.value)}
                aria-invalid={!!errors.poinMaks}
              />
              {errors.poinMaks && <p className="text-sm text-destructive">{errors.poinMaks}</p>}
            </Field>
          </div>
          <Field orientation="horizontal">
            <Checkbox
              id="post-terlambat"
              checked={form.izinTerlambat}
              onCheckedChange={(v) => set("izinTerlambat", v === true)}
            />
            <div className="flex flex-col gap-0.5">
              <FieldLabel htmlFor="post-terlambat">Terima pengumpulan terlambat</FieldLabel>
              <FieldDescription>Bila dimatikan, siswa tidak bisa mengumpulkan setelah tenggat.</FieldDescription>
            </div>
          </Field>
        </>
      )}

      {form.tipe === "UJIAN" && (
        <>
          <Field data-invalid={!!errors.durasiMenit}>
            <FieldLabel htmlFor="post-durasi">Durasi (menit)</FieldLabel>
            <Input
              id="post-durasi"
              type="number"
              min={1}
              max={300}
              value={form.durasiMenit}
              onChange={(e) => set("durasiMenit", e.target.value)}
              aria-invalid={!!errors.durasiMenit}
              className="sm:max-w-40"
            />
            {errors.durasiMenit && <p className="text-sm text-destructive">{errors.durasiMenit}</p>}
          </Field>
          <div className="grid gap-4 sm:grid-cols-2">
            <Field data-invalid={!!errors.mulaiAt}>
              <FieldLabel htmlFor="post-mulai">Dibuka (opsional)</FieldLabel>
              <Input
                id="post-mulai"
                type="datetime-local"
                value={form.mulaiAt}
                onChange={(e) => set("mulaiAt", e.target.value)}
                aria-invalid={!!errors.mulaiAt}
              />
              {errors.mulaiAt && <p className="text-sm text-destructive">{errors.mulaiAt}</p>}
            </Field>
            <Field data-invalid={!!errors.selesaiAt}>
              <FieldLabel htmlFor="post-selesai">Ditutup (opsional)</FieldLabel>
              <Input
                id="post-selesai"
                type="datetime-local"
                value={form.selesaiAt}
                onChange={(e) => set("selesaiAt", e.target.value)}
                aria-invalid={!!errors.selesaiAt}
              />
              {errors.selesaiAt && <p className="text-sm text-destructive">{errors.selesaiAt}</p>}
            </Field>
          </div>
          <Field orientation="horizontal">
            <Checkbox id="post-acak" checked={form.acakSoal} onCheckedChange={(v) => set("acakSoal", v === true)} />
            <FieldLabel htmlFor="post-acak">Acak urutan soal tiap siswa</FieldLabel>
          </Field>
          <Field orientation="horizontal">
            <Checkbox
              id="post-nilai"
              checked={form.tampilkanNilai}
              onCheckedChange={(v) => set("tampilkanNilai", v === true)}
            />
            <div className="flex flex-col gap-0.5">
              <FieldLabel htmlFor="post-nilai">Tampilkan nilai ke siswa setelah selesai</FieldLabel>
              <FieldDescription>Soal dan jawaban benar tidak pernah ditampilkan ke siswa.</FieldDescription>
            </div>
          </Field>
          <Field orientation="horizontal">
            <Checkbox id="post-token" checked={form.wajibToken} onCheckedChange={(v) => set("wajibToken", v === true)} />
            <div className="flex flex-col gap-0.5">
              <FieldLabel htmlFor="post-token">Wajib token untuk memulai</FieldLabel>
              <FieldDescription>
                Token dibuat otomatis dan tampil di halaman ujian. Siswa memasukkannya saat menekan Mulai.
              </FieldDescription>
            </div>
          </Field>
        </>
      )}

      {punyaLampiran && (
        <Field>
          <FieldLabel>Lampiran</FieldLabel>
          <LampiranInput
            value={form.lampiran}
            onChange={(v) => set("lampiran", v)}
            error={errors.berkas ?? errors["berkas.0"] ?? errors.tautan ?? errors["tautan.0"]}
          />
        </Field>
      )}
    </FieldGroup>
  )
}
