import * as React from "react"
import { CalendarDays, Check, Link2, Tag, UserRound } from "lucide-react"

import type { PostDetail } from "@/lib/types"
import { formatTanggal } from "@/lib/format"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import { ImageLightbox } from "@/components/landing/image-lightbox"
import { YouTubeEmbed } from "@/components/landing/youtube-embed"

function PostVideoPlayer({ video, title }: { video: NonNullable<PostDetail["video"]>; title: string }) {
  if (video.kind === "youtube") return <YouTubeEmbed id={video.id} title={title} />

  return (
    <video controls preload="metadata" className="aspect-video w-full rounded-xl bg-black" aria-label={title}>
      <source src={video.src} />
      Browser Anda tidak mendukung pemutaran video.
    </video>
  )
}

/** Detail berita/video di dalam halaman (tanpa pindah halaman). */
export function PostDialog({ post, onClose }: { post: PostDetail | null; onClose: () => void }) {
  const [copied, setCopied] = React.useState(false)
  const [lightbox, setLightbox] = React.useState<string | null>(null)

  async function copyLink() {
    if (!post) return
    try {
      await navigator.clipboard.writeText(`${window.location.origin}${post.url}`)
      setCopied(true)
      window.setTimeout(() => setCopied(false), 2000)
    } catch {
      // Clipboard tidak tersedia (mis. HTTP non-lokal) - abaikan.
    }
  }

  return (
    <>
      <Dialog open={post !== null} onOpenChange={(open) => !open && onClose()}>
        <DialogContent className="max-h-[92svh] gap-0 overflow-y-auto p-0 sm:max-w-3xl">
          {post && (
            <>
              {post.video ? (
                <PostVideoPlayer video={post.video} title={post.title} />
              ) : post.cover_url ? (
                <button type="button" onClick={() => setLightbox(post.cover_url)} className="block bg-muted outline-none">
                  <img src={post.cover_url} alt={post.title} className="max-h-[50svh] w-full object-contain" />
                </button>
              ) : null}

              <div className="flex flex-col gap-4 p-5 md:p-6">
                <DialogHeader className="gap-3">
                  <div className="flex flex-wrap items-center gap-2">
                    <Badge variant={post.type === "video" ? "outline" : "secondary"}>
                      {post.type === "video" ? "Video" : "Berita"}
                    </Badge>
                    {post.category && (
                      <Badge variant="outline">
                        <Tag /> {post.category}
                      </Badge>
                    )}
                  </div>
                  <DialogTitle className="text-xl leading-snug font-semibold text-balance [overflow-wrap:anywhere] md:text-2xl">{post.title}</DialogTitle>
                  <DialogDescription className="flex flex-wrap items-center gap-x-4 gap-y-1">
                    <span className="inline-flex items-center gap-1.5">
                      <CalendarDays className="size-3.5" /> {formatTanggal(post.date)}
                    </span>
                    {post.author && (
                      <span className="inline-flex items-center gap-1.5">
                        <UserRound className="size-3.5" /> {post.author}
                      </span>
                    )}
                  </DialogDescription>
                </DialogHeader>

                {post.content.trim() ? (
                  <p className="text-base leading-relaxed whitespace-pre-line [overflow-wrap:anywhere]">{post.content}</p>
                ) : (
                  <p className="text-muted-foreground">Tidak ada konten.</p>
                )}

                {post.video && post.cover_url && (
                  <button type="button" onClick={() => setLightbox(post.cover_url)} className="overflow-hidden rounded-xl bg-muted outline-none">
                    <img src={post.cover_url} alt={post.title} loading="lazy" className="max-h-80 w-full object-contain" />
                  </button>
                )}

                {post.gallery.length > 0 && (
                  <div>
                    <p className="mb-2 text-sm font-medium">Gambar terkait</p>
                    <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                      {post.gallery.map((image) => (
                        <button
                          key={image.id}
                          type="button"
                          onClick={() => setLightbox(image.url)}
                          className="overflow-hidden rounded-lg bg-muted outline-none focus-visible:ring-3 focus-visible:ring-ring/50"
                        >
                          <img src={image.url} alt="" loading="lazy" className="aspect-square w-full object-cover transition-transform hover:scale-105" />
                        </button>
                      ))}
                    </div>
                  </div>
                )}

                <div className="flex justify-end border-t pt-4">
                  <Button variant="outline" className="h-11 px-4 text-base" onClick={copyLink}>
                    {copied ? <Check /> : <Link2 />} {copied ? "Tautan disalin" : "Salin tautan"}
                  </Button>
                </div>
              </div>
            </>
          )}
        </DialogContent>
      </Dialog>
      <ImageLightbox src={lightbox} alt={post?.title ?? "Gambar"} onClose={() => setLightbox(null)} />
    </>
  )
}
