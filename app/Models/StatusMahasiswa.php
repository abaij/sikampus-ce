<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Mahasiswa;
use App\Models\Semester;
use App\Models\StatusAkademik;

class StatusMahasiswa extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'status_mahasiswa';
    protected $fillable = ['id_mahasiswa', 'id_semester', 'id_status_akademik', 'keterangan'];
    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];

    public function mahasiswa()
    {
        return $this->belongsTo(Mahasiswa::class, 'id_mahasiswa');
    }

    public function semester()
    {
        return $this->belongsTo(Semester::class, 'id_semester');
    }

    public function status_akademik()
    {
        return $this->belongsTo(StatusAkademik::class, 'id_status_akademik');
    }
}
