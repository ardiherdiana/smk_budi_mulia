import { DataPagination } from '@/components/sppd/data-pagination';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableFooter,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDate } from '@/lib/utils';
import { type SppdPageProps, type PaginatedData } from '@/types/sppd';
import { Head, router, usePage } from '@inertiajs/react';
import { FileSpreadsheetIcon, FileTextIcon } from 'lucide-react';

interface LaporanItem {
    id: number;
    status: string;
    tanggal_berangkat: string;
    pemohon: { name: string };
    tujuan: string;
    pencairans: { jumlah: string }[];
}

interface Props extends SppdPageProps {
    items: PaginatedData<LaporanItem>;
    filters: { tahun: number };
}

function rupiah(v: number) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(v);
}

const totalPencairan = (item: LaporanItem) => item.pencairans.reduce((sum, p) => sum + Number(p.jumlah), 0);

export default function Index() {
    const { items, filters } = usePage<Props>().props;

    const tahunOptions = Array.from({ length: 5 }, (_, i) => new Date().getFullYear() - i);
    const tahunItems = tahunOptions.map((y) => ({ value: y.toString(), label: y.toString() }));

    const exportQuery = `?tahun=${filters.tahun}`;

    const applyFilter = (tahun: number) => {
        router.get(route('sppd.laporan.index'), { tahun }, { preserveState: true, replace: true });
    };

    const totalHalaman = items.data.reduce((sum, item) => sum + totalPencairan(item), 0);

    return (
        <>
            <Head title="Laporan Keuangan" />

            <div className="flex flex-col gap-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">Laporan Keuangan</h1>
                        <p className="text-sm text-muted-foreground">Rekap pencairan dana perjalanan dinas per periode</p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Select
                            items={tahunItems}
                            value={filters.tahun.toString()}
                            onValueChange={(v) => v && applyFilter(Number(v))}
                        >
                            <SelectTrigger className="w-28">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {tahunItems.map((item) => (
                                    <SelectItem key={item.value} value={item.value}>
                                        {item.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Button variant="outline" nativeButton={false} render={<a href={`${route('sppd.laporan.export-excel')}${exportQuery}`} />}>
                            <FileSpreadsheetIcon /> Excel
                        </Button>
                        <Button variant="outline" nativeButton={false} render={<a href={`${route('sppd.laporan.export-pdf')}${exportQuery}`} />}>
                            <FileTextIcon /> PDF
                        </Button>
                    </div>
                </div>

                <div className="overflow-x-auto rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-10">No</TableHead>
                                <TableHead>Pemohon</TableHead>
                                <TableHead>Tujuan</TableHead>
                                <TableHead>Tanggal</TableHead>
                                <TableHead className="text-right">Total Pencairan</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {items.data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={5} className="text-center text-muted-foreground">
                                        Tidak ada data pada periode ini
                                    </TableCell>
                                </TableRow>
                            ) : (
                                items.data.map((item, index) => (
                                    <TableRow key={item.id}>
                                        <TableCell className="text-muted-foreground">
                                            {(items.current_page - 1) * items.per_page + index + 1}
                                        </TableCell>
                                        <TableCell className="font-medium">{item.pemohon.name}</TableCell>
                                        <TableCell>{item.tujuan}</TableCell>
                                        <TableCell>{formatDate(item.tanggal_berangkat)}</TableCell>
                                        <TableCell className="text-right">{rupiah(totalPencairan(item))}</TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                        {items.data.length > 0 && (
                            <TableFooter>
                                <TableRow>
                                    <TableCell colSpan={4} className="font-medium">
                                        Total halaman ini
                                    </TableCell>
                                    <TableCell className="text-right font-medium">{rupiah(totalHalaman)}</TableCell>
                                </TableRow>
                            </TableFooter>
                        )}
                    </Table>
                </div>

                <DataPagination paginator={items} />
            </div>
        </>
    );
}
