<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class SurveyQuestionOption extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'survey_question_option';
    protected $fillable = [
        'id_survey_question',
        'opsi',
        'nilai_numerik',
        'urutan',
        'created_by',
        'updated_by',
        'deleted_by',
    ];
    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];
    protected $casts = [
        'id_survey_question' => 'integer',
        'nilai_numerik' => 'integer',
        'urutan' => 'integer',
    ];

    public function question()
    {
        return $this->belongsTo(SurveyQuestion::class, 'id_survey_question');
    }
}

