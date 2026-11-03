import { Link } from "@inertiajs/react"
import { Files, UserRound } from "lucide-react"

const modules = [
  {
    title: "Akun Siswa",
    description: "Daftarkan siswa/alumni, atur ulang kata sandi, impor dan ekspor CSV",
    icon: UserRound,
    href: "/skl/siswa",
  },
  {
    title: "Berkas (SKL)",
    description: "Unggah PDF SKL, bagikan ke siswa, dan kelola gudang berkas",
    icon: Files,
    href: "/skl/berkas",
  },
]

export function SklDashboardPage() {
  return (
    <div className="flex flex-col gap-6">
      <div>
        <h1 className="text-xl font-semibold">Dashboard SKL</h1>
        <p className="text-sm text-muted-foreground">Pilih bagian yang ingin dikelola</p>
      </div>

      <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
        {modules.map((mod) => (
          <Link
            key={mod.href}
            href={mod.href}
            className="flex flex-col items-start gap-4 rounded-xl bg-card p-6 text-card-foreground ring-1 ring-foreground/10 transition-colors hover:bg-accent"
          >
            <div className="flex size-11 items-center justify-center rounded-lg bg-secondary text-secondary-foreground">
              <mod.icon className="size-5" />
            </div>
            <div className="space-y-1">
              <h2 className="font-heading text-base font-medium">{mod.title}</h2>
              <p className="text-sm text-muted-foreground">{mod.description}</p>
            </div>
          </Link>
        ))}
      </div>
    </div>
  )
}
