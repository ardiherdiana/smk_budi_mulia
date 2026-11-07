import type { ReactNode } from "react"
import { SppdSidebar } from "@/components/sppd/sppd-sidebar"
import { Separator } from "@/components/ui/separator"
import { ThemeToggle } from "@/components/theme-toggle"
import {
  SidebarInset,
  SidebarProvider,
  SidebarTrigger,
} from "@/components/ui/sidebar"
import { useFlashToast } from "@/hooks/use-flash-toast"

export function SppdLayout({ children }: { children: ReactNode }) {
  useFlashToast()

  return (
    <SidebarProvider>
      <SppdSidebar />
      <SidebarInset>
        <header className="flex h-14 shrink-0 items-center gap-2 border-b px-4">
          <SidebarTrigger />
          <Separator orientation="vertical" className="h-4!" />
          <h1 className="text-sm font-medium text-muted-foreground">
            Modul SPPD
          </h1>
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
