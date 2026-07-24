<?php

namespace App\Http\Controllers;

use Spatie\Permission\Models\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class PermissionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 10);
        $search = $request->get('search');
        $guardName = $request->get('guard_name', 'web');

        $query = Permission::where('guard_name', $guardName);

        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        $data = $query->orderBy('name')->paginate($perPage);

        return response()->json($data);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('permissions', 'name')->where(function ($query) use ($request) {
                    return $query->where('guard_name', $request->get('guard_name', 'web'));
                }),
            ],
            'guard_name' => ['nullable', 'string', 'max:255'],
        ]);

        $guardName = $validated['guard_name'] ?? 'web';

        // Clear permission cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permission = Permission::create([
            'name' => $validated['name'],
            'guard_name' => $guardName,
        ]);

        return response()->json($permission, 201);
    }

    public function show(Permission $permission): JsonResponse
    {
        return response()->json($permission);
    }

    public function update(Request $request, Permission $permission): JsonResponse
    {
        $validated = $request->validate([
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('permissions', 'name')
                    ->where(function ($query) use ($permission) {
                        return $query->where('guard_name', $permission->guard_name);
                    })
                    ->ignore($permission->id),
            ],
            'guard_name' => ['nullable', 'string', 'max:255'],
        ]);

        // Clear permission cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permission->update($validated);

        return response()->json($permission);
    }

    public function destroy(Permission $permission): JsonResponse
    {
        // Clear permission cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permission->delete();

        return response()->json(['message' => 'Permission dihapus']);
    }

    /**
     * Get all permissions (for dropdown/select)
     */
    public function getAll(Request $request): JsonResponse
    {
        $guardName = $request->get('guard_name', 'web');
        
        $permissions = Permission::where('guard_name', $guardName)
            ->orderBy('name')
            ->get(['id', 'name', 'guard_name']);

        return response()->json($permissions);
    }
}

