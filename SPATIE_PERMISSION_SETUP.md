# Setup Spatie Permission

Dokumentasi ini menjelaskan setup dan penggunaan Spatie Permission di aplikasi SIAK.

## Instalasi

Package Spatie Permission sudah terinstall dan dikonfigurasi. Berikut langkah-langkah yang sudah dilakukan:

1. ✅ Install package via composer
2. ✅ Publish migration dan config
3. ✅ Update model User dengan trait HasRoles
4. ✅ Update model Role untuk extend Spatie Role
5. ✅ Membuat migration untuk menambahkan guard_name ke tabel roles
6. ✅ Memodifikasi migration Spatie untuk tidak membuat tabel roles baru
7. ✅ Update middleware dan controller untuk menggunakan Spatie Permission

## Struktur Database

Spatie Permission menggunakan tabel berikut:
- `permissions` - Menyimpan permissions
- `roles` - Menyimpan roles (menggunakan tabel yang sudah ada)
- `model_has_roles` - Pivot table untuk user-role relationship
- `model_has_permissions` - Pivot table untuk user-permission relationship (direct)
- `role_has_permissions` - Pivot table untuk role-permission relationship

Tabel `user_roles` dan `user_role_scopes` tetap digunakan untuk menyimpan scope (fakultas, prodi, dll).

## Penggunaan

### Menambahkan Role ke User

```php
$user = User::find(1);
$user->assignRole('akademik');
$user->assignRole(['akademik', 'keuangan']);
```

### Menghapus Role dari User

```php
$user->removeRole('akademik');
$user->syncRoles(['akademik']); // Replace semua roles
```

### Mengecek Role

```php
if ($user->hasRole('akademik')) {
    // User memiliki role akademik
}

if ($user->hasAnyRole(['akademik', 'keuangan'])) {
    // User memiliki salah satu role
}

if ($user->hasAllRoles(['akademik', 'keuangan'])) {
    // User memiliki semua role
}
```

### Menambahkan Permission ke Role

```php
$role = Role::findByName('akademik');
$role->givePermissionTo('manage semester');
$role->syncPermissions(['manage semester', 'manage kurikulum']);
```

### Menambahkan Permission ke User (Direct)

```php
$user->givePermissionTo('manage semester');
$user->revokePermissionTo('manage semester');
```

### Mengecek Permission

```php
if ($user->can('manage semester')) {
    // User memiliki permission
}

if ($user->hasPermissionTo('manage semester')) {
    // User memiliki permission (dari role atau direct)
}

// Cek semua permissions user
$permissions = $user->getAllPermissions();
```

### Menggunakan di Middleware

```php
// Di routes/api.php
Route::middleware(['auth:sanctum', 'role:akademik'])->group(function () {
    // Routes untuk role akademik
});

Route::middleware(['auth:sanctum', 'permission:manage semester'])->group(function () {
    // Routes untuk permission tertentu
});
```

### Menggunakan di Controller

```php
public function index()
{
    $this->authorize('view akademik');
    // atau
    if (!auth()->user()->can('view akademik')) {
        abort(403);
    }
}
```

## Seeder

Jalankan seeder untuk membuat permissions dan roles dasar:

```bash
php artisan db:seed --class=PermissionSeeder
```

Seeder akan membuat:
- Permissions untuk semua menu (akademik, keuangan, administrasi, laporan, pengaturan)
- Roles: superadmin, akademik, keuangan
- Assign permissions ke masing-masing role

## Migration Data

Untuk migrasi data dari `user_roles` ke `model_has_roles`, jalankan:

```bash
php artisan migrate
```

Migration akan otomatis memindahkan data dari `user_roles` ke `model_has_roles`.

## Endpoint API

Endpoint `/auth/me` sudah diupdate untuk mengembalikan:
- `roles` - Array roles dengan scopes
- `permissions` - Array semua permissions user (dari roles dan direct)

Format response:
```json
{
  "user": {...},
  "roles": {
    "akademik": {
      "scopes": {
        "kampus": true,
        "fakultas": [1, 2]
      },
      "permissions": ["view akademik", "manage semester", ...]
    }
  },
  "permissions": ["view akademik", "manage semester", ...]
}
```

## Catatan Penting

1. Tabel `roles` yang sudah ada tetap digunakan, hanya ditambahkan kolom `guard_name`
2. Tabel `user_roles` dan `user_role_scopes` tetap digunakan untuk menyimpan scope
3. Spatie Permission menggunakan `model_has_roles` untuk relationship user-role
4. Untuk backward compatibility, sistem masih mendukung pengecekan role via field `role` di tabel users

