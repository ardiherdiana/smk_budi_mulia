import { Dialog, DialogContent, DialogDescription, DialogTitle } from "@/components/ui/dialog"

/** Pratinjau gambar layar penuh. `src` null = tertutup. */
export function ImageLightbox({
  src,
  alt,
  onClose,
}: {
  src: string | null
  alt: string
  onClose: () => void
}) {
  return (
    <Dialog open={src !== null} onOpenChange={(open) => !open && onClose()}>
      <DialogContent className="max-h-[92svh] p-2 sm:max-w-5xl" showCloseButton>
        <DialogTitle className="sr-only">{alt}</DialogTitle>
        <DialogDescription className="sr-only">Pratinjau gambar</DialogDescription>
        {src && <img src={src} alt={alt} className="max-h-[84svh] w-full rounded-lg object-contain" />}
      </DialogContent>
    </Dialog>
  )
}
