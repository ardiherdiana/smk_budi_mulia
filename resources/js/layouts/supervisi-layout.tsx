import type { ReactNode } from "react"
import { SupervisiSidebar } from "@/components/supervisi/supervisi-sidebar"
import { Separator } from "@/components/ui/separator"
import { ThemeToggle } from "@/components/theme-toggle"
import {
  SidebarInset,
  SidebarProvider,
  SidebarTrigger,
} from "@/components/ui/sidebar"

export function SupervisiLayout({ children }: { children: ReactNode }) {
  return (
    <SidebarProvider>
      <SupervisiSidebar />
      <SidebarInset>
        <header className="flex h-14 shrink-0 items-center gap-2 border-b px-4">
          <SidebarTrigger />
          <Separator orientation="vertical" className="h-4!" />
          <h1 className="min-w-0 truncate text-sm font-medium text-muted-foreground">
            Supervisi Guru
          </h1>
          <div className="ml-auto flex items-center">
            <ThemeToggle />
          </div>
        </header>
        <div className="flex min-w-0 flex-1 flex-col gap-4 p-4 md:p-6">
          {children}
        </div>
      </SidebarInset>
    </SidebarProvider>
  )
}
