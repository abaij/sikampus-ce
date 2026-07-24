<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class SurveyQuestion extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'survey_question';
    protected $fillable = [
        'id_survey',
        'pertanyaan',
        'tipe',
        'created_by',
        'updated_by',
        'deleted_by',
    ];
    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];
    protected $casts = [
        'id_survey' => 'integer',
    ];

    public function survey()
    {
        return $this->belongsTo(Survey::class, 'id_survey');
    }

    public function options()
    {
        return $this->hasMany(SurveyQuestionOption::class, 'id_survey_question')->orderBy('urutan');
    }
}

