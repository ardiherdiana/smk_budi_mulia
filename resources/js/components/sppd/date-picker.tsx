import * as React from 'react';
import { CalendarIcon } from 'lucide-react';

import { cn } from '@/lib/utils';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';

function toDateInputValue(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function parseDateInputValue(value: string): Date | undefined {
    if (!value) return undefined;
    const [year, month, day] = value.split('-').map(Number);
    if (!year || !month || !day) return undefined;
    return new Date(year, month - 1, day);
}

interface DatePickerProps {
    id?: string;
    value: string;
    onChange: (value: string) => void;
    className?: string;
    placeholder?: string;
    disabled?: (date: Date) => boolean;
}

export function DatePicker({ id, value, onChange, className, placeholder = 'Pilih tanggal', disabled }: DatePickerProps) {
    const [open, setOpen] = React.useState(false);
    const selected = parseDateInputValue(value);

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger
                render={
                    <Button
                        id={id}
                        type="button"
                        variant="outline"
                        className={cn('w-full justify-start font-normal', !selected && 'text-muted-foreground', className)}
                    />
                }
            >
                <CalendarIcon />
                {selected
                    ? selected.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
                    : placeholder}
            </PopoverTrigger>
            <PopoverContent className="w-auto p-0">
                <Calendar
                    mode="single"
                    selected={selected}
                    disabled={disabled}
                    onSelect={(date) => {
                        if (date) onChange(toDateInputValue(date));
                        setOpen(false);
                    }}
                />
            </PopoverContent>
        </Popover>
    );
}
