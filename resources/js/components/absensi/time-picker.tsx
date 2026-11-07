import * as React from "react"
import { ClockIcon } from "lucide-react"

import { cn } from "@/lib/utils"
import { Button } from "@/components/ui/button"
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"

const HOURS = Array.from({ length: 24 }, (_, i) => String(i).padStart(2, "0"))
const MINUTES = Array.from({ length: 60 }, (_, i) => String(i).padStart(2, "0"))

interface TimePickerProps {
  id?: string
  value: string
  onChange: (value: string) => void
  className?: string
}

export function TimePicker({ id, value, onChange, className }: TimePickerProps) {
  const [open, setOpen] = React.useState(false)
  const [hour, minute] = value ? value.split(":") : ["00", "00"]

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger
        render={
          <Button
            id={id}
            type="button"
            variant="outline"
            className={cn("w-32 justify-start font-normal", className)}
          />
        }
      >
        <ClockIcon />
        {value || "Pilih jam"}
      </PopoverTrigger>
      <PopoverContent className="w-auto p-2">
        <div className="flex items-center gap-1.5">
          <Select value={hour} onValueChange={(h) => h && onChange(`${h}:${minute}`)}>
            <SelectTrigger className="w-[4.5rem]">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {HOURS.map((h) => (
                <SelectItem key={h} value={h}>
                  {h}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          <span className="text-muted-foreground">:</span>
          <Select value={minute} onValueChange={(m) => m && onChange(`${hour}:${m}`)}>
            <SelectTrigger className="w-[4.5rem]">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {MINUTES.map((m) => (
                <SelectItem key={m} value={m}>
                  {m}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
      </PopoverContent>
    </Popover>
  )
}
