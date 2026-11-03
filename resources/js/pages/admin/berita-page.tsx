import * as React from "react"
import { router, useForm } from "@inertiajs/react"
import { ExternalLink, Newspaper, Pencil, Plus, Search, Trash2, Video } from "lucide-react"

import type { Paginated, PostCard } from "@/lib/types"
import { formatTanggal } from "@/lib/format"
import { DataPagination } from "@/components/data-pagination"
import { GalleryField, ImageField } from "@/components/admin/file-fields"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import { Field, FieldGroup, FieldLabel } from "@/components/ui/field"
import { Input } from "@/components/ui/input"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { Textarea } from "@/components/ui/textarea"

interface AdminPost extends PostCard {
  content: string
  video_url: string | null
  video_file_url: string | null
  gallery: { id: string; url: string }[]
}

interface Props {
  posts: Paginated<AdminPost>
  filters: { q: string; type: string }
}

interface FormState {
  title: string
  content: string
  type: string
  category: string
  author: string
  video_url: string
  image: File | null
  remove_image: boolean
  video_file: File | null
  remove_video_file: boolean
  gallery: File[]
  remove_gallery: string[]
}

const EMPTY: FormState = {
  title: "",
  content: "",
  type: "berita",
  category: "",
  author: "Admin",
  video_url: "",
  image: null,
  remove_image: false,
  video_file: null,
  remove_video_file: false,
  gallery: [],
  remove_gallery: [],
}

function PostFormDialog({ post, open, onOpenChange }: { post: AdminPost | null; open: boolean; onOpenChange: (open: boolean) => void }) {
  const form = useForm<FormState>(EMPTY)
  const { data, setData, errors, processing, reset, clearErrors } = form
  const videoInput = React.useRef<HTMLInputElement>(null)

  React.useEffect(() => {
    if (!open) return
    clearErrors()
    form.setData(
      post
        ? {
            ...EMPTY,
            title: post.title,
            content: post.content,
            type: post.type === "video" ? "video" : "berita",
            category: post.category ?? "",
            author: post.author ?? "Admin",
            video_url: post.video_url ?? "",
          }
        : EMPTY
    )
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, post])

  function handleSubmit(event: React.FormEvent) {
    event.preventDefault()
    const options = {
      forceFormData: true,
      preserveScroll: true,
      onSuccess: () => {
        onOpenChange(false)
        reset()
      },
    }
    form.transform((d) => (post ? { ...d, _method: "put" } : d))
    form.post(post ? `/admin/berita/${post.id}` : "/admin/berita", options)
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[92svh] overflow-y-auto sm:max-w-2xl">
        <form onSubmit={handleSubmit} className="grid gap-4">
          <DialogHeader>
            <DialogTitle>{post ? "Edit postingan" : "Postingan baru"}</DialogTitle>
            <DialogDescription>Tampil di bagian Berita & Galeri pada halaman utama.</DialogDescription>
          </DialogHeader>

          <FieldGroup>
            <Field>
              <FieldLabel>Jenis postingan</FieldLabel>
              <Tabs value={data.type} onValueChange={(v) => setData("type", String(v))}>
                <TabsList>
                  <TabsTrigger value="berita">Berita / Foto</TabsTrigger>
                  <TabsTrigger value="video">Video</TabsTrigger>
                </TabsList>
              </Tabs>
            </Field>
            <Field>
              <FieldLabel htmlFor="post-title">Judul</FieldLabel>
              <Input id="post-title" value={data.title} onChange={(e) => setData("title", e.target.value)} required />
              {errors.title && <p className="text-sm text-destructive">{errors.title}</p>}
            </Field>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <Field>
                <FieldLabel htmlFor="post-category">Kategori</FieldLabel>
                <Input id="post-category" value={data.category} onChange={(e) => setData("category", e.target.value)} placeholder="mis. Kegiatan Sekolah" />
                {errors.category && <p className="text-sm text-destructive">{errors.category}</p>}
              </Field>
              <Field>
                <FieldLabel htmlFor="post-author">Penulis</FieldLabel>
                <Input id="post-author" value={data.author} onChange={(e) => setData("author", e.target.value)} />
              </Field>
            </div>
            <Field>
              <FieldLabel htmlFor="post-content">Isi / deskripsi</FieldLabel>
              <Textarea id="post-content" rows={7} value={data.content} onChange={(e) => setData("content", e.target.value)} required />
              <p className="text-xs text-muted-foreground">Teks biasa; baris baru dipertahankan. Tag HTML tidak dijalankan.</p>
              {errors.content && <p className="text-sm text-destructive">{errors.content}</p>}
            </Field>
            <Field>
              <FieldLabel htmlFor="post-image">Gambar sampul</FieldLabel>
              <ImageField
                id="post-image"
                currentUrl={post?.cover_url}
                file={data.image}
                removed={data.remove_image}
                onFileChange={(f) => setData("image", f)}
                onRemovedChange={(v) => setData("remove_image", v)}
                error={errors.image}
              />
            </Field>
            <Field>
              <FieldLabel htmlFor="post-video-url">Link video (YouTube / .mp4)</FieldLabel>
              <Input
                id="post-video-url"
                value={data.video_url}
                onChange={(e) => setData("video_url", e.target.value)}
                placeholder="https://www.youtube.com/watch?v=..."
              />
              {errors.video_url && <p className="text-sm text-destructive">{errors.video_url}</p>}
            </Field>
            <Field>
              <FieldLabel htmlFor="post-video-file">Atau unggah file video</FieldLabel>
              <div className="flex flex-wrap items-center gap-2">
                <Button type="button" variant="outline" size="sm" onClick={() => videoInput.current?.click()}>
                  <Video /> {data.video_file ? "Ganti video" : "Pilih video"}
                </Button>
                <span className="text-sm text-muted-foreground">
                  {data.video_file
                    ? data.video_file.name
                    : post?.video_file_url && !data.remove_video_file
                      ? "Sudah ada video terunggah"
                      : "MP4, WebM, atau OGG"}
                </span>
                {(data.video_file || (post?.video_file_url && !data.remove_video_file)) && (
                  <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={() => {
                      setData((d) => ({ ...d, video_file: null, remove_video_file: !!post?.video_file_url }))
                    }}
                  >
                    <Trash2 /> Hapus
                  </Button>
                )}
              </div>
              <input
                ref={videoInput}
                id="post-video-file"
                type="file"
                accept="video/mp4,video/webm,video/ogg"
                className="sr-only"
                onChange={(e) => {
                  setData((d) => ({ ...d, video_file: e.target.files?.[0] ?? null, remove_video_file: false }))
                  e.target.value = ""
                }}
              />
              {errors.video_file && <p className="text-sm text-destructive">{errors.video_file}</p>}
            </Field>
            <Field>
              <FieldLabel htmlFor="post-gallery">Galeri gambar tambahan</FieldLabel>
              <GalleryField
                id="post-gallery"
                existing={post?.gallery ?? []}
                removedIds={data.remove_gallery}
                files={data.gallery}
                onRemovedChange={(ids) => setData("remove_gallery", ids)}
                onFilesChange={(files) => setData("gallery", files)}
                error={errors.gallery ?? errors["gallery.0"]}
              />
            </Field>
          </FieldGroup>

          {(errors as Record<string, string>).message && (
            <p className="text-sm text-destructive" role="alert">
              {(errors as Record<string, string>).message}
            </p>
          )}

          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
              Batal
            </Button>
            <Button type="submit" disabled={processing}>
              {processing ? "Menyimpan..." : "Simpan"}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}

export function AdminBeritaPage({ posts, filters }: Props) {
  const [editing, setEditing] = React.useState<AdminPost | null>(null)
  const [open, setOpen] = React.useState(false)
  const [search, setSearch] = React.useState(filters.q)
  const timer = React.useRef<number | undefined>(undefined)

  function visit(params: Record<string, string | number | undefined>) {
    const query: Record<string, string | number> = {}
    for (const [key, value] of Object.entries(params)) {
      if (value !== undefined && value !== "" && value !== "all" && !(key === "page" && value === 1)) query[key] = value
    }
    router.get("/admin/berita", query, { preserveScroll: true, preserveState: true, replace: true })
  }

  function onSearch(value: string) {
    setSearch(value)
    window.clearTimeout(timer.current)
    timer.current = window.setTimeout(() => visit({ q: value.trim(), type: filters.type, per_page: posts.per_page }), 350)
  }

  function handleDelete(post: AdminPost) {
    if (!window.confirm(`Hapus postingan "${post.title}"? Tindakan ini tidak dapat dibatalkan.`)) return
    router.delete(`/admin/berita/${post.id}`, { preserveScroll: true })
  }

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="text-xl font-semibold">Berita & Galeri</h1>
          <p className="text-sm text-muted-foreground">Kelola berita, foto kegiatan, dan video yang tampil di halaman utama</p>
        </div>
        <Button
          onClick={() => {
            setEditing(null)
            setOpen(true)
          }}
        >
          <Plus /> Postingan baru
        </Button>
      </div>

      <div className="flex flex-wrap items-center gap-3">
        <Tabs value={filters.type} onValueChange={(v) => visit({ q: filters.q, type: String(v), per_page: posts.per_page })}>
          <TabsList>
            <TabsTrigger value="all">Semua</TabsTrigger>
            <TabsTrigger value="berita">Berita</TabsTrigger>
            <TabsTrigger value="video">Video</TabsTrigger>
          </TabsList>
        </Tabs>
        <div className="relative w-full sm:w-72">
          <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
          <Input value={search} onChange={(e) => onSearch(e.target.value)} placeholder="Cari judul atau isi..." className="pl-8" />
        </div>
      </div>

      <div className="overflow-x-auto rounded-lg border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead className="w-20">Sampul</TableHead>
              <TableHead>Judul</TableHead>
              <TableHead>Jenis</TableHead>
              <TableHead>Kategori</TableHead>
              <TableHead>Tanggal</TableHead>
              <TableHead className="text-right">Aksi</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {posts.data.length === 0 ? (
              <TableRow>
                <TableCell colSpan={6} className="text-center text-muted-foreground">
                  Belum ada postingan
                </TableCell>
              </TableRow>
            ) : (
              posts.data.map((post) => (
                <TableRow key={post.id}>
                  <TableCell>
                    <div className="flex h-11 w-16 items-center justify-center overflow-hidden rounded-md bg-muted text-muted-foreground">
                      {post.cover_url ? <img src={post.cover_url} alt="" loading="lazy" className="size-full object-cover" /> : <Newspaper className="size-4" />}
                    </div>
                  </TableCell>
                  <TableCell className="max-w-md">
                    <p className="line-clamp-2 font-medium">{post.title}</p>
                  </TableCell>
                  <TableCell>
                    {/* Oranye hanya untuk aksi; jenis postingan dibedakan secondary vs outline (kontras teks kecil). */}
                    <Badge variant={post.type === "video" ? "outline" : "secondary"}>{post.type === "video" ? "Video" : "Berita"}</Badge>
                  </TableCell>
                  <TableCell className="max-w-[12rem] truncate text-muted-foreground" title={post.category ?? undefined}>
                    {post.category ?? "-"}
                  </TableCell>
                  <TableCell className="whitespace-nowrap text-muted-foreground">{formatTanggal(post.date)}</TableCell>
                  <TableCell className="text-right whitespace-nowrap">
                    <Button variant="ghost" size="icon-sm" aria-label="Lihat di website" render={<a href={`/berita/${post.id}`} target="_blank" rel="noreferrer" />}>
                      <ExternalLink />
                    </Button>
                    <Button
                      variant="ghost"
                      size="icon-sm"
                      aria-label="Edit"
                      onClick={() => {
                        setEditing(post)
                        setOpen(true)
                      }}
                    >
                      <Pencil />
                    </Button>
                    <Button variant="ghost" size="icon-sm" aria-label="Hapus" onClick={() => handleDelete(post)}>
                      <Trash2 />
                    </Button>
                  </TableCell>
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>
      </div>

      <DataPagination
        page={posts.current_page}
        pageSize={posts.per_page}
        total={posts.total}
        pageSizeOptions={[10, 25, 50]}
        onPageChange={(page) => visit({ q: filters.q, type: filters.type, per_page: posts.per_page, page })}
        onPageSizeChange={(size) => visit({ q: filters.q, type: filters.type, per_page: size })}
      />

      <PostFormDialog post={editing} open={open} onOpenChange={setOpen} />
    </div>
  )
}
