<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PmbFaq extends Model
{
    use SoftDeletes;

    protected $table = 'pmb_faq';

    protected $fillable = [
        'id_periode',
        'pertanyaan',
        'jawaban',
        'urutan',
    ];

    protected function casts(): array
    {
        return [
            'urutan' => 'integer',
        ];
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(PmbPeriode::class, 'id_periode');
    }
}
