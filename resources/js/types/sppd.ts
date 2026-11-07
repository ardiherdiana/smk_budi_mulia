import { type AuthUser } from "@/lib/types"

export type RoleValue = "pemohon" | "kepala_sekolah" | "tu" | "bendahara"

export interface PaginatedData<T> {
  data: T[]
  links: { url: string | null; label: string; active: boolean }[]
  current_page: number
  last_page: number
  total: number
  per_page: number
}

export type SppdPageProps<T extends Record<string, unknown> = Record<string, unknown>> = T & {
  auth: {
    user: AuthUser | null
    roles: RoleValue[]
  }
  flash: {
    success: string | null
    error: string | null
  }
}
