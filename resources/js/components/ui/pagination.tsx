import * as React from "react"
import { ChevronLeftIcon, ChevronRightIcon } from "lucide-react"

import { cn } from "@/lib/utils"
import { Button } from "@/components/ui/button"

function Pagination({ className, ...props }: React.ComponentProps<"nav">) {
  return (
    <nav
      role="navigation"
      aria-label="pagination"
      data-slot="pagination"
      className={cn("mx-auto flex w-full justify-center", className)}
      {...props}
    />
  )
}

function PaginationContent({ className, ...props }: React.ComponentProps<"ul">) {
  return (
    <ul
      data-slot="pagination-content"
      className={cn("flex flex-row items-center gap-1", className)}
      {...props}
    />
  )
}

function PaginationItem(props: React.ComponentProps<"li">) {
  return <li data-slot="pagination-item" {...props} />
}

function PaginationPrevious({
  className,
  disabled,
  ...props
}: React.ComponentProps<typeof Button> & { disabled?: boolean }) {
  return (
    <Button
      variant="outline"
      size="sm"
      disabled={disabled}
      aria-label="Halaman sebelumnya"
      className={cn("gap-1", className)}
      {...props}
    >
      <ChevronLeftIcon />
      Sebelumnya
    </Button>
  )
}

function PaginationNext({
  className,
  disabled,
  ...props
}: React.ComponentProps<typeof Button> & { disabled?: boolean }) {
  return (
    <Button
      variant="outline"
      size="sm"
      disabled={disabled}
      aria-label="Halaman berikutnya"
      className={cn("gap-1", className)}
      {...props}
    >
      Selanjutnya
      <ChevronRightIcon />
    </Button>
  )
}

export { Pagination, PaginationContent, PaginationItem, PaginationPrevious, PaginationNext }
