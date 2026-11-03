import * as React from "react"
import { Head, Link, useForm } from "@inertiajs/react"
import { ArrowLeft } from "lucide-react"

import { cn } from "@/lib/utils"
import { Button } from "@/components/ui/button"
import { Field, FieldGroup, FieldLabel } from "@/components/ui/field"
import { Input } from "@/components/ui/input"

const BACKGROUND_PHOTOS = ["/images/gedung-sekolah.webp", "/images/tkj.webp"]

const SLIDE_INTERVAL_MS = 6000

function BackgroundSlideshow() {
  const [index, setIndex] = React.useState(0)

  React.useEffect(() => {
    const timer = setInterval(() => {
      setIndex((current) => (current + 1) % BACKGROUND_PHOTOS.length)
    }, SLIDE_INTERVAL_MS)
    return () => clearInterval(timer)
  }, [])

  return (
    <div className="absolute inset-0 overflow-hidden bg-neutral-900">
      {BACKGROUND_PHOTOS.map((src, i) => (
        <div
          key={src}
          className={cn(
            "absolute inset-0 bg-cover bg-center transition-opacity duration-1000 ease-in-out",
            i === index ? "opacity-100" : "opacity-0"
          )}
          style={{ backgroundImage: `url(${src})` }}
        />
      ))}
      <div className="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-black/10" />
    </div>
  )
}

export function LoginPage() {
  const { data, setData, post, processing, errors } = useForm({
    email: "",
    password: "",
  })

  function handleSubmit(event: React.FormEvent) {
    event.preventDefault()
    post("/login")
  }

  return (
    <div className="relative flex min-h-svh w-full">
      <Head title="Masuk - SMK Budi Mulia" />
      <div className="relative hidden w-1/2 lg:block">
        <BackgroundSlideshow />
        <div className="relative z-10 flex h-full flex-col justify-end p-10 text-white">
          <h2 className="text-3xl font-semibold">SMK Budi Mulia Karawang</h2>
          <p className="mt-2 max-w-sm text-sm text-white/80">
            Kelola website sekolah, unduh berkas kelulusan, atau belajar di Classroom melalui portal resmi sekolah.
          </p>
        </div>
      </div>

      <div className="flex w-full flex-1 items-center justify-center p-6 lg:w-1/2">
        <div className="w-full max-w-sm">
          <form onSubmit={handleSubmit}>
            <FieldGroup>
              <div className="flex flex-col items-center gap-1 text-center">
                <img src="/logo_smk.png" alt="Logo SMK Budi Mulia" className="mb-2 size-14 object-contain" />
                <h1 className="text-2xl font-bold">Masuk ke akun Anda</h1>
                <p className="text-sm text-balance text-muted-foreground">
                  Untuk admin, guru, dan siswa/alumni SMK Budi Mulia
                </p>
              </div>
              <Field>
                <FieldLabel htmlFor="email">Email / Username</FieldLabel>
                <Input
                  id="email"
                  type="text"
                  autoComplete="username"
                  autoCapitalize="none"
                  autoCorrect="off"
                  spellCheck={false}
                  value={data.email}
                  onChange={(e) => setData("email", e.target.value)}
                  required
                />
              </Field>
              <Field>
                <FieldLabel htmlFor="password">Password</FieldLabel>
                <Input
                  id="password"
                  type="password"
                  autoComplete="current-password"
                  value={data.password}
                  onChange={(e) => setData("password", e.target.value)}
                  required
                />
              </Field>
              {(errors.email || errors.password) && (
                <p className="text-sm text-destructive" role="alert">
                  {errors.email ?? errors.password}
                </p>
              )}
              <Field>
                {/* Teks 18px: putih di atas oranye 3,4:1 hanya cukup untuk teks besar. */}
                <Button type="submit" disabled={processing} className="h-11 text-lg">
                  {processing ? "Memproses..." : "Masuk"}
                </Button>
              </Field>
              <Link
                href="/"
                className="inline-flex items-center justify-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
              >
                <ArrowLeft className="size-4" /> Kembali ke website
              </Link>
            </FieldGroup>
          </form>
        </div>
      </div>
    </div>
  )
}
