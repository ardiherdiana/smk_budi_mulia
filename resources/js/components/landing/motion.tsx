import { motion, type HTMLMotionProps, type Variants } from "framer-motion"

import { cn } from "@/lib/utils"

export const EASE_OUT = [0.22, 1, 0.36, 1] as const

const container: Variants = {
  hidden: {},
  show: { transition: { staggerChildren: 0.07 } },
}

const item: Variants = {
  hidden: { opacity: 0, y: 16 },
  show: { opacity: 1, y: 0, transition: { duration: 0.5, ease: EASE_OUT } },
}

/** Muncul sekali (fade + geser kecil) saat masuk layar, lalu diam. */
export function Reveal({
  className,
  delay = 0,
  y = 16,
  x = 0,
  children,
  ...props
}: HTMLMotionProps<"div"> & { delay?: number; y?: number; x?: number }) {
  return (
    <motion.div
      initial={{ opacity: 0, y, x }}
      whileInView={{ opacity: 1, y: 0, x: 0 }}
      viewport={{ once: true, margin: "-80px" }}
      transition={{ duration: 0.55, delay, ease: EASE_OUT }}
      className={className}
      {...props}
    >
      {children}
    </motion.div>
  )
}

/** Anak-anaknya (StaggerItem) muncul berurutan, sekali. Hanya untuk daftar pendek. */
export function Stagger({ className, children, ...props }: HTMLMotionProps<"div">) {
  return (
    <motion.div
      variants={container}
      initial="hidden"
      whileInView="show"
      viewport={{ once: true, margin: "-60px" }}
      className={className}
      {...props}
    >
      {children}
    </motion.div>
  )
}

export function StaggerItem({ className, children, ...props }: HTMLMotionProps<"div">) {
  return (
    <motion.div variants={item} className={className} {...props}>
      {children}
    </motion.div>
  )
}

/** Judul seksi rata kiri. Tanpa lencana di atasnya: judul sudah menyebut isi seksi. */
export function SectionHeader({
  title,
  description,
  className,
}: {
  title: React.ReactNode
  description?: string
  className?: string
}) {
  return (
    <Reveal className={cn("max-w-2xl", className)}>
      <h2 className="text-3xl font-bold tracking-tight text-balance md:text-4xl">{title}</h2>
      {description && <p className="mt-3 text-lg text-pretty text-muted-foreground">{description}</p>}
    </Reveal>
  )
}
