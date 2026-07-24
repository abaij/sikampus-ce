<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SettingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $prefix = $request->get('prefix');
        $perPage = (int) $request->get('per_page', 100);

        $query = Setting::query();

        if ($prefix) {
            $query->where('key', 'like', "{$prefix}%");
        }

        $data = $query->orderBy('order')->orderBy('key')->paginate($perPage);

        return response()->json($data);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'key' => ['required', 'string', 'max:255', 'unique:settings,key'],
            'value' => ['required', 'string'],
            'description' => ['nullable', 'string'],
            'order' => ['nullable', 'integer', 'min:0'],
        ]);

        $setting = Setting::create($validated);

        return response()->json($setting, 201);
    }

    public function show(Setting $setting): JsonResponse
    {
        return response()->json($setting);
    }

    public function update(Request $request, Setting $setting): JsonResponse
    {
        $validated = $request->validate([
            'key' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('settings', 'key')->ignore($setting->id),
            ],
            'value' => ['sometimes', 'required', 'string'],
            'description' => ['nullable', 'string'],
            'order' => ['nullable', 'integer', 'min:0'],
        ]);

        $setting->update($validated);

        return response()->json($setting);
    }

    public function destroy(Setting $setting): JsonResponse
    {
        $setting->delete();

        return response()->json(['message' => 'Setting dihapus']);
    }

    /**
     * Update multiple settings at once (bulk update)
     */
    public function bulkUpdate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'settings' => ['required', 'array'],
            'settings.*.key' => ['required', 'string', 'max:255'],
            'settings.*.value' => ['required', 'string'],
        ]);

        DB::beginTransaction();
        try {
            foreach ($validated['settings'] as $item) {
                Setting::updateOrCreate(
                    ['key' => $item['key']],
                    ['value' => $item['value']]
                );
            }

            DB::commit();

            return response()->json(['message' => 'Settings berhasil diperbarui']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Terjadi kesalahan saat memperbarui settings: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get settings by prefix and return as key-value object
     */
    public function getByPrefix(Request $request, string $prefix): JsonResponse
    {
        $settings = Setting::where('key', 'like', "{$prefix}%")
            ->orderBy('order')
            ->orderBy('key')
            ->get();

        $result = $settings->mapWithKeys(function ($setting) {
            return [$setting->key => $setting->value];
        });

        return response()->json($result);
    }

    /**
     * Upload file (logo, images, etc.)
     */
    public function uploadFile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'image', 'max:2048', 'mimes:jpeg,jpg,png,gif,webp'],
            'folder' => ['nullable', 'string', 'max:100'],
        ]);

        $file = $request->file('file');
        $folder = $validated['folder'] ?? 'logos';
        
        // Generate unique filename
        $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs($folder, $filename, 'public');

        // Get public URL
        $baseUrl = config('app.url');
        $url = $baseUrl . '/storage/' . $path;

        return response()->json([
            'success' => true,
            'url' => $url,
            'path' => $path,
        ], 201);
    }

    /**
     * Get public university information (logo and name) - no authentication required
     */
    public function getUnivInfo(): JsonResponse
    {
        $logo = Setting::where('key', 'app_univ_logo')->first();
        $name = Setting::where('key', 'app_univ_name')->first();
        $appName = Setting::where('key', 'app_name')->first();

        return response()->json([
            'logo' => $logo?->value ?? null,
            'name' => $name?->value ?? null,
            'app_name' => $appName?->value ?? null,
        ]);
    }
}
