<?php

use App\Livewire\Admin\Survey\Form;
use App\Livewire\Admin\Survey\Index;
use App\Livewire\Admin\Survey\Show;
use App\Models\Krs;
use App\Models\Mahasiswa;
use App\Models\Prodi;
use App\Models\Semester;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyQuestionOption;
use App\Models\SurveyResponse;
use App\Models\SurveyResponseDetail;
use Livewire\Livewire;

it('renders index, create form, and show as full pages', function () {
    $admin = adminUser();
    $semester = Semester::factory()->create();
    $survey = Survey::factory()->create(['nama' => 'Survey Kepuasan', 'id_semester' => $semester->id]);

    $this->actingAs($admin)->get(route('admin.administrasi.survey'))->assertOk()->assertSee('Survey Kepuasan');
    $this->actingAs($admin)->get(route('admin.administrasi.survey.create'))->assertOk()->assertSee('Tambah Survey');
    $this->actingAs($admin)->get(route('admin.administrasi.survey.show', $survey->id))->assertOk()->assertSee('Survey Kepuasan');
});

it('creates, updates, and deletes a survey', function () {
    $admin = adminUser();
    $semester = Semester::factory()->create();

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('nama', 'Survey Baru')
        ->set('kode', 'SRV-001')
        ->set('id_semester', $semester->id)
        ->call('save')
        ->assertRedirect(route('admin.administrasi.survey'));

    $survey = Survey::where('kode', 'SRV-001')->firstOrFail();
    expect($survey->nama)->toBe('Survey Baru');

    Livewire::actingAs($admin)
        ->test(Form::class, ['id' => $survey->id])
        ->assertSet('nama', 'Survey Baru')
        ->set('nama', 'Survey Diubah')
        ->set('is_active', true)
        ->call('save');

    expect($survey->fresh()->nama)->toBe('Survey Diubah');
    expect($survey->fresh()->is_active)->toBeTrue();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $survey->id)
        ->call('delete');

    expect(Survey::find($survey->id))->toBeNull();
});

it('rejects a duplicate kode', function () {
    $admin = adminUser();
    $semester = Semester::factory()->create();
    Survey::factory()->create(['kode' => 'SRV-DUP']);

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('nama', 'Survey Lain')
        ->set('kode', 'SRV-DUP')
        ->set('id_semester', $semester->id)
        ->call('save')
        ->assertHasErrors('kode');
});

it('adds, edits, and deletes a survey question with options', function () {
    $admin = adminUser();
    $survey = Survey::factory()->create();

    $component = Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $survey->id])
        ->call('openAddQuestion')
        ->set('qPertanyaan', 'Bagaimana kepuasan Anda?')
        ->set('qTipe', 'single_choice')
        ->call('addOption')
        ->set('qOptions.0.opsi', 'Puas')
        ->set('qOptions.0.nilai_numerik', 5)
        ->call('addOption')
        ->set('qOptions.1.opsi', 'Tidak Puas')
        ->set('qOptions.1.nilai_numerik', 1)
        ->call('saveQuestion')
        ->assertHasNoErrors();

    $question = SurveyQuestion::where('id_survey', $survey->id)->firstOrFail();
    expect($question->pertanyaan)->toBe('Bagaimana kepuasan Anda?');
    expect($question->options()->count())->toBe(2);

    $component
        ->call('openEditQuestion', $question->id)
        ->assertSet('qPertanyaan', 'Bagaimana kepuasan Anda?')
        ->set('qPertanyaan', 'Seberapa puas Anda?')
        ->call('removeOption', 1)
        ->call('saveQuestion');

    expect($question->fresh()->pertanyaan)->toBe('Seberapa puas Anda?');
    expect($question->fresh()->options()->count())->toBe(1);

    $component
        ->call('confirmDeleteQuestion', $question->id)
        ->call('deleteQuestion');

    expect(SurveyQuestion::find($question->id))->toBeNull();
});

it('rejects an essay question with an empty options list requirement but allows no options', function () {
    $admin = adminUser();
    $survey = Survey::factory()->create();

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $survey->id])
        ->call('openAddQuestion')
        ->set('qPertanyaan', 'Ceritakan pengalaman Anda')
        ->set('qTipe', 'essay')
        ->call('saveQuestion')
        ->assertHasNoErrors();

    $question = SurveyQuestion::where('id_survey', $survey->id)->firstOrFail();
    expect($question->options()->count())->toBe(0);
});

it('computes statistik pengisian survey with prodi filter', function () {
    $admin = adminUser();
    $survey = Survey::factory()->create();
    $question = SurveyQuestion::factory()->create(['id_survey' => $survey->id, 'tipe' => 'single_choice']);
    SurveyQuestionOption::factory()->create(['id_survey_question' => $question->id, 'opsi' => 'Puas', 'nilai_numerik' => 5]);
    SurveyQuestionOption::factory()->create(['id_survey_question' => $question->id, 'opsi' => 'Tidak Puas', 'nilai_numerik' => 1]);

    $prodiA = Prodi::factory()->create();
    $prodiB = Prodi::factory()->create();
    $mahasiswaA = Mahasiswa::factory()->create(['id_prodi' => $prodiA->id]);
    $mahasiswaB = Mahasiswa::factory()->create(['id_prodi' => $prodiB->id]);
    $krsA = Krs::factory()->create(['id_mahasiswa' => $mahasiswaA->id]);
    $krsB = Krs::factory()->create(['id_mahasiswa' => $mahasiswaB->id]);

    $responseA = SurveyResponse::factory()->create(['id_survey' => $survey->id, 'id_mahasiswa' => $mahasiswaA->id, 'id_krs' => $krsA->id]);
    SurveyResponseDetail::factory()->create(['id_survey_response' => $responseA->id, 'id_survey_question' => $question->id, 'nilai_numerik' => 5]);

    $responseB = SurveyResponse::factory()->create(['id_survey' => $survey->id, 'id_mahasiswa' => $mahasiswaB->id, 'id_krs' => $krsB->id]);
    SurveyResponseDetail::factory()->create(['id_survey_response' => $responseB->id, 'id_survey_question' => $question->id, 'nilai_numerik' => 1]);

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $survey->id])
        ->call('setTab', 'statistik')
        ->assertSee('2') // total responden tanpa filter
        ->set('filterProdi', (string) $prodiA->id)
        ->assertSee('Puas');
});

it('carries the current page/search state from index into the Lihat and Ubah links', function () {
    $admin = adminUser();
    Survey::factory()->count(15)->create();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('perPage', 10)
        ->call('gotoPage', 2)
        ->assertSee('page=2');
});

it('points the Kembali button on the detail page to the page/search state carried in the query string', function () {
    $admin = adminUser();
    $survey = Survey::factory()->create();

    $this->actingAs($admin)
        ->get(route('admin.administrasi.survey.show', $survey->id).'?page=2&search=kepuasan&unexpected=1')
        ->assertOk()
        ->assertSee(route('admin.administrasi.survey').'?page=2&search=kepuasan')
        ->assertDontSee('unexpected=1');
});

it('carries the forwarded state through the edit form Batal link and the save redirect', function () {
    $admin = adminUser();
    $survey = Survey::factory()->create();

    $expectedBackUrl = route('admin.administrasi.survey').'?page=2&search=kepuasan';

    $this->actingAs($admin)
        ->get(route('admin.administrasi.survey.edit', $survey->id).'?page=2&search=kepuasan&unexpected=1')
        ->assertOk()
        ->assertSee($expectedBackUrl)
        ->assertDontSee('unexpected=1');

    Livewire::withQueryParams(['page' => '2', 'search' => 'kepuasan'])
        ->actingAs($admin)
        ->test(Form::class, ['id' => $survey->id])
        ->set('nama', 'Survey Update')
        ->call('save')
        ->assertRedirect($expectedBackUrl);
});

it('keeps the show tab and modal buttons inside the livewire root so wire:click stays bound', function () {
    $admin = adminUser();
    $survey = Survey::factory()->create();

    $html = $this->actingAs($admin)->get(route('admin.administrasi.survey.show', $survey->id))->getContent();

    $rootStart = strpos($html, 'wire:id=');
    expect($rootStart)->not->toBeFalse();
    expect(strpos($html, "wire:click=\"setTab('pertanyaan')\""))->toBeGreaterThan($rootStart);

    $pertanyaanHtml = Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $survey->id])
        ->call('setTab', 'pertanyaan')
        ->html();

    expect(strpos($pertanyaanHtml, 'wire:click="openAddQuestion"'))->not->toBeFalse();
});

it('redirects unauthenticated users to the admin login page', function () {
    $this->get(route('admin.administrasi.survey'))->assertRedirect(route('login'));
});
