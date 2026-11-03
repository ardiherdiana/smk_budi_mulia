import * as React from "react"
import { Head } from "@inertiajs/react"
import { MotionConfig } from "framer-motion"

import type { LandingProps } from "@/lib/types"
import { ContactSection } from "@/components/landing/contact-section"
import { FeaturesSection } from "@/components/landing/features-section"
import { HeroSection } from "@/components/landing/hero-section"
import { LeadershipSection } from "@/components/landing/leadership-section"
import { MajorsSection } from "@/components/landing/majors-section"
import { NewsSection } from "@/components/landing/news-section"
import { ProfileSection } from "@/components/landing/profile-section"
import { RegistrationSection } from "@/components/landing/registration-section"
import { SiteFooter } from "@/components/landing/site-footer"
import { SiteHeader } from "@/components/landing/site-header"
import { TeamSection } from "@/components/landing/team-section"

export function LandingPage(props: LandingProps) {
  const { school, majors, abouts, principals, staff, posts, categories, filters, activePost } = props

  // Tautan langsung ke sebuah berita (/berita/{id}) atau #anchor: gulir ke bagian yang tepat sekali saat dibuka.
  React.useEffect(() => {
    const target = activePost ? "berita" : window.location.hash.slice(1)
    if (!target) return
    const timer = window.setTimeout(() => document.getElementById(target)?.scrollIntoView(), 120)
    return () => window.clearTimeout(timer)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  // Meta description, canonical, Open Graph, dan data terstruktur dirender server (partials/seo.blade.php)
  // supaya crawler dan WhatsApp membacanya dari HTML awal. Di sini hanya judul tab yang ikut berganti
  // saat dialog berita dibuka/ditutup tanpa memuat ulang halaman.
  const title = activePost ? `${activePost.title} - ${school.short_name}` : school.seo_title

  return (
    <MotionConfig reducedMotion="user">
      <Head title={title} />

      <a
        href="#isi"
        className="sr-only z-[70] rounded-lg bg-foreground px-4 py-3 text-background focus:not-sr-only focus:fixed focus:top-3 focus:left-3"
      >
        Lewati ke konten utama
      </a>

      <SiteHeader school={school} />

      <main id="isi">
        <HeroSection school={school} majors={majors} />
        <FeaturesSection school={school} />
        <MajorsSection majors={majors} />
        <ProfileSection school={school} abouts={abouts} />
        <LeadershipSection principals={principals} />
        <TeamSection staff={staff} />
        <NewsSection posts={posts} categories={categories} filters={filters} activePost={activePost} />
        <RegistrationSection school={school} />
        <ContactSection school={school} />
      </main>

      <SiteFooter school={school} />
    </MotionConfig>
  )
}
