import { usePage } from "@inertiajs/react"
import type { AuthUser } from "@/lib/classroom/types"

interface PageProps {
  auth: { user: AuthUser | null }
  [key: string]: unknown
}

export function useAuth() {
  const { props } = usePage<PageProps>()
  const user = props.auth.user
  return {
    user,
    isSiswa: user?.role === "SISWA",
    isAdmin: user?.role === "ADMIN",
    /** Guru pengajar atau admin: boleh membuat ruang kelas dan isinya. */
    bisaMengajar: user?.role === "GURU" || user?.role === "ADMIN",
  }
}
