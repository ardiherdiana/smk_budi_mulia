import { usePage } from "@inertiajs/react"
import type { ReactNode } from "react"
import { ClassroomSidebar } from "@/components/classroom/classroom-sidebar"
import { Separator } from "@/components/ui/separator"
import { ThemeToggle } from "@/components/theme-toggle"
import { UserMenu } from "@/components/classroom/user-menu"
import {
  SidebarInset,
  SidebarProvider,
  SidebarTrigger,
} from "@/components/ui/sidebar"

const JUDUL_HALAMAN: Record<string, string> = {
  "/classroom/tugas": "Daftar tugas",
  "/classroom/kalender": "Kalender",
  "/classroom/kelas?arsip=1": "Kelas yang diarsipkan",
}

export function ClassroomLayout({ children }: { children: ReactNode }) {
  const { url } = usePage()
  const judulHalaman = JUDUL_HALAMAN[url] ?? JUDUL_HALAMAN[url.split("?")[0]]
  return (
    <SidebarProvider>
      <ClassroomSidebar />
      <SidebarInset>
        <header className="flex h-14 shrink-0 items-center gap-2 border-b px-4">
          <SidebarTrigger />
          <Separator orientation="vertical" className="h-4!" />
          <h1 className="min-w-0 truncate text-sm font-medium text-muted-foreground">
            {judulHalaman ? `Classroom › ${judulHalaman}` : "Classroom SMK Budi Mulia"}
          </h1>
          <div className="ml-auto flex items-center gap-2">
            <ThemeToggle />
            <UserMenu />
          </div>
        </header>
        <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
          {children}
        </div>
      </SidebarInset>
    </SidebarProvider>
  )
}
