import { usePage } from "@inertiajs/react"
import type { AuthUser } from "@/lib/types"

interface PageProps {
  auth: { user: AuthUser | null }
  [key: string]: unknown
}

export function useAuth() {
  const { props } = usePage<PageProps>()
  return { user: props.auth.user }
}
