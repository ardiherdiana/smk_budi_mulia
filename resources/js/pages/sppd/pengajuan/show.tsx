import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { WhatsAppIcon } from '@/components/absensi/whatsapp-icon';
import { useConfirm } from '@/hooks/use-confirm';
import { statusBadge } from '@/lib/absensi/pengajuan';
import { formatDate, formatJam } from '@/lib/utils';
import { whatsappUrl } from '@/lib/absensi/whatsapp';
import { type SppdPageProps } from '@/types/sppd';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { DownloadIcon } from 'lucide-react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';

interface UserRef {
    id: number;
    name: string;
}

interface Pencairan {
    id: number;
    jenis: string;
    jumlah: string;
    tanggal: string;
    keterangan: string | null;
    pencair: UserRef;
}

interface Pengajuan {
    id: number;
    tujuan: string;
    maksud: string;
    status: string;
    tanggal_berangkat: string;
    jam_berangkat: string;
    tanggal_kembali: string;
    jam_kembali: string;
    undangan_path: string | null;
    catatan_kepsek: string | null;
    alat_angkutan: string | null;
    keterangan: string | null;
    pengikuts: { id: number; name: string; jabatan: string | null }[];
    pemohon: UserRef;
    penyetuju: UserRef | null;
    sppd: { nomor_sppd: string; akun_anggaran: string | null } | null;
    pencairans: Pencairan[];
}

interface LaporanFoto {
    id: number;
    path: string;
    nama_asli: string | null;
}

interface Laporan {
    ringkasan: string;
    penulis: string | null;
    diperbarui_at: string;
    fotos: LaporanFoto[];
}

interface Riwayat {
    id: number;
    aksi: string;
    created_at: string;
    user: UserRef | null;
}

interface Can {
    approve: boolean;
    revisi: boolean;
    isiNomor: boolean;
    isiAkunAnggaran: boolean;
    isiLaporan: boolean;
    cairkanLumsum: boolean;
    selesaikan: boolean;
}

/** Nomor WhatsApp (format internasional) yang dikirim server hanya ke pemohon pengajuan ini. */
interface Kontak {
    kepala_sekolah: string;
    tu: string;
}

interface Props extends SppdPageProps {
    pengajuan: Pengajuan;
    diarsipkan: boolean;
    riwayat: Riwayat[];
    laporan: Laporan | null;
    kontak: Kontak | null;
    can: Can;
}

function rupiah(v: string | number) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(v));
}

function DetailField({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div>
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="text-sm">{children}</div>
        </div>
    );
}

function ApprovalPanel({ pengajuan }: { pengajuan: Pengajuan }) {
    const { data, setData, post, processing, reset } = useForm({ catatan: '' });

    const approve = () => {
        post(route('sppd.pengajuan.approve', pengajuan.id), { onSuccess: () => reset() });
    };

    const reject = () => {
        if (!data.catatan) {
            toast.error('Catatan wajib diisi untuk menolak pengajuan.');
            return;
        }
        post(route('sppd.pengajuan.reject', pengajuan.id), { onSuccess: () => reset() });
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">Persetujuan Kepala Sekolah</CardTitle>
            </CardHeader>
            <CardContent>
                <FieldGroup>
                    <Field>
                        <FieldLabel htmlFor="catatan">Catatan (wajib jika menolak)</FieldLabel>
                        <Textarea id="catatan" rows={2} value={data.catatan} onChange={(e) => setData('catatan', e.target.value)} />
                    </Field>
                    <div className="flex gap-2">
                        <Button type="button" disabled={processing} onClick={approve}>Setujui</Button>
                        <Button type="button" variant="destructive" disabled={processing} onClick={reject}>Tolak</Button>
                    </div>
                </FieldGroup>
            </CardContent>
        </Card>
    );
}

function HubungiButton({ nomor, pesan, children }: { nomor: string; pesan: string; children: React.ReactNode }) {
    return (
        <Button variant="whatsapp" nativeButton={false} render={<a href={whatsappUrl(nomor, pesan)} target="_blank" rel="noreferrer" />}>
            <WhatsAppIcon /> {children}
        </Button>
    );
}

/**
 * Posisi pengajuan bagi pemohon sebelum SPPD terbit: menunggu Kepala Sekolah, ditolak (revisi), atau menunggu nomor
 * dari TU. Setiap tahap punya tombol WhatsApp ke orang yang sedang ditunggu.
 */
function TindakLanjutPanel({ pengajuan, kontak, bisaRevisi }: { pengajuan: Pengajuan; kontak: Kontak; bisaRevisi: boolean }) {
    const salam = `Permisi, saya ${pengajuan.pemohon.name}. `;
    const rujukan = `pengajuan SPPD #${pengajuan.id} ke ${pengajuan.tujuan}`;

    const hubungiKepsek = (pesan: string) => (
        <HubungiButton nomor={kontak.kepala_sekolah} pesan={salam + pesan}>
            Hubungi Kepsek
        </HubungiButton>
    );

    if (pengajuan.status === 'diajukan_ke_kepsek') {
        return (
            <Card>
                <CardHeader>
                    <CardTitle className="text-base">Menunggu Persetujuan Kepala Sekolah</CardTitle>
                </CardHeader>
                <CardContent className="flex flex-col gap-3">
                    <p className="text-sm text-muted-foreground">
                        Pengajuan Anda sudah masuk dan menunggu keputusan Kepala Sekolah. Bila mendesak, hubungi beliau lewat WhatsApp.
                    </p>
                    <div className="flex flex-wrap gap-2">{hubungiKepsek(`Mohon persetujuan untuk ${rujukan}. Terima kasih.`)}</div>
                </CardContent>
            </Card>
        );
    }

    if (pengajuan.status === 'ditolak') {
        return (
            <Card>
                <CardHeader>
                    <CardTitle className="text-base">Pengajuan Ditolak</CardTitle>
                </CardHeader>
                <CardContent className="flex flex-col gap-3">
                    <p className="text-sm text-muted-foreground">
                        Kepala Sekolah menolak pengajuan ini; alasannya ada pada catatan di bawah. Perbaiki lalu ajukan ulang, Anda tidak perlu
                        membuat pengajuan baru.
                    </p>
                    <div className="flex flex-wrap gap-2">
                        {bisaRevisi && (
                            <Button nativeButton={false} render={<Link href={route('sppd.pengajuan.edit', pengajuan.id)} />}>
                                Revisi & Ajukan Ulang
                            </Button>
                        )}
                        {hubungiKepsek(`${rujukan} ditolak. Saya ingin berkonsultasi sebelum merevisinya. Terima kasih.`)}
                    </div>
                </CardContent>
            </Card>
        );
    }

    if (pengajuan.status === 'menunggu_nomor') {
        return (
            <Card>
                <CardHeader>
                    <CardTitle className="text-base">Disetujui, Menunggu Nomor Surat</CardTitle>
                </CardHeader>
                <CardContent className="flex flex-col gap-3">
                    <p className="text-sm text-muted-foreground">
                        Kepala Sekolah sudah menyetujui. TU akan mengisi nomor surat; setelah itu SPPD bisa diunduh. Untuk meminta konfirmasi,
                        hubungi TU lewat WhatsApp.
                    </p>
                    <div className="flex flex-wrap gap-2">
                        <HubungiButton nomor={kontak.tu} pesan={`${salam}${rujukan} sudah disetujui Kepala Sekolah. Mohon konfirmasi nomor suratnya. Terima kasih.`}>
                            Hubungi TU
                        </HubungiButton>
                    </div>
                </CardContent>
            </Card>
        );
    }

    return null;
}

function LumsumPanel({ pengajuan }: { pengajuan: Pengajuan }) {
    const [jumlah, setJumlah] = useState('');
    const [keterangan, setKeterangan] = useState('');
    const [processing, setProcessing] = useState(false);

    const submit = () => {
        setProcessing(true);
        router.post(route('sppd.pengajuan.cairkan-lumsum', pengajuan.id), { jumlah, keterangan }, {
            preserveScroll: true,
            onSuccess: () => {
                setJumlah('');
                setKeterangan('');
            },
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">Cairkan Lumsum</CardTitle>
            </CardHeader>
            <CardContent>
                <FieldGroup>
                    <Field>
                        <FieldLabel htmlFor="jumlah">Jumlah (Rp)</FieldLabel>
                        <Input id="jumlah" type="number" value={jumlah} onChange={(e) => setJumlah(e.target.value)} />
                        <FieldDescription>
                            Dicetak pada baris 11 (Lumsum Biaya) SPPD. Bila dicairkan lebih dari sekali, yang dicetak jumlah totalnya; unduhan berikutnya langsung memuatnya.
                        </FieldDescription>
                    </Field>
                    <Field>
                        <FieldLabel htmlFor="keterangan-pencairan">Keterangan (opsional)</FieldLabel>
                        <Input id="keterangan-pencairan" value={keterangan} onChange={(e) => setKeterangan(e.target.value)} />
                    </Field>
                    <Field>
                        <Button type="button" disabled={processing || !jumlah} onClick={submit} className="w-fit">
                            {processing ? 'Memproses...' : 'Cairkan Lumsum'}
                        </Button>
                    </Field>
                </FieldGroup>
            </CardContent>
        </Card>
    );
}

function NomorSppdPanel({ pengajuan }: { pengajuan: Pengajuan }) {
    const { data, setData, patch, processing, errors } = useForm({ nomor_sppd: pengajuan.sppd?.nomor_sppd ?? '' });
    const sudahBernomor = pengajuan.sppd !== null;

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">Nomor SPPD</CardTitle>
            </CardHeader>
            <CardContent>
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        patch(route('sppd.pengajuan.nomor', pengajuan.id), { preserveScroll: true });
                    }}
                >
                    <FieldGroup>
                        <Field>
                            <FieldLabel htmlFor="nomor_sppd">Nomor Surat</FieldLabel>
                            <Input
                                id="nomor_sppd"
                                placeholder="mis. 009/SMK-BM/X/2026"
                                maxLength={50}
                                value={data.nomor_sppd}
                                onChange={(e) => setData('nomor_sppd', e.target.value)}
                            />
                            <FieldDescription>
                                {sudahBernomor
                                    ? 'Nomor bisa diperbaiki sampai perjalanan ditandai selesai.'
                                    : 'Ketik nomor surat sesuai buku agenda. Setelah disimpan, SPPD bisa diunduh pemohon atau dicetak TU.'}
                            </FieldDescription>
                            {errors.nomor_sppd && <FieldError errors={[{ message: errors.nomor_sppd }]} />}
                        </Field>
                        <Button type="submit" className="w-fit" disabled={processing || !data.nomor_sppd.trim()}>
                            {processing ? 'Menyimpan...' : sudahBernomor ? 'Perbarui Nomor' : 'Simpan Nomor'}
                        </Button>
                    </FieldGroup>
                </form>
            </CardContent>
        </Card>
    );
}

function AkunAnggaranPanel({ pengajuan }: { pengajuan: Pengajuan }) {
    const { data, setData, patch, processing, errors } = useForm({ akun_anggaran: pengajuan.sppd?.akun_anggaran ?? '' });

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">Akun Anggaran</CardTitle>
            </CardHeader>
            <CardContent>
                <FieldGroup>
                    <Field>
                        <FieldLabel htmlFor="akun_anggaran">Akun Pembebanan Anggaran (opsional)</FieldLabel>
                        <Input
                            id="akun_anggaran"
                            placeholder="mis. 5.2.02.01 Belanja Perjalanan Dinas"
                            maxLength={100}
                            value={data.akun_anggaran}
                            onChange={(e) => setData('akun_anggaran', e.target.value)}
                        />
                        <FieldDescription>
                            Dicetak pada baris 10 (Pembebanan Anggaran) SPPD. Instansi terisi otomatis. Kosongkan bila akan ditulis tangan; unduhan berikutnya langsung memuat perubahan ini.
                        </FieldDescription>
                        {errors.akun_anggaran && <FieldError errors={[{ message: errors.akun_anggaran }]} />}
                    </Field>
                    <Button type="button" className="w-fit" disabled={processing} onClick={() => patch(route('sppd.pengajuan.akun-anggaran', pengajuan.id), { preserveScroll: true })}>
                        {processing ? 'Menyimpan...' : 'Simpan Akun Anggaran'}
                    </Button>
                </FieldGroup>
            </CardContent>
        </Card>
    );
}

function FotoGrid({ fotos }: { fotos: LaporanFoto[] }) {
    return (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
            {fotos.map((foto) => (
                <a key={foto.id} href={`/uploads/${foto.path}`} target="_blank" rel="noreferrer" className="block overflow-hidden rounded-md border">
                    <img src={`/uploads/${foto.path}`} alt={foto.nama_asli ?? 'Foto dokumentasi'} className="aspect-video w-full object-cover" loading="lazy" />
                </a>
            ))}
        </div>
    );
}

function LaporanPanel({ pengajuan, laporan }: { pengajuan: Pengajuan; laporan: Laporan | null }) {
    const { data, setData, post, processing, errors } = useForm({
        ringkasan: laporan?.ringkasan ?? '',
        fotos: [] as File[],
        hapus_foto_ids: [] as number[],
    });

    const fotoLama = laporan?.fotos ?? [];
    const sisaLama = fotoLama.filter((f) => !data.hapus_foto_ids.includes(f.id));
    const previews = useMemo(() => data.fotos.map((file) => URL.createObjectURL(file)), [data.fotos]);
    const totalFoto = sisaLama.length + data.fotos.length;

    const fotoErrors = Object.entries(errors)
        .filter(([key]) => key === 'fotos' || key.startsWith('fotos.'))
        .map(([, message]) => ({ message }));

    const tambahFoto = (files: FileList | null) => {
        if (!files) return;
        setData('fotos', [...data.fotos, ...Array.from(files)]);
    };

    const toggleHapus = (id: number) => {
        setData('hapus_foto_ids', data.hapus_foto_ids.includes(id) ? data.hapus_foto_ids.filter((x) => x !== id) : [...data.hapus_foto_ids, id]);
    };

    const submit: React.FormEventHandler = (e) => {
        e.preventDefault();
        post(route('sppd.pengajuan.laporan', pengajuan.id), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                setData('fotos', []);
                setData('hapus_foto_ids', []);
            },
        });
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">Laporan Perjalanan Dinas</CardTitle>
            </CardHeader>
            <CardContent>
                <form onSubmit={submit}>
                    <FieldGroup>
                        <p className="text-sm text-muted-foreground">
                            Tuliskan rangkuman/intisari yang Anda dapat selama perjalanan dinas, lalu lampirkan foto-foto dokumentasi. Laporan ini hanya tampil di
                            sistem dan tidak dicetak pada SPPD.
                        </p>
                        <Field>
                            <FieldLabel htmlFor="ringkasan">Rangkuman / Intisari</FieldLabel>
                            <Textarea
                                id="ringkasan"
                                rows={8}
                                maxLength={5000}
                                value={data.ringkasan}
                                onChange={(e) => setData('ringkasan', e.target.value)}
                                placeholder="Apa yang dibahas, hasil, dan tindak lanjut untuk sekolah"
                            />
                            <FieldDescription>{data.ringkasan.length}/5000 karakter</FieldDescription>
                            <FieldError errors={errors.ringkasan ? [{ message: errors.ringkasan }] : undefined} />
                        </Field>

                        <Field>
                            <FieldLabel htmlFor="fotos">Foto Dokumentasi</FieldLabel>

                            {fotoLama.length > 0 && (
                                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                                    {fotoLama.map((foto) => {
                                        const dihapus = data.hapus_foto_ids.includes(foto.id);
                                        return (
                                            <div key={foto.id} className="flex flex-col gap-1">
                                                <img
                                                    src={`/uploads/${foto.path}`}
                                                    alt={foto.nama_asli ?? 'Foto dokumentasi'}
                                                    className={`aspect-video w-full rounded-md border object-cover ${dihapus ? 'opacity-40' : ''}`}
                                                />
                                                <Button type="button" size="sm" variant={dihapus ? 'secondary' : 'outline'} onClick={() => toggleHapus(foto.id)}>
                                                    {dihapus ? 'Batal hapus' : 'Hapus'}
                                                </Button>
                                            </div>
                                        );
                                    })}
                                </div>
                            )}

                            {data.fotos.length > 0 && (
                                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                                    {data.fotos.map((file, index) => (
                                        <div key={`${file.name}-${index}`} className="flex flex-col gap-1">
                                            <img src={previews[index]} alt={file.name} className="aspect-video w-full rounded-md border object-cover" />
                                            <Button type="button" size="sm" variant="outline" onClick={() => setData('fotos', data.fotos.filter((_, i) => i !== index))}>
                                                Buang
                                            </Button>
                                        </div>
                                    ))}
                                </div>
                            )}

                            <Input
                                id="fotos"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                multiple
                                value=""
                                onChange={(e) => tambahFoto(e.target.files)}
                            />
                            <FieldDescription>
                                Wajib minimal 1 foto, maksimal 10 foto (JPG, PNG, atau WebP, tiap foto maks. 5 MB). Terpilih: {totalFoto} foto.
                            </FieldDescription>
                            <FieldError errors={fotoErrors.length ? fotoErrors : undefined} />
                        </Field>

                        <Button type="submit" className="w-fit" disabled={processing}>
                            {processing ? 'Menyimpan...' : laporan ? 'Perbarui Laporan' : 'Simpan Laporan'}
                        </Button>
                    </FieldGroup>
                </form>
            </CardContent>
        </Card>
    );
}

function LaporanCard({ laporan }: { laporan: Laporan }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">Laporan Perjalanan Dinas</CardTitle>
            </CardHeader>
            <CardContent className="flex flex-col gap-3">
                <DetailField label="Rangkuman / Intisari">
                    <span className="whitespace-pre-wrap">{laporan.ringkasan}</span>
                </DetailField>
                {laporan.fotos.length > 0 && (
                    <DetailField label={`Foto Dokumentasi (${laporan.fotos.length})`}>
                        <FotoGrid fotos={laporan.fotos} />
                    </DetailField>
                )}
                <p className="text-xs text-muted-foreground">
                    Diisi oleh {laporan.penulis ?? '-'} &middot; diperbarui {new Date(laporan.diperbarui_at).toLocaleString('id-ID')}
                </p>
            </CardContent>
        </Card>
    );
}

export default function Show() {
    const { pengajuan, diarsipkan, riwayat, can, laporan, kontak } = usePage<Props>().props;
    const { confirm, confirmDialog } = useConfirm();
    const badge = statusBadge(pengajuan.status);

    return (
        <>
            <Head title={`Pengajuan #${pengajuan.id}`} />
            {confirmDialog}

            <div className="flex flex-col gap-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">Pengajuan SPPD #{pengajuan.id}</h1>
                        <p className="text-sm text-muted-foreground">Diajukan oleh {pengajuan.pemohon.name}</p>
                    </div>
                    <Badge variant={badge.variant}>{badge.label}</Badge>
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <div className="flex flex-col gap-4 lg:col-span-2">
                        {kontak && <TindakLanjutPanel pengajuan={pengajuan} kontak={kontak} bisaRevisi={can.revisi} />}

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Detail Pengajuan</CardTitle>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-3">
                                <div className="grid gap-3 sm:grid-cols-2">
                                    <DetailField label="Pemohon">
                                        {pengajuan.pemohon.name}
                                    </DetailField>
                                    <DetailField label="Tujuan">{pengajuan.tujuan}</DetailField>
                                    <DetailField label="Berangkat">
                                        {formatDate(pengajuan.tanggal_berangkat)}, {formatJam(pengajuan.jam_berangkat)}
                                    </DetailField>
                                    <DetailField label="Kembali">
                                        {formatDate(pengajuan.tanggal_kembali)}, {formatJam(pengajuan.jam_kembali)}
                                    </DetailField>
                                </div>
                                <DetailField label="Maksud">
                                    <span className="whitespace-pre-wrap">{pengajuan.maksud}</span>
                                </DetailField>
                                <div className="grid gap-3 sm:grid-cols-2">
                                    <DetailField label="Alat Angkutan">{pengajuan.alat_angkutan ?? '-'}</DetailField>
                                    <DetailField label="Keterangan Lain-lain">{pengajuan.keterangan ?? '-'}</DetailField>
                                </div>
                                <DetailField label="Pengikut">
                                    {pengajuan.pengikuts.length === 0
                                        ? '-'
                                        : pengajuan.pengikuts.map((p) => (p.jabatan ? `${p.name} (${p.jabatan})` : p.name)).join(', ')}
                                </DetailField>
                                {pengajuan.undangan_path && (
                                    <DetailField label="Surat Undangan">
                                        <a href={`/uploads/${pengajuan.undangan_path}`} target="_blank" rel="noreferrer" className="text-primary hover:underline">
                                            Lihat undangan
                                        </a>
                                    </DetailField>
                                )}
                                {pengajuan.catatan_kepsek && <DetailField label="Catatan Kepala Sekolah">{pengajuan.catatan_kepsek}</DetailField>}
                                {pengajuan.penyetuju && <DetailField label="Disetujui oleh">{pengajuan.penyetuju.name}</DetailField>}

                                {pengajuan.sppd && (
                                    <div className="flex flex-wrap gap-2 border-t pt-4">
                                        <Button variant="outline" nativeButton={false} render={<a href={route('sppd.pengajuan.sppd-pdf', pengajuan.id)} />}>
                                            <DownloadIcon /> {diarsipkan ? 'Download Arsip SPPD (bertanda tangan)' : 'Download SPPD'} ({pengajuan.sppd.nomor_sppd})
                                        </Button>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {can.approve && <ApprovalPanel pengajuan={pengajuan} />}

                        {can.isiNomor && <NomorSppdPanel pengajuan={pengajuan} />}

                        {can.isiAkunAnggaran && pengajuan.sppd && <AkunAnggaranPanel pengajuan={pengajuan} />}

                        {can.isiLaporan && <LaporanPanel pengajuan={pengajuan} laporan={laporan} />}

                        {laporan && !can.isiLaporan && <LaporanCard laporan={laporan} />}

                        {can.cairkanLumsum && <LumsumPanel pengajuan={pengajuan} />}

                        {can.selesaikan && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">Selesaikan Perjalanan Dinas</CardTitle>
                                </CardHeader>
                                <CardContent className="flex flex-col gap-3">
                                    <p className="text-sm text-muted-foreground">
                                        Tandai perjalanan dinas ini selesai setelah pemohon kembali. SPPD otomatis diarsipkan sebagai PDF yang sudah berisi
                                        tanda tangan Kepala Sekolah, dan sesudahnya tombol unduh memberikan arsip itu.
                                    </p>
                                    <Button
                                        type="button"
                                        className="w-fit"
                                        onClick={async () => {
                                            const ok = await confirm({
                                                title: 'Tandai perjalanan dinas ini selesai?',
                                                description: 'SPPD diarsipkan sebagai PDF bertanda tangan Kepala Sekolah dan akun anggaran tidak bisa diubah lagi. Pemohon tetap bisa mengisi atau memperbarui laporan perjalanan.',
                                                confirmLabel: 'Tandai Selesai',
                                            });
                                            if (ok) router.post(route('sppd.pengajuan.selesai', pengajuan.id));
                                        }}
                                    >
                                        Tandai Selesai & Arsipkan
                                    </Button>
                                </CardContent>
                            </Card>
                        )}

                        {pengajuan.pencairans.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">Riwayat Pencairan Dana</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="overflow-x-auto rounded-lg border">
                                        <Table kartu>
                                            <TableHeader>
                                                <TableRow>
                                                    <TableHead className="w-10">No</TableHead>
                                                    <TableHead>Jenis</TableHead>
                                                    <TableHead>Jumlah</TableHead>
                                                    <TableHead>Tanggal</TableHead>
                                                    <TableHead>Oleh</TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                {pengajuan.pencairans.map((p, index) => (
                                                    <TableRow key={p.id}>
                                                        <TableCell kartu="sembunyi" className="text-muted-foreground">{index + 1}</TableCell>
                                                        <TableCell kartu="judul" className="capitalize">{p.jenis.replace('_', ' ')}</TableCell>
                                                        <TableCell>{rupiah(p.jumlah)}</TableCell>
                                                        <TableCell>{formatDate(p.tanggal)}</TableCell>
                                                        <TableCell>{p.pencair.name}</TableCell>
                                                    </TableRow>
                                                ))}
                                            </TableBody>
                                        </Table>
                                    </div>
                                </CardContent>
                            </Card>
                        )}
                    </div>

                    <div>
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Riwayat & Audit Trail</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <ol className="flex flex-col gap-4 border-l pl-4">
                                    {riwayat.map((r) => (
                                        <li key={r.id} className="relative">
                                            <span className="absolute top-1 -left-[21px] size-2 rounded-full bg-primary" />
                                            <p className="text-sm">{r.aksi}</p>
                                            <p className="text-xs text-muted-foreground">
                                                {r.user?.name ?? 'Sistem'} &middot; {new Date(r.created_at).toLocaleString('id-ID')}
                                            </p>
                                        </li>
                                    ))}
                                </ol>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </>
    );
}
