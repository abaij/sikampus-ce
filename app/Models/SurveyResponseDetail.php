<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class SurveyResponseDetail extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'survey_response_detail';
    protected $fillable = [
        'id_survey_response',
        'id_survey_question',
        'nilai_numerik',
        'nilai_text',
        'created_by',
        'updated_by',
        'deleted_by',
    ];
    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];
    protected $casts = [
        'id_survey_response' => 'integer',
        'id_survey_question' => 'integer',
        'nilai_numerik' => 'integer',
    ];

    public function response()
    {
        return $this->belongsTo(SurveyResponse::class, 'id_survey_response');
    }

    public function question()
    {
        return $this->belongsTo(SurveyQuestion::class, 'id_survey_question');
    }
}

