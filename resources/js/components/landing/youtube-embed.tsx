import { cn } from "@/lib/utils"

/** Embed YouTube tanpa cookie pelacak (youtube-nocookie), ID sudah divalidasi di server (11 karakter). */
export function YouTubeEmbed({ id, title, className }: { id: string; title: string; className?: string }) {
  return (
    <iframe
      className={cn("aspect-video w-full rounded-xl", className)}
      src={`https://www.youtube-nocookie.com/embed/${encodeURIComponent(id)}?rel=0`}
      title={title}
      loading="lazy"
      allow="accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen"
      referrerPolicy="strict-origin-when-cross-origin"
      allowFullScreen
    />
  )
}
