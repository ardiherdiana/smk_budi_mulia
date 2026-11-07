import * as React from "react"
import { Link, router, usePage } from "@inertiajs/react"
import {
  Award,
  GraduationCap,
  Globe,
  History,
  Home,
  LayoutDashboard,
  LogOut,
  Newspaper,
  Users,
} from "lucide-react"

import { useAuth } from "@/context/auth-context"
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

interface NavItem {
  title: string
  url: string
  icon: React.ComponentType<{ className?: string }>
}

interface NavGroup {
  label: string
  items: NavItem[]
}

const adminNav: NavGroup[] = [
  {
    label: "Menu",
    items: [
      { title: "Menu Utama", url: "/menu-utama", icon: Home },
      { title: "Dashboard", url: "/admin", icon: LayoutDashboard },
    ],
  },
  {
    label: "Konten Website",
    items: [
      { title: "Berita & Galeri", url: "/admin/berita", icon: Newspaper },
      { title: "Tentang Kami", url: "/admin/tentang-kami", icon: History },
      { title: "Jurusan", url: "/admin/jurusan", icon: GraduationCap },
      { title: "Guru & Staf", url: "/admin/guru-staff", icon: Users },
      { title: "Kepala Sekolah", url: "/admin/kepala-sekolah", icon: Award },
    ],
  },
]

export function AppSidebar({ ...props }: React.ComponentProps<typeof Sidebar>) {
  const { user } = useAuth()
  const { url } = usePage()
  const pathname = url.split("?")[0]
  const { isMobile, setOpenMobile } = useSidebar()

  function logout() {
    router.post("/logout")
  }

  return (
    <Sidebar {...props}>
      <SidebarHeader>
        <div className="flex items-center gap-2 px-2 py-1.5">
          <img src="/logo_smk.png" alt="Logo SMK Budi Mulia" className="size-7 shrink-0 object-contain" />
          <div className="flex flex-col leading-none">
            <span className="font-medium">Panel Admin</span>
            <span className="text-xs text-muted-foreground">SMK Budi Mulia</span>
          </div>
        </div>
      </SidebarHeader>
      <SidebarContent>
        {adminNav.map((group) => (
          <SidebarGroup key={group.label}>
            <SidebarGroupLabel>{group.label}</SidebarGroupLabel>
            <SidebarGroupContent>
              <SidebarMenu>
                {group.items.map((item) => {
                  const isActive = item.url === "/admin" || item.url === "/menu-utama" ? pathname === item.url : pathname.startsWith(item.url)
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
        <SidebarGroup>
          <SidebarGroupLabel>Sistem</SidebarGroupLabel>
          <SidebarGroupContent>
            <SidebarMenu>
              <SidebarMenuItem>
                <SidebarMenuButton render={<a href="/" target="_blank" rel="noreferrer" />}>
                  <Globe />
                  Lihat Website
                </SidebarMenuButton>
              </SidebarMenuItem>
            </SidebarMenu>
          </SidebarGroupContent>
        </SidebarGroup>
      </SidebarContent>
      <SidebarFooter>
        <SidebarMenu>
          <SidebarMenuItem>
            <div className="flex items-center justify-between gap-2 px-2 py-1.5 text-sm">
              <span className="truncate font-medium">{user?.email ?? "Admin"}</span>
            </div>
          </SidebarMenuItem>
          <SidebarMenuItem>
            <SidebarMenuButton onClick={logout}>
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
