import * as React from "react"
import { useForm } from "@inertiajs/react"

import type { School } from "@/lib/types"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Field, FieldGroup, FieldLabel } from "@/components/ui/field"
import { Input } from "@/components/ui/input"
import { Textarea } from "@/components/ui/textarea"
import { Reveal, SectionHeader } from "@/components/landing/motion"

function mapEmbedUrl({ lat, lng }: School["map"]): string {
  const dx = 0.006
  const dy = 0.004
  const bbox = [lng - dx, lat - dy, lng + dx, lat + dy].join(",")
  return `https://www.openstreetmap.org/export/embed.html?bbox=${bbox}&layer=mapnik&marker=${lat},${lng}`
}

function ContactForm() {
  const { data, setData, post, processing, errors, reset } = useForm({
    name: "",
    email: "",
    subject: "",
    message: "",
    website: "", // honeypot: manusia tidak melihat/mengisi kolom ini
  })

  function handleSubmit(event: React.FormEvent) {
    event.preventDefault()
    post("/kontak", {
      preserveScroll: true,
      onSuccess: () => reset(),
    })
  }

  return (
    <Card className="[--card-spacing:--spacing(6)]">
      <CardHeader>
        <CardTitle className="text-xl">Kirim pesan</CardTitle>
        <CardDescription>Pesan diteruskan ke email sekolah. Jawaban dikirim ke alamat email yang Anda isi.</CardDescription>
      </CardHeader>
      <CardContent>
        <form onSubmit={handleSubmit} noValidate>
          <FieldGroup>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <Field>
                <FieldLabel htmlFor="contact-name">Nama lengkap</FieldLabel>
                <Input id="contact-name" className="h-11 sm:h-9" autoComplete="name" value={data.name} onChange={(e) => setData("name", e.target.value)} required />
                {errors.name && <p className="text-sm text-destructive">{errors.name}</p>}
              </Field>
              <Field>
                <FieldLabel htmlFor="contact-email">Email</FieldLabel>
                <Input id="contact-email" className="h-11 sm:h-9" type="email" autoComplete="email" value={data.email} onChange={(e) => setData("email", e.target.value)} required />
                {errors.email && <p className="text-sm text-destructive">{errors.email}</p>}
              </Field>
            </div>
            <Field>
              <FieldLabel htmlFor="contact-subject">Subjek</FieldLabel>
              <Input id="contact-subject" className="h-11 sm:h-9" value={data.subject} onChange={(e) => setData("subject", e.target.value)} required />
              {errors.subject && <p className="text-sm text-destructive">{errors.subject}</p>}
            </Field>
            <Field>
              <FieldLabel htmlFor="contact-message">Pesan</FieldLabel>
              <Textarea id="contact-message" rows={5} value={data.message} onChange={(e) => setData("message", e.target.value)} required />
              {errors.message && (
                <p className="text-sm text-destructive" role="alert">
                  {errors.message}
                </p>
              )}
            </Field>
            <input
              type="text"
              name="website"
              tabIndex={-1}
              autoComplete="off"
              aria-hidden="true"
              value={data.website}
              onChange={(e) => setData("website", e.target.value)}
              className="absolute -left-[9999px] h-0 w-0 opacity-0"
            />
            {/* Teks 18px: putih di atas oranye 3,4:1 hanya cukup untuk teks besar. */}
            <Button type="submit" disabled={processing} className="h-12 px-6 text-lg sm:w-fit">
              {processing ? "Mengirim..." : "Kirim pesan"}
            </Button>
          </FieldGroup>
        </form>
      </CardContent>
    </Card>
  )
}

export function ContactSection({ school }: { school: School }) {
  const items: { label: string; value: string; href: string | null }[] = [
    { label: "Alamat", value: school.address, href: null },
    { label: "Telepon", value: school.phone, href: `tel:${school.phone.replace(/[^\d+]/g, "")}` },
    { label: "Email", value: school.email, href: `mailto:${school.email}` },
    { label: "Instagram", value: "@smk_budimuliakrw", href: school.instagram },
  ]

  return (
    <section id="kontak" className="bg-sidebar py-16 md:py-20">
      <div className="mx-auto max-w-7xl px-4 md:px-6">
        <SectionHeader title="Kontak" description="Tanya soal pendaftaran, jurusan, atau kunjungan lewat telepon, email, atau formulir." />

        <div className="mt-10 grid grid-cols-1 gap-10 lg:grid-cols-2 lg:gap-14">
          <div className="flex flex-col gap-8">
            <Reveal>
              <dl className="divide-y border-y">
                {items.map((item) => (
                  <div key={item.label} className="grid gap-1 py-4 sm:grid-cols-[8rem_1fr] sm:gap-4">
                    <dt className="text-muted-foreground">{item.label}</dt>
                    <dd className="font-medium">
                      {item.href ? (
                        <a
                          href={item.href}
                          {...(item.href.startsWith("http") ? { target: "_blank", rel: "noopener noreferrer" } : {})}
                          className="inline-block break-words underline decoration-foreground/30 underline-offset-4 hover:decoration-foreground"
                        >
                          {item.value}
                        </a>
                      ) : (
                        item.value
                      )}
                    </dd>
                  </div>
                ))}
              </dl>
            </Reveal>

            <Reveal>
              <iframe
                title="Peta lokasi SMK Budi Mulia Karawang"
                src={mapEmbedUrl(school.map)}
                loading="lazy"
                referrerPolicy="no-referrer"
                className="h-72 w-full rounded-xl border-0 ring-1 ring-foreground/10"
              />
              <a
                href={`https://www.openstreetmap.org/?mlat=${school.map.lat}&mlon=${school.map.lng}#map=16/${school.map.lat}/${school.map.lng}`}
                target="_blank"
                rel="noopener noreferrer"
                className="mt-2 inline-block py-2 text-sm font-medium underline decoration-foreground/30 underline-offset-4 hover:decoration-foreground"
              >
                Buka peta di OpenStreetMap
              </a>
            </Reveal>
          </div>

          <Reveal delay={0.05}>
            <ContactForm />
          </Reveal>
        </div>
      </div>
    </section>
  )
}
