import { StrictMode } from "react"
import { createRoot } from "react-dom/client"
import { createInertiaApp, router } from "@inertiajs/react"
import { toast } from "sonner"

import "./index.css"
import { ThemeProvider } from "@/components/theme-provider.tsx"
import { Toaster } from "@/components/ui/sonner.tsx"
import { ClassroomLayout } from "@/layouts/classroom-layout"
import { DashboardLayout } from "@/layouts/dashboard-layout"
import { SklLayout } from "@/layouts/skl-layout"
import type { FlashToast } from "@/lib/types"

// Halaman full-bleed (tanpa sidebar). Layout dipilih dari awalan nama halaman: `classroom/*` ClassroomLayout,
// `skl/*` SklLayout, `absensi/*` AbsensiLayout, `sppd/*` SppdLayout, `supervisi/*` SupervisiLayout, semua halaman lain
// DashboardLayout (panel Website).
const BARE_PAGES = new Set([
  "landing-page",
  "login-page",
  "portal-page",
  "module-picker-page",
  "profil-page",
  "data-guru-page",
  "classroom/ujian/kerjakan-page",
  "absensi/kiosk-page",
])

// Layout absensi, SPPD, dan Supervisi dimuat lazy bersama halamannya supaya bundle halaman publik tidak ikut membesar.
async function layoutUntuk(name: string): Promise<React.ComponentType<{ children: React.ReactNode }>> {
  if (name.startsWith("classroom/")) return ClassroomLayout
  if (name.startsWith("skl/")) return SklLayout
  if (name.startsWith("absensi/")) return (await import("@/layouts/absensi-layout")).AbsensiLayout
  if (name.startsWith("sppd/")) return (await import("@/layouts/sppd-layout")).SppdLayout
  if (name.startsWith("supervisi/")) return (await import("@/layouts/supervisi-layout")).SupervisiLayout
  return DashboardLayout
}

window.addEventListener("pageshow", (event) => {
  if (event.persisted) {
    window.location.reload()
  }
})

// Service worker (push pengingat absen) hanya untuk pengguna yang login: pengunjung situs publik tidak perlu.
// sw.js hanya menangani push dan klik notifikasi, tanpa cache/fetch, jadi tidak memengaruhi halaman publik.
let swTerdaftar = false
function daftarkanServiceWorker(props: Record<string, unknown>) {
  const auth = props.auth as { user?: unknown } | undefined
  if (swTerdaftar || !auth?.user || !("serviceWorker" in navigator)) return
  swTerdaftar = true
  navigator.serviceWorker.register("/sw.js")
}
router.on("navigate", (event) => daftarkanServiceWorker(event.detail.page.props))

// Toast dari flash session server (`->with('toast', [...])`).
router.on("success", (event) => {
  const flash = (event.detail.page.props as { flash?: { toast?: FlashToast | null } }).flash
  const message = flash?.toast
  if (!message) return
  const show = toast[message.type] ?? toast.info
  show(message.message)
})

type PageModule = Record<string, React.ComponentType>

// Landing page dimuat langsung (pengunjung publik hanya butuh ini); login, portal, dan
// seluruh panel admin dimuat lazy per halaman supaya bundle publik tetap kecil.
const eagerPages = import.meta.glob("./pages/landing-page.tsx", { eager: true }) as Record<string, PageModule>
const lazyPages = import.meta.glob(["./pages/**/*.tsx", "!./pages/landing-page.tsx"]) as Record<
  string,
  () => Promise<PageModule>
>

createInertiaApp({
  resolve: async (name) => {
    const path = `./pages/${name}.tsx`
    const page = eagerPages[path] ?? (await lazyPages[path]?.())
    if (!page) throw new Error(`Page not found: ${name}`)

    const component = Object.values(page)[0]

    if (!BARE_PAGES.has(name)) {
      const Layout = await layoutUntuk(name)
      // @ts-expect-error - Inertia's persistent-layout convention
      component.layout = (page: React.ReactNode) => <Layout>{page}</Layout>
    }

    return component
  },
  setup({ el, App, props }) {
    daftarkanServiceWorker(props.initialPage.props)

    createRoot(el).render(
      <StrictMode>
        <ThemeProvider>
          <App {...props} />
          <Toaster />
        </ThemeProvider>
      </StrictMode>
    )
  },
})
