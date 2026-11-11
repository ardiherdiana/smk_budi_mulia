import { DataPagination } from '@/components/sppd/data-pagination';
import { useConfirm } from '@/hooks/use-confirm';
import { Badge } from '@/components/ui/badge';
import { TombolAksi } from '@/components/tombol-aksi';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { type SppdPageProps, type PaginatedData } from '@/types/sppd';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { PencilIcon, PlusIcon, Trash2Icon } from 'lucide-react';

interface Role {
    value: string;
    label: string;
}

interface Pegawai {
    id: number;
    name: string;
    username: string;
    jabatan: string | null;
    is_active: boolean;
    roles: { name: string }[];
}

interface Props extends SppdPageProps {
    items: PaginatedData<Pegawai>;
    roles: Role[];
}

export default function Index() {
    const { items, roles } = usePage<Props>().props;
    const { confirm, confirmDialog } = useConfirm();

    const roleLabel = (name: string) => roles.find((r) => r.value === name)?.label ?? name;

    const hapus = async (pegawai: Pegawai) => {
        const ok = await confirm({
            title: `Hapus akun "${pegawai.name}"?`,
            description: 'Akun ini tidak bisa lagi dipakai masuk.',
            confirmLabel: 'Hapus',
            destructive: true,
        });
        if (!ok) return;
        router.delete(route('sppd.pegawai.destroy', pegawai.id), { preserveScroll: true });
    };

    return (
        <>
            <Head title="Data Pegawai" />
            {confirmDialog}

            <div className="flex flex-col gap-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">Data Pegawai</h1>
                        <p className="text-sm text-muted-foreground">Kelola akun pegawai dan role akses sistem</p>
                    </div>
                    <Button nativeButton={false} render={<Link href={route('sppd.pegawai.create')} />}>
                        <PlusIcon /> Tambah Akun
                    </Button>
                </div>

                <div className="overflow-x-auto rounded-lg border">
                    <Table kartu>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-10">No</TableHead>
                                <TableHead>Nama</TableHead>
                                <TableHead>Username</TableHead>
                                <TableHead>Role</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="text-right">Aksi</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {items.data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={6} className="text-center text-muted-foreground">
                                        Belum ada akun pegawai
                                    </TableCell>
                                </TableRow>
                            ) : (
                                items.data.map((p, index) => (
                                    <TableRow key={p.id}>
                                        <TableCell kartu="sembunyi" className="text-muted-foreground">
                                            {(items.current_page - 1) * items.per_page + index + 1}
                                        </TableCell>
                                        <TableCell kartu="judul">
                                            <div className="font-medium">{p.name}</div>
                                            {p.jabatan && <div className="text-xs text-muted-foreground">{p.jabatan}</div>}
                                        </TableCell>
                                        <TableCell>{p.username}</TableCell>
                                        <TableCell>
                                            <div className="flex flex-wrap gap-1">
                                                {p.roles.map((r) => (
                                                    <Badge key={r.name} variant="secondary">
                                                        {roleLabel(r.name)}
                                                    </Badge>
                                                ))}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant={p.is_active ? 'success' : 'secondary'}>
                                                {p.is_active ? 'Aktif' : 'Nonaktif'}
                                            </Badge>
                                        </TableCell>
                                        <TableCell kartu="aksi" className="text-right">
                                            <div className="flex justify-end gap-1">
                                                <TombolAksi
                                                    jenis="edit"
                                                    nativeButton={false}
                                                    render={<Link href={route('sppd.pegawai.edit', p.id)} />}
                                                >
                                                    <PencilIcon />
                                                </TombolAksi>
                                                <TombolAksi jenis="hapus" onClick={() => hapus(p)}>
                                                    <Trash2Icon />
                                                </TombolAksi>
                                            </div>
                                        </TableCell>
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
