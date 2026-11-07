import { type Pegawai, PengajuanForm } from '@/components/sppd/pengajuan-form';
import { type SppdPageProps } from '@/types/sppd';
import { Head, usePage } from '@inertiajs/react';

interface Props extends SppdPageProps {
    pegawai: Pegawai[];
}

export default function Create() {
    const { pegawai } = usePage<Props>().props;

    return (
        <>
            <Head title="Ajukan SPPD" />

            <div className="flex max-w-2xl flex-col gap-6">
                <div>
                    <h1 className="text-xl font-semibold">Ajukan SPPD Baru</h1>
                    <p className="text-sm text-muted-foreground">
                        SPPD diajukan ke Kepala Sekolah; setelah disetujui, TU mengisi nomor surat dan SPPD bisa diunduh
                    </p>
                </div>

                <PengajuanForm pegawai={pegawai} action={route('sppd.pengajuan.store')} submitLabel="Ajukan SPPD" />
            </div>
        </>
    );
}
