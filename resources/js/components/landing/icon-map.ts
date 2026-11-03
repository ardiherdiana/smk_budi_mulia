import {
  Award,
  BookOpen,
  Briefcase,
  Building,
  Cable,
  Calculator,
  Camera,
  Code,
  Cog,
  Cpu,
  Database,
  GraduationCap,
  Handshake,
  Heart,
  History,
  Lightbulb,
  Monitor,
  Network,
  Palette,
  Store,
  Target,
  Trophy,
  Users,
  Wallet,
  Wrench,
  type LucideIcon,
} from "lucide-react"

/**
 * Nama ikon yang disimpan di DB -> komponen lucide (daftar tertutup, aman dari input liar).
 * Sengaja tanpa ikon generik ala "AI" (sparkle, bintang, petir): tiap ikon harus relevan dengan isinya.
 * Lucide dipakai karena itu ikon design system `absensi` (lihat DESIGN.md).
 */
const ICONS: Record<string, LucideIcon> = {
  Award,
  BookOpen,
  Briefcase,
  Building,
  Cable,
  Calculator,
  Camera,
  Code,
  Cog,
  Cpu,
  Database,
  GraduationCap,
  Handshake,
  Heart,
  History,
  Lightbulb,
  Monitor,
  Network,
  Palette,
  Store,
  Target,
  Trophy,
  Users,
  Wallet,
  Wrench,
}

export function iconFor(name: string | null | undefined, fallback: LucideIcon = Building): LucideIcon {
  return (name && ICONS[name]) || fallback
}
