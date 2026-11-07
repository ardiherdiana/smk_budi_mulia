import * as React from "react"
import { router } from "@inertiajs/react"
import { toast } from "sonner"
import { Check, Eye, Paperclip, X } from "lucide-react"

import { assetUrl } from "@/lib/absensi/api"
import type { LeaveRequest, StatusIzin } from "@/lib/absensi/types"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { DataPagination } from "@/components/data-pagination"
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"

function statusBadge(status: StatusIzin) {
  if (status === "APPROVED") return { label: "Disetujui", variant: "success" as const }
  if (status === "REJECTED") return { label: "Ditolak", variant: "destructive" as const }
  return { label: "Menunggu", variant: "warning" as const }
}

interface Props {
  list: LeaveRequest[]
  filter: StatusIzin | "all"
}

export function AdminIzinPage({ list, filter }: Props) {
  const [busyId, setBusyId] = React.useState<string | null>(null)
  const [detail, setDetail] = React.useState<LeaveRequest | null>(null)
  const [page, setPage] = React.useState(1)
  const [pageSize, setPageSize] = React.useState(50)

  function changeFilter(next: StatusIzin | "all") {
    router.get(
      "/persetujuan",
      { status: next },
      { preserveState: true, replace: true, only: ["list", "filter"] }
    )
    setPage(1)
  }

  const paginatedList = list.slice((page - 1) * pageSize, page * pageSize)

  function review(id: string, status: "APPROVED" | "REJECTED") {
    setBusyId(id)
    router.patch(
      `/persetujuan/${id}/review`,
      { status },
      {
        preserveScroll: true,
        onSuccess: () => {
          setDetail(null)
          toast.success(status === "APPROVED" ? "Pengajuan disetujui" : "Pengajuan ditolak")
        },
        onError: () => toast.error("Gagal memproses pengajuan"),
        onFinish: () => setBusyId(null),
      }
    )
  }

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-xl font-semibold">Izin & Sakit</h1>
          <p className="text-sm text-muted-foreground">Tinjau pengajuan izin dari guru</p>
        </div>
        <Select
          items={{ PENDING: "Menunggu", APPROVED: "Disetujui", REJECTED: "Ditolak", all: "Semua" }}
          value={filter}
          onValueChange={(v) => v && changeFilter(v as StatusIzin | "all")}
        >
          <SelectTrigger className="w-48">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="PENDING">Menunggu</SelectItem>
            <SelectItem value="APPROVED">Disetujui</SelectItem>
            <SelectItem value="REJECTED">Ditolak</SelectItem>
            <SelectItem value="all">Semua</SelectItem>
          </SelectContent>
        </Select>
      </div>

      <div className="overflow-x-auto rounded-lg border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead className="w-10">No</TableHead>
              <TableHead>Guru</TableHead>
              <TableHead>Jenis</TableHead>
              <TableHead>Periode</TableHead>
              <TableHead>Alasan</TableHead>
              <TableHead>Status</TableHead>
              <TableHead className="text-right">Aksi</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {list.length === 0 ? (
              <TableRow>
                <TableCell colSpan={7} className="text-center text-muted-foreground">
                  Tidak ada pengajuan
                </TableCell>
              </TableRow>
            ) : (
              paginatedList.map((item, index) => {
                const badge = statusBadge(item.status)
                return (
                  <TableRow key={item.id}>
                    <TableCell className="text-muted-foreground">
                      {(page - 1) * pageSize + index + 1}
                    </TableCell>
                    <TableCell>
                      <div className="font-medium">{item.guru?.nama}</div>
                    </TableCell>
                    <TableCell>{item.jenis === "IZIN" ? "Izin" : "Sakit"}</TableCell>
                    <TableCell>
                      {item.tanggalMulai.slice(0, 10)} s/d {item.tanggalSelesai.slice(0, 10)}
                    </TableCell>
                    <TableCell className="max-w-xs truncate">{item.alasan}</TableCell>
                    <TableCell>
                      <Badge variant={badge.variant}>{badge.label}</Badge>
                    </TableCell>
                    <TableCell className="text-right">
                      <Button variant="ghost" size="icon-sm" onClick={() => setDetail(item)}>
                        <Eye />
                      </Button>
                    </TableCell>
                  </TableRow>
                )
              })
            )}
          </TableBody>
        </Table>
      </div>

      <DataPagination
        page={page}
        pageSize={pageSize}
        total={list.length}
        onPageChange={setPage}
        onPageSizeChange={(size) => {
          setPageSize(size)
          setPage(1)
        }}
      />

      <Dialog open={detail !== null} onOpenChange={(open) => !open && setDetail(null)}>
        <DialogContent>
          {detail && (
            <>
              <DialogHeader>
                <DialogTitle>Detail Pengajuan</DialogTitle>
              </DialogHeader>
              <div className="flex flex-col gap-3 text-sm">
                <div>
                  <div className="font-medium">{detail.guru?.nama}</div>
                </div>
                <div className="grid grid-cols-2 gap-3">
                  <div>
                    <div className="text-xs text-muted-foreground">Jenis</div>
                    <div>{detail.jenis === "IZIN" ? "Izin" : "Sakit"}</div>
                  </div>
                  <div>
                    <div className="text-xs text-muted-foreground">Status</div>
                    <Badge variant={statusBadge(detail.status).variant}>
                      {statusBadge(detail.status).label}
                    </Badge>
                  </div>
                </div>
                <div>
                  <div className="text-xs text-muted-foreground">Periode</div>
                  <div>
                    {detail.tanggalMulai.slice(0, 10)} s/d {detail.tanggalSelesai.slice(0, 10)}
                  </div>
                </div>
                <div>
                  <div className="text-xs text-muted-foreground">Alasan</div>
                  <div className="whitespace-pre-wrap">{detail.alasan}</div>
                </div>
                <div>
                  <div className="text-xs text-muted-foreground">Lampiran</div>
                  {detail.lampiranUrl ? (
                    <a
                      href={assetUrl(detail.lampiranUrl)}
                      target="_blank"
                      rel="noreferrer"
                      className="inline-flex items-center gap-1 text-primary hover:underline"
                    >
                      <Paperclip className="size-3.5" /> Lihat lampiran
                    </a>
                  ) : (
                    <span className="text-muted-foreground">Tidak ada lampiran</span>
                  )}
                </div>
              </div>
              {detail.status === "PENDING" && (
                <DialogFooter>
                  <Button
                    variant="outline"
                    disabled={busyId === detail.id}
                    onClick={() => review(detail.id, "REJECTED")}
                  >
                    <X className="text-destructive" /> Tolak
                  </Button>
                  <Button
                    disabled={busyId === detail.id}
                    onClick={() => review(detail.id, "APPROVED")}
                  >
                    <Check /> Setuju
                  </Button>
                </DialogFooter>
              )}
            </>
          )}
        </DialogContent>
      </Dialog>
    </div>
  )
}
