<?php

use App\Livewire\Dosen\Jadwal\Index;
use App\Models\Dosen;
use App\Models\Jadwal;
use App\Models\JadwalDosen;
use App\Models\Kelas;
use App\Models\Semester;
use App\Models\User;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

it('redirects unauthenticated users to the login page', function () {
    $this->get(route('dosen.jadwal'))->assertRedirect(route('login'));
});

it('forbids a non-dosen user', function () {
    $mahasiswa = User::factory()->create(['role' => 'mahasiswa']);

    $this->actingAs($mahasiswa)->get(route('dosen.jadwal'))->assertForbidden();
});

it('expands a recurring weekly jadwal into every matching date in the viewed month', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();

    $semesterAktif = Semester::factory()->active()->create();
    $kelas = Kelas::factory()->create(['id_semester' => $semesterAktif->id]);
    $jadwal = Jadwal::factory()->create(['id_kelas' => $kelas->id, 'hari' => 'senin', 'tanggal' => null, 'is_active' => true]);
    JadwalDosen::create(['id_jadwal' => $jadwal->id, 'id_dosen' => $dosen->id, 'status' => 'active']);

    $viewMonth = CarbonImmutable::now()->startOfMonth()->format('Y-m-d');
    $events = Livewire::actingAs($dosenUser)
        ->test(Index::class)
        ->set('viewMonth', $viewMonth)
        ->instance()
        ->eventsByDate();

    $expectedMondays = 0;
    $cursor = CarbonImmutable::parse($viewMonth);
    while ($cursor->month === CarbonImmutable::parse($viewMonth)->month) {
        if ($cursor->dayOfWeekIso === 1) {
            $expectedMondays++;
        }
        $cursor = $cursor->addDay();
    }

    $totalEvents = collect($events)->sum(fn ($dayEvents) => count($dayEvents));
    expect($totalEvents)->toBe($expectedMondays);
});

it('places an explicit-date jadwal only on that single date, not recurring', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();

    $semesterAktif = Semester::factory()->active()->create();
    $kelas = Kelas::factory()->create(['id_semester' => $semesterAktif->id]);
    $tanggal = CarbonImmutable::now()->startOfMonth()->addDays(4);
    $jadwal = Jadwal::factory()->create(['id_kelas' => $kelas->id, 'tanggal' => $tanggal->toDateString(), 'is_active' => true]);
    JadwalDosen::create(['id_jadwal' => $jadwal->id, 'id_dosen' => $dosen->id, 'status' => 'active']);

    $events = Livewire::actingAs($dosenUser)
        ->test(Index::class)
        ->set('viewMonth', $tanggal->startOfMonth()->format('Y-m-d'))
        ->instance()
        ->eventsByDate();

    expect($events)->toHaveKey($tanggal->format('Y-m-d'));
    expect(collect($events)->sum(fn ($d) => count($d)))->toBe(1);
});

it('excludes jadwal_dosen rows that are not active', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();

    $semesterAktif = Semester::factory()->active()->create();
    $kelas = Kelas::factory()->create(['id_semester' => $semesterAktif->id]);
    $jadwal = Jadwal::factory()->create(['id_kelas' => $kelas->id, 'hari' => 'senin', 'is_active' => true]);
    JadwalDosen::create(['id_jadwal' => $jadwal->id, 'id_dosen' => $dosen->id, 'status' => 'inactive']);

    $events = Livewire::actingAs($dosenUser)->test(Index::class)->instance()->eventsByDate();

    expect(collect($events)->sum(fn ($d) => count($d)))->toBe(0);
});
