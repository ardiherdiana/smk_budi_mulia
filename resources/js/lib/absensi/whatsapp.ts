/** Tautan wa.me ke `nomor` (format internasional tanpa plus) dengan pesan yang sudah terisi. */
export function whatsappUrl(nomor: string, pesan: string) {
    return `https://wa.me/${nomor}?text=${encodeURIComponent(pesan)}`;
}
