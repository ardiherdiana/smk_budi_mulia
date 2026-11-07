import { DataPagination } from '@/components/sppd/data-pagination';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { type SppdPageProps, type PaginatedData } from '@/types/sppd';
import { Head, Link, usePage } from '@inertiajs/react';

interface LogItem {
    id: number;
    entitas_terkait: string;
    entitas_id: number;
    aksi: string;
    created_at: string;
    user: { name: string } | null;
}

interface Props extends SppdPageProps {
    items: PaginatedData<LogItem>;
}

function entityLabel(fqcn: string) {
    return fqcn.split('\\').pop() ?? fqcn;
}

export default function Index() {
    const { items } = usePage<Props>().props;

    return (
        <>
            <Head title="Log Audit" />

            <div className="flex flex-col gap-4">
                <div>
                    <h1 className="text-xl font-semibold">Log Audit</h1>
                    <p className="text-sm text-muted-foreground">Jejak seluruh aksi dan perpindahan status di sistem</p>
                </div>

                <div className="overflow-x-auto rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-10">No</TableHead>
                                <TableHead>Waktu</TableHead>
                                <TableHead>Pengguna</TableHead>
                                <TableHead>Entitas</TableHead>
                                <TableHead>Aksi</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {items.data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={5} className="text-center text-muted-foreground">
                                        Belum ada aktivitas tercatat
                                    </TableCell>
                                </TableRow>
                            ) : (
                                items.data.map((log, index) => (
                                    <TableRow key={log.id}>
                                        <TableCell className="text-muted-foreground">
                                            {(items.current_page - 1) * items.per_page + index + 1}
                                        </TableCell>
                                        <TableCell className="whitespace-nowrap">{new Date(log.created_at).toLocaleString('id-ID')}</TableCell>
                                        <TableCell>{log.user?.name ?? 'Sistem'}</TableCell>
                                        <TableCell>
                                            {entityLabel(log.entitas_terkait) === 'PengajuanSppd' ? (
                                                <Link href={route('sppd.pengajuan.show', log.entitas_id)} className="hover:underline">
                                                    Pengajuan #{log.entitas_id}
                                                </Link>
                                            ) : (
                                                `${entityLabel(log.entitas_terkait)} #${log.entitas_id}`
                                            )}
                                        </TableCell>
                                        <TableCell>{log.aksi}</TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </div>

                <DataPagination paginator={items} />
            </div>
        </>
    );
}
