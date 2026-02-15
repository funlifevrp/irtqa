<?php
/**
 * صفحة تسجيل الخروج
 * Halqat Management System v3.0
 */

// تحميل الإعدادات
require_once __DIR__ . '/config/config.php';

try {
    // الحصول على معلومات المستخدم الحالي قبل تسجيل الخروج
    $auth = Auth::getInstance();
    $currentUser = $auth->getCurrentUser();
    
    if ($currentUser) {
        // تسجيل عملية تسجيل الخروج في سجل الأحداث
        Logger::info('User logged out', [
            'user_id' => $currentUser['id'],
            'username' => $currentUser['username'],
            'role' => $currentUser['role'],
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
        
        // تسجيل في جدول audit_log
        $db = Database::getInstance();
        $db->insert('audit_log', [
            'user_id' => $currentUser['id'],
            'action' => 'تسجيل خروج',
            'table_name' => 'users',
            'record_id' => $currentUser['id'],
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    }
    
    // تسجيل الخروج
    $auth->logout();
    
    // تدمير الجلسة بالكامل
    session_unset();
    session_destroy();
    
    // حذف كوكيز الجلسة
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // حذف أي كوكيز أخرى قد تكون موجودة
    setcookie('remember_me', '', time() - 3600, '/');
    setcookie('user_id', '', time() - 3600, '/');
    setcookie('username', '', time() - 3600, '/');
    setcookie('PHPSESSID', '', time() - 3600, '/');
    
    // بدء جلسة جديدة نظيفة
    session_start();
    session_regenerate_id(true);
    
    // رسالة نجاح
    $success_message = 'تم تسجيل الخروج بنجاح';
    
} catch (Exception $e) {
    Logger::error('Logout error: ' . $e->getMessage());
    $success_message = 'تم تسجيل الخروج';
}

// توليد رمز CSRF جديد لصفحة تسجيل الدخول
$csrfToken = Security::generateCSRFToken('login');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الخروج - <?php echo SYSTEM_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .logout-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            padding: 40px;
            text-align: center;
            max-width: 450px;
            width: 100%;
            margin: 20px;
        }
        
        .logout-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #51cf66 0%, #40c057 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 2rem;
            color: white;
            animation: checkmark 0.6s ease-in-out;
        }
        
        @keyframes checkmark {
            0% {
                transform: scale(0);
                opacity: 0;
            }
            50% {
                transform: scale(1.2);
            }
            100% {
                transform: scale(1);
                opacity: 1;
            }
        }
        
        .logout-title {
            color: #1f2937;
            font-weight: 600;
            margin-bottom: 15px;
        }
        
        .logout-message {
            color: #64748b;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
            font-size: 16px;
            color: white;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            margin: 5px;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
            font-size: 16px;
            color: white;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            margin: 5px;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
            color: white;
        }
        
        .countdown {
            font-size: 0.9rem;
            color: #64748b;
            margin-top: 20px;
        }
        
        .system-info {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
        }
        
        .system-info h6 {
            color: #4f46e5;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .system-info p {
            color: #64748b;
            font-size: 0.9rem;
            margin: 0;
        }
        
        @media (max-width: 576px) {
            .logout-container {
                margin: 10px;
                padding: 30px 20px;
            }
            
            .btn-login,
            .btn-secondary {
                display: block;
                width: 100%;
                margin: 10px 0;
            }
        }
    </style>
</head>
<body>
    <div class="logout-container">
        <div class="logout-icon">
            <i class="bi bi-check-lg"></i>
        </div>
        
        <h2 class="logout-title">تم تسجيل الخروج بنجاح</h2>
        
        <p class="logout-message">
            <?php echo htmlspecialchars($success_message); ?><br>
            شكرًا لاستخدامك نظام إدارة الحلقات. نتمنى لك يومًا سعيدًا!
        </p>
        
        <div class="d-flex flex-wrap justify-content-center">
            <a href="login.php" class="btn-login">
                <i class="bi bi-box-arrow-in-right me-2"></i>
                تسجيل دخول جديد
            </a>
            
            <a href="javascript:history.back()" class="btn-secondary">
                <i class="bi bi-arrow-right me-2"></i>
                العودة للخلف
            </a>
        </div>
        
        <div class="countdown">
            <small>سيتم توجيهك إلى صفحة تسجيل الدخول خلال <span id="countdown">10</span> ثانية</small>
        </div>
        
        <div class="system-info">
            <h6><?php echo SYSTEM_NAME; ?></h6>
            <p><?php echo ORGANIZATION_NAME; ?></p>
            <small class="text-muted">
                الإصدار 3.0 | <?php echo date('Y'); ?>
            </small>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // العد التنازلي لإعادة التوجيه
        let countdown = 10;
        const countdownElement = document.getElementById('countdown');
        
        const timer = setInterval(function() {
            countdown--;
            countdownElement.textContent = countdown;
            
            if (countdown <= 0) {
                clearInterval(timer);
                window.location.href = 'login.php';
            }
        }, 1000);
        
        // إيقاف العد التنازلي عند النقر على أي رابط
        document.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', function() {
                clearInterval(timer);
            });
        });
        
        // إيقاف العد التنازلي عند حركة الماوس أو الضغط على أي مفتاح
        let userInteracted = false;
        
        function stopCountdown() {
            if (!userInteracted) {
                userInteracted = true;
                clearInterval(timer);
                countdownElement.parentElement.innerHTML = 
                    '<small class="text-muted">تم إيقاف العد التنازلي</small>';
            }
        }
        
        document.addEventListener('mousemove', stopCountdown);
        document.addEventListener('keydown', stopCountdown);
        document.addEventListener('click', stopCountdown);
        
        // تأثير بصري عند تحميل الصفحة
        window.addEventListener('load', function() {
            document.querySelector('.logout-container').style.animation = 'fadeInUp 0.6s ease-out';
        });
        
        // إضافة تأثير fadeInUp
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fadeInUp {
                from {
                    opacity: 0;
                    transform: translateY(30px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>

