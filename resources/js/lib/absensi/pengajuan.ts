export type StatusBadgeVariant = 'success' | 'warning' | 'destructive' | 'secondary' | 'outline';

export const STATUS_LABEL: Record<string, string> = {
    diajukan_ke_kepsek: 'Menunggu Persetujuan',
    ditolak: 'Ditolak',
    menunggu_nomor: 'Menunggu Nomor Surat',
    sedang_ditugaskan: 'Sedang Ditugaskan',
    selesai: 'Selesai',
};

export function statusBadge(status: string): { label: string; variant: StatusBadgeVariant } {
    const label = STATUS_LABEL[status] ?? status;

    switch (status) {
        case 'diajukan_ke_kepsek':
        case 'menunggu_nomor':
            return { label, variant: 'warning' };
        case 'ditolak':
            return { label, variant: 'destructive' };
        case 'selesai':
            return { label, variant: 'success' };
        case 'sedang_ditugaskan':
            return { label, variant: 'outline' };
        default:
            return { label, variant: 'secondary' };
    }
}
