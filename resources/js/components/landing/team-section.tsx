import * as React from "react"
import { AnimatePresence, motion } from "framer-motion"
import { ChevronDown, Search } from "lucide-react"

import { initials } from "@/lib/utils"
import type { StaffItem } from "@/lib/types"
import { Button } from "@/components/ui/button"
import { Card } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { EASE_OUT, Reveal, SectionHeader } from "@/components/landing/motion"

type Group = "pimpinan" | "guru" | "staf"

const GROUPS: { id: Group; label: string; roles: StaffItem["role"][] }[] = [
  { id: "pimpinan", label: "Pimpinan", roles: ["vice_principal", "program_head"] },
  { id: "guru", label: "Guru", roles: ["teacher"] },
  // Label satu kata supaya ketiga tab sama lebar dan sama tinggi (judul seksi sudah menyebut "tenaga kependidikan").
  { id: "staf", label: "Staf", roles: ["staff"] },
]

const INITIAL_VISIBLE = 12

function roleLine(member: StaffItem): string {
  const detail = member.expertise || member.subject || member.position || ""
  if (member.role === "vice_principal") return ["Wakil Kepala Sekolah", detail].filter(Boolean).join(" · ")
  if (member.role === "program_head") return ["Kepala Program", detail].filter(Boolean).join(" · ")
  if (member.role === "staff") return detail || "Tenaga Kependidikan"
  return detail || "Guru"
}

/**
 * Bingkai foto 3:4 tegak, dijangkar ke ATAS (object-top). Alasannya dari data: 31 dari 35 foto guru
 * berukuran 2:3 tegak dengan kepala di sepertiga atas, jadi lingkaran/persegi 1:1 tengah memotong
 * sepertiga foto dan sering memangkas puncak kepala. Bingkai 3:4 hanya memangkas ~11% bagian bawah.
 * Foto asli di database tidak diubah; bingkai yang menyesuaikan.
 */
function MemberCard({ member }: { member: StaffItem }) {
  return (
    <motion.li
      layout
      initial={{ opacity: 0, scale: 0.96 }}
      animate={{ opacity: 1, scale: 1 }}
      exit={{ opacity: 0, scale: 0.96 }}
      transition={{ duration: 0.3, ease: EASE_OUT }}
    >
      <Card className="h-full gap-0 overflow-hidden py-0 text-center">
        <div className="aspect-[3/4] w-full overflow-hidden bg-secondary">
          {member.image_url ? (
            <img src={member.image_url} alt={member.name} loading="lazy" className="size-full object-cover object-top" />
          ) : (
            <div className="flex size-full items-center justify-center text-4xl font-semibold text-secondary-foreground" aria-hidden>
              {initials(member.name)}
            </div>
          )}
        </div>
        <div className="flex flex-1 flex-col gap-1 px-3 py-3">
          <p className="text-sm leading-snug font-semibold text-balance">{member.name}</p>
          <p className="text-xs leading-snug text-pretty text-muted-foreground">{roleLine(member)}</p>
        </div>
      </Card>
    </motion.li>
  )
}

export function TeamSection({ staff }: { staff: StaffItem[] }) {
  const [group, setGroup] = React.useState<Group>("pimpinan")
  const [query, setQuery] = React.useState("")
  const [expanded, setExpanded] = React.useState(false)

  const byGroup = React.useMemo(
    () =>
      Object.fromEntries(
        GROUPS.map((g) => [g.id, staff.filter((s) => g.roles.includes(s.role))])
      ) as Record<Group, StaffItem[]>,
    [staff]
  )

  const normalized = query.trim().toLowerCase()
  const filtered = byGroup[group].filter(
    (m) => !normalized || m.name.toLowerCase().includes(normalized) || roleLine(m).toLowerCase().includes(normalized)
  )
  const visible = expanded || normalized ? filtered : filtered.slice(0, INITIAL_VISIBLE)
  const hidden = filtered.length - visible.length

  return (
    <section id="guru" className="bg-background py-20 md:py-24">
      <div className="mx-auto max-w-7xl px-4 md:px-6">
        <SectionHeader title="Guru dan tenaga kependidikan" />

        <Reveal className="mt-8 flex flex-col items-stretch justify-between gap-4 md:flex-row md:items-center">
          <Tabs
            value={group}
            onValueChange={(value) => {
              setGroup(value as Group)
              setExpanded(false)
            }}
          >
            {/* Tiga kolom sama lebar supaya simetris. */}
            <TabsList className="grid w-full grid-cols-3 items-stretch md:w-[26rem]">
              {GROUPS.map((g) => (
                // Layar kecil: label dan jumlah ditumpuk dua baris supaya label panjang tidak terpotong.
                <TabsTrigger
                  key={g.id}
                  value={g.id}
                  className="flex-col gap-0 px-2 py-2 text-center leading-tight whitespace-normal sm:flex-row sm:gap-1.5 sm:py-3"
                >
                  <span>{g.label}</span>
                  <span className="font-normal tabular-nums">{byGroup[g.id].length}</span>
                </TabsTrigger>
              ))}
            </TabsList>
          </Tabs>

          <div className="relative w-full md:w-72">
            <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input
              value={query}
              onChange={(e) => setQuery(e.target.value)}
              placeholder="Cari nama atau bidang"
              aria-label="Cari guru atau staf"
              className="h-11 pl-8 sm:h-8"
            />
          </div>
        </Reveal>

        <motion.ul layout className="mt-8 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">
          <AnimatePresence mode="popLayout">
            {visible.map((member) => (
              <MemberCard key={member.id} member={member} />
            ))}
          </AnimatePresence>
        </motion.ul>

        {filtered.length === 0 && (
          <p className="mt-8 text-muted-foreground">
            {normalized ? `Tidak ada yang cocok dengan "${query.trim()}". Coba kata lain atau pindah kelompok.` : "Data kelompok ini belum ditambahkan."}
          </p>
        )}

        {hidden > 0 && (
          <div className="mt-8">
            <Button variant="outline" className="h-11 px-4 text-base" onClick={() => setExpanded(true)}>
              Tampilkan {hidden} lainnya <ChevronDown />
            </Button>
          </div>
        )}
      </div>
    </section>
  )
}
