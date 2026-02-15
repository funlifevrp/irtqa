<?php
/**
 * صفحة تسجيل الدخول
 * Halqat Management System v3.0
 */

// تحميل الإعدادات
require_once __DIR__ . '/config/config.php';

// التحقق من تسجيل الدخول المسبق
$auth = Auth::getInstance();

// التأكد من عدم وجود جلسة نشطة
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    // التحقق من صحة الجلسة في قاعدة البيانات
    $db = Database::getInstance();
    $user = $db->select('users', ['id' => $_SESSION['user_id']]);
    
    if ($user && $user['status'] === 'نشط') {
        // المستخدم مسجل دخول بالفعل وحسابه نشط
        header('Location: dashboard.php');
        exit;
    } else {
        // الجلسة غير صحيحة، قم بتنظيفها
        session_unset();
        session_destroy();
        session_start();
        session_regenerate_id(true);
    }
}

$error = '';
$success = '';

// معالجة طلب تسجيل الدخول
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // التحقق من رمز CSRF
        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!Security::verifyCSRFToken($csrfToken, 'login')) {
            throw new Exception('رمز الأمان غير صحيح');
        }

        $loginType = Security::sanitizeInput($_POST['login_type'] ?? '');
        
        if ($loginType === 'programmer') {
            // تسجيل دخول المبرمج
            $username = Security::sanitizeInput($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            
            $result = $auth->loginProgrammer($username, $password);
            
        } elseif ($loginType === 'staff') {
            // تسجيل دخول الموظفين
            $personalCode = Security::sanitizeInput($_POST['personal_code'] ?? '', 'int');
            $role = Security::sanitizeInput($_POST['role'] ?? '');
            
            $result = $auth->loginStaff($personalCode, $role);
            
        } else {
            throw new Exception('نوع تسجيل الدخول غير صحيح');
        }

        if ($result['success']) {
            $success = $result['message'];
            // إعادة توجيه بعد ثانيتين
            header('Refresh: 2; URL=dashboard.php');
        } else {
            $error = $result['message'];
        }

    } catch (Exception $e) {
        $error = $e->getMessage();
        Logger::error('Login page error: ' . $e->getMessage());
    }
}

// توليد رمز CSRF
$csrfToken = Security::generateCSRFToken('login');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول - <?php echo SYSTEM_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            max-width: 450px;
            width: 100%;
        }
        
        .login-header {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .login-header h2 {
            margin: 0;
            font-weight: 600;
        }
        
        .login-header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
        }
        
        .login-body {
            padding: 40px 30px;
        }
        
        .login-tabs {
            display: flex;
            margin-bottom: 30px;
            background: #f8f9fa;
            border-radius: 10px;
            padding: 5px;
        }
        
        .login-tab {
            flex: 1;
            padding: 12px;
            text-align: center;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        
        .login-tab.active {
            background: #007bff;
            color: white;
            box-shadow: 0 2px 10px rgba(0, 123, 255, 0.3);
        }
        
        .login-form {
            display: none;
        }
        
        .login-form.active {
            display: block;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }
        
        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 12px 15px;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }
        
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
            font-size: 16px;
            width: 100%;
            transition: all 0.3s ease;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }
        
        .alert {
            border-radius: 10px;
            border: none;
            padding: 15px 20px;
            margin-bottom: 20px;
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
            color: white;
        }
        
        .alert-success {
            background: linear-gradient(135deg, #51cf66 0%, #40c057 100%);
            color: white;
        }
        
        .input-group-text {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-left: none;
            border-radius: 0 10px 10px 0;
        }
        
        .input-group .form-control {
            border-left: none;
            border-radius: 10px 0 0 10px;
        }
        
        .loading {
            display: none;
        }
        
        .loading.show {
            display: inline-block;
        }
        
        @media (max-width: 576px) {
            .login-container {
                padding: 10px;
            }
            
            .login-body {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h2><i class="bi bi-mortarboard-fill me-2"></i><?php echo SYSTEM_NAME; ?></h2>
                <p><?php echo ORGANIZATION_NAME; ?></p>
            </div>
            
            <div class="login-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle-fill me-2"></i>
                        <?php echo htmlspecialchars($success); ?>
                        <div class="mt-2">
                            <small>جاري التوجيه إلى لوحة التحكم...</small>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="login-tabs">
                    <div class="login-tab active" onclick="switchTab('programmer')">
                        <i class="bi bi-code-slash me-1"></i>
                        مبرمج
                    </div>
                    <div class="login-tab" onclick="switchTab('staff')">
                        <i class="bi bi-people-fill me-1"></i>
                        موظف
                    </div>
                </div>
                
                <!-- نموذج تسجيل دخول المبرمج -->
                <form method="POST" class="login-form active" id="programmer-form">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="login_type" value="programmer">
                    
                    <div class="form-group">
                        <label class="form-label">اسم المستخدم</label>
                        <div class="input-group">
                            <input type="text" class="form-control" name="username" required 
                                   placeholder="أدخل اسم المستخدم" autocomplete="username">
                            <span class="input-group-text">
                                <i class="bi bi-person-fill"></i>
                            </span>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">كلمة المرور</label>
                        <div class="input-group">
                            <input type="password" class="form-control" name="password" required 
                                   placeholder="أدخل كلمة المرور" autocomplete="current-password">
                            <span class="input-group-text">
                                <i class="bi bi-lock-fill"></i>
                            </span>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-login">
                        <span class="loading spinner-border spinner-border-sm me-2" role="status"></span>
                        <i class="bi bi-box-arrow-in-right me-2"></i>
                        تسجيل الدخول
                    </button>
                </form>
                
                <!-- نموذج تسجيل دخول الموظفين -->
                <form method="POST" class="login-form" id="staff-form">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="login_type" value="staff">
                    
                    <div class="form-group">
                        <label class="form-label">الدور</label>
                        <select class="form-control" name="role" required>
                            <option value="">اختر الدور</option>
                            <option value="مشرف">مشرف</option>
                            <option value="معلم">معلم</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">الرمز الشخصي</label>
                        <div class="input-group">
                            <input type="text" class="form-control" name="personal_code" required 
                                   placeholder="أدخل الرمز الشخصي (4 أرقام)" maxlength="4" 
                                   pattern="[0-9]{4}" autocomplete="off">
                            <span class="input-group-text">
                                <i class="bi bi-key-fill"></i>
                            </span>
                        </div>
                        <small class="text-muted">الرمز الشخصي مكون من 4 أرقام</small>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-login">
                        <span class="loading spinner-border spinner-border-sm me-2" role="status"></span>
                        <i class="bi bi-box-arrow-in-right me-2"></i>
                        تسجيل الدخول
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function switchTab(type) {
            // إزالة الفئة النشطة من جميع التبويبات
            document.querySelectorAll('.login-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // إزالة الفئة النشطة من جميع النماذج
            document.querySelectorAll('.login-form').forEach(form => {
                form.classList.remove('active');
            });
            
            // تفعيل التبويب والنموذج المحدد
            event.target.classList.add('active');
            document.getElementById(type + '-form').classList.add('active');
        }
        
        // إضافة مؤشر التحميل عند إرسال النموذج
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function() {
                const button = this.querySelector('button[type="submit"]');
                const loading = button.querySelector('.loading');
                
                button.disabled = true;
                loading.classList.add('show');
            });
        });
        
        // التحقق من صحة الرمز الشخصي
        document.querySelector('input[name="personal_code"]').addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
            if (this.value.length > 4) {
                this.value = this.value.slice(0, 4);
            }
        });
        
        // إخفاء رسائل التنبيه بعد 5 ثوان
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                if (!alert.classList.contains('alert-success')) {
                    alert.style.transition = 'opacity 0.5s ease';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                }
            });
        }, 5000);
    </script>
</body>
</html>

