import { DatePicker } from '@/components/sppd/date-picker';
import { TimePicker } from '@/components/absensi/time-picker';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useForm } from '@inertiajs/react';
import { XIcon } from 'lucide-react';
import { type FormEventHandler, useMemo, useState } from 'react';

const MAX_PENGIKUT = 3;
const MAX_MAKSUD = 300;

export interface Pegawai {
    id: number;
    name: string;
    jabatan: string | null;
}

export interface NilaiPengajuan {
    tujuan: string;
    maksud: string;
    alat_angkutan: string | null;
    keterangan: string | null;
    tanggal_berangkat: string;
    jam_berangkat: string;
    tanggal_kembali: string;
    jam_kembali: string;
    pengikut_ids: number[];
    undangan_path: string | null;
}

interface Props {
    pegawai: Pegawai[];
    action: string;
    submitLabel: string;
    /** Nilai awal saat merevisi; kosong untuk pengajuan baru. */
    nilai?: NilaiPengajuan;
}

// new Date('YYYY-MM-DD') dibaca UTC (07.00 WIB) sehingga hari yang sama ikut terkunci; baca sebagai tanggal lokal.
const parseTanggal = (value: string) => {
    const [y, m, d] = value.split('-').map(Number);
    return new Date(y, m - 1, d);
};

const fieldError = (message?: string) => (message ? [{ message }] : undefined);

function PengikutPicker({ pegawai, value, onChange }: { pegawai: Pegawai[]; value: number[]; onChange: (ids: number[]) => void }) {
    const [query, setQuery] = useState('');
    const penuh = value.length >= MAX_PENGIKUT;

    const daftar = useMemo(
        () => pegawai.filter((p) => p.name.toLowerCase().includes(query.trim().toLowerCase())),
        [pegawai, query],
    );

    const toggle = (id: number) => {
        if (value.includes(id)) {
            onChange(value.filter((v) => v !== id));
        } else if (!penuh) {
            onChange([...value, id]);
        }
    };

    return (
        <div className="flex flex-col gap-2">
            {value.length > 0 && (
                <div className="flex flex-wrap gap-1.5">
                    {value.map((id) => {
                        const p = pegawai.find((x) => x.id === id);
                        return (
                            <Badge key={id} variant="secondary" className="gap-1">
                                {p?.name}
                                <button type="button" aria-label={`Hapus ${p?.name}`} onClick={() => toggle(id)}>
                                    <XIcon className="size-3" />
                                </button>
                            </Badge>
                        );
                    })}
                </div>
            )}
            <Input placeholder="Cari nama pegawai..." value={query} onChange={(e) => setQuery(e.target.value)} />
            <div className="max-h-44 overflow-y-auto rounded-lg border">
                {daftar.length === 0 ? (
                    <p className="px-3 py-2 text-sm text-muted-foreground">Pegawai tidak ditemukan</p>
                ) : (
                    daftar.map((p) => {
                        const dipilih = value.includes(p.id);
                        return (
                            <label key={p.id} className="flex cursor-pointer items-center gap-2 px-3 py-2 text-sm hover:bg-muted/50">
                                <Checkbox
                                    checked={dipilih}
                                    disabled={!dipilih && penuh}
                                    onCheckedChange={() => toggle(p.id)}
                                />
                                <span>{p.name}</span>
                                {p.jabatan && <span className="text-muted-foreground">({p.jabatan})</span>}
                            </label>
                        );
                    })
                )}
            </div>
        </div>
    );
}

export function PengajuanForm({ pegawai, action, submitLabel, nilai }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        tujuan: nilai?.tujuan ?? '',
        maksud: nilai?.maksud ?? '',
        alat_angkutan: nilai?.alat_angkutan ?? '',
        keterangan: nilai?.keterangan ?? '',
        pengikut_ids: nilai?.pengikut_ids ?? ([] as number[]),
        tanggal_berangkat: nilai?.tanggal_berangkat ?? '',
        jam_berangkat: nilai?.jam_berangkat ?? '',
        tanggal_kembali: nilai?.tanggal_kembali ?? '',
        jam_kembali: nilai?.jam_kembali ?? '',
        undangan: null as File | null,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(action, { forceFormData: true });
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">Data Pengajuan</CardTitle>
            </CardHeader>
            <CardContent>
                <form onSubmit={submit}>
                    <FieldGroup>
                        <Field>
                            <FieldLabel htmlFor="tujuan">Tujuan</FieldLabel>
                            <Input
                                id="tujuan"
                                placeholder="mis. Dinas Pendidikan Kabupaten Karawang"
                                value={data.tujuan}
                                onChange={(e) => setData('tujuan', e.target.value)}
                            />
                            <FieldDescription>Tulis instansi atau lokasi yang dituju</FieldDescription>
                            <FieldError errors={fieldError(errors.tujuan)} />
                        </Field>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field>
                                <FieldLabel htmlFor="tanggal_berangkat">Tanggal Berangkat</FieldLabel>
                                <DatePicker
                                    id="tanggal_berangkat"
                                    value={data.tanggal_berangkat}
                                    onChange={(value) => setData('tanggal_berangkat', value)}
                                    disabled={(date) => date < new Date(new Date().toDateString())}
                                />
                                <FieldError errors={fieldError(errors.tanggal_berangkat)} />
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="jam_berangkat">Jam Berangkat</FieldLabel>
                                <TimePicker
                                    id="jam_berangkat"
                                    className="w-full"
                                    value={data.jam_berangkat}
                                    onChange={(value) => setData('jam_berangkat', value)}
                                />
                                <FieldError errors={fieldError(errors.jam_berangkat)} />
                            </Field>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field>
                                <FieldLabel htmlFor="tanggal_kembali">Tanggal Kembali</FieldLabel>
                                <DatePicker
                                    id="tanggal_kembali"
                                    value={data.tanggal_kembali}
                                    onChange={(value) => setData('tanggal_kembali', value)}
                                    disabled={(date) =>
                                        data.tanggal_berangkat
                                            ? date < parseTanggal(data.tanggal_berangkat)
                                            : date < new Date(new Date().toDateString())
                                    }
                                />
                                <FieldError errors={fieldError(errors.tanggal_kembali)} />
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="jam_kembali">Jam Kembali</FieldLabel>
                                <TimePicker
                                    id="jam_kembali"
                                    className="w-full"
                                    value={data.jam_kembali}
                                    onChange={(value) => setData('jam_kembali', value)}
                                />
                                <FieldError errors={fieldError(errors.jam_kembali)} />
                            </Field>
                        </div>

                        <Field>
                            <FieldLabel htmlFor="maksud">Maksud Perjalanan Dinas</FieldLabel>
                            <Textarea
                                id="maksud"
                                rows={4}
                                maxLength={MAX_MAKSUD}
                                value={data.maksud}
                                onChange={(e) => setData('maksud', e.target.value)}
                            />
                            <FieldDescription>
                                {data.maksud.length}/{MAX_MAKSUD} karakter (dibatasi agar muat pada form SPPD)
                            </FieldDescription>
                            <FieldError errors={fieldError(errors.maksud)} />
                        </Field>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field>
                                <FieldLabel htmlFor="alat_angkutan">Alat Angkutan</FieldLabel>
                                <Input
                                    id="alat_angkutan"
                                    placeholder="mis. Sepeda motor pribadi"
                                    value={data.alat_angkutan}
                                    onChange={(e) => setData('alat_angkutan', e.target.value)}
                                />
                                <FieldError errors={fieldError(errors.alat_angkutan)} />
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="keterangan">Keterangan Lain-lain (opsional)</FieldLabel>
                                <Input
                                    id="keterangan"
                                    maxLength={70}
                                    value={data.keterangan}
                                    onChange={(e) => setData('keterangan', e.target.value)}
                                />
                                <FieldError errors={fieldError(errors.keterangan)} />
                            </Field>
                        </div>

                        <Field>
                            <FieldLabel>Pengikut (opsional, maksimal {MAX_PENGIKUT} orang)</FieldLabel>
                            <PengikutPicker pegawai={pegawai} value={data.pengikut_ids} onChange={(ids) => setData('pengikut_ids', ids)} />
                            <FieldError
                                errors={fieldError(
                                    errors.pengikut_ids ?? Object.entries(errors).find(([key]) => key.startsWith('pengikut_ids.'))?.[1],
                                )}
                            />
                        </Field>

                        <Field>
                            <FieldLabel htmlFor="undangan">Surat Undangan (opsional)</FieldLabel>
                            <Input
                                id="undangan"
                                type="file"
                                accept="application/pdf,image/*"
                                onChange={(e) => setData('undangan', e.target.files?.[0] ?? null)}
                            />
                            {nilai?.undangan_path && (
                                <FieldDescription>
                                    Surat undangan sebelumnya:{' '}
                                    <a href={`/uploads/${nilai.undangan_path}`} target="_blank" rel="noreferrer" className="text-primary hover:underline">
                                        lihat berkas
                                    </a>
                                    . Pilih berkas baru hanya bila ingin menggantinya.
                                </FieldDescription>
                            )}
                            <FieldError errors={fieldError(errors.undangan)} />
                        </Field>

                        <Field>
                            <Button type="submit" disabled={processing} className="w-fit">
                                {processing ? 'Memproses...' : submitLabel}
                            </Button>
                        </Field>
                    </FieldGroup>
                </form>
            </CardContent>
        </Card>
    );
}
