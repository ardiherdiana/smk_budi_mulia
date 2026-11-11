<?php

namespace App\Support\LogAktivitas;

use App\Models\LogAktivitas;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Throwable;
use UnitEnum;

/**
 * Menulis log aktivitas bersama (tabel `log_aktivitas`). Satu instans per permintaan (scoped). Hanya aktif selama
 * menangani permintaan web (diaktifkan middleware CatatAktivitas), jadi seeder, impor, dan perintah konsol tidak
 * mengisi log. Gagal mencatat tidak pernah menggagalkan aksi pengguna: galatnya hanya dilaporkan.
 *
 * - catatModel(): dipanggil pendengar event Eloquent (AppServiceProvider) untuk model di config/log_aktivitas.php.
 * - catat(): aksi yang tidak memicu event model (simpan soal massal, bagikan berkas, masuk/keluar akun).
 */
class Perekam
{
    private bool $aktif = false;

    /** @var array<string, true> grup|id|aksi yang sudah dicatat di permintaan ini (lihat `grup` di config). */
    private array $tercatat = [];

    public function aktifkan(): void
    {
        $this->aktif = true;
    }

    public function nonaktifkan(): void
    {
        $this->aktif = false;
        $this->tercatat = [];
    }

    public function aktif(): bool
    {
        return $this->aktif;
    }

    /**
     * @param  array<string, array{0: mixed, 1: mixed}|string>|null  $perubahan
     */
    public function catat(
        string $aksi,
        string $modul,
        string $deskripsi,
        ?string $entitas = null,
        ?Model $subjek = null,
        ?array $perubahan = null,
        Model|Authenticatable|null $pengguna = null,
    ): void {
        if (! $this->aktif) {
            return;
        }

        try {
            $pengguna ??= auth()->user();
            $request = request();

            LogAktivitas::create([
                'userId' => $pengguna ? self::idPengguna($pengguna) : null,
                'namaPengguna' => $pengguna ? Str::limit(self::namaPengguna($pengguna), 188) : null,
                'modul' => $modul,
                'aksi' => $aksi,
                'entitas' => $entitas,
                'entitasTipe' => $subjek ? $subjek::class : null,
                'entitasId' => $subjek ? (string) $subjek->getKey() : null,
                'deskripsi' => Str::limit($deskripsi, 497),
                'perubahan' => $perubahan ?: null,
                'ip' => $request->ip(),
                'userAgent' => Str::limit((string) $request->userAgent(), 497),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** @param  'created'|'updated'|'deleted'  $peristiwa */
    public function catatModel(string $peristiwa, Model $model): void
    {
        if (! $this->aktif) {
            return;
        }

        $aturan = config('log_aktivitas.model')[$model::class] ?? null;
        if ($aturan === null) {
            return;
        }

        try {
            $aksi = ['created' => LogAktivitas::TAMBAH, 'updated' => LogAktivitas::UBAH, 'deleted' => LogAktivitas::HAPUS][$peristiwa];

            $perubahan = match ($aksi) {
                LogAktivitas::UBAH => $this->selisih($model),
                LogAktivitas::TAMBAH => $this->isian($model, baru: true),
                default => $this->isian($model, baru: false),
            };
            if ($aksi === LogAktivitas::UBAH && $perubahan === []) {
                return; // hanya kolom waktu yang berubah
            }

            if (isset($aturan['grup']) && $aksi !== LogAktivitas::UBAH) {
                $kunci = $aturan['grup'].'|'.$model->getKey().'|'.$aksi;
                if (isset($this->tercatat[$kunci])) {
                    return;
                }
                $this->tercatat[$kunci] = true;
            }

            $kata = ['tambah' => 'menambah', 'ubah' => 'mengubah', 'hapus' => 'menghapus'][$aksi];
            $pengguna = auth()->user();
            $siapa = $pengguna ? self::namaPengguna($pengguna) : 'Sistem';
            $judul = $this->judul($model, $aturan['judul'] ?? null);

            $this->catat(
                aksi: $aksi,
                modul: $aturan['modul'],
                deskripsi: "{$siapa} {$kata} {$aturan['label']} \"{$judul}\"",
                entitas: $aturan['label'],
                subjek: $model,
                perubahan: $perubahan,
                pengguna: $pengguna,
            );
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** @return array<string, array{0: mixed, 1: mixed}|string> kolom => [lama, baru], atau "diubah" untuk kolom rahasia */
    private function selisih(Model $model): array
    {
        $hasil = [];
        foreach (array_keys($model->getChanges()) as $kolom) {
            if ($this->diabaikan($model, $kolom)) {
                continue;
            }
            $hasil[$kolom] = $this->rahasia($kolom)
                ? 'diubah'
                : [$this->nilai($model->getOriginal($kolom)), $this->nilai($model->getAttribute($kolom))];
        }

        return $hasil;
    }

    /** @return array<string, array{0: mixed, 1: mixed}|string> isian saat ditambah ([null, nilai]) atau dihapus ([nilai, null]) */
    private function isian(Model $model, bool $baru): array
    {
        $hasil = [];
        foreach (array_keys($model->getAttributes()) as $kolom) {
            if ($this->diabaikan($model, $kolom) || $kolom === $model->getKeyName()) {
                continue;
            }
            if ($this->rahasia($kolom)) {
                $hasil[$kolom] = 'diisi';

                continue;
            }
            $nilai = $this->nilai($model->getAttribute($kolom));
            if ($nilai === null || $nilai === '') {
                continue;
            }
            $hasil[$kolom] = $baru ? [null, $nilai] : [$nilai, null];
        }

        return $hasil;
    }

    private function diabaikan(Model $model, string $kolom): bool
    {
        return in_array($kolom, config('log_aktivitas.abaikan_kolom'), true)
            || (in_array($kolom, $model->getHidden(), true) && ! $this->rahasia($kolom));
    }

    private function rahasia(string $kolom): bool
    {
        return in_array($kolom, config('log_aktivitas.kolom_rahasia'), true);
    }

    /** Nilai siap simpan JSON: tanggal jadi teks, enum jadi nilainya, teks panjang dipotong. */
    private function nilai(mixed $nilai): mixed
    {
        if ($nilai instanceof DateTimeInterface) {
            return $nilai->format('Y-m-d H:i');
        }
        if ($nilai instanceof BackedEnum) {
            return $nilai->value;
        }
        if ($nilai instanceof UnitEnum) {
            return $nilai->name;
        }
        if (is_array($nilai) || is_object($nilai)) {
            $nilai = json_encode($nilai, JSON_UNESCAPED_UNICODE);
        }

        return is_string($nilai) ? Str::limit($nilai, 300) : $nilai;
    }

    /** @param  string|list<string>|null  $kolom */
    private function judul(Model $model, string|array|null $kolom): string
    {
        $bagian = [];
        foreach ((array) $kolom as $jalur) {
            $nilai = data_get($model, $jalur);
            if ($nilai instanceof DateTimeInterface) {
                $nilai = $nilai->format('d/m/Y');
            } elseif ($nilai instanceof BackedEnum) {
                $nilai = $nilai->value;
            }
            if (filled($nilai) && is_scalar($nilai)) {
                $bagian[] = Str::limit(trim(preg_replace('/\s+/', ' ', (string) $nilai)), 80);
            }
        }

        return $bagian === [] ? '#'.$model->getKey() : implode(' - ', $bagian);
    }

    /** Nama tampil akun untuk keterangan log: nama profil, lalu email, lalu nomor akun. */
    public static function namaPengguna(Model|Authenticatable $pengguna): string
    {
        $nama = $pengguna instanceof Model ? ($pengguna->getAttribute('name') ?: $pengguna->getAttribute('email')) : null;

        return (string) ($nama ?: 'Akun #'.self::idPengguna($pengguna));
    }

    /** `User` (guard) dan `Akun` (pengguna permintaan Classroom) berbagi id yang sama. */
    private static function idPengguna(Model|Authenticatable $pengguna): mixed
    {
        return $pengguna instanceof Authenticatable ? $pengguna->getAuthIdentifier() : $pengguna->getKey();
    }
}
