import * as React from "react"
import { Head, Link, router } from "@inertiajs/react"
import { motion } from "framer-motion"
import { ArrowLeft, Download, FileText, Inbox, KeyRound, LogOut } from "lucide-react"

import { useAuth } from "@/context/auth-context"
import { formatTanggalWaktu } from "@/lib/format"
import { PasswordDialog } from "@/components/password-dialog"
import { ThemeToggle } from "@/components/theme-toggle"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"

interface PortalFile {
  id: number
  nama_file: string
  uploaded_at: string | null
  available: boolean
  download_url: string
}

export function PortalPage({ files }: { files: PortalFile[] }) {
  const { user } = useAuth()
  const [passwordOpen, setPasswordOpen] = React.useState(false)

  return (
    <div className="min-h-svh bg-sidebar">
      <Head title="Portal Siswa - SMK Budi Mulia" />

      <header className="border-b bg-background">
        <div className="mx-auto flex h-14 max-w-4xl items-center gap-3 px-4">
          <img src="/logo_smk.png" alt="Logo SMK Budi Mulia" className="size-8 object-contain" />
          <div className="flex flex-col leading-none">
            <span className="text-sm font-medium">Portal Siswa</span>
            <span className="text-xs text-muted-foreground">SMK Budi Mulia</span>
          </div>
          <div className="ml-auto flex items-center gap-1">
            <ThemeToggle />
            <Button variant="ghost" size="sm" onClick={() => setPasswordOpen(true)}>
              <KeyRound /> <span className="hidden sm:inline">Ganti password</span>
            </Button>
            <Button variant="ghost" size="sm" onClick={() => router.post("/logout")}>
              <LogOut /> <span className="hidden sm:inline">Keluar</span>
            </Button>
          </div>
        </div>
      </header>

      <main className="mx-auto flex max-w-4xl flex-col gap-6 px-4 py-8">
        <div className="flex flex-wrap items-end justify-between gap-2">
          <div>
            <h1 className="text-xl font-semibold">Berkas Anda</h1>
            <p className="text-sm text-muted-foreground">Masuk sebagai {user?.email}</p>
          </div>
          <div className="flex items-center gap-4">
            <Link href="/menu-utama" className="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground">
              Menu utama
            </Link>
            <Link href="/" className="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground">
              <ArrowLeft className="size-4" /> Kembali ke website
            </Link>
          </div>
        </div>

        {files.length === 0 ? (
          <Card>
            <CardHeader className="items-center text-center">
              <span className="mb-2 flex size-12 items-center justify-center rounded-full bg-muted text-muted-foreground">
                <Inbox className="size-6" />
              </span>
              <CardTitle>Belum ada berkas</CardTitle>
              <CardDescription>Berkas Anda belum tersedia. Silakan hubungi admin sekolah.</CardDescription>
            </CardHeader>
          </Card>
        ) : (
          <ul className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            {files.map((file, index) => (
              <motion.li
                key={file.id}
                initial={{ opacity: 0, y: 16 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.4, delay: index * 0.06 }}
              >
                <Card className="h-full">
                  <CardHeader>
                    <CardTitle className="flex items-start gap-2">
                      <FileText className="mt-0.5 size-5 shrink-0 text-primary" />
                      <span className="break-words">{file.nama_file}</span>
                    </CardTitle>
                    <CardDescription>Diunggah {formatTanggalWaktu(file.uploaded_at)}</CardDescription>
                  </CardHeader>
                  <CardContent className="mt-auto">
                    {file.available ? (
                      // Teks 18px: putih di atas oranye 3,4:1 hanya cukup untuk teks besar.
                      <Button className="h-12 w-full text-lg" render={<a href={file.download_url} />}>
                        <Download /> Unduh PDF
                      </Button>
                    ) : (
                      <Badge variant="destructive">File tidak tersedia, hubungi admin</Badge>
                    )}
                  </CardContent>
                </Card>
              </motion.li>
            ))}
          </ul>
        )}
      </main>

      <PasswordDialog open={passwordOpen} onOpenChange={setPasswordOpen} />
    </div>
  )
}
