import { router } from "@inertiajs/react"
import { useEffect } from "react"
import { toast } from "sonner"

interface FlashProps {
  success?: string | null
  error?: string | null
}

/** Memunculkan pesan `->with('success' | 'error', ...)` dari server sebagai toast setiap kunjungan Inertia berhasil.
 * Dipasang sekali di SppdLayout; tanpanya pesan itu (termasuk galat seperti arsip yang gagal dibuat) tidak terlihat.
 * Halaman absensi tidak memakainya: mereka memanggil `toast` sendiri di `onSuccess`. */
export function useFlashToast() {
  useEffect(
    () =>
      router.on("success", (event) => {
        const flash = event.detail.page.props.flash as FlashProps | undefined

        if (flash?.success) toast.success(flash.success)
        if (flash?.error) toast.error(flash.error)
      }),
    [],
  )
}
