import * as React from "react"
import { Link, router, usePage } from "@inertiajs/react"
import {
  CalendarClock,
  ClipboardCheck,
  ClipboardPen,
  FileSpreadsheet,
  Home,
  LayoutDashboard,
  LogOut,
} from "lucide-react"

import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarGroup,
  SidebarGroupContent,
  SidebarGroupLabel,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarRail,
  useSidebar,
} from "@/components/ui/sidebar"
import type { AuthUser } from "@/lib/types"
import type { MenuSupervisi } from "@/lib/supervisi/types"

interface NavItem {
  title: string
  url: string
  icon: React.ComponentType<{ className?: string }>
}

interface PageProps {
  auth: { user: AuthUser | null }
  supervisiMenu?: MenuSupervisi
  [key: string]: unknown
}

export function SupervisiSidebar(props: React.ComponentProps<typeof Sidebar>) {
  const page = usePage<PageProps>()
  const { auth, supervisiMenu } = page.props
  const pathname = page.url.split("?")[0]
  const { isMobile, setOpenMobile } = useSidebar()

  const menu: NavItem[] = [
    { title: "Menu Utama", url: "/menu-utama", icon: Home },
    { title: "Dashboard", url: "/supervisi/dashboard", icon: LayoutDashboard },
  ]
  if (supervisiMenu?.disupervisi) menu.push({ title: "Supervisi Saya", url: "/supervisi/saya", icon: ClipboardCheck })
  if (supervisiMenu?.tugas) menu.push({ title: "Tugas Supervisi", url: "/supervisi/tugas", icon: ClipboardPen })

  const groups: { label: string; items: NavItem[] }[] = [{ label: "Menu", items: menu }]
  if (supervisiMenu?.kelola) {
    groups.push({
      label: "Kelola",
      items: [
        { title: "Jadwal Supervisi", url: "/supervisi/jadwal", icon: CalendarClock },
        { title: "Rekap", url: "/supervisi/rekap", icon: FileSpreadsheet },
      ],
    })
  }

  return (
    <Sidebar {...props}>
      <SidebarHeader>
        <div className="flex items-center gap-2 px-2 py-1.5">
          <img src="/logo_smk.png" alt="Logo SMK Budi Mulia" className="size-7 shrink-0 object-contain" />
          <div className="flex flex-col leading-none">
            <span className="font-medium">Supervisi Guru</span>
            <span className="text-xs text-muted-foreground">SMK Budi Mulia Karawang</span>
          </div>
        </div>
      </SidebarHeader>
      <SidebarContent>
        {groups.map((group) => (
          <SidebarGroup key={group.label}>
            <SidebarGroupLabel>{group.label}</SidebarGroupLabel>
            <SidebarGroupContent>
              <SidebarMenu>
                {group.items.map((item) => {
                  const isActive = item.url === "/menu-utama" ? pathname === item.url : pathname.startsWith(item.url)
                  return (
                    <SidebarMenuItem key={item.title}>
                      <SidebarMenuButton
                        isActive={isActive}
                        onClick={() => isMobile && setOpenMobile(false)}
                        render={<Link href={item.url} />}
                      >
                        <item.icon />
                        {item.title}
                      </SidebarMenuButton>
                    </SidebarMenuItem>
                  )
                })}
              </SidebarMenu>
            </SidebarGroupContent>
          </SidebarGroup>
        ))}
      </SidebarContent>
      <SidebarFooter>
        <SidebarMenu>
          <SidebarMenuItem>
            <div className="truncate px-2 py-1.5 text-sm font-medium">
              {auth.user?.admin ? "Admin" : auth.user?.name}
            </div>
          </SidebarMenuItem>
          <SidebarMenuItem>
            <SidebarMenuButton onClick={() => router.post("/logout")}>
              <LogOut />
              Keluar
            </SidebarMenuButton>
          </SidebarMenuItem>
        </SidebarMenu>
      </SidebarFooter>
      <SidebarRail />
    </Sidebar>
  )
}
