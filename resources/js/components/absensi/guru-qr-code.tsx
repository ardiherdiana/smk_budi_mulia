import * as React from "react"
import QRCode from "qrcode"
import { Download } from "lucide-react"

import { Button } from "@/components/ui/button"

interface GuruQrCodeProps {
  qrToken: string
  nama: string
  size?: number
}

/** Renders a guru's permanent static QR (encodes their `qrToken` as-is) plus
 * a download button - used both on the guru's own "QR Saya" page and the
 * admin's "Lihat QR" dialog, so the same card can be shown on a phone or
 * printed out. */
export function GuruQrCode({ qrToken, nama, size = 240 }: GuruQrCodeProps) {
  const canvasRef = React.useRef<HTMLCanvasElement>(null)

  React.useEffect(() => {
    if (canvasRef.current) {
      QRCode.toCanvas(canvasRef.current, qrToken, { width: size, margin: 1 })
    }
  }, [qrToken, size])

  function handleDownload() {
    const canvas = canvasRef.current
    if (!canvas) return
    const link = document.createElement("a")
    link.href = canvas.toDataURL("image/png")
    link.download = `qr-absen-${nama.replace(/[^a-z0-9]+/gi, "-").toLowerCase()}.png`
    link.click()
  }

  return (
    <div className="flex flex-col items-center gap-3">
      <div className="rounded-lg border bg-white p-3">
        <canvas ref={canvasRef} />
      </div>
      <Button type="button" variant="outline" size="sm" onClick={handleDownload}>
        <Download /> Download QR
      </Button>
    </div>
  )
}
