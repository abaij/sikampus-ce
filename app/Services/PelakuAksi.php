<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Identitas pelaku untuk kolom jejak audit (`created_by`, `updated_by`, `deleted_by`,
 * `approved_by`) pada dokumen keuangan.
 *
 * Sebelumnya yang disimpan adalah NAMA TAMPILAN user (`$user->name`). Nama bisa diubah kapan
 * saja lewat modul Pengguna, dan dua orang bisa bernama sama — jadi jejaknya putus persis pada
 * saat paling dibutuhkan. Sekarang yang disimpan pengenal yang stabil, dengan urutan:
 *
 *   1. `username` — pengenal login yang tidak berubah untuk sebagian besar akun;
 *   2. `email`    — di basis data ini 7 dari 10 user (termasuk seluruh admin) belum punya
 *                   username, sementara email terisi 100%, jadi ini yang biasanya terpakai;
 *   3. `user#<id>` — pengaman terakhir; tetap bisa ditelusuri ke baris users walau keduanya kosong.
 *
 * Sengaja TIDAK pernah jatuh ke `$user->name`: kalau sampai ke situ jejaknya tidak stabil lagi,
 * dan lebih baik menyimpan id yang jelek dibaca tapi benar.
 *
 * `sistem` dipakai saat tidak ada user login — perintah artisan, seeder, atau job terjadwal.
 */
final class PelakuAksi
{
    public const SISTEM = 'sistem';

    public static function sekarang(): string
    {
        return self::untukUser(Auth::user());
    }

    public static function untukUser(?User $user): string
    {
        if (! $user) {
            return self::SISTEM;
        }

        $username = trim((string) $user->username);
        if ($username !== '') {
            return $username;
        }

        $email = trim((string) $user->email);
        if ($email !== '') {
            return $email;
        }

        return 'user#'.$user->id;
    }
}
