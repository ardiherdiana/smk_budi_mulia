import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardAction, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { statusBadge } from '@/lib/absensi/pengajuan';
import { formatDate } from '@/lib/utils';
import { type SppdPageProps } from '@/types/sppd';
import { Head, Link, usePage } from '@inertiajs/react';
import {
    CircleCheckIcon,
    ClipboardListIcon,
    ClockIcon,
    HourglassIcon,
    MapPinIcon,
} from 'lucide-react';

interface TerbaruItem {
    id: number;
    pemohon: string;
    tujuan: string;
    status: string;
    status_label: string;
    tanggal_berangkat: string;
}

interface Props extends SppdPageProps {
    stats: {
        total: number;
        menunggu_persetujuan: number;
        menunggu_nomor: number;
        sedang_ditugaskan: number;
        selesai: number;
    };
    terbaru: TerbaruItem[];
}

export default function Dashboard() {
    const { stats, terbaru } = usePage<Props>().props;

    const cards = [
        { label: 'Total Pengajuan', value: stats.total, icon: ClipboardListIcon },
        { label: 'Menunggu Persetujuan', value: stats.menunggu_persetujuan, icon: ClockIcon },
        { label: 'Menunggu Nomor', value: stats.menunggu_nomor, icon: HourglassIcon },
        { label: 'Sedang Ditugaskan', value: stats.sedang_ditugaskan, icon: MapPinIcon },
        { label: 'Selesai', value: stats.selesai, icon: CircleCheckIcon },
    ];

    return (
        <>
            <Head title="Dashboard SPPD" />

            <div className="flex flex-col gap-6">
                <div>
                    <h1 className="text-xl font-semibold">Dashboard SPPD</h1>
                    <p className="text-sm text-muted-foreground">Ringkasan pengajuan SPPD</p>
                </div>

                <div className="grid grid-cols-2 gap-4 lg:grid-cols-5">
                    {cards.map((card) => (
                        <Card key={card.label}>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-sm font-medium text-muted-foreground">{card.label}</CardTitle>
                                <CardAction>
                                    <card.icon className="size-4 text-muted-foreground" />
                                </CardAction>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-semibold">{card.value}</div>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Pengajuan Terbaru</CardTitle>
                        <CardAction>
                            <Button variant="outline" size="sm" nativeButton={false} render={<Link href={route('sppd.pengajuan.index')} />}>
                                Lihat Selengkapnya
                            </Button>
                        </CardAction>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto rounded-lg border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-10">No</TableHead>
                                        <TableHead>Pemohon</TableHead>
                                        <TableHead>Tujuan</TableHead>
                                        <TableHead>Berangkat</TableHead>
                                        <TableHead>Status</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {terbaru.length === 0 ? (
                                        <TableRow>
                                            <TableCell colSpan={5} className="text-center text-muted-foreground">
                                                Belum ada pengajuan
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        terbaru.map((p, index) => {
                                            const badge = statusBadge(p.status);

                                            return (
                                                <TableRow key={p.id}>
                                                    <TableCell className="text-muted-foreground">{index + 1}</TableCell>
                                                    <TableCell>
                                                        <Link href={route('sppd.pengajuan.show', p.id)} className="font-medium hover:underline">
                                                            {p.pemohon}
                                                        </Link>
                                                    </TableCell>
                                                    <TableCell>{p.tujuan}</TableCell>
                                                    <TableCell>{formatDate(p.tanggal_berangkat)}</TableCell>
                                                    <TableCell>
                                                        <Badge variant={badge.variant}>{badge.label}</Badge>
                                                    </TableCell>
                                                </TableRow>
                                            );
                                        })
                                    )}
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
