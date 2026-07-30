<?php

use App\Livewire\Mahasiswa\Survey\Isi;
use App\Models\Kelas;
use App\Models\Krs;
use App\Models\KurikulumMatkul;
use App\Models\Mahasiswa;
use App\Models\Matkul;
use App\Models\Semester;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyQuestionOption;
use App\Models\SurveyResponse;
use App\Models\SurveyResponseDetail;
use App\Models\User;
use Livewire\Livewire;

function surveyMahasiswaUser(): array
{
    $user = User::factory()->create(['role' => 'mahasiswa']);
    $mahasiswa = Mahasiswa::factory()->create(['id_user' => $user->id]);

    return [$user, $mahasiswa];
}

function buatKrsUntukSurvey(Mahasiswa $mahasiswa, Semester $semester, array $matkulAttrs = []): Krs
{
    $matkul = Matkul::factory()->create($matkulAttrs);
    $kurikulumMatkul = KurikulumMatkul::factory()->create(['id_matkul' => $matkul->id]);
    $kelas = Kelas::factory()->create(['id_kurikulum_matkul' => $kurikulumMatkul->id, 'id_semester' => $semester->id]);

    return Krs::factory()->create(['id_mahasiswa' => $mahasiswa->id, 'id_kelas' => $kelas->id, 'approved_at' => now()]);
}

it('redirects unauthenticated users to the login page', function () {
    $this->get(route('mahasiswa.survey'))->assertRedirect(route('login'));
});

it('shows an empty state when there is no active survey for a contracted course', function () {
    [$user] = surveyMahasiswaUser();

    $this->actingAs($user)->get(route('mahasiswa.survey'))
        ->assertOk()
        ->assertSee('Tidak ada survey aktif');
});

it('lists active surveys only for semesters the mahasiswa has an approved krs in, with fill status', function () {
    [$user, $mahasiswa] = surveyMahasiswaUser();
    $semester = Semester::factory()->create();
    $krs = buatKrsUntukSurvey($mahasiswa, $semester, ['kode' => 'IF301', 'nama' => 'Basis Data']);
    $survey = Survey::factory()->create(['id_semester' => $semester->id, 'is_active' => true, 'nama' => 'Evaluasi Dosen']);

    // Survey aktif tapi semesternya beda (mahasiswa tidak kontrak apa pun) tidak boleh muncul.
    $otherSemester = Semester::factory()->create();
    Survey::factory()->create(['id_semester' => $otherSemester->id, 'is_active' => true, 'nama' => 'Survey Tidak Relevan']);

    $this->actingAs($user)->get(route('mahasiswa.survey'))
        ->assertOk()
        ->assertSee('Evaluasi Dosen')
        ->assertSee('IF301')
        ->assertSee('Basis Data')
        ->assertSee('Isi Survey')
        ->assertDontSee('Survey Tidak Relevan');

    SurveyResponse::create([
        'id_survey' => $survey->id,
        'id_mahasiswa' => $mahasiswa->id,
        'id_krs' => $krs->id,
        'tanggal_submit' => now(),
    ]);

    $this->actingAs($user)->get(route('mahasiswa.survey'))
        ->assertOk()
        ->assertSee('Sudah diisi')
        ->assertSee('Lihat/Ubah');
});

it('lets a mahasiswa submit answers for every question type', function () {
    [$user, $mahasiswa] = surveyMahasiswaUser();
    $semester = Semester::factory()->create();
    $krs = buatKrsUntukSurvey($mahasiswa, $semester);
    $survey = Survey::factory()->create(['id_semester' => $semester->id, 'is_active' => true]);

    $essay = SurveyQuestion::factory()->create(['id_survey' => $survey->id, 'tipe' => 'essay']);
    $likert = SurveyQuestion::factory()->create(['id_survey' => $survey->id, 'tipe' => 'likert']);
    $option = SurveyQuestionOption::factory()->create(['id_survey_question' => $likert->id, 'nilai_numerik' => 4]);

    Livewire::actingAs($user)
        ->withQueryParams(['krs' => (string) $krs->id])
        ->test(Isi::class, ['id' => $survey->id])
        ->set("responses.{$essay->id}.nilai_text", 'Sangat baik.')
        ->set("responses.{$likert->id}.nilai_numerik", $option->nilai_numerik)
        ->set('feedback', 'Terima kasih pak dosen.')
        ->call('submit')
        ->assertRedirect(route('mahasiswa.survey'));

    $response = SurveyResponse::where('id_survey', $survey->id)->where('id_krs', $krs->id)->firstOrFail();
    expect($response->feedback)->toBe('Terima kasih pak dosen.');

    $essayDetail = SurveyResponseDetail::where('id_survey_response', $response->id)->where('id_survey_question', $essay->id)->firstOrFail();
    expect($essayDetail->nilai_text)->toBe('Sangat baik.');

    $likertDetail = SurveyResponseDetail::where('id_survey_response', $response->id)->where('id_survey_question', $likert->id)->firstOrFail();
    expect($likertDetail->nilai_numerik)->toBe(4);
});

it('rejects submission when a required question is left unanswered', function () {
    [$user, $mahasiswa] = surveyMahasiswaUser();
    $semester = Semester::factory()->create();
    $krs = buatKrsUntukSurvey($mahasiswa, $semester);
    $survey = Survey::factory()->create(['id_semester' => $semester->id, 'is_active' => true]);
    SurveyQuestion::factory()->create(['id_survey' => $survey->id, 'tipe' => 'essay']);

    Livewire::actingAs($user)
        ->withQueryParams(['krs' => (string) $krs->id])
        ->test(Isi::class, ['id' => $survey->id])
        ->call('submit')
        ->assertHasErrors('responses');

    expect(SurveyResponse::where('id_survey', $survey->id)->count())->toBe(0);
});

it('lets a mahasiswa resubmit and overwrite their previous answers', function () {
    [$user, $mahasiswa] = surveyMahasiswaUser();
    $semester = Semester::factory()->create();
    $krs = buatKrsUntukSurvey($mahasiswa, $semester);
    $survey = Survey::factory()->create(['id_semester' => $semester->id, 'is_active' => true]);
    $essay = SurveyQuestion::factory()->create(['id_survey' => $survey->id, 'tipe' => 'essay']);

    $response = SurveyResponse::create([
        'id_survey' => $survey->id,
        'id_mahasiswa' => $mahasiswa->id,
        'id_krs' => $krs->id,
        'tanggal_submit' => now()->subDay(),
        'feedback' => 'Jawaban lama',
    ]);
    SurveyResponseDetail::create([
        'id_survey_response' => $response->id,
        'id_survey_question' => $essay->id,
        'nilai_text' => 'Jawaban lama',
    ]);

    Livewire::actingAs($user)
        ->withQueryParams(['krs' => (string) $krs->id])
        ->test(Isi::class, ['id' => $survey->id])
        ->assertSet("responses.{$essay->id}.nilai_text", 'Jawaban lama')
        ->set("responses.{$essay->id}.nilai_text", 'Jawaban baru')
        ->set('feedback', 'Feedback baru')
        ->call('submit')
        ->assertRedirect(route('mahasiswa.survey'));

    expect(SurveyResponse::where('id_survey', $survey->id)->count())->toBe(1);
    $response->refresh();
    expect($response->feedback)->toBe('Feedback baru');

    $details = SurveyResponseDetail::where('id_survey_response', $response->id)->get();
    expect($details)->toHaveCount(1);
    expect($details->first()->nilai_text)->toBe('Jawaban baru');
});

it('forbids filling a survey for a krs that does not belong to the mahasiswa', function () {
    [$user] = surveyMahasiswaUser();
    $otherMahasiswa = Mahasiswa::factory()->create();
    $semester = Semester::factory()->create();
    $otherKrs = buatKrsUntukSurvey($otherMahasiswa, $semester);
    $survey = Survey::factory()->create(['id_semester' => $semester->id, 'is_active' => true]);

    $this->actingAs($user)->get(route('mahasiswa.survey.isi', ['id' => $survey->id, 'krs' => $otherKrs->id]))
        ->assertNotFound();
});
