<?php

use App\Livewire\Admin\Survey\Form;
use App\Livewire\Admin\Survey\Index;
use App\Livewire\Admin\Survey\Show;
use App\Models\Semester;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use Livewire\Livewire;

beforeEach(function () {
    config(['access.granular_permissions' => true]);
});

it('lets a fresh akademik admin view survey but not reach create/edit routes', function () {
    $admin = adminUser('admin_akademik');
    $survey = Survey::factory()->create();

    $this->actingAs($admin)->get(route('admin.administrasi.survey'))->assertOk();
    $this->actingAs($admin)->get(route('admin.administrasi.survey.show', $survey->id))->assertOk();

    $this->actingAs($admin)->get(route('admin.administrasi.survey.create'))->assertStatus(403);
    $this->actingAs($admin)->get(route('admin.administrasi.survey.edit', $survey->id))->assertStatus(403);
});

it('hides the tambah/ubah/hapus survey and pertanyaan buttons from a view-only akademik admin', function () {
    $admin = adminUser('admin_akademik');
    $survey = Survey::factory()->create();
    $question = SurveyQuestion::factory()->create(['id_survey' => $survey->id]);

    $this->actingAs($admin)->get(route('admin.administrasi.survey'))
        ->assertOk()
        ->assertDontSee('Tambah Survey')
        ->assertDontSee(route('admin.administrasi.survey.edit', $survey->id));

    $this->actingAs($admin)->get(route('admin.administrasi.survey.show', $survey->id))
        ->assertOk()
        ->assertDontSee('Tambah Pertanyaan');
});

it('blocks a view-only akademik admin from deleting a survey or managing questions via the livewire methods directly', function () {
    $admin = adminUser('admin_akademik');
    $survey = Survey::factory()->create();
    $question = SurveyQuestion::factory()->create(['id_survey' => $survey->id]);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $survey->id)
        ->assertStatus(403);

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $survey->id])
        ->call('openAddQuestion')
        ->assertStatus(403);

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $survey->id])
        ->call('confirmDeleteQuestion', $question->id)
        ->assertStatus(403);

    expect(Survey::find($survey->id))->not->toBeNull();
    expect(SurveyQuestion::find($question->id))->not->toBeNull();
});

it('lets an akademik admin create/edit/delete survey and manage questions once granted the specific permissions', function () {
    $admin = adminUser('admin_akademik');
    $admin->givePermissionTo(['create survey', 'update survey', 'delete survey']);

    $this->actingAs($admin)->get(route('admin.administrasi.survey.create'))
        ->assertOk()
        ->assertSee('Tambah Survey');

    $semester = Semester::factory()->create();

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('nama', 'Survey Baru')
        ->set('kode', 'SRV-GRAN')
        ->set('id_semester', $semester->id)
        ->call('save')
        ->assertRedirect(route('admin.administrasi.survey'));

    $survey = Survey::where('kode', 'SRV-GRAN')->firstOrFail();

    $this->actingAs($admin)->get(route('admin.administrasi.survey.edit', $survey->id))->assertOk();

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $survey->id])
        ->call('openAddQuestion')
        ->set('qPertanyaan', 'Bagaimana kepuasan Anda?')
        ->call('saveQuestion')
        ->assertHasNoErrors();

    $question = SurveyQuestion::where('id_survey', $survey->id)->firstOrFail();

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $survey->id])
        ->call('confirmDeleteQuestion', $question->id)
        ->call('deleteQuestion');

    expect(SurveyQuestion::find($question->id))->toBeNull();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $survey->id)
        ->call('delete');

    expect(Survey::find($survey->id))->toBeNull();
});

it('still lets superadmin do everything on survey regardless of granular mode', function () {
    $admin = adminUser();
    $survey = Survey::factory()->create();

    $this->actingAs($admin)->get(route('admin.administrasi.survey.create'))->assertOk();
    $this->actingAs($admin)->get(route('admin.administrasi.survey.edit', $survey->id))->assertOk();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $survey->id)
        ->call('delete');

    expect(Survey::find($survey->id))->toBeNull();
});

it('still blocks keuangan-only admins from survey entirely in granular mode', function () {
    $admin = adminUser('admin_keuangan');

    $this->actingAs($admin)->get(route('admin.administrasi.survey'))->assertStatus(403);
});
