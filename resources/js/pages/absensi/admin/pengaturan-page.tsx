import * as React from "react"
import { useForm } from "@inertiajs/react"
import { toast } from "sonner"

import { api } from "@/lib/absensi/api"
import { useConfirm } from "@/hooks/use-confirm"
import type { JadwalHari, Settings } from "@/lib/absensi/types"
import { cn } from "@/lib/utils"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Checkbox } from "@/components/ui/checkbox"
import { Field, FieldGroup, FieldLabel } from "@/components/ui/field"
import { Input } from "@/components/ui/input"
import { TimePicker } from "@/components/absensi/time-picker"

const HARI_LABEL: Record<number, string> = {
  1: "Senin",
  2: "Selasa",
  3: "Rabu",
  4: "Kamis",
  5: "Jumat",
  6: "Sabtu",
  0: "Minggu",
}

interface Props {
  settings: Settings
  jadwal: JadwalHari[]
}

export function PengaturanPage({ settings, jadwal: initialJadwal }: Props) {
  const { confirm, confirmDialog } = useConfirm()
  const { data, setData, patch, processing, errors } = useForm({
    namaSekolah: settings.namaSekolah,
  })

  const [jadwal, setJadwal] = React.useState(initialJadwal)
  const [savedJadwal, setSavedJadwal] = React.useState(initialJadwal)
  const [savingJadwal, setSavingJadwal] = React.useState(false)

  React.useEffect(() => {
    setJadwal(initialJadwal)
    setSavedJadwal(initialJadwal)
  }, [initialJadwal])

  const isJadwalDirty = React.useMemo(
    () => JSON.stringify(jadwal) !== JSON.stringify(savedJadwal),
    [jadwal, savedJadwal]
  )

  function isRowDirty(hari: number): boolean {
    const current = jadwal.find((r) => r.hari === hari)
    const saved = savedJadwal.find((r) => r.hari === hari)
    return !saved || JSON.stringify(current) !== JSON.stringify(saved)
  }

  function handleSubmit(event: React.FormEvent) {
    event.preventDefault()
    patch("/pengaturan", {
      preserveScroll: true,
      onSuccess: () => toast.success("Pengaturan berhasil disimpan"),
    })
  }

  function updateJadwalRow(hari: number, patch: Partial<JadwalHari>) {
    setJadwal((rows) => rows.map((r) => (r.hari === hari ? { ...r, ...patch } : r)))
  }

  function defaultJadwalRow(hari: number): JadwalHari {
    return {
      hari,
      aktif: hari !== 0 && hari !== 6,
      jamMulaiAbsen: "07:00",
      jamMasuk: "07:00",
      batasTelat: "07:15",
      jamPulang: "15:30",
      jamBriefing: null,
      jamSelesaiBriefing: null,
    }
  }

  async function applyJadwalUpdates(rows: JadwalHari[], successMessage: string) {
    if (rows.length === 0) return

    setSavingJadwal(true)
    const results = await Promise.allSettled(
      rows.map((row) =>
        api.patch<JadwalHari>(`/pengaturan/jadwal/${row.hari}`, {
          aktif: row.aktif,
          jamMulaiAbsen: row.jamMulaiAbsen,
          jamMasuk: row.jamMasuk,
          batasTelat: row.batasTelat,
          jamPulang: row.jamPulang,
          jamBriefing: row.jamBriefing,
          jamSelesaiBriefing: row.jamSelesaiBriefing,
        })
      )
    )

    const succeeded: JadwalHari[] = []
    const failedLabels: string[] = []
    results.forEach((result, i) => {
      if (result.status === "fulfilled") {
        succeeded.push(result.value)
      } else {
        failedLabels.push(HARI_LABEL[rows[i].hari])
      }
    })

    if (succeeded.length > 0) {
      const merge = (current: JadwalHari[]) =>
        current.map((r) => succeeded.find((s) => s.hari === r.hari) ?? r)
      setJadwal(merge)
      setSavedJadwal(merge)
    }

    if (failedLabels.length === 0) {
      toast.success(successMessage)
    } else if (succeeded.length === 0) {
      toast.error(`Gagal menyimpan jadwal ${failedLabels.join(", ")}`)
    } else {
      toast.warning(`Sebagian tersimpan - gagal: ${failedLabels.join(", ")}`)
    }
    setSavingJadwal(false)
  }

  async function handleSaveJadwal() {
    const changed = jadwal.filter((row) => {
      const base = savedJadwal.find((s) => s.hari === row.hari)
      return !base || JSON.stringify(base) !== JSON.stringify(row)
    })
    await applyJadwalUpdates(changed, "Jadwal berhasil disimpan")
  }

  async function handleApplyToAllDays(sourceHari: number) {
    const source = jadwal.find((r) => r.hari === sourceHari)
    if (!source) return
    const ok = await confirm({
      title: `Terapkan jam ${HARI_LABEL[sourceHari]} ke semua hari lain?`,
      description: "Status Aktif tiap hari tidak ikut berubah.",
      confirmLabel: "Terapkan",
    })
    if (!ok) return

    setJadwal((rows) =>
      rows.map((r) =>
        r.hari === sourceHari
          ? r
          : {
              ...r,
              jamMulaiAbsen: source.jamMulaiAbsen,
              jamMasuk: source.jamMasuk,
              batasTelat: source.batasTelat,
              jamPulang: source.jamPulang,
              jamBriefing: source.jamBriefing,
              jamSelesaiBriefing: source.jamSelesaiBriefing,
            }
      )
    )
    toast.success(`Jam ${HARI_LABEL[sourceHari]} diterapkan ke semua hari - klik Simpan Jadwal untuk menyimpan`)
  }

  async function handleResetJadwal() {
    const ok = await confirm({
      title: "Reset jadwal mingguan ke pengaturan default?",
      description: "Semua jam dan status aktif yang sudah dikustomisasi akan hilang.",
      confirmLabel: "Reset",
      destructive: true,
    })
    if (!ok) return
    await applyJadwalUpdates(
      jadwal.map((row) => defaultJadwalRow(row.hari)),
      "Jadwal berhasil direset ke default"
    )
  }

  return (
    <div className="flex flex-col gap-4">
      {confirmDialog}
      <div>
        <h1 className="text-xl font-semibold">Pengaturan</h1>
        <p className="text-sm text-muted-foreground">Identitas sekolah dan jadwal kehadiran</p>
      </div>

      <Card className="max-w-lg">
        <CardHeader>
          <CardTitle className="text-base">Nama Sekolah</CardTitle>
        </CardHeader>
        <CardContent>
          <form onSubmit={handleSubmit}>
            <FieldGroup>
              <Field>
                <FieldLabel htmlFor="namaSekolah">Nama Sekolah</FieldLabel>
                <Input
                  id="namaSekolah"
                  value={data.namaSekolah}
                  onChange={(e) => setData("namaSekolah", e.target.value)}
                />
              </Field>
              {errors.namaSekolah && (
                <p className="text-sm text-destructive" role="alert">
                  {errors.namaSekolah}
                </p>
              )}
              <Field>
                <Button type="submit" disabled={processing} className="w-fit">
                  {processing ? "Menyimpan..." : "Simpan"}
                </Button>
              </Field>
            </FieldGroup>
          </form>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">Jadwal Mingguan</CardTitle>
        </CardHeader>
        <CardContent className="flex flex-col gap-3">
          <p className="text-sm text-muted-foreground">
            Atur jam mulai absen, jam masuk, batas telat, dan jam pulang untuk tiap hari -
            matikan toggle untuk hari libur (misal Sabtu/Minggu), atau hidupkan kalau sekolah
            masuk hari itu.
            <br />
            <span className="font-medium text-foreground">Jam Mulai Absen</span> = absen masuk
            paling cepat bisa dilakukan mulai jam ini (belum bisa absen sama sekali sebelumnya).
            Antara <span className="font-medium text-foreground">Jam Masuk</span> sampai{" "}
            <span className="font-medium text-foreground">Batas Telat</span> masih dihitung
            Hadir, lewat dari Batas Telat baru dihitung Telat.{" "}
            <span className="font-medium text-foreground">Jam Pulang</span> = absen pulang baru
            bisa dilakukan mulai jam itu. Centang{" "}
            <span className="font-medium text-foreground">Jam Briefing</span> kalau ada briefing
            hari itu - menu Absen Briefing otomatis kebuka mulai jam yang diisi sampai{" "}
            <span className="font-medium text-foreground">Jam Selesai Briefing</span>. Sudah
            atur jam untuk satu hari? Klik{" "}
            <span className="font-medium text-foreground">Terapkan ke Semua Hari</span> di bawah
            kolom hari itu biar gak perlu isi satu-satu.
          </p>
          <div className="overflow-x-auto rounded-lg border">
            <div className="flex divide-x">
              {jadwal.map((row) => (
                <div key={row.hari} className="flex min-w-44 flex-1 flex-col">
                  <div className="flex flex-col items-center gap-1.5 border-b bg-muted/30 px-2 py-3">
                    <span className="text-sm font-semibold">{HARI_LABEL[row.hari]}</span>
                    <div className="flex items-center gap-1.5">
                      <Checkbox
                        id={`aktif-${row.hari}`}
                        checked={row.aktif}
                        onCheckedChange={(checked) => updateJadwalRow(row.hari, { aktif: checked })}
                      />
                      <FieldLabel
                        htmlFor={`aktif-${row.hari}`}
                        className="text-xs font-normal text-muted-foreground"
                      >
                        Aktif
                      </FieldLabel>
                    </div>
                  </div>
                  <div className="flex flex-1 flex-col gap-3 p-3">
                    <Field>
                      <FieldLabel htmlFor={`mulai-${row.hari}`} className="text-xs text-muted-foreground">
                        Jam Mulai Absen
                      </FieldLabel>
                      <TimePicker
                        id={`mulai-${row.hari}`}
                        value={row.jamMulaiAbsen}
                        onChange={(v) => updateJadwalRow(row.hari, { jamMulaiAbsen: v })}
                        className="w-full"
                      />
                    </Field>
                    <Field>
                      <FieldLabel htmlFor={`masuk-${row.hari}`} className="text-xs text-muted-foreground">
                        Jam Masuk
                      </FieldLabel>
                      <TimePicker
                        id={`masuk-${row.hari}`}
                        value={row.jamMasuk}
                        onChange={(v) => updateJadwalRow(row.hari, { jamMasuk: v })}
                        className="w-full"
                      />
                    </Field>
                    <Field>
                      <FieldLabel htmlFor={`telat-${row.hari}`} className="text-xs text-muted-foreground">
                        Batas Telat
                      </FieldLabel>
                      <TimePicker
                        id={`telat-${row.hari}`}
                        value={row.batasTelat}
                        onChange={(v) => updateJadwalRow(row.hari, { batasTelat: v })}
                        className="w-full"
                      />
                    </Field>
                    <Field>
                      <FieldLabel htmlFor={`pulang-${row.hari}`} className="text-xs text-muted-foreground">
                        Jam Pulang
                      </FieldLabel>
                      <TimePicker
                        id={`pulang-${row.hari}`}
                        value={row.jamPulang}
                        onChange={(v) => updateJadwalRow(row.hari, { jamPulang: v })}
                        className="w-full"
                      />
                    </Field>
                    <Field>
                      <div className="flex items-center gap-1.5">
                        <Checkbox
                          id={`briefing-toggle-${row.hari}`}
                          checked={row.jamBriefing !== null}
                          onCheckedChange={(checked) =>
                            updateJadwalRow(
                              row.hari,
                              checked
                                ? { jamBriefing: "12:45", jamSelesaiBriefing: "13:15" }
                                : { jamBriefing: null, jamSelesaiBriefing: null }
                            )
                          }
                        />
                        <FieldLabel
                          htmlFor={`briefing-toggle-${row.hari}`}
                          className="text-xs text-muted-foreground"
                        >
                          Jam Briefing
                        </FieldLabel>
                      </div>
                      <TimePicker
                        id={`briefing-${row.hari}`}
                        value={row.jamBriefing ?? "12:45"}
                        onChange={(v) => updateJadwalRow(row.hari, { jamBriefing: v })}
                        className={cn("w-full", row.jamBriefing === null && "pointer-events-none opacity-50")}
                      />
                    </Field>
                    <Field>
                      <FieldLabel htmlFor={`selesai-briefing-${row.hari}`} className="text-xs text-muted-foreground">
                        Jam Selesai Briefing
                      </FieldLabel>
                      <TimePicker
                        id={`selesai-briefing-${row.hari}`}
                        value={row.jamSelesaiBriefing ?? "13:15"}
                        onChange={(v) => updateJadwalRow(row.hari, { jamSelesaiBriefing: v })}
                        className={cn("w-full", row.jamBriefing === null && "pointer-events-none opacity-50")}
                      />
                    </Field>
                    {isRowDirty(row.hari) && (
                      <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="mt-auto w-full"
                        onClick={() => handleApplyToAllDays(row.hari)}
                      >
                        Terapkan ke Semua Hari
                      </Button>
                    )}
                  </div>
                </div>
              ))}
            </div>
          </div>
          <div className="flex flex-wrap gap-2">
            <Button
              type="button"
              disabled={!isJadwalDirty || savingJadwal}
              onClick={handleSaveJadwal}
              className="w-fit"
            >
              {savingJadwal ? "Menyimpan..." : "Simpan Jadwal"}
            </Button>
            <Button
              type="button"
              variant="outline"
              disabled={savingJadwal}
              onClick={handleResetJadwal}
              className="w-fit"
            >
              Reset
            </Button>
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
