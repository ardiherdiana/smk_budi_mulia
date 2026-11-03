import { router } from "@inertiajs/react"

import type { Post } from "@/lib/classroom/types"
import { useConfirm } from "@/hooks/use-confirm"

interface Opsi {
  title: string
  description?: string
  preserveScroll?: boolean
}

/**
 * Hapus postingan dengan dialog konfirmasi. Untuk postingan yang diterbitkan ke beberapa ruang kelas sekaligus,
 * dialog menawarkan kotak centang untuk menghapus salinannya di kelas lain juga. Render `confirmDialog` sekali di JSX.
 */
export function useHapusPost(post: Post, { title, description, preserveScroll }: Opsi) {
  const { confirm, confirmDialog, checked } = useConfirm()
  const lainnya = post.jumlahGrup - 1

  async function hapus() {
    const ok = await confirm({
      title,
      description,
      confirmLabel: "Hapus",
      destructive: true,
      checkbox: lainnya > 0 ? `Hapus juga di ${lainnya} ruang kelas lainnya` : undefined,
    })
    if (!ok) return

    router.delete(`/classroom/post/${post.id}`, {
      preserveScroll,
      ...(lainnya > 0 && checked() ? { data: { semuaKelas: 1 } } : {}),
    })
  }

  return { hapus, confirmDialog }
}
