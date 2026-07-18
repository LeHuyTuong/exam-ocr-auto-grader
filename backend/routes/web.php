<?php

use App\Models\SchoolClass;
use App\Models\User;
use App\Models\Yle\YleExam;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
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

    return response()->json(['status' => 'done', 'detail' => $result]);
});

/*
|--------------------------------------------------------------------------
| Dọn dữ liệu seed/demo trên production — CHẠY MỘT LẦN RỒI XÓA ROUTE NÀY
|--------------------------------------------------------------------------
| Xóa lớp TA-101 (cascade: học sinh, exam, điểm) + toàn bộ đề Cambridge YLE
| (cascade: parts, questions, submissions). KHÔNG đụng tới user (admin +
| giáo viên vẫn giữ nguyên). Bảo vệ bằng cùng DEPLOY_TOKEN với /__deploy.
*/
Route::get('/__cleanup', function (Request $request) {
    $expected = (string) config('deploy.token');
    abort_if($expected === '', 403);
    abort_unless(hash_equals($expected, (string) $request->query('token')), 403);

    return DB::transaction(function () {
        $classesDeleted = SchoolClass::where('code', 'TA-101')->get();
        $classCodes = $classesDeleted->pluck('code')->all();
        SchoolClass::where('code', 'TA-101')->each(fn ($c) => $c->delete());

        $yleCount = YleExam::count();
        YleExam::query()->each(fn ($e) => $e->delete());

        $remainingUsers = User::query()->pluck('email')->all();

        return response()->json([
            'status' => 'done',
            'deleted_classes' => $classCodes,
            'deleted_yle_exams' => $yleCount,
            'remaining_users' => $remainingUsers,
        ]);
    });
});
