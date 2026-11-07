import * as React from "react"
import { Link, router, usePage } from "@inertiajs/react"
import {
  CalendarOff,
  ClipboardList,
  FileText,
  Home,
  LayoutDashboard,
  LogOut,
  QrCode,
  ScanLine,
  Settings,
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

// Profil dan notifikasi tidak ada di sini: keduanya terpusat di Menu Utama (kartu Profil, lonceng di topbar).
// Data Guru juga: data master untuk semua modul, dibuka dari kartu Data Guru di Menu Utama.
const adminNav: NavGroup[] = [
  {
    label: "Menu",
    items: [
      { title: "Menu Utama", url: "/menu-utama", icon: Home },
      { title: "Dashboard", url: "/absen/dashboard", icon: LayoutDashboard },
    ],
  },
  {
    label: "Absensi",
    items: [
      { title: "Scan QR", url: "/kiosk", icon: QrCode },
      { title: "Absen Briefing", url: "/briefing", icon: ScanLine },
    ],
  },
  {
    label: "Data",
    items: [
      { title: "Rekap Absensi", url: "/rekap", icon: ClipboardList },
      { title: "Izin & Sakit", url: "/persetujuan", icon: FileText },
      { title: "Hari Libur", url: "/hari-libur", icon: CalendarOff },
    ],
  },
  {
    label: "Sistem",
    items: [
      { title: "Pengaturan", url: "/pengaturan", icon: Settings },
    ],
  },
]

const guruNav: NavGroup[] = [
  {
    label: "Menu",
    items: [
      { title: "Menu Utama", url: "/menu-utama", icon: Home },
      { title: "Dashboard", url: "/absen/dashboard", icon: LayoutDashboard },
      { title: "QR Saya", url: "/qr", icon: QrCode },
      { title: "Riwayat Absensi", url: "/riwayat", icon: ClipboardList },
      { title: "Izin & Sakit", url: "/izin", icon: FileText },
    ],
  },
]

const kepsekNav: NavGroup[] = [
  {
    label: "Absensi Saya",
    items: [
      { title: "Menu Utama", url: "/menu-utama", icon: Home },
      { title: "Dashboard", url: "/absen/dashboard", icon: LayoutDashboard },
      { title: "QR Saya", url: "/qr", icon: QrCode },
      { title: "Riwayat Absensi", url: "/riwayat", icon: ClipboardList },
      { title: "Izin & Sakit Saya", url: "/izin", icon: FileText },
    ],
  },
  {
    label: "Sekolah",
    items: [
      { title: "Rekap Absensi", url: "/rekap", icon: ClipboardList },
      { title: "Approve Izin & Sakit", url: "/persetujuan", icon: FileText },
      { title: "Hari Libur", url: "/hari-libur", icon: CalendarOff },
    ],
  },
  {
    label: "Sistem",
    items: [
      { title: "Pengaturan", url: "/pengaturan", icon: Settings },
    ],
  },
]

export function AbsensiSidebar({ ...props }: React.ComponentProps<typeof Sidebar>) {
  const { user } = useAuth()
  const { url } = usePage()
  const pathname = url.split("?")[0]
  const { isMobile, setOpenMobile } = useSidebar()
  const groups = user?.role === "ADMIN" ? adminNav : user?.role === "KEPSEK" ? kepsekNav : guruNav
  const displayName = user?.role === "ADMIN" ? "Admin" : (user?.guru?.nama ?? user?.username)

  function logout() {
    router.post("/logout")
  }

  return (
    <Sidebar {...props}>
      <SidebarHeader>
        <div className="flex items-center gap-2 px-2 py-1.5">
          <img src="/logo_smk.png" alt="Logo SMK Budi Mulia" className="size-7 shrink-0 object-contain" />
          <div className="flex flex-col leading-none">
            <span className="font-medium">Absensi Guru</span>
            <span className="text-xs text-muted-foreground">SMK Budi Mulia</span>
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
                  const isExact = item.url === "/menu-utama"
                  const isActive = isExact
                    ? pathname === item.url
                    : pathname.startsWith(item.url)
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
            <div className="flex items-center justify-between gap-2 px-2 py-1.5 text-sm">
              <span className="truncate font-medium">{displayName}</span>
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
