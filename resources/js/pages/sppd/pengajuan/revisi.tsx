import { type NilaiPengajuan, type Pegawai, PengajuanForm } from '@/components/sppd/pengajuan-form';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { type SppdPageProps } from '@/types/sppd';
import { Head, usePage } from '@inertiajs/react';

interface Props extends SppdPageProps {
    pegawai: Pegawai[];
    pengajuan: NilaiPengajuan & { id: number; catatan_kepsek: string | null };
}

export default function Revisi() {
    const { pegawai, pengajuan } = usePage<Props>().props;

    return (
        <>
            <Head title={`Revisi Pengajuan #${pengajuan.id}`} />

            <div className="flex max-w-2xl flex-col gap-6">
                <div>
                    <h1 className="text-xl font-semibold">Revisi Pengajuan SPPD #{pengajuan.id}</h1>
                    <p className="text-sm text-muted-foreground">
                        Perbaiki data sesuai catatan Kepala Sekolah lalu ajukan ulang; tidak perlu membuat pengajuan baru
                    </p>
                </div>

                {pengajuan.catatan_kepsek && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Catatan Kepala Sekolah</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-sm whitespace-pre-wrap">{pengajuan.catatan_kepsek}</p>
                        </CardContent>
                    </Card>
                )}

                <PengajuanForm
                    pegawai={pegawai}
                    action={route('sppd.pengajuan.revisi', pengajuan.id)}
                    submitLabel="Ajukan Ulang"
                    nilai={pengajuan}
                />
            </div>
        </>
    );
}
