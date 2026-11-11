import { DataPagination } from '@/components/sppd/data-pagination';
import { Badge } from '@/components/ui/badge';
import { TombolAksi } from '@/components/tombol-aksi';
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
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { STATUS_LABEL, statusBadge } from '@/lib/absensi/pengajuan';
import { formatDate, formatJam } from '@/lib/utils';
import { type SppdPageProps, type PaginatedData } from '@/types/sppd';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { EyeIcon, PlusIcon } from 'lucide-react';

interface PengajuanRow {
    id: number;
    maksud: string;
    status: string;
    tanggal_berangkat: string;
    jam_berangkat: string;
    tanggal_kembali: string;
    jam_kembali: string;
    pemohon: { name: string };
    tujuan: string;
}

interface Props extends SppdPageProps {
    pengajuans: PaginatedData<PengajuanRow>;
    filters: { status: string | null };
}

const FILTER_ITEMS = [
    { value: 'all', label: 'Semua Status' },
    ...Object.entries(STATUS_LABEL).map(([value, label]) => ({ value, label })),
];

export default function Index() {
    const { pengajuans, filters, auth } = usePage<Props>().props;
    const isPemohonOnly = auth.roles.includes('pemohon') && auth.roles.length === 1;
    const columnCount = isPemohonOnly ? 5 : 6;

    function changeFilter(status: string) {
        router.get(
            route('sppd.pengajuan.index'),
            status === 'all' ? {} : { status },
            { preserveState: true, replace: true },
        );
    }

    return (
        <>
            <Head title="Pengajuan SPPD" />

            <div className="flex flex-col gap-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">Pengajuan SPPD</h1>
                        <p className="text-sm text-muted-foreground">
                            {isPemohonOnly ? 'Riwayat pengajuan perjalanan dinas Anda' : 'Seluruh pengajuan perjalanan dinas'}
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Select
                            items={FILTER_ITEMS}
                            value={filters.status ?? 'all'}
                            onValueChange={(v) => v && changeFilter(v)}
                        >
                            <SelectTrigger className="w-56">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {FILTER_ITEMS.map((item) => (
                                    <SelectItem key={item.value} value={item.value}>
                                        {item.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {(auth.user?.role === 'ADMIN' || auth.roles.includes('pemohon')) && (
                            <Button nativeButton={false} render={<Link href={route('sppd.pengajuan.create')} />}>
                                <PlusIcon /> Ajukan SPPD
                            </Button>
                        )}
                    </div>
                </div>

                <div className="overflow-x-auto rounded-lg border">
                    <Table kartu>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-10">No</TableHead>
                                {!isPemohonOnly && <TableHead>Pemohon</TableHead>}
                                <TableHead>Tujuan</TableHead>
                                <TableHead>Waktu</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="text-right">Aksi</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {pengajuans.data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={columnCount} className="text-center text-muted-foreground">
                                        Belum ada pengajuan SPPD
                                    </TableCell>
                                </TableRow>
                            ) : (
                                pengajuans.data.map((p, index) => {
                                    const badge = statusBadge(p.status);

                                    return (
                                        <TableRow key={p.id}>
                                            <TableCell kartu="sembunyi" className="text-muted-foreground">
                                                {(pengajuans.current_page - 1) * pengajuans.per_page + index + 1}
                                            </TableCell>
                                            {!isPemohonOnly && (
                                                <TableCell kartu="judul">
                                                    <div className="font-medium">{p.pemohon.name}</div>
                                                </TableCell>
                                            )}
                                            <TableCell>{p.tujuan}</TableCell>
                                            <TableCell>
                                                <div>
                                                    {formatDate(p.tanggal_berangkat)}, {formatJam(p.jam_berangkat)}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    s/d {formatDate(p.tanggal_kembali)}, {formatJam(p.jam_kembali)}
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant={badge.variant}>{badge.label}</Badge>
                                            </TableCell>
                                            <TableCell kartu="aksi" className="text-right">
                                                <TombolAksi
                                                    jenis="lihat"
                                                    nativeButton={false}
                                                    render={<Link href={route('sppd.pengajuan.show', p.id)} />}
                                                >
                                                    <EyeIcon />
                                                </TombolAksi>
                                            </TableCell>
                                        </TableRow>
                                    );
                                })
                            )}
                        </TableBody>
                    </Table>
                </div>

                <DataPagination paginator={pengajuans} />
            </div>
        </>
    );
}
