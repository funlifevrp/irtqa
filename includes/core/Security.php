<?php
/**
 * طبقة الأمان والحماية
 * Halqat Management System
 */

class Security
{
    private static $csrfTokens = [];
    private static $sessionStarted = false;

    public static function init()
    {
        if (!self::$sessionStarted) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            self::$sessionStarted = true;
        }
    }

    /**
     * تشفير كلمة المرور
     */
    public static function hashPassword($password)
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    /**
     * التحقق من كلمة المرور
     */
    public static function verifyPassword($password, $hash)
    {
        return password_verify($password, $hash);
    }

    /**
     * توليد رمز CSRF
     */
    public static function generateCSRFToken($formName = 'default')
    {
        self::init();
        
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_tokens'][$formName] = $token;
        self::$csrfTokens[$formName] = $token;
        
        return $token;
    }

    /**
     * التحقق من رمز CSRF
     */
    public static function verifyCSRFToken($token, $formName = 'default')
    {
        self::init();
        
        if (!isset($_SESSION['csrf_tokens'][$formName])) {
            return false;
        }
        
        $isValid = hash_equals($_SESSION['csrf_tokens'][$formName], $token);
        
        // حذف الرمز بعد الاستخدام
        unset($_SESSION['csrf_tokens'][$formName]);
        
        return $isValid;
    }

    /**
     * تنظيف البيانات المدخلة
     */
    public static function sanitizeInput($input, $type = 'string')
    {
        if (is_array($input)) {
            return array_map(function($item) use ($type) {
                return self::sanitizeInput($item, $type);
            }, $input);
        }

        switch ($type) {
            case 'email':
                return filter_var(trim($input), FILTER_SANITIZE_EMAIL);
            
            case 'url':
                return filter_var(trim($input), FILTER_SANITIZE_URL);
            
            case 'int':
                return filter_var($input, FILTER_SANITIZE_NUMBER_INT);
            
            case 'float':
                return filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            
            case 'html':
                return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
            
            case 'string':
            default:
                return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
        }
    }

    /**
     * التحقق من صحة البيانات المدخلة
     */
    public static function validateInput($input, $rules)
    {
        $errors = [];

        foreach ($rules as $field => $rule) {
            $value = $input[$field] ?? null;
            
            // التحقق من الحقول المطلوبة
            if (isset($rule['required']) && $rule['required'] && empty($value)) {
                $errors[$field] = $rule['message'] ?? "الحقل {$field} مطلوب";
                continue;
            }

            // تخطي التحقق إذا كان الحقل فارغاً وغير مطلوب
            if (empty($value) && (!isset($rule['required']) || !$rule['required'])) {
                continue;
            }

            // التحقق من نوع البيانات
            if (isset($rule['type'])) {
                switch ($rule['type']) {
                    case 'email':
                        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $errors[$field] = "البريد الإلكتروني غير صحيح";
                        }
                        break;
                    
                    case 'numeric':
                        if (!is_numeric($value)) {
                            $errors[$field] = "يجب أن يكون الحقل رقماً";
                        }
                        break;
                    
                    case 'date':
                        if (!strtotime($value)) {
                            $errors[$field] = "تاريخ غير صحيح";
                        }
                        break;
                }
            }

            // التحقق من الحد الأدنى للطول
            if (isset($rule['min_length']) && strlen($value) < $rule['min_length']) {
                $errors[$field] = "الحد الأدنى للطول هو {$rule['min_length']} أحرف";
            }

            // التحقق من الحد الأقصى للطول
            if (isset($rule['max_length']) && strlen($value) > $rule['max_length']) {
                $errors[$field] = "الحد الأقصى للطول هو {$rule['max_length']} حرف";
            }

            // التحقق من النمط (Regex)
            if (isset($rule['pattern']) && !preg_match($rule['pattern'], $value)) {
                $errors[$field] = $rule['pattern_message'] ?? "تنسيق الحقل غير صحيح";
            }
        }

        return $errors;
    }

    /**
     * منع هجمات SQL Injection
     */
    public static function preventSQLInjection($input)
    {
        if (is_array($input)) {
            return array_map([self::class, 'preventSQLInjection'], $input);
        }
        
        // إزالة الأحرف الخطيرة
        $dangerous = ['--', ';', '/*', '*/', 'xp_', 'sp_', 'UNION', 'SELECT', 'INSERT', 'UPDATE', 'DELETE', 'DROP'];
        
        foreach ($dangerous as $pattern) {
            $input = str_ireplace($pattern, '', $input);
        }
        
        return $input;
    }

    /**
     * منع هجمات XSS
     */
    public static function preventXSS($input)
    {
        if (is_array($input)) {
            return array_map([self::class, 'preventXSS'], $input);
        }
        
        return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    }

    /**
     * توليد رمز عشوائي آمن
     */
    public static function generateSecureToken($length = 32)
    {
        return bin2hex(random_bytes($length));
    }

    /**
     * تشفير البيانات
     */
    public static function encrypt($data, $key = null)
    {
        if ($key === null) {
            $key = defined('ENCRYPTION_KEY') ? ENCRYPTION_KEY : 'default_key';
        }
        
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        
        return base64_encode($iv . $encrypted);
    }

    /**
     * فك تشفير البيانات
     */
    public static function decrypt($encryptedData, $key = null)
    {
        if ($key === null) {
            $key = defined('ENCRYPTION_KEY') ? ENCRYPTION_KEY : 'default_key';
        }
        
        $data = base64_decode($encryptedData);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }

    /**
     * التحقق من قوة كلمة المرور
     */
    public static function checkPasswordStrength($password)
    {
        $score = 0;
        $feedback = [];

        // الطول
        if (strlen($password) >= 8) {
            $score += 1;
        } else {
            $feedback[] = 'يجب أن تكون كلمة المرور 8 أحرف على الأقل';
        }

        // الأحرف الكبيرة
        if (preg_match('/[A-Z]/', $password)) {
            $score += 1;
        } else {
            $feedback[] = 'يجب أن تحتوي على حرف كبير واحد على الأقل';
        }

        // الأحرف الصغيرة
        if (preg_match('/[a-z]/', $password)) {
            $score += 1;
        } else {
            $feedback[] = 'يجب أن تحتوي على حرف صغير واحد على الأقل';
        }

        // الأرقام
        if (preg_match('/[0-9]/', $password)) {
            $score += 1;
        } else {
            $feedback[] = 'يجب أن تحتوي على رقم واحد على الأقل';
        }

        // الرموز الخاصة
        if (preg_match('/[^A-Za-z0-9]/', $password)) {
            $score += 1;
        } else {
            $feedback[] = 'يجب أن تحتوي على رمز خاص واحد على الأقل';
        }

        $strength = 'ضعيف';
        if ($score >= 4) {
            $strength = 'قوي';
        } elseif ($score >= 3) {
            $strength = 'متوسط';
        }

        return [
            'score' => $score,
            'strength' => $strength,
            'feedback' => $feedback
        ];
    }

    /**
     * تسجيل محاولة تسجيل دخول مشبوهة
     */
    public static function logSuspiciousActivity($activity, $details = [])
    {
        $logData = [
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'timestamp' => date('Y-m-d H:i:s'),
            'activity' => $activity,
            'details' => $details
        ];

        Logger::warning('Suspicious activity detected', $logData);
    }

    /**
     * التحقق من معدل الطلبات (Rate Limiting)
     */
    public static function checkRateLimit($identifier, $maxRequests = 10, $timeWindow = 60)
    {
        self::init();
        
        $key = 'rate_limit_' . $identifier;
        $now = time();
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = [];
        }
        
        // إزالة الطلبات القديمة
        $_SESSION[$key] = array_filter($_SESSION[$key], function($timestamp) use ($now, $timeWindow) {
            return ($now - $timestamp) < $timeWindow;
        });
        
        // التحقق من تجاوز الحد
        if (count($_SESSION[$key]) >= $maxRequests) {
            return false;
        }
        
        // إضافة الطلب الحالي
        $_SESSION[$key][] = $now;
        
        return true;
    }
}

