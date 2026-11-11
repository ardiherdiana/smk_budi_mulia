import { Head, router } from "@inertiajs/react"

import { DaftarSupervisi } from "@/components/supervisi/daftar-supervisi"
import { TahunAjaranSelect } from "@/components/supervisi/tahun-ajaran-select"
import type { SupervisiRingkas, TahunAjaranOpsi } from "@/lib/supervisi/types"

interface Props {
  mode: "saya" | "tugas"
  list: SupervisiRingkas[]
  tahunAjaran: TahunAjaranOpsi[]
  tahunAjaranId: string | null
}

const TEKS = {
  saya: {
    judul: "Supervisi Saya",
    keterangan: "Jadwal dan hasil supervisi Anda. Hasil bisa dibaca setelah supervisor menyelesaikan lembarnya.",
    kosong: "Belum ada jadwal supervisi untuk Anda di tahun ajaran ini.",
  },
  tugas: {
    judul: "Tugas Supervisi",
    keterangan: "Guru yang ditugaskan kepada Anda untuk disupervisi. Buka lembarnya untuk mengisi penilaian.",
    kosong: "Belum ada guru yang ditugaskan kepada Anda di tahun ajaran ini.",
  },
}

export function DaftarPage({ mode, list, tahunAjaran, tahunAjaranId }: Props) {
  const teks = TEKS[mode]

  return (
    <div className="flex flex-col gap-4">
      <Head title={`${teks.judul} - Supervisi`} />
      <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <h1 className="text-xl font-semibold">{teks.judul}</h1>
          <p className="text-sm text-muted-foreground">{teks.keterangan}</p>
        </div>
        <TahunAjaranSelect
          list={tahunAjaran}
          value={tahunAjaranId}
          onChange={(id) => router.get(`/supervisi/${mode}`, { tahunAjaranId: id }, { preserveScroll: true })}
        />
      </div>

      <DaftarSupervisi
        list={list}
        tampilGuru={mode === "tugas"}
        tampilSupervisor={mode === "saya"}
        kosong={teks.kosong}
      />
    </div>
  )
}
