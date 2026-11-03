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

// Halaman full-bleed (tanpa sidebar). `classroom/*` memakai ClassroomLayout, `skl/*` SklLayout, semua halaman lain DashboardLayout.
const BARE_PAGES = new Set(["landing-page", "login-page", "portal-page", "module-picker-page", "classroom/ujian/kerjakan-page"])

window.addEventListener("pageshow", (event) => {
  if (event.persisted) {
    window.location.reload()
  }
})

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
      const Layout = name.startsWith("classroom/")
        ? ClassroomLayout
        : name.startsWith("skl/")
          ? SklLayout
          : DashboardLayout
      // @ts-expect-error - Inertia's persistent-layout convention
      component.layout = (page: React.ReactNode) => <Layout>{page}</Layout>
    }

    return component
  },
  setup({ el, App, props }) {
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
