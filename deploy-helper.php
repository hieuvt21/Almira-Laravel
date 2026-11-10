<?php
// deploy-helper.php (bản có chẩn đoán lỗi)
// Không hiện lỗi ra ngoài, nhưng mọi lỗi nghiêm trọng đều được trả về dạng JSON để workflow in ra.
ini_set('display_errors', '0');
error_reporting(E_ALL);
@set_time_limit(300);
@ini_set('memory_limit', '512M');
header('Content-Type: application/json');

register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (!headers_sent()) {
            http_response_code(500);
        }
        echo json_encode([
            'error' => 'Lỗi nghiêm trọng: ' . $e['message'],
            'at' => basename($e['file']) . ':' . $e['line'],
            'php' => PHP_VERSION,
        ], JSON_UNESCAPED_UNICODE);
    }
});

function fail(int $code, string $message, array $extra = []): void
{
    http_response_code($code);
    echo json_encode(['error' => $message, 'php' => PHP_VERSION] + $extra, JSON_UNESCAPED_UNICODE);
    exit;
}

// 1. Đọc DEPLOY_TOKEN từ .env
$root = dirname(__DIR__);
$deployToken = '';
if (is_file($root . '/.env') && preg_match('/^DEPLOY_TOKEN=(.*)$/m', (string) file_get_contents($root . '/.env'), $m)) {
    $deployToken = trim($m[1], " \t\n\r\0\x0B\"'");
}
$headerToken = $_SERVER['HTTP_X_DEPLOY_TOKEN'] ?? '';
if (!$deployToken || !hash_equals($deployToken, $headerToken)) {
    fail(401, 'Unauthorized. Sai hoặc thiếu DEPLOY_TOKEN trong .env.');
}

// 2. Giải nén deploy.zip
$zipPath = $root . '/deploy.zip';
if (!is_file($zipPath)) {
    fail(404, 'Không tìm thấy file deploy.zip trên máy chủ.', ['looked_in' => $zipPath]);
}
if (!class_exists('ZipArchive')) {
    fail(500, 'PHP trên cPanel chưa bật extension zip (MultiPHP INI Editor / Select PHP Version).');
}

$zip = new ZipArchive;
$opened = $zip->open($zipPath);
if ($opened !== true) {
    fail(500, 'Không mở được deploy.zip (file có thể tải lên chưa đủ hoặc bị hỏng).', [
        'zip_error_code' => $opened,
        'zip_size_bytes' => filesize($zipPath),
    ]);
}
$files = $zip->numFiles;
if (!$zip->extractTo($root)) {
    $zip->close();
    fail(500, 'Giải nén thất bại (thường do không đủ quyền ghi hoặc hết dung lượng).', ['files' => $files, 'root' => $root]);
}
$zip->close();
@unlink($zipPath);

// 3. Chạy Artisan
try {
    require $root . '/vendor/autoload.php';
    $app = require_once $root . '/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

    $kernel->call('config:clear');
    $kernel->call('route:clear');
    $kernel->call('view:clear');
    $kernel->call('cache:clear');
    $kernel->call('migrate', ['--force' => true]);
    $migrate = $kernel->output();
    $kernel->call('db:seed', ['--class' => 'RolePermissionSeeder', '--force' => true]);

    $app[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Lỗi khi chạy lệnh Artisan: ' . $e->getMessage(),
        'type' => get_class($e),
        'at' => basename($e->getFile()) . ':' . $e->getLine(),
        'php' => PHP_VERSION,
    ], JSON_UNESCAPED_UNICODE);
    @unlink(__FILE__);
    exit;
}

@unlink(__FILE__);
echo json_encode(['success' => true, 'message' => 'Triển khai thành công!', 'files' => $files, 'migrate' => trim($migrate ?? '')], JSON_UNESCAPED_UNICODE);
