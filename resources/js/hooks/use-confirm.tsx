import * as React from "react"

import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog"
import { Checkbox } from "@/components/ui/checkbox"
import { Field, FieldLabel } from "@/components/ui/field"

export interface ConfirmOptions {
  title: string
  description?: React.ReactNode
  confirmLabel?: string
  cancelLabel?: string
  /** Tombol konfirmasi berwarna merah, untuk tindakan yang menghapus atau tidak bisa dibatalkan. */
  destructive?: boolean
  /** Kotak centang tambahan (mulai tidak tercentang). Baca hasilnya lewat `checked()` setelah `confirm` selesai. */
  checkbox?: string
}

/** Pengganti `window.confirm` dengan AlertDialog shadcn. Pakai `await confirm({...})` di
 * handler (bernilai true bila pengguna menekan konfirmasi) dan render `confirmDialog`
 * sekali di JSX komponen. Bila `checkbox` diisi, `checked()` memberi tahu apakah kotaknya dicentang. */
export function useConfirm() {
  const [open, setOpen] = React.useState(false)
  // Opsi terakhir dipertahankan setelah dialog tertutup supaya teks tidak hilang saat animasi keluar.
  const [options, setOptions] = React.useState<ConfirmOptions>({ title: "" })
  const [centang, setCentang] = React.useState(false)
  const centangRef = React.useRef(false)
  const resolveRef = React.useRef<((result: boolean) => void) | null>(null)

  const confirm = React.useCallback((next: ConfirmOptions) => {
    resolveRef.current?.(false)
    centangRef.current = false
    setCentang(false)
    setOptions(next)
    setOpen(true)
    return new Promise<boolean>((resolve) => {
      resolveRef.current = resolve
    })
  }, [])

  function settle(result: boolean) {
    resolveRef.current?.(result)
    resolveRef.current = null
    setOpen(false)
  }

  const confirmDialog = (
    <AlertDialog open={open} onOpenChange={(next) => !next && settle(false)}>
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>{options.title}</AlertDialogTitle>
          {options.description && <AlertDialogDescription>{options.description}</AlertDialogDescription>}
        </AlertDialogHeader>
        {options.checkbox && (
          <Field orientation="horizontal">
            <Checkbox
              id="confirm-centang"
              checked={centang}
              onCheckedChange={(v) => {
                centangRef.current = v === true
                setCentang(v === true)
              }}
            />
            <FieldLabel htmlFor="confirm-centang">{options.checkbox}</FieldLabel>
          </Field>
        )}
        <AlertDialogFooter>
          <AlertDialogCancel>{options.cancelLabel ?? "Batal"}</AlertDialogCancel>
          <AlertDialogAction variant={options.destructive ? "destructive" : "default"} onClick={() => settle(true)}>
            {options.confirmLabel ?? "Ya, lanjutkan"}
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  )

  return { confirm, confirmDialog, checked: () => centangRef.current }
}
