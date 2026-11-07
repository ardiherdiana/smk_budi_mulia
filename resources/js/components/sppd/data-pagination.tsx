import { router, usePage } from '@inertiajs/react';

import {
    Pagination,
    PaginationContent,
    PaginationItem,
    PaginationNext,
    PaginationPrevious,
} from '@/components/ui/pagination';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { type PaginatedData } from '@/types/sppd';

const PAGE_SIZES = [50, 100, 200];

export function DataPagination({ paginator }: { paginator: PaginatedData<unknown> }) {
    const { url } = usePage();

    const page = paginator.current_page;
    const pageSize = paginator.per_page;
    const total = paginator.total;
    const totalPages = Math.max(1, paginator.last_page);
    const from = total === 0 ? 0 : (page - 1) * pageSize + 1;
    const to = Math.min(page * pageSize, total);

    function visit(params: Record<string, number>) {
        const target = new URL(url, window.location.origin);
        Object.entries(params).forEach(([key, value]) => target.searchParams.set(key, String(value)));

        router.get(target.pathname + target.search, {}, { preserveState: true, preserveScroll: true });
    }

    return (
        <div className="flex flex-wrap items-center justify-between gap-3">
            <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                <span>Baris per halaman</span>
                <Select
                    items={Object.fromEntries(PAGE_SIZES.map((n) => [String(n), String(n)]))}
                    value={String(pageSize)}
                    onValueChange={(v) => v && visit({ per_page: Number(v), page: 1 })}
                >
                    <SelectTrigger size="sm" className="w-20">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {PAGE_SIZES.map((n) => (
                            <SelectItem key={n} value={String(n)}>
                                {n}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <span>{total === 0 ? 'Tidak ada data' : `Menampilkan ${from}-${to} dari ${total} data`}</span>
            </div>

            <Pagination className="mx-0 w-auto justify-end">
                <PaginationContent>
                    <PaginationItem>
                        <PaginationPrevious disabled={page <= 1} onClick={() => visit({ page: page - 1 })} />
                    </PaginationItem>
                    <PaginationItem>
                        <span className="px-2 text-sm text-muted-foreground">
                            Halaman {page} dari {totalPages}
                        </span>
                    </PaginationItem>
                    <PaginationItem>
                        <PaginationNext disabled={page >= totalPages} onClick={() => visit({ page: page + 1 })} />
                    </PaginationItem>
                </PaginationContent>
            </Pagination>
        </div>
    );
}
