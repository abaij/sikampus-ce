<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PmbProdiPilih extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'pmb_prodi_pilih';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id_pendaftaran',
        'id_prodi',
    ];

    /**
     * Get the pendaftaran that owns the prodi pilih.
     */
    public function pendaftaran(): BelongsTo
    {
        return $this->belongsTo(PmbPendaftaran::class, 'id_pendaftaran');
    }

    /**
     * Get the prodi that owns the prodi pilih.
     */
    public function prodi(): BelongsTo
    {
        return $this->belongsTo(Prodi::class, 'id_prodi');
    }
}
