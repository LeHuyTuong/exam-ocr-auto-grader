<?php

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
            // Zip Slip protection: validate each entry's destination path
            $targetDir = realpath(base_path());
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entryPath = $zip->getNameIndex($i);
                $destPath = base_path($entryPath);
                $destDir = dirname($destPath);
                // Ensure parent directory exists and is within base_path
                if (! is_dir($destDir)) {
                    mkdir($destDir, 0755, true);
                }
                $realDest = realpath($destDir);
                $withinBase = $realDest === $targetDir || str_starts_with($realDest, $targetDir.DIRECTORY_SEPARATOR);
                if ($realDest === false || ! $withinBase) {
                    $zip->close();
                    abort(400, 'Invalid zip entry path');
                }
            }
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

    Artisan::call('db:seed', ['--class' => 'SettingsSeeder', '--force' => true]);
    $result['seed_settings'] = Artisan::output();

    return response()->json(['status' => 'done', 'detail' => $result]);
});

/*
|--------------------------------------------------------------------------
| Dọn user thừa trên production — ĐÃ XÓA SAU SECURITY AUDIT 2026-07-18
|--------------------------------------------------------------------------
| Route này đã được xóa vì lý do bảo mật: endpoint web-accessible với
| quyền xóa dữ liệu + token qua GET query parameter.
*/

