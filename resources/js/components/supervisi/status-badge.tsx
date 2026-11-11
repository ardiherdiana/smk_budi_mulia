import { Badge } from "@/components/ui/badge"
import { STATUS_LABEL, statusVariant } from "@/lib/supervisi/format"
import type { StatusSupervisi } from "@/lib/supervisi/types"

export function StatusBadge({ status }: { status: StatusSupervisi }) {
  return <Badge variant={statusVariant(status)}>{STATUS_LABEL[status]}</Badge>
}
