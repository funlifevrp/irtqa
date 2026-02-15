<?php
/**
 * ملف اختبار سلامة النظام
 * يتحقق من جميع المتطلبات والإعدادات
 */

// تفعيل عرض الأخطاء للتشخيص
ini_set('display_errors', 1);
error_reporting(E_ALL);

// بدء الجلسة
session_start();

// تضمين ملف الإعدادات مع معالجة الأخطاء
try {
    require_once __DIR__ . '/config/config.php';
} catch (Exception $e) {
    die('<div class="alert alert-danger">خطأ في تحميل ملف الإعدادات: ' . $e->getMessage() . '</div>');
}

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>اختبار سلامة النظام - نظام الحلقات</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .test-item { margin-bottom: 15px; padding: 15px; border-radius: 8px; }
        .test-success { background-color: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .test-error { background-color: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .test-warning { background-color: #fff3cd; border: 1px solid #ffeaa7; color: #856404; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px 0; }
    </style>
</head>
<body>
    <div class="header text-center">
        <div class="container">
            <h1><i class="fas fa-cogs"></i> اختبار سلامة النظام</h1>
            <p class="lead">فحص شامل لجميع متطلبات وإعدادات نظام الحلقات</p>
        </div>
    </div>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-12">
                <?php
                $tests = [];
                $allPassed = true;

                // اختبار إصدار PHP
                $phpVersion = phpversion();
                if (version_compare($phpVersion, '7.4.0', '>=')) {
                    $tests[] = [
                        'name' => 'إصدار PHP',
                        'status' => 'success',
                        'message' => "إصدار PHP: {$phpVersion} ✓"
                    ];
                } else {
                    $tests[] = [
                        'name' => 'إصدار PHP',
                        'status' => 'error',
                        'message' => "إصدار PHP قديم: {$phpVersion}. يتطلب 7.4 أو أحدث"
                    ];
                    $allPassed = false;
                }

                // اختبار إضافات PHP المطلوبة
                $requiredExtensions = ['mysqli', 'session', 'json', 'mbstring'];
                foreach ($requiredExtensions as $ext) {
                    if (extension_loaded($ext)) {
                        $tests[] = [
                            'name' => "إضافة PHP: {$ext}",
                            'status' => 'success',
                            'message' => "إضافة {$ext} متوفرة ✓"
                        ];
                    } else {
                        $tests[] = [
                            'name' => "إضافة PHP: {$ext}",
                            'status' => 'error',
                            'message' => "إضافة {$ext} غير متوفرة"
                        ];
                        $allPassed = false;
                    }
                }

                // اختبار الاتصال بقاعدة البيانات
                try {
                    $db = Database::getInstance();
                    $connection = $db->getConnection();
                    if ($connection) {
                        $tests[] = [
                            'name' => 'الاتصال بقاعدة البيانات',
                            'status' => 'success',
                            'message' => 'الاتصال بقاعدة البيانات ناجح ✓'
                        ];
                        
                        // اختبار وجود الجداول الأساسية
                        $requiredTables = ['users', 'students', 'halaqat', 'attendance', 'audit_log'];
                        foreach ($requiredTables as $table) {
                            try {
                                $stmt = $connection->query("SELECT 1 FROM `{$table}` LIMIT 1");
                                $tests[] = [
                                    'name' => "جدول: {$table}",
                                    'status' => 'success',
                                    'message' => "جدول {$table} موجود ✓"
                                ];
                            } catch (Exception $e) {
                                $tests[] = [
                                    'name' => "جدول: {$table}",
                                    'status' => 'error',
                                    'message' => "جدول {$table} غير موجود"
                                ];
                                $allPassed = false;
                            }
                        }
                    }
                } catch (Exception $e) {
                    $tests[] = [
                        'name' => 'الاتصال بقاعدة البيانات',
                        'status' => 'error',
                        'message' => 'فشل الاتصال بقاعدة البيانات: ' . $e->getMessage()
                    ];
                    $allPassed = false;
                }

                // اختبار صلاحيات المجلدات
                $requiredDirs = ['logs', 'storage', 'public'];
                foreach ($requiredDirs as $dir) {
                    $dirPath = __DIR__ . '/' . $dir;
                    if (is_dir($dirPath) && is_writable($dirPath)) {
                        $tests[] = [
                            'name' => "صلاحيات مجلد: {$dir}",
                            'status' => 'success',
                            'message' => "مجلد {$dir} قابل للكتابة ✓"
                        ];
                    } else {
                        $tests[] = [
                            'name' => "صلاحيات مجلد: {$dir}",
                            'status' => 'warning',
                            'message' => "مجلد {$dir} غير قابل للكتابة أو غير موجود"
                        ];
                    }
                }

                // اختبار ملفات النظام الأساسية
                $requiredFiles = [
                    'config/config.php',
                    'includes/core/Autoloader.php',
                    'includes/core/Database.php',
                    'includes/core/Auth.php',
                    'includes/core/Security.php',
                    'includes/core/Logger.php',
                    'app/models/User.php'
                ];
                
                foreach ($requiredFiles as $file) {
                    $filePath = __DIR__ . '/' . $file;
                    if (file_exists($filePath) && is_readable($filePath)) {
                        $tests[] = [
                            'name' => "ملف: {$file}",
                            'status' => 'success',
                            'message' => "ملف {$file} موجود وقابل للقراءة ✓"
                        ];
                    } else {
                        $tests[] = [
                            'name' => "ملف: {$file}",
                            'status' => 'error',
                            'message' => "ملف {$file} غير موجود أو غير قابل للقراءة"
                        ];
                        $allPassed = false;
                    }
                }

                // اختبار المستخدم الافتراضي
                try {
                    if (isset($db) && $connection) {
                        $stmt = $connection->prepare("SELECT id, username, role FROM users WHERE username = 'sle'");
                        $stmt->execute();
                        $result = $stmt->fetch();
                        
                        if ($result) {
                            $tests[] = [
                                'name' => 'المستخدم الافتراضي',
                                'status' => 'success',
                                'message' => "المستخدم 'sle' موجود بدور: {$result['role']} ✓"
                            ];
                        } else {
                            $tests[] = [
                                'name' => 'المستخدم الافتراضي',
                                'status' => 'error',
                                'message' => "المستخدم الافتراضي 'sle' غير موجود"
                            ];
                            $allPassed = false;
                        }
                    }
                } catch (Exception $e) {
                    $tests[] = [
                        'name' => 'المستخدم الافتراضي',
                        'status' => 'error',
                        'message' => 'خطأ في فحص المستخدم الافتراضي: ' . $e->getMessage()
                    ];
                }

                // عرض النتائج
                foreach ($tests as $test) {
                    $statusClass = 'test-' . $test['status'];
                    $icon = $test['status'] === 'success' ? 'fa-check-circle' : 
                           ($test['status'] === 'error' ? 'fa-times-circle' : 'fa-exclamation-triangle');
                    
                    echo "<div class='test-item {$statusClass}'>";
                    echo "<i class='fas {$icon} me-2'></i>";
                    echo "<strong>{$test['name']}:</strong> {$test['message']}";
                    echo "</div>";
                }

                // النتيجة النهائية
                if ($allPassed) {
                    echo "<div class='alert alert-success mt-4'>";
                    echo "<h4><i class='fas fa-check-circle'></i> تهانينا!</h4>";
                    echo "<p>جميع الاختبارات نجحت. النظام جاهز للاستخدام.</p>";
                    echo "<a href='login.php' class='btn btn-success'><i class='fas fa-sign-in-alt'></i> الذهاب لصفحة تسجيل الدخول</a>";
                    echo "</div>";
                } else {
                    echo "<div class='alert alert-danger mt-4'>";
                    echo "<h4><i class='fas fa-exclamation-triangle'></i> يوجد مشاكل!</h4>";
                    echo "<p>يرجى إصلاح المشاكل المذكورة أعلاه قبل استخدام النظام.</p>";
                    echo "<p>راجع ملف <code>SETUP_INSTRUCTIONS.md</code> للحصول على تعليمات مفصلة.</p>";
                    echo "</div>";
                }
                ?>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-info-circle"></i> معلومات النظام</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>إصدار PHP:</strong> <?php echo phpversion(); ?></p>
                                <p><strong>نظام التشغيل:</strong> <?php echo php_uname('s') . ' ' . php_uname('r'); ?></p>
                                <p><strong>خادم الويب:</strong> <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'غير محدد'; ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>الذاكرة المتاحة:</strong> <?php echo ini_get('memory_limit'); ?></p>
                                <p><strong>وقت التنفيذ الأقصى:</strong> <?php echo ini_get('max_execution_time'); ?> ثانية</p>
                                <p><strong>حجم الملف الأقصى:</strong> <?php echo ini_get('upload_max_filesize'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="text-center mt-4 mb-4">
            <p class="text-muted">نظام إدارة الحلقات - الإصدار 3.0</p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

