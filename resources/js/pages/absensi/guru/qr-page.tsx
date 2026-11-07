import { useAuth } from "@/context/auth-context"
import type { Guru } from "@/lib/absensi/types"
import { GuruQrCode } from "@/components/absensi/guru-qr-code"
import { Card, CardContent } from "@/components/ui/card"

export function GuruQrPage({ guru }: { guru: Guru }) {
  const { user } = useAuth()

  return (
    <div className="flex max-w-md flex-col gap-4">
      <div>
        <h1 className="text-xl font-semibold">QR Saya</h1>
        <p className="text-sm text-muted-foreground">
          Tunjukkan QR ini ke alat scan di sekolah untuk absen masuk/pulang
        </p>
      </div>

      <Card>
        <CardContent className="flex flex-col items-center gap-4 py-8 text-center">
          <GuruQrCode qrToken={guru.qrToken} nama={guru.nama} />
          <div>
            <p className="font-medium">{guru.nama}</p>
            <p className="text-xs text-muted-foreground">{user?.username}</p>
          </div>
          <p className="text-sm text-muted-foreground">
            QR ini permanen dan hanya milik Anda - jangan bagikan ke orang lain. Kalau hilang
            atau disalahgunakan, minta admin untuk generate ulang QR Anda.
          </p>
        </CardContent>
      </Card>
    </div>
  )
}
