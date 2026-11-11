import * as React from "react"
import { router } from "@inertiajs/react"
import { LogOut } from "lucide-react"

import { useAuth } from "@/context/classroom-auth"
import { initials } from "@/lib/classroom/utils"
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import { Button } from "@/components/ui/button"
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover"

const LABEL_ROLE = { ADMIN: "Admin", KEPSEK: "Kepala sekolah", GURU: "Guru", SISWA: "Siswa" } as const

/** Avatar di pojok kanan atas: nama, peran, dan Keluar. Profil dibuka dari kartu Profil di Menu Utama (satu untuk semua modul). */
export function UserMenu() {
  const { user } = useAuth()
  const [buka, setBuka] = React.useState(false)
  if (!user) return null

  return (
    <Popover open={buka} onOpenChange={setBuka}>
      <PopoverTrigger
        render={
          <button
            type="button"
            aria-label="Menu akun"
            className="rounded-full outline-none focus-visible:ring-2 focus-visible:ring-ring max-md:p-1.5"
          />
        }
      >
        <Avatar>
          {user.fotoUrl && <AvatarImage src={user.fotoUrl} alt="" className="object-top" />}
          <AvatarFallback>{initials(user.name)}</AvatarFallback>
        </Avatar>
      </PopoverTrigger>
      <PopoverContent align="end" className="w-80 gap-1 p-2">
        <div className="flex items-center gap-4 rounded-lg px-2 py-2">
          <Avatar size="lg">
            {user.fotoUrl && <AvatarImage src={user.fotoUrl} alt="" className="object-top" />}
            <AvatarFallback>{initials(user.name)}</AvatarFallback>
          </Avatar>
          <div className="min-w-0">
            <p className="truncate text-base font-medium">{user.name}</p>
            <p className="truncate text-xs text-muted-foreground">
              {user.username} · {LABEL_ROLE[user.role]}
            </p>
          </div>
        </div>
        <Button
          variant="ghost"
          className="justify-start gap-3"
          onClick={() => router.post("/logout")}
        >
          <LogOut /> Keluar
        </Button>
      </PopoverContent>
    </Popover>
  )
}
