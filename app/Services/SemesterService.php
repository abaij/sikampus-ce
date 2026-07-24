<?php

namespace App\Services;

use App\Models\Semester;

/**
 * Service class untuk perhitungan semester ditempuh
 * 
 * Contoh penggunaan:
 * 
 * 1. Menggunakan kode semester langsung:
 *    $semesterDitempuh = SemesterService::hitungSemesterDitempuh('20241', '20252');
 *    // Hasil: 4 (Semester 4)
 * 
 * 2. Menggunakan model Semester:
 *    $semesterMasuk = Semester::find(1);
 *    $semesterTarget = Semester::find(5);
 *    $semesterDitempuh = SemesterService::hitungSemesterDitempuhFromModel($semesterMasuk, $semesterTarget);
 * 
 * 3. Menggunakan dengan semester aktif (jika tidak ada target):
 *    $semesterMasuk = $mahasiswa->semester_masuk;
 *    $semesterDitempuh = SemesterService::hitungSemesterDitempuhDenganAktif($semesterMasuk, $idSemesterTarget);
 *    // Jika $idSemesterTarget null, akan menggunakan semester aktif
 */
class SemesterService
{
    /**
     * Hitung semester ditempuh berdasarkan semester masuk dan semester target
     * 
     * @param string|null $kodeSemesterMasuk Kode semester masuk (contoh: "20241")
     * @param string|null $kodeSemesterTarget Kode semester target (contoh: "20252")
     * @return int|null Semester ditempuh atau null jika tidak bisa dihitung
     */
    public static function hitungSemesterDitempuh(?string $kodeSemesterMasuk, ?string $kodeSemesterTarget): ?int
    {
        if (!$kodeSemesterMasuk || !$kodeSemesterTarget) {
            return null;
        }

        // Parse kode semester: format biasanya "20241" (tahun + semester)
        // Semester 1 = Ganjil, Semester 2 = Genap
        $masuk = self::parseKodeSemester($kodeSemesterMasuk);
        $target = self::parseKodeSemester($kodeSemesterTarget);

        if (!$masuk || !$target) {
            return null;
        }

        // Hitung selisih semester
        // Formula: (tahun_target - tahun_masuk) * 2 + (semester_target - semester_masuk) + 1
        $selisihTahun = $target['tahun'] - $masuk['tahun'];
        $selisihSemester = $target['semester'] - $masuk['semester'];
        $semesterKe = $selisihTahun * 2 + $selisihSemester + 1;

        // Pastikan hasilnya positif
        return $semesterKe > 0 ? $semesterKe : null;
    }

    /**
     * Hitung semester ditempuh dari model Semester
     * 
     * @param Semester|null $semesterMasuk Model semester masuk
     * @param Semester|null $semesterTarget Model semester target
     * @return int|null Semester ditempuh atau null jika tidak bisa dihitung
     */
    public static function hitungSemesterDitempuhFromModel(?Semester $semesterMasuk, ?Semester $semesterTarget): ?int
    {
        if (!$semesterMasuk || !$semesterTarget || !$semesterMasuk->kode || !$semesterTarget->kode) {
            return null;
        }

        return self::hitungSemesterDitempuh($semesterMasuk->kode, $semesterTarget->kode);
    }

    /**
     * Hitung semester ditempuh dengan menggunakan semester aktif jika tidak ada semester target
     * 
     * @param Semester|null $semesterMasuk Model semester masuk
     * @param int|null $idSemesterTarget ID semester target (opsional)
     * @return int|null Semester ditempuh atau null jika tidak bisa dihitung
     */
    public static function hitungSemesterDitempuhDenganAktif(?Semester $semesterMasuk, ?int $idSemesterTarget = null): ?int
    {
        if (!$semesterMasuk) {
            return null;
        }

        // Jika ada ID semester target, ambil dari database
        if ($idSemesterTarget) {
            $semesterTarget = Semester::find($idSemesterTarget);
        } else {
            // Jika tidak ada, gunakan semester aktif
            $semesterTarget = Semester::where('is_active', true)->first();
        }

        if (!$semesterTarget) {
            return null;
        }

        return self::hitungSemesterDitempuhFromModel($semesterMasuk, $semesterTarget);
    }

    /**
     * Parse kode semester menjadi array dengan tahun dan semester
     * 
     * @param string $kode Kode semester (contoh: "20241")
     * @return array|null Array dengan key 'tahun' dan 'semester' atau null jika tidak valid
     */
    private static function parseKodeSemester(string $kode): ?array
    {
        if (preg_match('/^(\d{4})([12])$/', $kode, $matches)) {
            return [
                'tahun' => (int) $matches[1],
                'semester' => (int) $matches[2],
            ];
        }

        return null;
    }
}

