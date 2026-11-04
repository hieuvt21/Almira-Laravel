<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\PermissionRegistrar;

Route::post('/he-thong/cap-nhat', function (Request $request) {
    // 1. Xác thực bảo mật bằng Token
    $token = env('DEPLOY_TOKEN');
    if (!$token || $request->header('X-Deploy-Token') !== $token) {
        return response()->json(['error' => 'Unauthorized. Sai hoặc thiếu DEPLOY_TOKEN.'], 401);
    }

    try {
        // 2. TÌM VÀ GIẢI NÉN FILE ZIP
        $zipPath = base_path('deploy.zip');
        if (file_exists($zipPath)) {
            $zip = new ZipArchive;
            if ($zip->open($zipPath) === TRUE) {
                // Giải nén đè lên toàn bộ mã nguồn hiện tại ở thư mục gốc
                $zip->extractTo(base_path());
                $zip->close();
                
                // Xóa file zip để dọn rác, giải phóng dung lượng
                unlink($zipPath);
            } else {
                return response()->json(['error' => 'Không thể mở file deploy.zip để giải nén.'], 500);
            }
        } else {
            return response()->json(['error' => 'Không tìm thấy file deploy.zip trên máy chủ.'], 404);
        }
        
        // 3. CHẠY CÁC LỆNH TỐI ƯU VÀ CẬP NHẬT
        // Xóa các bộ nhớ đệm
        Artisan::call('config:clear');
        Artisan::call('route:clear');
        Artisan::call('view:clear');
        
        // Cập nhật cấu trúc database (bỏ qua câu hỏi xác nhận bằng --force)
        Artisan::call('migrate', ['--force' => true]);
        
        // Nạp lại bộ nhớ đệm của Spatie Permission (theo tóm tắt lưu ý dự án)
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return response()->json([
            'success' => true, 
            'message' => 'Triển khai code mới, giải nén và migrate thành công!'
        ]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
});