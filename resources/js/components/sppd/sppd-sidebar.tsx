import * as React from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import {
    ClipboardListIcon,
    FileClockIcon,
    HomeIcon,
    LayoutDashboardIcon,
    LogOutIcon,
    UsersIcon,
} from 'lucide-react';

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
} from '@/components/ui/sidebar';
import { type SppdPageProps } from '@/types/sppd';

interface NavItem {
    title: string;
    url: string;
    icon: React.ComponentType<{ className?: string }>;
}

interface NavGroup {
    label: string;
    items: NavItem[];
}

const path = (name: string) => route(name, undefined, false);

export function SppdSidebar({ ...props }: React.ComponentProps<typeof Sidebar>) {
    const page = usePage<SppdPageProps>();
    const { auth } = page.props;
    const pathname = page.url.split('?')[0];
    const { isMobile, setOpenMobile } = useSidebar();
    const roles = auth.roles;
    // Admin akun absensi (users.role) lolos semua pengecekan Policy & middleware role di backend
    // (lihat App\Models\User::isAdmin, Gate::before di AppServiceProvider) — sidebar mengikuti hal
    // yang sama supaya semua menu yang bisa diakses juga terlihat.
    const isAdmin = auth.user?.role === 'ADMIN';

    const menu: NavItem[] = [
        { title: 'Menu Utama', url: '/menu-utama', icon: HomeIcon },
        { title: 'Dashboard', url: path('sppd.dashboard'), icon: LayoutDashboardIcon },
        { title: 'Pengajuan SPPD', url: path('sppd.pengajuan.index'), icon: ClipboardListIcon },
    ];

    // Profil dan notifikasi tidak ada di sini: keduanya terpusat di Menu Utama (kartu Profil, lonceng di topbar).
    const sistem: NavItem[] = [];

    if (isAdmin || roles.some((r) => ['kepala_sekolah', 'tu', 'bendahara'].includes(r))) {
        sistem.push({ title: 'Log Audit', url: path('sppd.audit-log.index'), icon: FileClockIcon });
    }

    const groups: NavGroup[] = [{ label: 'Menu', items: menu }];

    if (isAdmin || roles.includes('tu')) {
        groups.push({
            label: 'Tata Usaha',
            items: [
                { title: 'Data Pegawai', url: path('sppd.pegawai.index'), icon: UsersIcon },
            ],
        });
    }

    if (isAdmin || roles.includes('bendahara')) {
        groups.push({
            label: 'Bendahara',
            items: [
                { title: 'Laporan Keuangan', url: path('sppd.laporan.index'), icon: FileClockIcon },
            ],
        });
    }

    if (sistem.length > 0) {
        groups.push({ label: 'Sistem', items: sistem });
    }

    return (
        <Sidebar {...props}>
            <SidebarHeader>
                <div className="flex items-center gap-2 px-2 py-1.5">
                    <img src="/logo_smk.png" alt="Logo SMK Budi Mulia" className="size-7 shrink-0 object-contain" />
                    <div className="flex flex-col leading-none">
                        <span className="font-medium">SPPD</span>
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
                                    const isExact = item.url === '/menu-utama';
                                    const isActive = isExact ? pathname === item.url : pathname.startsWith(item.url);

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
                                    );
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
                            <span className="truncate font-medium">{isAdmin ? 'Admin' : auth.user?.name}</span>
                        </div>
                    </SidebarMenuItem>
                    <SidebarMenuItem>
                        <SidebarMenuButton onClick={() => router.post('/logout')}>
                            <LogOutIcon />
                            Keluar
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarFooter>
            <SidebarRail />
        </Sidebar>
    );
}
