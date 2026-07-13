<?php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
| Deploy hook — chạy migrate + seed qua HTTP (cho host chỉ FTP, không shell)
|--------------------------------------------------------------------------
| Bảo vệ bằng token bí mật đọc từ env (config/deploy.php). GitHub Actions gọi
| endpoint này ở cuối mỗi lần deploy. Token KHÔNG nằm trong code → an toàn để
| commit công khai. Nếu DEPLOY_TOKEN chưa set thì endpoint bị vô hiệu (403).
*/
Route::get('/__deploy', function (Request $request) {
    $expected = (string) config('deploy.token');
    abort_if($expected === '', 403);
    abort_unless(hash_equals($expected, (string) $request->query('token')), 403);

    $result = [];

    // Xóa cache config để .env mới (JWT_SECRET) được đọc lại.
    Artisan::call('config:clear');
    Artisan::call('cache:clear');

    Artisan::call('migrate', ['--force' => true]);
    $result['migrate'] = Artisan::output();

    Artisan::call('db:seed', ['--class' => 'RolePermissionSeeder', '--force' => true]);
    $result['seed'] = Artisan::output();

    // Cột role cũ đã bị migration xóa → gán lại role cho user đang tồn tại.
    $assigned = [];
    foreach (User::all() as $user) {
        if ($user->roles()->count() === 0) {
            $role = $user->email === 'admin@chamthi.com' ? 'admin' : 'teacher';
            $user->assignRole($role);
            $assigned[] = "{$user->email} => {$role}";
        }
    }
    $result['roles_assigned'] = $assigned;

    return response()->json(['status' => 'done', 'detail' => $result]);
});
