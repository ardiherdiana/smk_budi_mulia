import type { ReactNode } from "react"
import { SklSidebar } from "@/components/skl-sidebar"
import { Separator } from "@/components/ui/separator"
import { ThemeToggle } from "@/components/theme-toggle"
import {
  SidebarInset,
  SidebarProvider,
  SidebarTrigger,
} from "@/components/ui/sidebar"

export function SklLayout({ children }: { children: ReactNode }) {
  return (
    <SidebarProvider>
      <SklSidebar />
      {/* min-w-0: tanpa ini isi yang lebar (tabel) memaksa kolom utama melebar dan halaman ikut scroll horizontal. */}
      <SidebarInset className="min-w-0">
        <header className="flex h-14 shrink-0 items-center gap-2 border-b px-4">
          <SidebarTrigger />
          <Separator orientation="vertical" className="h-4!" />
          <h1 className="text-sm font-medium text-muted-foreground">SKL SMK Budi Mulia</h1>
          <div className="ml-auto flex items-center">
            <ThemeToggle />
          </div>
        </header>
        <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
          {children}
        </div>
      </SidebarInset>
    </SidebarProvider>
  )
}
