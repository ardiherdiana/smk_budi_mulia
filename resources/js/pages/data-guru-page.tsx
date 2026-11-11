import * as React from "react"
import { Head, router, useForm } from "@inertiajs/react"
import { toast } from "sonner"
import { Eye, Flame, Pencil, Plus, QrCode, RefreshCw, Trash2, Upload } from "lucide-react"

import { HeaderMandiri } from "@/components/header-mandiri"
import { useAuth } from "@/context/auth-context"
import { api, ApiError, assetUrl } from "@/lib/absensi/api"
import { useConfirm } from "@/hooks/use-confirm"
import type { Guru, GuruAttendanceDetail } from "@/lib/absensi/types"
import { initials } from "@/lib/utils"
import { startOfMonthIso } from "@/lib/absensi/attendance-format"
import { AttendanceHeatmap } from "@/components/absensi/attendance-heatmap"
import { GuruQrCode } from "@/components/absensi/guru-qr-code"
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import { Badge } from "@/components/ui/badge"
import { TombolAksi } from "@/components/tombol-aksi"
import { Button } from "@/components/ui/button"
import { DataPagination } from "@/components/data-pagination"
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import { Field, FieldDescription, FieldGroup, FieldLabel } from "@/components/ui/field"
import { Input } from "@/components/ui/input"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"

function GuruAvatar({ guru, size }: { guru: Pick<Guru, "nama" | "fotoUrl">; size?: "sm" | "default" | "lg" }) {
  return (
    <Avatar size={size}>
      {guru.fotoUrl && <AvatarImage src={assetUrl(guru.fotoUrl)} alt={guru.nama} />}
      <AvatarFallback>{initials(guru.nama)}</AvatarFallback>
    </Avatar>
  )
}

const MAX_FOTO_BYTES = 2 * 1024 * 1024

interface FormState {
  id: string | null
  nama: string
  noHp: string
  mapel: string
  password: string
  aktif: boolean
  fotoUrl: string | null
  role: "GURU" | "KEPSEK"
}

const EMPTY_FORM: FormState = {
  id: null,
  nama: "",
  noHp: "",
  mapel: "",
  password: "",
  aktif: true,
  fotoUrl: null,
  role: "GURU",
}

interface Props {
  guruList: Guru[]
  search: string | null
}

function GuruPage({ guruList, search: initialSearch }: Props) {
  const { user } = useAuth()
  const isAdmin = user?.role === "ADMIN"
  const { confirm, confirmDialog } = useConfirm()
  const [search, setSearch] = React.useState(initialSearch ?? "")
  const [dialogOpen, setDialogOpen] = React.useState(false)
  const [uploadingPhoto, setUploadingPhoto] = React.useState(false)
  const photoInputRef = React.useRef<HTMLInputElement>(null)
  const [page, setPage] = React.useState(1)
  const [pageSize, setPageSize] = React.useState(50)
  const [detailFor, setDetailFor] = React.useState<Guru | null>(null)
  const [detail, setDetail] = React.useState<GuruAttendanceDetail | null>(null)
  const [qrFor, setQrFor] = React.useState<Guru | null>(null)
  const [regenerating, setRegenerating] = React.useState(false)

  const { data, setData, post, patch, processing, errors, reset, clearErrors, transform } =
    useForm<FormState>(EMPTY_FORM)

  const isFirstSearch = React.useRef(true)
  React.useEffect(() => {
    if (isFirstSearch.current) {
      isFirstSearch.current = false
      return
    }
    const timer = setTimeout(() => {
      router.get(
        "/data-guru",
        search ? { search } : {},
        { preserveState: true, replace: true, only: ["guruList", "search"] }
      )
    }, 300)
    return () => clearTimeout(timer)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [search])

  React.useEffect(() => {
    setPage(1)
  }, [search])

  React.useEffect(() => {
    if (!data.id) return
    const updated = guruList.find((g) => g.id === data.id)
    if (updated && updated.fotoUrl !== data.fotoUrl) {
      setData("fotoUrl", updated.fotoUrl)
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [guruList])

  const paginatedList = guruList.slice((page - 1) * pageSize, page * pageSize)

  function openCreate() {
    clearErrors()
    reset()
    setDialogOpen(true)
  }

  function openDetail(guru: Guru) {
    setDetailFor(guru)
    setDetail(null)
    api.get<GuruAttendanceDetail>(`/data-guru/${guru.id}/detail`).then(setDetail)
  }

  function openEdit(guru: Guru) {
    clearErrors()
    setData({
      id: guru.id,
      nama: guru.nama,
      noHp: guru.noHp ?? "",
      mapel: guru.mapel ?? "",
      password: "",
      aktif: guru.aktif,
      fotoUrl: guru.fotoUrl,
      role: guru.user.role === "KEPSEK" ? "KEPSEK" : "GURU",
    })
    setDialogOpen(true)
  }

  function handlePhotoChange(event: React.ChangeEvent<HTMLInputElement>) {
    const file = event.target.files?.[0]
    event.target.value = ""
    if (!file || !data.id) return

    if (file.size > MAX_FOTO_BYTES) {
      toast.error("Ukuran foto maksimal 2MB")
      return
    }

    const body = new FormData()
    body.append("foto", file)

    setUploadingPhoto(true)
    router.post(`/data-guru/${data.id}/foto`, body, {
      forceFormData: true,
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => toast.success("Foto berhasil diperbarui"),
      onError: (errs) => toast.error(Object.values(errs)[0] ?? "Gagal mengunggah foto"),
      onFinish: () => setUploadingPhoto(false),
    })
  }

  function handleSubmit(event: React.FormEvent) {
    event.preventDefault()
    clearErrors()

    transform((formData) => ({
      nama: formData.nama,
      noHp: formData.noHp || undefined,
      mapel: formData.mapel || undefined,
      role: formData.role,
      ...(formData.id
        ? { aktif: formData.aktif, password: formData.password || undefined }
        : { password: formData.password }),
    }))

    if (data.id) {
      patch(`/data-guru/${data.id}`, {
        preserveScroll: true,
        onSuccess: () => {
          setDialogOpen(false)
          toast.success("Data guru berhasil diperbarui")
        },
      })
    } else {
      post("/data-guru", {
        preserveScroll: true,
        onSuccess: () => {
          setDialogOpen(false)
          toast.success("Guru baru berhasil ditambahkan")
        },
      })
    }
  }

  async function handleRegenerateQr(guru: Guru) {
    const ok = await confirm({
      title: `Buat ulang QR untuk "${guru.nama}"?`,
      description: "QR lama (di HP/kartu lama) tidak akan berfungsi lagi.",
      confirmLabel: "Buat ulang QR",
      destructive: true,
    })
    if (!ok) return
    setRegenerating(true)
    try {
      const updated = await api.post<Guru>(`/data-guru/${guru.id}/qr/regenerate`)
      setQrFor(updated)
      router.reload({ only: ["guruList"] })
      toast.success("QR baru berhasil dibuat")
    } catch (err) {
      toast.error(err instanceof ApiError ? err.message : "Gagal membuat ulang QR")
    } finally {
      setRegenerating(false)
    }
  }

  async function handleDelete(guru: Guru) {
    const ok = await confirm({
      title: `Hapus akun guru "${guru.nama}"?`,
      description: "Tindakan ini tidak bisa dibatalkan.",
      confirmLabel: "Hapus",
      destructive: true,
    })
    if (!ok) return
    router.delete(`/data-guru/${guru.id}`, {
      preserveScroll: true,
      onSuccess: () => toast.success("Akun guru berhasil dihapus"),
      onError: () => toast.error("Gagal menghapus akun guru"),
    })
  }

  return (
    <div className="flex flex-col gap-4">
      {confirmDialog}
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-xl font-semibold">Data Guru</h1>
          <p className="text-sm text-muted-foreground">
            {isAdmin
              ? "Data master guru untuk semua modul: akun, foto, dan QR absen"
              : "Daftar guru untuk semua modul: akun, foto, dan QR absen"}
          </p>
        </div>
        {isAdmin && (
          <Button onClick={openCreate}>
            <Plus /> Tambah Guru
          </Button>
        )}
      </div>

      <Input
        placeholder="Cari nama..."
        value={search}
        onChange={(e) => setSearch(e.target.value)}
        className="max-w-sm"
      />

      <div className="overflow-x-auto rounded-lg border">
        <Table kartu>
          <TableHeader>
            <TableRow>
              <TableHead className="w-10">No</TableHead>
              <TableHead className="w-10"></TableHead>
              <TableHead>Username</TableHead>
              <TableHead>Nama</TableHead>
              <TableHead>Mapel</TableHead>
              <TableHead>No HP</TableHead>
              <TableHead>Status</TableHead>
              <TableHead className="text-right">Aksi</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {guruList.length === 0 ? (
              <TableRow>
                <TableCell colSpan={8} className="text-center text-muted-foreground">
                  Belum ada data guru
                </TableCell>
              </TableRow>
            ) : (
              paginatedList.map((guru, index) => (
                <TableRow key={guru.id}>
                  <TableCell kartu="sembunyi" className="text-muted-foreground">
                    {(page - 1) * pageSize + index + 1}
                  </TableCell>
                  <TableCell kartu="sembunyi">
                    <GuruAvatar guru={guru} />
                  </TableCell>
                  <TableCell className="font-mono text-xs">{guru.user.username}</TableCell>
                  <TableCell kartu="judul">
                    <div className="flex items-center gap-2">
                      {/* Di ponsel kolom foto disembunyikan; fotonya tampil di samping nama. */}
                      <span className="md:hidden">
                        <GuruAvatar guru={guru} />
                      </span>
                      {guru.nama}
                      {guru.user.role === "KEPSEK" && <Badge variant="secondary">Kepsek</Badge>}
                    </div>
                  </TableCell>
                  <TableCell>{guru.mapel ?? "-"}</TableCell>
                  <TableCell>{guru.noHp ?? "-"}</TableCell>
                  <TableCell>
                    <Badge variant={guru.aktif ? "success" : "destructive"}>
                      {guru.aktif ? "Aktif" : "Nonaktif"}
                    </Badge>
                  </TableCell>
                  <TableCell kartu="aksi" className="text-right">
                    <div className="flex justify-end gap-1">
                      {isAdmin && (
                        <TombolAksi jenis="edit" onClick={() => openEdit(guru)}>
                          <Pencil />
                        </TombolAksi>
                      )}
                      <TombolAksi jenis="lihat" onClick={() => openDetail(guru)}>
                        <Eye />
                      </TombolAksi>
                      <TombolAksi label="QR" aria-label="Kode QR" onClick={() => setQrFor(guru)}>
                        <QrCode />
                      </TombolAksi>
                      {isAdmin && (
                        <TombolAksi jenis="hapus" onClick={() => handleDelete(guru)}>
                          <Trash2 />
                        </TombolAksi>
                      )}
                    </div>
                  </TableCell>
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>
      </div>

      <DataPagination
        page={page}
        pageSize={pageSize}
        total={guruList.length}
        onPageChange={setPage}
        onPageSizeChange={(size) => {
          setPageSize(size)
          setPage(1)
        }}
      />

      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{data.id ? "Edit Guru" : "Tambah Guru"}</DialogTitle>
          </DialogHeader>
          <form onSubmit={handleSubmit}>
            <FieldGroup>
              {data.id && (
                <Field orientation="horizontal">
                  <GuruAvatar guru={{ nama: data.nama, fotoUrl: data.fotoUrl }} size="lg" />
                  <input
                    ref={photoInputRef}
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    className="hidden"
                    onChange={handlePhotoChange}
                  />
                  <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    disabled={uploadingPhoto}
                    onClick={() => photoInputRef.current?.click()}
                  >
                    <Upload /> {uploadingPhoto ? "Mengunggah..." : "Ganti Foto"}
                  </Button>
                </Field>
              )}
              <Field>
                <FieldLabel htmlFor="nama">Nama</FieldLabel>
                <Input
                  id="nama"
                  required
                  value={data.nama}
                  onChange={(e) => setData("nama", e.target.value)}
                />
                {!data.id && (
                  <FieldDescription>
                    Username login dibuat otomatis dari nama ini (2 kata pertama, huruf kecil)
                  </FieldDescription>
                )}
              </Field>
              <Field>
                <FieldLabel htmlFor="role">Role</FieldLabel>
                <Select
                  items={{ GURU: "Guru", KEPSEK: "Kepala Sekolah" }}
                  value={data.role}
                  onValueChange={(v) => v && setData("role", v as "GURU" | "KEPSEK")}
                >
                  <SelectTrigger id="role" className="w-full">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="GURU">Guru</SelectItem>
                    <SelectItem value="KEPSEK">Kepala Sekolah</SelectItem>
                  </SelectContent>
                </Select>
                <FieldDescription>
                  Kepala Sekolah dapat absen seperti guru, plus akses rekap, approve izin/sakit,
                  notifikasi, hari libur, dan pengaturan
                </FieldDescription>
              </Field>
              <div className="grid gap-4 sm:grid-cols-2">
                <Field>
                  <FieldLabel htmlFor="mapel">Mapel</FieldLabel>
                  <Input
                    id="mapel"
                    value={data.mapel}
                    onChange={(e) => setData("mapel", e.target.value)}
                  />
                </Field>
                <Field>
                  <FieldLabel htmlFor="noHp">No HP</FieldLabel>
                  <Input
                    id="noHp"
                    value={data.noHp}
                    onChange={(e) => setData("noHp", e.target.value)}
                  />
                </Field>
              </div>
              <Field>
                <FieldLabel htmlFor="password">
                  {data.id ? "Ganti Password (opsional)" : "Password Awal"}
                </FieldLabel>
                <Input
                  id="password"
                  type="text"
                  required={!data.id}
                  minLength={6}
                  placeholder={data.id ? "Kosongkan jika tidak diubah" : "Minimal 6 karakter"}
                  value={data.password}
                  onChange={(e) => setData("password", e.target.value)}
                />
              </Field>
              {data.id && (
                <Field orientation="horizontal">
                  <input
                    id="aktif"
                    type="checkbox"
                    className="size-4"
                    checked={data.aktif}
                    onChange={(e) => setData("aktif", e.target.checked)}
                  />
                  <FieldLabel htmlFor="aktif" className="font-normal">
                    Akun aktif (bisa login &amp; absen)
                  </FieldLabel>
                </Field>
              )}
              {(errors.nama ?? errors.password ?? errors.role ?? (errors as Record<string, string>).message) && (
                <p className="text-sm text-destructive" role="alert">
                  {errors.nama ?? errors.password ?? errors.role ?? (errors as Record<string, string>).message}
                </p>
              )}
              <DialogFooter>
                <Button type="submit" disabled={processing}>
                  {processing ? "Menyimpan..." : "Simpan"}
                </Button>
              </DialogFooter>
            </FieldGroup>
          </form>
        </DialogContent>
      </Dialog>

      <Dialog open={detailFor !== null} onOpenChange={(open) => !open && setDetailFor(null)}>
        <DialogContent>
          {detailFor && (
            <>
              <DialogHeader>
                <DialogTitle>Detail Kehadiran</DialogTitle>
              </DialogHeader>
              <div className="flex items-center gap-3">
                <GuruAvatar guru={detailFor} size="lg" />
                <div>
                  <div className="font-medium">{detailFor.nama}</div>
                  <div className="text-xs text-muted-foreground">{detailFor.user.username}</div>
                </div>
              </div>

              {detail === null ? (
                <p className="py-6 text-center text-sm text-muted-foreground">Memuat...</p>
              ) : (
                <>
                  <div className="flex items-center gap-3 rounded-lg border border-orange-500/20 bg-orange-500/5 px-4 py-3">
                    <div className="flex size-9 shrink-0 items-center justify-center rounded-full bg-orange-500/10 text-orange-500">
                      <Flame className="size-4" />
                    </div>
                    <div className="text-sm">
                      <span className="text-lg font-semibold">{detail.streak}</span> hari
                      beruntun kehadiran
                    </div>
                  </div>

                  <div>
                    <p className="mb-2 text-sm font-medium">
                      Kehadiran bulan{" "}
                      {new Date().toLocaleDateString("id-ID", { month: "long", year: "numeric" })}
                    </p>
                    <AttendanceHeatmap monthStart={startOfMonthIso()} days={detail.days} />
                  </div>
                </>
              )}
            </>
          )}
        </DialogContent>
      </Dialog>

      <Dialog open={qrFor !== null} onOpenChange={(open) => !open && setQrFor(null)}>
        <DialogContent>
          {qrFor && (
            <>
              <DialogHeader>
                <DialogTitle>QR Absen - {qrFor.nama}</DialogTitle>
              </DialogHeader>
              <div className="flex flex-col items-center gap-4 py-2">
                <GuruQrCode qrToken={qrFor.qrToken} nama={qrFor.nama} />
                <p className="text-center text-sm text-muted-foreground">
                  Tunjukkan QR ini ke alat scan di sekolah untuk absen masuk/pulang. Guru juga bisa
                  lihat QR yang sama di akun mereka sendiri (menu "QR Saya").
                </p>
                {isAdmin && (
                  <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    disabled={regenerating}
                    onClick={() => handleRegenerateQr(qrFor)}
                  >
                    <RefreshCw /> {regenerating ? "Memproses..." : "Buat Ulang QR"}
                  </Button>
                )}
              </div>
            </>
          )}
        </DialogContent>
      </Dialog>
    </div>
  )
}

/**
 * Data Guru: data master guru dan kepala sekolah untuk semua modul (akun, foto, QR absen, mapel, no. HP). Dibuka dari
 * kartu Data Guru di Menu Utama sebagai halaman mandiri (tanpa sidebar modul); isinya sama dengan Data Guru Classroom.
 */
export function DataGuruPage(props: Props) {
  return (
    <div className="flex min-h-svh flex-col">
      <Head title="Data Guru - SMK Budi Mulia" />
      <HeaderMandiri judul="Data Guru" />
      <main className="flex-1 p-4 md:p-6">
        <GuruPage {...props} />
      </main>
    </div>
  )
}
