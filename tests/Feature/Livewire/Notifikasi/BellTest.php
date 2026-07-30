<?php

use App\Livewire\Notifikasi\Bell;
use App\Models\Notifikasi;
use Livewire\Livewire;

it('counts only unread notifications belonging to the logged-in user and lists them when opened', function () {
    $user = dosenUser();
    $otherUser = dosenUser();

    Notifikasi::factory()->for($user, 'user')->create(['dibaca_pada' => null, 'judul' => 'Punya user ini']);
    Notifikasi::factory()->for($user, 'user')->create(['dibaca_pada' => now(), 'judul' => 'Sudah dibaca']);
    Notifikasi::factory()->for($otherUser, 'user')->create(['dibaca_pada' => null, 'judul' => 'Punya user lain']);

    $component = Livewire::actingAs($user)
        ->test(Bell::class)
        ->assertSet('open', false);

    expect($component->instance()->unreadCount())->toBe(1);

    $component->call('toggle')
        ->assertSet('open', true)
        ->assertSee('Punya user ini')
        ->assertSee('Sudah dibaca')
        ->assertDontSee('Punya user lain');
});

it('marks a notification as read and redirects to the route mapped from its tipe', function () {
    $user = dosenUser();
    $notifikasi = Notifikasi::factory()->for($user, 'user')->create([
        'tipe' => 'krs_diajukan',
        'dibaca_pada' => null,
    ]);

    Livewire::actingAs($user)
        ->test(Bell::class)
        ->call('toggle')
        ->call('openItem', $notifikasi->id)
        ->assertRedirect(route('dosen.krs'));

    expect($notifikasi->fresh()->dibaca_pada)->not->toBeNull();
});

it('does not redirect for an unmapped tipe but still marks it as read', function () {
    $user = dosenUser();
    $notifikasi = Notifikasi::factory()->for($user, 'user')->create([
        'tipe' => 'tipe_tidak_dikenal',
        'dibaca_pada' => null,
    ]);

    Livewire::actingAs($user)
        ->test(Bell::class)
        ->call('openItem', $notifikasi->id)
        ->assertNoRedirect();

    expect($notifikasi->fresh()->dibaca_pada)->not->toBeNull();
});

it('forbids marking a notification belonging to another user as read', function () {
    $user = dosenUser();
    $otherUser = dosenUser();
    $notifikasi = Notifikasi::factory()->for($otherUser, 'user')->create(['dibaca_pada' => null]);

    Livewire::actingAs($user)
        ->test(Bell::class)
        ->call('openItem', $notifikasi->id)
        ->assertStatus(403);

    expect($notifikasi->fresh()->dibaca_pada)->toBeNull();
});

it('marks all of the current user unread notifications as read without touching other users', function () {
    $user = dosenUser();
    $otherUser = dosenUser();

    $mine1 = Notifikasi::factory()->for($user, 'user')->create(['dibaca_pada' => null]);
    $mine2 = Notifikasi::factory()->for($user, 'user')->create(['dibaca_pada' => null]);
    $theirs = Notifikasi::factory()->for($otherUser, 'user')->create(['dibaca_pada' => null]);

    Livewire::actingAs($user)
        ->test(Bell::class)
        ->call('markAllAsRead');

    expect($mine1->fresh()->dibaca_pada)->not->toBeNull()
        ->and($mine2->fresh()->dibaca_pada)->not->toBeNull()
        ->and($theirs->fresh()->dibaca_pada)->toBeNull();
});

it('shows an empty state when the user has no notifications', function () {
    $user = dosenUser();

    Livewire::actingAs($user)
        ->test(Bell::class)
        ->call('toggle')
        ->assertSee('Belum ada notifikasi');
});
