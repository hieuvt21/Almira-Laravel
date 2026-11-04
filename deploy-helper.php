<?php
// deploy-helper.php
// Tắt hiển thị lỗi mặc định để tránh lộ cấu trúc thư mục server
error_reporting(0);
header('Content-Type: application/json');

// 1. Tự động tìm và đọc biến DEPLOY_TOKEN từ file .env
$envPath = __DIR__ . '/../.env';
$deployToken = '';
if (file_exists($envPath)) {
    $envContent = file_get_contents($envPath);
    if (preg_match('/^DEPLOY_TOKEN=(.*)$/m', $envContent, $matches)) {
        // Loại bỏ khoảng trắng hoặc ký tự thừa (như \r, \n)
        $deployToken = trim($matches[1]);
    }
}

// Lấy token từ header của lệnh curl (X-Deploy-Token sẽ được PHP chuyển thành HTTP_X_DEPLOY_TOKEN)
$headerToken = $_SERVER['HTTP_X_DEPLOY_TOKEN'] ?? '';
if (!$deployToken || $headerToken !== $deployToken) {
    http_response_code(401);
    die(json_encode(['error' => 'Unauthorized. Sai hoặc thiếu DEPLOY_TOKEN trong .env.']));
}

// 2. Tìm và giải nén file deploy.zip
$zipPath = __DIR__ . '/../deploy.zip';
if (file_exists($zipPath)) {
    $zip = new ZipArchive;
    if ($zip->open($zipPath) === TRUE) {
        $zip->extractTo(__DIR__ . '/../');
        $zip->close();
        unlink($zipPath); // Xóa file zip sau khi giải nén xong
    } else {
        http_response_code(500);
        die(json_encode(['error' => 'Không thể mở file deploy.zip để giải nén.']));
    }
} else {
    http_response_code(404);
    die(json_encode(['error' => 'Không tìm thấy file deploy.zip trên máy chủ.']));
}

// 3. Khởi động hạt nhân Laravel để chạy Artisan
try {
    // Đảm bảo code mới (có vendor) đã được giải nén trước khi gọi require
    require __DIR__.'/../vendor/autoload.php';
    $app = require_once __DIR__.'/../bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

    $kernel->call('config:clear');
    $kernel->call('route:clear');
    $kernel->call('view:clear');
    $kernel->call('migrate', ['--force' => true]);
    
    // Nạp lại bộ nhớ đệm của Spatie Permission
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

} catch (\Exception $e) {
    http_response_code(500);
    unlink(__FILE__); // Vẫn cố xóa file bảo mật này nếu có lỗi
    die(json_encode(['error' => 'Lỗi khi chạy lệnh Artisan: ' . $e->getMessage()]));
}

// 4. Tự tiêu hủy: Xóa chính file helper này để bảo mật 100%
unlink(__FILE__);

echo json_encode(['success' => true, 'message' => 'Triển khai thành công bằng độc lập!']);