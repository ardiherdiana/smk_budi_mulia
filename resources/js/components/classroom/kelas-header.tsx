import { Link, router, usePage } from "@inertiajs/react"
import { Archive, ArchiveRestore, Trash2 } from "lucide-react"

import type { KelasHeader as KelasHeaderData } from "@/lib/classroom/types"
import { useConfirm } from "@/hooks/use-confirm"
import { warnaKelas } from "@/lib/classroom/kelas-warna"
import { cn } from "@/lib/classroom/utils"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"

const TAB = [
  { label: "Forum", path: "" },
  { label: "Tugas", path: "/tugas" },
  { label: "Anggota", path: "/anggota" },
  { label: "Nilai", path: "/nilai" },
] as const

/** Judul ruang kelas dengan tab Forum / Tugas / Anggota / Nilai, dipakai semua halaman di dalam satu kelas. */
export function KelasHeader({ kelas }: { kelas: KelasHeaderData }) {
  const { url } = usePage()
  const pathname = url.split("?")[0]
  const { confirm, confirmDialog } = useConfirm()
  const dasar = `/classroom/kelas/${kelas.id}`

  async function arsip() {
    const ok = await confirm({
      title: kelas.diarsipkan ? `Pulihkan "${kelas.mapel}"?` : `Arsipkan "${kelas.mapel} - ${kelas.rombel}"?`,
      description: kelas.diarsipkan
        ? "Ruang kelas akan muncul lagi di daftar."
        : "Ruang kelas disembunyikan dari daftar dan tidak bisa menerima postingan atau pengumpulan baru. Data tetap tersimpan.",
      confirmLabel: kelas.diarsipkan ? "Pulihkan" : "Arsipkan",
    })
    if (ok) router.post(`${dasar}/arsip`)
  }

  async function hapus() {
    const ok = await confirm({
      title: `Hapus "${kelas.mapel} - ${kelas.rombel}" selamanya?`,
      description: "Semua materi, tugas, ujian, nilai, dan komentar di ruang kelas ini ikut terhapus dan tidak bisa dikembalikan.",
      confirmLabel: "Hapus selamanya",
      destructive: true,
    })
    if (ok) router.delete(dasar)
  }

  return (
    <div className="flex flex-col gap-1">
      {confirmDialog}
      <div className="flex min-w-0 items-center gap-3 px-1">
        <span className={cn("flex size-10 shrink-0 items-center justify-center rounded-full text-base font-medium", warnaKelas(kelas.id))}>
          {kelas.mapel.charAt(0).toUpperCase()}
        </span>
        <div className="min-w-0">
          <div className="flex flex-wrap items-center gap-2">
            <h1 className="truncate text-xl font-semibold">{kelas.mapel}</h1>
            {kelas.diarsipkan && <Badge variant="outline">Diarsipkan</Badge>}
          </div>
          <p className="truncate text-xs text-muted-foreground">
            {kelas.rombel} Â· {kelas.guru} Â· {kelas.tahunAjaran} Â· {kelas.jumlahSiswa} siswa
          </p>
        </div>
      </div>
      {kelas.deskripsi && <p className="max-w-2xl px-1 text-sm text-muted-foreground">{kelas.deskripsi}</p>}
      <div className="flex items-center justify-between gap-2 border-b">
        <nav className="flex gap-1 overflow-x-auto" aria-label="Menu ruang kelas">
          {TAB.map((tab) => {
            const href = `${dasar}${tab.path}`
            const aktif = pathname === href
            return (
              <Link
                key={tab.label}
                href={href}
                className={cn(
                  "relative px-4 py-3 text-sm font-medium whitespace-nowrap text-muted-foreground transition-colors hover:text-foreground",
                  aktif && "text-primary after:absolute after:inset-x-0 after:bottom-0 after:h-0.5 after:rounded-full after:bg-primary"
                )}
                aria-current={aktif ? "page" : undefined}
              >
                {tab.label}
              </Link>
            )
          })}
        </nav>
        {kelas.dapatDikelola && (
          <div className="flex shrink-0 gap-1">
            <Button variant="ghost" size="sm" onClick={arsip}>
              {kelas.diarsipkan ? <ArchiveRestore /> : <Archive />}
              {kelas.diarsipkan ? "Pulihkan" : "Arsipkan"}
            </Button>
            <Button variant="ghost" size="icon-sm" onClick={hapus} aria-label="Hapus ruang kelas">
              <Trash2 />
            </Button>
          </div>
        )}
      </div>
    </div>
  )
}
