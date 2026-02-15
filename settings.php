<?php
/**
 * صفحة الإعدادات - نظام إدارة الحلقات
 */

require_once 'config/config.php';
require_once 'includes/core/Autoloader.php';

use Core\Auth;
use Core\Database;
use Core\Security;
use Core\Logger;

// التحقق من تسجيل الدخول
$auth = new Auth();
$auth->requireLogin();

// التحقق من الصلاحيات
if (!$auth->hasPermission('manage_settings')) {
    http_response_code(403);
    die('ليس لديك صلاحية للوصول إلى هذه الصفحة');
}

$db = Database::getInstance();
$logger = new Logger();

$message = '';
$messageType = '';

// معالجة النموذج
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // التحقق من رمز CSRF
        if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
            throw new Exception('رمز الأمان غير صحيح');
        }

        $settings = [
            'system_name' => Security::sanitizeInput($_POST['system_name'] ?? ''),
            'system_description' => Security::sanitizeInput($_POST['system_description'] ?? ''),
            'admin_email' => Security::sanitizeInput($_POST['admin_email'] ?? ''),
            'timezone' => Security::sanitizeInput($_POST['timezone'] ?? ''),
            'date_format' => Security::sanitizeInput($_POST['date_format'] ?? ''),
            'items_per_page' => (int) ($_POST['items_per_page'] ?? 10),
            'session_timeout' => (int) ($_POST['session_timeout'] ?? 30),
            'backup_enabled' => isset($_POST['backup_enabled']) ? 1 : 0,
            'maintenance_mode' => isset($_POST['maintenance_mode']) ? 1 : 0
        ];

        // حفظ الإعدادات (يمكن تطوير هذا لاحقاً لحفظها في قاعدة البيانات)
        $configFile = __DIR__ . '/config/settings.json';
        file_put_contents($configFile, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $message = 'تم حفظ الإعدادات بنجاح';
        $messageType = 'success';

        // تسجيل العملية
        $logger->info('Settings updated', [
            'user_id' => $auth->getCurrentUser()['id'],
            'settings' => $settings
        ]);

    } catch (Exception $e) {
        $message = 'حدث خطأ: ' . $e->getMessage();
        $messageType = 'error';
        $logger->error('Settings update failed', [
            'error' => $e->getMessage(),
            'user_id' => $auth->getCurrentUser()['id']
        ]);
    }
}

// قراءة الإعدادات الحالية
$currentSettings = [
    'system_name' => 'نظام إدارة الحلقات',
    'system_description' => 'نظام شامل لإدارة الحلقات والطلاب',
    'admin_email' => 'admin@example.com',
    'timezone' => 'Asia/Riyadh',
    'date_format' => 'Y-m-d',
    'items_per_page' => 10,
    'session_timeout' => 30,
    'backup_enabled' => 1,
    'maintenance_mode' => 0
];

$configFile = __DIR__ . '/config/settings.json';
if (file_exists($configFile)) {
    $savedSettings = json_decode(file_get_contents($configFile), true);
    if ($savedSettings) {
        $currentSettings = array_merge($currentSettings, $savedSettings);
    }
}

$pageTitle = 'الإعدادات';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - نظام إدارة الحلقات</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        
        .main-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            margin: 20px;
            padding: 30px;
        }
        
        .page-header {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .settings-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            border: none;
        }
        
        .settings-card h5 {
            color: #2c3e50;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        
        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.4);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
        }
        
        .alert {
            border-radius: 10px;
            border: none;
            padding: 15px 20px;
        }
        
        .form-check-input:checked {
            background-color: #3498db;
            border-color: #3498db;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="main-container">
            <!-- Header -->
            <div class="page-header">
                <h1><i class="fas fa-cogs me-3"></i><?php echo $pageTitle; ?></h1>
                <p class="mb-0">إدارة إعدادات النظام العامة</p>
            </div>

            <!-- Messages -->
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Settings Form -->
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                
                <!-- General Settings -->
                <div class="settings-card">
                    <h5><i class="fas fa-info-circle me-2"></i>الإعدادات العامة</h5>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="system_name" class="form-label">اسم النظام</label>
                            <input type="text" class="form-control" id="system_name" name="system_name" 
                                   value="<?php echo htmlspecialchars($currentSettings['system_name']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="admin_email" class="form-label">البريد الإلكتروني للمدير</label>
                            <input type="email" class="form-control" id="admin_email" name="admin_email" 
                                   value="<?php echo htmlspecialchars($currentSettings['admin_email']); ?>" required>
                        </div>
                        <div class="col-12 mb-3">
                            <label for="system_description" class="form-label">وصف النظام</label>
                            <textarea class="form-control" id="system_description" name="system_description" rows="3"><?php echo htmlspecialchars($currentSettings['system_description']); ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Display Settings -->
                <div class="settings-card">
                    <h5><i class="fas fa-desktop me-2"></i>إعدادات العرض</h5>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="timezone" class="form-label">المنطقة الزمنية</label>
                            <select class="form-select" id="timezone" name="timezone">
                                <option value="Asia/Riyadh" <?php echo $currentSettings['timezone'] === 'Asia/Riyadh' ? 'selected' : ''; ?>>الرياض</option>
                                <option value="Asia/Dubai" <?php echo $currentSettings['timezone'] === 'Asia/Dubai' ? 'selected' : ''; ?>>دبي</option>
                                <option value="Asia/Kuwait" <?php echo $currentSettings['timezone'] === 'Asia/Kuwait' ? 'selected' : ''; ?>>الكويت</option>
                                <option value="Asia/Qatar" <?php echo $currentSettings['timezone'] === 'Asia/Qatar' ? 'selected' : ''; ?>>قطر</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="date_format" class="form-label">تنسيق التاريخ</label>
                            <select class="form-select" id="date_format" name="date_format">
                                <option value="Y-m-d" <?php echo $currentSettings['date_format'] === 'Y-m-d' ? 'selected' : ''; ?>>2024-01-01</option>
                                <option value="d/m/Y" <?php echo $currentSettings['date_format'] === 'd/m/Y' ? 'selected' : ''; ?>>01/01/2024</option>
                                <option value="d-m-Y" <?php echo $currentSettings['date_format'] === 'd-m-Y' ? 'selected' : ''; ?>>01-01-2024</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="items_per_page" class="form-label">عدد العناصر في الصفحة</label>
                            <select class="form-select" id="items_per_page" name="items_per_page">
                                <option value="10" <?php echo $currentSettings['items_per_page'] == 10 ? 'selected' : ''; ?>>10</option>
                                <option value="25" <?php echo $currentSettings['items_per_page'] == 25 ? 'selected' : ''; ?>>25</option>
                                <option value="50" <?php echo $currentSettings['items_per_page'] == 50 ? 'selected' : ''; ?>>50</option>
                                <option value="100" <?php echo $currentSettings['items_per_page'] == 100 ? 'selected' : ''; ?>>100</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Security Settings -->
                <div class="settings-card">
                    <h5><i class="fas fa-shield-alt me-2"></i>إعدادات الأمان</h5>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="session_timeout" class="form-label">مهلة انتهاء الجلسة (بالدقائق)</label>
                            <input type="number" class="form-control" id="session_timeout" name="session_timeout" 
                                   value="<?php echo $currentSettings['session_timeout']; ?>" min="5" max="1440">
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="form-check form-switch mt-4">
                                <input class="form-check-input" type="checkbox" id="backup_enabled" name="backup_enabled" 
                                       <?php echo $currentSettings['backup_enabled'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="backup_enabled">
                                    تفعيل النسخ الاحتياطي التلقائي
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- System Settings -->
                <div class="settings-card">
                    <h5><i class="fas fa-tools me-2"></i>إعدادات النظام</h5>
                    <div class="row">
                        <div class="col-12 mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="maintenance_mode" name="maintenance_mode" 
                                       <?php echo $currentSettings['maintenance_mode'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="maintenance_mode">
                                    <strong>وضع الصيانة</strong> - سيمنع الوصول للنظام عدا المبرمجين
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="text-center">
                    <button type="submit" class="btn btn-primary me-3">
                        <i class="fas fa-save me-2"></i>حفظ الإعدادات
                    </button>
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-right me-2"></i>العودة للوحة التحكم
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

