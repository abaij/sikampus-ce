<?php

namespace App\Models;

use App\Models\Concerns\MencatatPelaku;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TagihanRinci extends Model
{
    use HasFactory, MencatatPelaku, SoftDeletes;

    protected $table = 'tagihan_rinci';

    protected $fillable = ['id_tagihan', 'id_komponen_biaya', 'nominal'];

    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'id_tagihan' => 'integer',
        'id_komponen_biaya' => 'integer',
        'nominal' => 'decimal:2',
    ];

    public function tagihan()
    {
        return $this->belongsTo(Tagihan::class, 'id_tagihan');
    }

    public function komponenBiaya()
    {
        return $this->belongsTo(KomponenBiaya::class, 'id_komponen_biaya');
    }
}
