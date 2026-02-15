<?php
/**
 * ملف إعدادات النظام الجديد
 * Halqat Management System v3.0
 */

// إعدادات قاعدة البيانات
define('DB_HOST', 'localhost');
define('DB_NAME', 'halqat_db');
define('DB_USER', 'halqat_user');
define('DB_PASS', 'halqat_pass123');
define('DB_CHARSET', 'utf8mb4');

// إعدادات النظام
define('SYSTEM_NAME', 'نظام إدارة الحلقات');
define('ORGANIZATION_NAME', 'جمعية ارتقاء التعليمية');
define('SYSTEM_VERSION', '3.0.0');

// إعدادات الأمان
define('SESSION_TIMEOUT', 30 * 60); // 30 دقيقة
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 15 * 60); // 15 دقيقة
define('ENCRYPTION_KEY', 'halqat_system_2024_secure_key_v3');
define('PASSWORD_HASH_ALGO', PASSWORD_DEFAULT);

// إعدادات التطوير
define('DEBUG_MODE', true);
define('LOG_ERRORS', true);
define('DISPLAY_ERRORS', false);

// إعدادات المنطقة الزمنية
date_default_timezone_set('Asia/Riyadh');

// إعدادات اللغة
define('DEFAULT_LANGUAGE', 'ar');
define('CHARSET', 'UTF-8');

// إعدادات الملفات
define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024); // 5 ميجابايت
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx']);

// إعدادات الأداء
define('PAGINATION_LIMIT', 50);
define('SEARCH_MIN_LENGTH', 2);
define('AUTOCOMPLETE_LIMIT', 10);

// مسارات النظام
define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('PUBLIC_PATH', ROOT_PATH . '/public');
define('STORAGE_PATH', ROOT_PATH . '/storage');
define('CONFIG_PATH', ROOT_PATH . '/config');
define('INCLUDES_PATH', ROOT_PATH . '/includes');

// URLs
define('BASE_URL', 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . dirname($_SERVER['SCRIPT_NAME'] ?? ''));
define('ASSETS_URL', BASE_URL . '/public/assets');

// أدوار المستخدمين والصلاحيات
define('USER_ROLES', [
    'مبرمج' => [
        'manage_users',
        'manage_system',
        'view_logs',
        'manage_settings',
        'manage_halaqat',
        'manage_students',
        'manage_courses',
        'manage_attendance',
        'manage_grades',
        'manage_reports',
        'manage_backups',
        'view_statistics',
        'export_data',
        'import_data'
    ],
    'مشرف' => [
        'manage_halaqat',
        'manage_students',
        'manage_courses',
        'manage_attendance',
        'manage_grades',
        'view_reports',
        'view_statistics',
        'export_data'
    ],
    'معلم' => [
        'view_students',
        'manage_attendance',
        'manage_grades',
        'view_reports'
    ]
]);

// إعدادات الجلسة (فقط إذا لم تبدأ الجلسة بعد)
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? 1 : 0);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Strict');
}

// إعدادات معالجة الأخطاء
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', DISPLAY_ERRORS ? 1 : 0);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
ini_set('upload_max_filesize', '5M');
ini_set('post_max_size', '10M');

// تحديد الترميز
mb_internal_encoding(CHARSET);
mb_http_output(CHARSET);

// تحميل النظام الأساسي
require_once INCLUDES_PATH . '/core/Autoloader.php';
Autoloader::register();

// تهيئة النظام
Logger::init();
Security::init();

// معالج الأخطاء المخصص
set_error_handler(function($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    
    $errorTypes = [
        E_ERROR => 'ERROR',
        E_WARNING => 'WARNING',
        E_PARSE => 'PARSE',
        E_NOTICE => 'NOTICE',
        E_CORE_ERROR => 'CORE_ERROR',
        E_CORE_WARNING => 'CORE_WARNING',
        E_COMPILE_ERROR => 'COMPILE_ERROR',
        E_COMPILE_WARNING => 'COMPILE_WARNING',
        E_USER_ERROR => 'USER_ERROR',
        E_USER_WARNING => 'USER_WARNING',
        E_USER_NOTICE => 'USER_NOTICE',
        E_STRICT => 'STRICT',
        E_RECOVERABLE_ERROR => 'RECOVERABLE_ERROR',
        E_DEPRECATED => 'DEPRECATED',
        E_USER_DEPRECATED => 'USER_DEPRECATED'
    ];
    
    $errorType = $errorTypes[$severity] ?? 'UNKNOWN';
    $errorMessage = "[$errorType] $message in $file on line $line";
    
    Logger::error($errorMessage);
    
    if (DEBUG_MODE && DISPLAY_ERRORS) {
        echo "<div style='background: #ffebee; color: #c62828; padding: 10px; margin: 10px; border-radius: 5px; font-family: monospace;'>";
        echo "<strong>خطأ في النظام:</strong><br>";
        echo htmlspecialchars($errorMessage);
        echo "</div>";
    }
    
    return true;
});

// معالج الاستثناءات المخصص
set_exception_handler(function($exception) {
    $message = "Uncaught exception: " . $exception->getMessage() . " in " . $exception->getFile() . " on line " . $exception->getLine();
    Logger::critical($message, ['trace' => $exception->getTraceAsString()]);
    
    if (DEBUG_MODE && DISPLAY_ERRORS) {
        echo "<div style='background: #ffebee; color: #c62828; padding: 15px; margin: 10px; border-radius: 5px; font-family: monospace;'>";
        echo "<strong>استثناء غير معالج:</strong><br>";
        echo htmlspecialchars($exception->getMessage()) . "<br>";
        echo "<small>الملف: " . htmlspecialchars($exception->getFile()) . " السطر: " . $exception->getLine() . "</small>";
        echo "</div>";
    } else {
        echo "<div style='background: #ffebee; color: #c62828; padding: 15px; margin: 10px; border-radius: 5px; text-align: center;'>";
        echo "حدث خطأ في النظام. يرجى المحاولة مرة أخرى أو الاتصال بالدعم الفني.";
        echo "</div>";
    }
});

// تسجيل بداية تشغيل النظام
Logger::info('System initialized', [
    'version' => SYSTEM_VERSION,
    'php_version' => PHP_VERSION,
    'memory_limit' => ini_get('memory_limit'),
    'max_execution_time' => ini_get('max_execution_time')
]);

