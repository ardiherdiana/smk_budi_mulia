import type { KelasHeader as KelasHeaderData } from "@/lib/classroom/types"
import { initials } from "@/lib/classroom/utils"
import { KelasHeader } from "@/components/classroom/kelas-header"
import { Avatar, AvatarFallback } from "@/components/ui/avatar"
import { Badge } from "@/components/ui/badge"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"

interface Props {
  kelas: KelasHeaderData
  guru: string
  waliKelas: string | null
  siswa: { id: string; nama: string; nis: string | null }[]
}

function Orang({ nama, keterangan }: { nama: string; keterangan?: string | null }) {
  return (
    <li className="flex items-center gap-3 py-2">
      <Avatar>
        <AvatarFallback>{initials(nama)}</AvatarFallback>
      </Avatar>
      <span className="min-w-0 flex-1 truncate text-sm font-medium">{nama}</span>
      {keterangan && <span className="shrink-0 font-mono text-xs text-muted-foreground">{keterangan}</span>}
    </li>
  )
}

export function AnggotaPage({ kelas, guru, waliKelas, siswa }: Props) {
  return (
    <div className="flex flex-col gap-4">
      <KelasHeader kelas={kelas} />

      <div className="flex w-full flex-col gap-4">
        <Card>
          <CardHeader>
            <CardTitle>Guru</CardTitle>
          </CardHeader>
          <CardContent>
            <ul className="divide-y">
              <Orang nama={guru} />
              {waliKelas && waliKelas !== guru && (
                <li className="flex items-center gap-3 py-2">
                  <Avatar>
                    <AvatarFallback>{initials(waliKelas)}</AvatarFallback>
                  </Avatar>
                  <span className="min-w-0 flex-1 truncate text-sm font-medium">{waliKelas}</span>
                  <Badge variant="secondary">Wali kelas</Badge>
                </li>
              )}
            </ul>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Siswa ({siswa.length})</CardTitle>
          </CardHeader>
          <CardContent>
            {siswa.length === 0 ? (
              <p className="py-4 text-center text-sm text-muted-foreground">Belum ada siswa di rombel {kelas.rombel}</p>
            ) : (
              <ul className="divide-y">
                {siswa.map((s) => (
                  <Orang key={s.id} nama={s.nama} keterangan={s.nis} />
                ))}
              </ul>
            )}
          </CardContent>
        </Card>
      </div>
    </div>
  )
}
