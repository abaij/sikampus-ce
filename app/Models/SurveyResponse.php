<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class SurveyResponse extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'survey_response';
    protected $fillable = [
        'id_survey',
        'id_mahasiswa',
        'id_krs',
        'tanggal_submit',
        'feedback',
        'created_by',
        'updated_by',
        'deleted_by',
    ];
    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];
    protected $casts = [
        'id_survey' => 'integer',
        'id_mahasiswa' => 'integer',
        'id_krs' => 'integer',
        'tanggal_submit' => 'datetime',
    ];

    public function survey()
    {
        return $this->belongsTo(Survey::class, 'id_survey');
    }

    public function mahasiswa()
    {
        return $this->belongsTo(Mahasiswa::class, 'id_mahasiswa');
    }

    public function krs()
    {
        return $this->belongsTo(Krs::class, 'id_krs');
    }

    public function details()
    {
        return $this->hasMany(SurveyResponseDetail::class, 'id_survey_response');
    }
}

