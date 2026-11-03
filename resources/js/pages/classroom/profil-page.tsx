import * as React from "react"
import { router } from "@inertiajs/react"

import type { Role } from "@/lib/classroom/types"
import { initials } from "@/lib/classroom/utils"
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Field, FieldGroup, FieldLabel } from "@/components/ui/field"
import { Input } from "@/components/ui/input"

interface Props {
  akun: {
    username: string
    nama: string
    fotoUrl: string | null
    peran: Role
    nis: string | null
    rombel: string | null
    jumlahKelas: number | null
    dapatGantiPassword: boolean
  }
}

const LABEL_PERAN: Record<Role, string> = { ADMIN: "Admin", KEPSEK: "Kepala Sekolah", GURU: "Guru", SISWA: "Siswa" }

function Baris({ label, nilai }: { label: string; nilai: React.ReactNode }) {
  return (
    <div className="flex items-center justify-between gap-4 py-2 text-sm">
      <dt className="text-muted-foreground">{label}</dt>
      <dd className="font-medium">{nilai}</dd>
    </div>
  )
}

export function ProfilPage({ akun }: Props) {
  const [lama, setLama] = React.useState("")
  const [baru, setBaru] = React.useState("")
  const [errors, setErrors] = React.useState<Record<string, string>>({})
  const [memproses, setMemproses] = React.useState(false)

  function ganti(event: React.FormEvent) {
    event.preventDefault()
    router.post("/classroom/profil/password", { oldPassword: lama, newPassword: baru }, {
      preserveScroll: true,
      onStart: () => {
        setMemproses(true)
        setErrors({})
      },
      onSuccess: () => {
        setLama("")
        setBaru("")
      },
      onError: (e) => setErrors(e),
      onFinish: () => setMemproses(false),
    })
  }

  return (
    <div className="mx-auto flex w-full max-w-xl flex-col gap-4">
      <div>
        <h1 className="text-xl font-semibold">Profil</h1>
        <p className="text-sm text-muted-foreground">Informasi akun kamu</p>
      </div>

      <Card>
        <CardContent className="flex flex-col gap-4">
          <div className="flex items-center gap-4">
            <Avatar size="lg">
              {akun.fotoUrl && <AvatarImage src={akun.fotoUrl} alt="" className="object-top" />}
              <AvatarFallback>{initials(akun.nama)}</AvatarFallback>
            </Avatar>
            <div className="min-w-0">
              <p className="truncate text-lg font-semibold">{akun.nama}</p>
              <Badge variant="secondary">{LABEL_PERAN[akun.peran]}</Badge>
            </div>
          </div>
          <dl className="divide-y">
            <Baris label="Username" nilai={<span className="font-mono">{akun.username}</span>} />
            {akun.nis && <Baris label="NIS" nilai={<span className="font-mono">{akun.nis}</span>} />}
            {akun.rombel && <Baris label="Rombel" nilai={akun.rombel} />}
            {akun.jumlahKelas !== null && <Baris label="Ruang kelas aktif" nilai={akun.jumlahKelas} />}
          </dl>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Ganti Password</CardTitle>
          <CardDescription>
            {akun.dapatGantiPassword
              ? "Gunakan password yang mudah kamu ingat tapi sulit ditebak."
              : "Password akun ini dikelola di aplikasi absensi, jadi diganti di sana."}
          </CardDescription>
        </CardHeader>
        {akun.dapatGantiPassword && (
          <CardContent>
            <form onSubmit={ganti}>
              <FieldGroup>
                <Field data-invalid={!!errors.oldPassword}>
                  <FieldLabel htmlFor="pw-lama">Password lama</FieldLabel>
                  <Input
                    id="pw-lama"
                    type="password"
                    autoComplete="current-password"
                    value={lama}
                    onChange={(e) => setLama(e.target.value)}
                    required
                    aria-invalid={!!errors.oldPassword}
                  />
                  {errors.oldPassword && <p className="text-sm text-destructive">{errors.oldPassword}</p>}
                </Field>
                <Field data-invalid={!!errors.newPassword}>
                  <FieldLabel htmlFor="pw-baru">Password baru</FieldLabel>
                  <Input
                    id="pw-baru"
                    type="password"
                    autoComplete="new-password"
                    value={baru}
                    onChange={(e) => setBaru(e.target.value)}
                    minLength={6}
                    maxLength={100}
                    required
                    aria-invalid={!!errors.newPassword}
                  />
                  {errors.newPassword && <p className="text-sm text-destructive">{errors.newPassword}</p>}
                </Field>
                <Field>
                  <Button type="submit" disabled={memproses} className="w-fit">
                    {memproses ? "Menyimpan..." : "Ganti password"}
                  </Button>
                </Field>
              </FieldGroup>
            </form>
          </CardContent>
        )}
      </Card>
    </div>
  )
}
