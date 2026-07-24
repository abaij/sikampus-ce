<?php

use App\Models\Kelas;
use App\Models\KelasDosen;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('kelas_dosen', 'is_pic')) {
            Schema::table('kelas_dosen', function (Blueprint $table) {
                $table->boolean('is_pic')->default(false)->after('id_dosen');
            });
        }

        foreach (Kelas::query()->whereNotNull('id_dosen_pic')->cursor() as $kelas) {
            $picId = (int) $kelas->id_dosen_pic;
            $row = KelasDosen::withTrashed()
                ->where('id_kelas', $kelas->id)
                ->where('id_dosen', $picId)
                ->first();
            if (!$row) {
                KelasDosen::create([
                    'id_kelas' => $kelas->id,
                    'id_dosen' => $picId,
                    'is_pic' => true,
                ]);
            } else {
                if ($row->trashed()) {
                    $row->restore();
                }
                $row->update(['is_pic' => true]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('kelas_dosen', 'is_pic')) {
            Schema::table('kelas_dosen', function (Blueprint $table) {
                $table->dropColumn('is_pic');
            });
        }
    }
};
