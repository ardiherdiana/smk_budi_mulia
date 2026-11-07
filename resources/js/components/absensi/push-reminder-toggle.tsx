import * as React from "react"
import { toast } from "sonner"
import { Bell, BellOff } from "lucide-react"

import { api, ApiError } from "@/lib/absensi/api"
import { Button } from "@/components/ui/button"

function urlBase64ToUint8Array(base64String: string): Uint8Array {
  const padding = "=".repeat((4 - (base64String.length % 4)) % 4)
  const base64 = (base64String + padding).replace(/-/g, "+").replace(/_/g, "/")
  const rawData = atob(base64)
  const bytes = new Uint8Array(rawData.length)
  for (let i = 0; i < rawData.length; i++) bytes[i] = rawData.charCodeAt(i)
  return bytes
}

/** Toggle for browser push reminders (15 minutes before jam masuk/pulang) -
 * only rendered once we know whether push is supported and, if so, whether
 * this device already has an active subscription. */
export function PushReminderToggle() {
  const [supported, setSupported] = React.useState(true)
  const [subscribed, setSubscribed] = React.useState<boolean | null>(null)
  const [busy, setBusy] = React.useState(false)

  React.useEffect(() => {
    if (!("serviceWorker" in navigator) || !("PushManager" in window)) {
      setSupported(false)
      return
    }
    navigator.serviceWorker.ready
      .then((reg) => reg.pushManager.getSubscription())
      .then((sub) => setSubscribed(sub !== null))
      .catch(() => setSubscribed(false))
  }, [])

  async function handleEnable() {
    setBusy(true)
    try {
      const { publicKey } = await api.get<{ publicKey: string | null }>(
        "/push/vapid-public-key"
      )
      if (!publicKey) {
        toast.warning("Notifikasi push belum dikonfigurasi di server")
        return
      }

      const permission = await Notification.requestPermission()
      if (permission !== "granted") {
        toast.warning("Izin notifikasi ditolak")
        return
      }

      const registration = await navigator.serviceWorker.ready
      const subscription = await registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(publicKey) as BufferSource,
      })
      const json = subscription.toJSON()
      await api.post("/push/subscribe", {
        endpoint: json.endpoint,
        keys: { p256dh: json.keys?.p256dh, auth: json.keys?.auth },
      })
      setSubscribed(true)
      toast.success("Reminder absen diaktifkan")
    } catch (err) {
      toast.error(err instanceof ApiError ? err.message : "Gagal mengaktifkan notifikasi")
    } finally {
      setBusy(false)
    }
  }

  async function handleDisable() {
    setBusy(true)
    try {
      const registration = await navigator.serviceWorker.ready
      const subscription = await registration.pushManager.getSubscription()
      if (subscription) {
        await api.post("/push/unsubscribe", { endpoint: subscription.endpoint })
        await subscription.unsubscribe()
      }
      setSubscribed(false)
      toast.success("Reminder absen dimatikan")
    } catch (err) {
      toast.error(err instanceof ApiError ? err.message : "Gagal mematikan notifikasi")
    } finally {
      setBusy(false)
    }
  }

  if (!supported || subscribed === null) return null

  return subscribed ? (
    <Button
      variant="outline"
      size="sm"
      className="w-full sm:w-auto"
      disabled={busy}
      onClick={handleDisable}
    >
      <BellOff /> Matikan Reminder Absen
    </Button>
  ) : (
    <Button
      variant="outline"
      size="sm"
      className="w-full sm:w-auto"
      disabled={busy}
      onClick={handleEnable}
    >
      <Bell /> Aktifkan Reminder Absen
    </Button>
  )
}
