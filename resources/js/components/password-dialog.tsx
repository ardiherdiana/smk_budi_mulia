import * as React from "react"
import { useForm } from "@inertiajs/react"

import { Button } from "@/components/ui/button"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import { Field, FieldGroup, FieldLabel } from "@/components/ui/field"
import { Input } from "@/components/ui/input"

export function PasswordDialog({ open, onOpenChange }: { open: boolean; onOpenChange: (open: boolean) => void }) {
  const { data, setData, patch, processing, errors, reset, clearErrors } = useForm({
    current_password: "",
    password: "",
    password_confirmation: "",
  })

  function handleOpenChange(next: boolean) {
    if (!next) {
      reset()
      clearErrors()
    }
    onOpenChange(next)
  }

  function handleSubmit(event: React.FormEvent) {
    event.preventDefault()
    patch("/akun/password", {
      preserveScroll: true,
      onSuccess: () => handleOpenChange(false),
    })
  }

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogContent>
        <form onSubmit={handleSubmit} className="grid gap-4">
          <DialogHeader>
            <DialogTitle>Ganti password</DialogTitle>
            <DialogDescription>
              Gunakan minimal 8 karakter dengan kombinasi huruf dan angka.
            </DialogDescription>
          </DialogHeader>
          <FieldGroup>
            <Field>
              <FieldLabel htmlFor="current_password">Password saat ini</FieldLabel>
              <Input
                id="current_password"
                type="password"
                autoComplete="current-password"
                value={data.current_password}
                onChange={(e) => setData("current_password", e.target.value)}
                required
              />
              {errors.current_password && <p className="text-sm text-destructive">{errors.current_password}</p>}
            </Field>
            <Field>
              <FieldLabel htmlFor="new_password">Password baru</FieldLabel>
              <Input
                id="new_password"
                type="password"
                autoComplete="new-password"
                value={data.password}
                onChange={(e) => setData("password", e.target.value)}
                required
              />
              {errors.password && <p className="text-sm text-destructive">{errors.password}</p>}
            </Field>
            <Field>
              <FieldLabel htmlFor="password_confirmation">Ulangi password baru</FieldLabel>
              <Input
                id="password_confirmation"
                type="password"
                autoComplete="new-password"
                value={data.password_confirmation}
                onChange={(e) => setData("password_confirmation", e.target.value)}
                required
              />
            </Field>
          </FieldGroup>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => handleOpenChange(false)}>
              Batal
            </Button>
            <Button type="submit" disabled={processing}>
              {processing ? "Menyimpan..." : "Simpan"}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
