<?php

namespace App\Livewire\Prodi\Matkul;

use App\Models\Matkul;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Show extends Component
{
    public int $matkulId;

    public Matkul $matkul;

    public string $backUrl;

    public function mount(int $id): void
    {
        $this->matkulId = $id;

        $matkul = Matkul::with(['prodi.jenjang', 'jenisMatkul'])->findOrFail($id);

        $this->ensureAccess($matkul);

        $this->matkul = $matkul;

        $forwarded = collect(request()->query())
            ->only(['search', 'id_prodi', 'semester', 'status', 'page'])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();
        $this->backUrl = $forwarded === [] ? route('prodi.matkul') : route('prodi.matkul').'?'.http_build_query($forwarded);
    }

    /**
     * Sama persis dengan MatkulController::show.
     */
    private function ensureAccess(Matkul $matkul): void
    {
        $user = Auth::user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null && ! in_array((int) $matkul->id_prodi, $allowedProdiIds, true)) {
                abort(403, 'Anda tidak memiliki akses ke mata kuliah ini.');
            }
        }
    }

    public function render()
    {
        return view('livewire.prodi.matkul.show')->extends('layouts.prodi');
    }
}
