use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;

Route::post('/he-thong/cap-nhat', function (Request $request) {
    // Kiểm tra token bảo mật
    $token = env('DEPLOY_TOKEN');
    if (!$token || $request->header('X-Deploy-Token') !== $token) {
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    try {
        // Tắt chế độ bảo trì
        Artisan::call('up');
        
        // Xóa cache
        Artisan::call('config:clear');
        Artisan::call('route:clear');
        Artisan::call('view:clear');
        
        // Chạy migrate
        Artisan::call('migrate', ['--force' => true]);
        
        // Nạp lại permission cache (theo lưu ý trong tom-tat-app.md)
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        return response()->json(['success' => true, 'message' => 'Deployed successfully']);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
});