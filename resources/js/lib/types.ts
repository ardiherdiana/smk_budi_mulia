export interface AuthUser {
  id: number
  email: string
  role: "admin" | "user"
}

export interface FlashToast {
  type: "success" | "error" | "warning" | "info"
  message: string
}

export interface Credential {
  email: string
  password: string
}

export interface Paginated<T> {
  data: T[]
  current_page: number
  per_page: number
  total: number
}

export interface School {
  name: string
  short_name: string
  tagline: string
  description: string
  /** <title> beranda, sama dengan yang dirender server. */
  seo_title: string
  address: string
  phone: string
  email: string
  instagram: string
  map: { lat: number; lng: number }
  ppdb_url: string
  features: { title: string; description: string }[]
  culture: string
  vision: string
  missions: string[]
}

export interface Major {
  id: number
  name: string
  description: string | null
  icon: string | null
  color: string | null
  image_url: string | null
  video_url: string | null
  youtube_id: string | null
}

export interface AboutMediaItem {
  id: string
  type: string
  url: string
}

export interface AboutItem {
  id: string
  title: string
  description: string
  icon_type: string | null
  display_order: number
  is_active: boolean
  media: AboutMediaItem[]
}

export interface PrincipalItem {
  id: number
  nama: string
  tahun_jabatan: string
  foto_url: string | null
}

export type StaffRole = "principal" | "vice_principal" | "program_head" | "teacher" | "staff"

export interface StaffItem {
  id: number
  name: string
  email: string | null
  role: StaffRole
  type: string | null
  subject: string | null
  expertise: string | null
  position: string | null
  image_url: string | null
}

export interface PostCard {
  id: string
  /** Tautan kanonik `/berita/{slug}-{id}` dari server. */
  url: string
  title: string
  excerpt: string
  type: "berita" | "video" | string
  category: string | null
  author: string | null
  date: string | null
  cover_url: string | null
  has_video: boolean
  youtube_id: string | null
}

export type PostVideo =
  | { kind: "youtube"; id: string }
  | { kind: "file"; src: string }
  | { kind: "upload"; src: string }

export interface PostDetail extends PostCard {
  content: string
  gallery: { id: string; url: string }[]
  video: PostVideo | null
}

export interface PostFilters {
  type: string
  category: string | null
  q: string
}

export interface PostsPage {
  data: PostCard[]
  current_page: number
  last_page: number
  total: number
}

export interface LandingProps {
  school: School
  majors: Major[]
  abouts: AboutItem[]
  principals: PrincipalItem[]
  staff: StaffItem[]
  posts: PostsPage
  categories: string[]
  filters: PostFilters
  activePost: PostDetail | null
}
