import * as React from "react"
import { router } from "@inertiajs/react"
import { AnimatePresence, motion } from "framer-motion"
import { ChevronLeft, ChevronRight, Newspaper, PlayCircle, Search, Video } from "lucide-react"

import { cn } from "@/lib/utils"
import type { PostCard, PostDetail, PostFilters, PostsPage } from "@/lib/types"
import { formatTanggal } from "@/lib/format"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { EASE_OUT, Reveal, SectionHeader } from "@/components/landing/motion"
import { PostDialog } from "@/components/landing/post-dialog"

interface Props {
  posts: PostsPage
  categories: string[]
  filters: PostFilters
  activePost: PostDetail | null
}

type Query = Record<string, string | number>

const ONLY = ["posts", "filters", "categories", "activePost"]

function buildQuery(filters: { type: string; category: string | null; q: string }, page = 1): Query {
  const query: Query = {}
  if (filters.type && filters.type !== "all") query.type = filters.type
  if (filters.category) query.category = filters.category
  if (filters.q) query.q = filters.q
  if (page > 1) query.page = page
  return query
}

/** Berita terbaru (halaman 1 tanpa filter) tampil lebih lebar karena urutannya memang berdasarkan waktu. */
function PostCardView({ post, featured: wantFeatured, onOpen }: { post: PostCard; featured: boolean; onOpen: (post: PostCard) => void }) {
  const cover = post.cover_url ?? (post.youtube_id ? `https://i.ytimg.com/vi/${post.youtube_id}/hqdefault.jpg` : null)
  const Fallback = post.type === "video" ? Video : Newspaper
  // Kartu lebar hanya masuk akal kalau ada gambar yang ditampilkan lebar; tanpa gambar tetap kartu biasa.
  const featured = wantFeatured && cover !== null

  return (
    <motion.li
      initial={{ opacity: 0, y: 12 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.4, ease: EASE_OUT }}
      className={cn(featured && "sm:col-span-2")}
    >
      <a
        href={post.url}
        onClick={(event) => {
          if (event.metaKey || event.ctrlKey || event.shiftKey || event.button !== 0) return
          event.preventDefault()
          onOpen(post)
        }}
        className={cn(
          "group flex h-full flex-col overflow-hidden rounded-xl bg-card ring-1 ring-foreground/10 outline-none transition-colors hover:ring-foreground/30 focus-visible:ring-3 focus-visible:ring-ring/50",
          featured && "lg:flex-row"
        )}
      >
        <div className={cn("relative aspect-video overflow-hidden bg-muted", featured && "lg:aspect-auto lg:w-1/2")}>
          {cover ? (
            <img src={cover} alt="" loading="lazy" className="size-full object-cover" />
          ) : (
            <div className="flex size-full items-center justify-center text-muted-foreground">
              <Fallback className="size-10" aria-hidden />
            </div>
          )}
          {post.has_video && (
            <span className="absolute bottom-3 left-3 flex items-center gap-1.5 rounded-md bg-foreground px-2 py-1 text-xs font-medium text-background">
              <PlayCircle className="size-3.5" aria-hidden /> Video
            </span>
          )}
        </div>

        <div className={cn("flex flex-1 flex-col gap-2 p-4", featured && "lg:justify-center lg:p-8")}>
          <p className="text-sm text-muted-foreground">
            {post.category ? `${post.category} · ` : ""}
            {formatTanggal(post.date)}
          </p>
          <h3
            className={cn(
              "line-clamp-3 leading-snug font-semibold text-balance [overflow-wrap:anywhere]",
              featured ? "text-xl lg:text-3xl" : "text-lg"
            )}
          >
            {post.title}
          </h3>
          <p className={cn("line-clamp-3 text-muted-foreground [overflow-wrap:anywhere]", featured && "lg:line-clamp-4 lg:text-lg")}>
            {post.excerpt}
          </p>
        </div>
      </a>
    </motion.li>
  )
}

export function NewsSection({ posts, categories, filters, activePost }: Props) {
  const [loading, setLoading] = React.useState(false)
  const [search, setSearch] = React.useState(filters.q)
  const searchTimer = React.useRef<number | undefined>(undefined)

  const visit = React.useCallback((query: Query) => {
    router.get("/", query, {
      preserveScroll: true,
      preserveState: true,
      replace: true,
      only: ONLY,
      onStart: () => setLoading(true),
      onFinish: () => setLoading(false),
    })
  }, [])

  function changeFilters(next: Partial<{ type: string; category: string | null; q: string }>) {
    visit(buildQuery({ ...filters, ...next }))
  }

  function onSearchChange(value: string) {
    setSearch(value)
    window.clearTimeout(searchTimer.current)
    searchTimer.current = window.setTimeout(() => changeFilters({ q: value.trim() }), 350)
  }

  function clearFilters() {
    setSearch("")
    visit({})
  }

  function openPost(post: PostCard) {
    router.get(post.url, buildQuery(filters, posts.current_page), {
      preserveScroll: true,
      preserveState: true,
      only: ["activePost"],
    })
  }

  function closePost() {
    router.get("/", buildQuery(filters, posts.current_page), {
      preserveScroll: true,
      preserveState: true,
      only: ["activePost"],
    })
  }

  const filtering = filters.type !== "all" || filters.category !== null || filters.q !== ""
  const gridKey = `${filters.type}|${filters.category}|${filters.q}|${posts.current_page}`
  const featureFirst = posts.current_page === 1 && !filtering && posts.data.length > 1

  return (
    <section id="berita" className="bg-sidebar py-16 md:py-24">
      <div className="mx-auto max-w-7xl px-4 md:px-6">
        <SectionHeader title="Berita dan galeri" description="Kegiatan, prestasi, dan video dari sekolah." />

        <Reveal className="mt-8 flex flex-col gap-4">
          <div className="flex flex-col items-stretch justify-between gap-4 md:flex-row md:items-center">
            <Tabs value={filters.type} onValueChange={(value) => changeFilters({ type: String(value) })}>
              {/* Tiga kolom sama lebar (simetris), sama seperti tab di seksi Guru dan staf. */}
              <TabsList className="grid w-full grid-cols-3 md:w-72">
                {[
                  ["all", "Semua"],
                  ["berita", "Berita"],
                  ["video", "Video"],
                ].map(([value, label]) => (
                  <TabsTrigger key={value} value={value} className="px-2">
                    {label}
                  </TabsTrigger>
                ))}
              </TabsList>
            </Tabs>

            <div className="relative w-full md:w-72">
              <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
              <Input
                value={search}
                onChange={(e) => onSearchChange(e.target.value)}
                placeholder="Cari judul atau isi"
                aria-label="Cari berita"
                className="h-11 pl-8 sm:h-8"
              />
            </div>
          </div>

          {categories.length > 0 && (
            <div className="flex flex-wrap gap-2" role="group" aria-label="Filter kategori">
              {[null, ...categories].map((category) => (
                <Button
                  key={category ?? "semua"}
                  variant={filters.category === category ? "secondary" : "outline"}
                  aria-pressed={filters.category === category}
                  className="h-11 px-4 text-base sm:h-8 sm:px-2.5 sm:text-sm"
                  onClick={() => changeFilters({ category })}
                >
                  {category ?? "Semua kategori"}
                </Button>
              ))}
            </div>
          )}
        </Reveal>

        <div className={cn("mt-8 transition-opacity", loading && "pointer-events-none opacity-60")} aria-busy={loading}>
          <AnimatePresence mode="wait">
            {posts.data.length === 0 ? (
              <motion.div key="empty" initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }} className="py-12">
                <p className="text-lg">{filtering ? "Tidak ada postingan yang cocok dengan filter ini." : "Belum ada postingan."}</p>
                {filtering && (
                  <Button variant="outline" className="mt-4 h-11 px-4 text-base" onClick={clearFilters}>
                    Hapus filter
                  </Button>
                )}
              </motion.div>
            ) : (
              <motion.ul key={gridKey} exit={{ opacity: 0 }} className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                {posts.data.map((post, index) => (
                  <PostCardView key={post.id} post={post} featured={featureFirst && index === 0} onOpen={openPost} />
                ))}
              </motion.ul>
            )}
          </AnimatePresence>
        </div>

        {posts.last_page > 1 && (
          <nav aria-label="Halaman berita" className="mt-10 flex items-center gap-3">
            <Button
              variant="outline"
              className="h-11 px-4 text-base"
              disabled={posts.current_page <= 1 || loading}
              onClick={() => visit(buildQuery(filters, posts.current_page - 1))}
            >
              <ChevronLeft /> Sebelumnya
            </Button>
            <span className="text-sm text-muted-foreground">
              Halaman {posts.current_page} dari {posts.last_page}
            </span>
            <Button
              variant="outline"
              className="h-11 px-4 text-base"
              disabled={posts.current_page >= posts.last_page || loading}
              onClick={() => visit(buildQuery(filters, posts.current_page + 1))}
            >
              Selanjutnya <ChevronRight />
            </Button>
          </nav>
        )}
      </div>

      <PostDialog post={activePost} onClose={closePost} />
    </section>
  )
}
