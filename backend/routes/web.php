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

    // Nếu CI đã upload deploy.zip lên (Laravel root) thì giải nén server-side
    // rồi xóa — nhanh hơn nhiều so với FTP từng file cho host cPanel FTP-only.
    $zipPath = base_path('deploy.zip');
    if (is_file($zipPath)) {
        $zip = new \ZipArchive();
        if ($zip->open($zipPath) === true) {
            $zip->extractTo(base_path());
            $zip->close();
            @unlink($zipPath);
            $result['extract'] = 'ok';
        } else {
            $result['extract'] = 'ERROR: không mở được deploy.zip';
        }
    }

    // Xóa mọi cache (config/route/view) để code + .env mới được áp dụng.
    Artisan::call('optimize:clear');

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
