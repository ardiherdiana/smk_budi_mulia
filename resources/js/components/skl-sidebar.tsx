import * as React from "react"
import { Link, router, usePage } from "@inertiajs/react"
import { Files, Home, KeyRound, LayoutDashboard, LogOut, UserRound } from "lucide-react"

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
import { PasswordDialog } from "@/components/password-dialog"

interface NavItem {
  title: string
  url: string
  icon: React.ComponentType<{ className?: string }>
  /** Aktif hanya jika alamat persis sama. */
  persis?: boolean
}

interface NavGroup {
  label: string
  items: NavItem[]
}

const nav: NavGroup[] = [
  {
    label: "Menu",
    items: [
      { title: "Menu Utama", url: "/menu-utama", icon: Home, persis: true },
      { title: "Dashboard", url: "/skl", icon: LayoutDashboard, persis: true },
    ],
  },
  {
    label: "Data",
    items: [
      { title: "Akun Siswa", url: "/skl/siswa", icon: UserRound },
      { title: "Berkas (SKL)", url: "/skl/berkas", icon: Files },
    ],
  },
]

export function SklSidebar({ ...props }: React.ComponentProps<typeof Sidebar>) {
  const { user } = useAuth()
  const { url } = usePage()
  const pathname = url.split("?")[0]
  const { isMobile, setOpenMobile } = useSidebar()
  const [passwordOpen, setPasswordOpen] = React.useState(false)

  return (
    <Sidebar {...props}>
      <SidebarHeader>
        <div className="flex items-center gap-2 px-2 py-1.5">
          <img src="/logo_smk.png" alt="Logo SMK Budi Mulia" className="size-7 shrink-0 object-contain" />
          <div className="flex flex-col leading-none">
            <span className="font-medium">SKL</span>
            <span className="text-xs text-muted-foreground">SMK Budi Mulia</span>
          </div>
        </div>
      </SidebarHeader>
      <SidebarContent>
        {nav.map((group) => (
          <SidebarGroup key={group.label}>
            <SidebarGroupLabel>{group.label}</SidebarGroupLabel>
            <SidebarGroupContent>
              <SidebarMenu>
                {group.items.map((item) => {
                  const isActive = item.persis ? pathname === item.url : pathname.startsWith(item.url)
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
                <SidebarMenuButton onClick={() => setPasswordOpen(true)}>
                  <KeyRound />
                  Ganti Password
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
            <SidebarMenuButton onClick={() => router.post("/logout")}>
              <LogOut />
              Keluar
            </SidebarMenuButton>
          </SidebarMenuItem>
        </SidebarMenu>
      </SidebarFooter>
      <SidebarRail />
      <PasswordDialog open={passwordOpen} onOpenChange={setPasswordOpen} />
    </Sidebar>
  )
}
