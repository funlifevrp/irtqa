<?php
/**
 * نظام المصادقة المحسن
 * Halqat Management System
 */

class Auth
{
    private static $instance = null;
    private $db;
    private $currentUser = null;
    private $sessionKey = 'halqat_user';

    private function __construct()
    {
        $this->db = Database::getInstance();
        $this->checkSession();
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * تسجيل دخول المبرمج
     */
    public function loginProgrammer($username, $password)
    {
        try {
            // التحقق من معدل المحاولات
            $clientId = $this->getClientIdentifier();
            if (!Security::checkRateLimit($clientId, 5, 300)) { // 5 محاولات كل 5 دقائق
                Logger::warning('Login rate limit exceeded', ['client_id' => $clientId]);
                return [
                    'success' => false,
                    'message' => 'تم تجاوز عدد المحاولات المسموح. يرجى المحاولة بعد 5 دقائق.'
                ];
            }

            // تنظيف البيانات المدخلة
            $username = Security::sanitizeInput($username);
            $password = trim($password);

            if (empty($username) || empty($password)) {
                return [
                    'success' => false,
                    'message' => 'يرجى إدخال اسم المستخدم وكلمة المرور'
                ];
            }

            // البحث عن المستخدم
            $user = $this->db->selectOne(
                "SELECT * FROM users WHERE username = ? AND role = 'مبرمج' AND is_active = 1",
                [$username]
            );

            if (!$user) {
                Logger::warning('Failed programmer login attempt', [
                    'username' => $username,
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
                return [
                    'success' => false,
                    'message' => 'اسم المستخدم أو كلمة المرور غير صحيحة'
                ];
            }

            // التحقق من كلمة المرور
            if (!Security::verifyPassword($password, $user['password'])) {
                Logger::warning('Failed programmer login attempt - wrong password', [
                    'username' => $username,
                    'user_id' => $user['id'],
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
                return [
                    'success' => false,
                    'message' => 'اسم المستخدم أو كلمة المرور غير صحيحة'
                ];
            }

            // تسجيل الدخول بنجاح
            return $this->createSession($user);

        } catch (Exception $e) {
            Logger::error('Login error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'حدث خطأ أثناء تسجيل الدخول'
            ];
        }
    }

    /**
     * تسجيل دخول الموظفين (مشرف/معلم)
     */
    public function loginStaff($personalCode, $role)
    {
        try {
            // التحقق من معدل المحاولات
            $clientId = $this->getClientIdentifier();
            if (!Security::checkRateLimit($clientId, 5, 300)) {
                Logger::warning('Login rate limit exceeded', ['client_id' => $clientId]);
                return [
                    'success' => false,
                    'message' => 'تم تجاوز عدد المحاولات المسموح. يرجى المحاولة بعد 5 دقائق.'
                ];
            }

            // تنظيف البيانات المدخلة
            $personalCode = Security::sanitizeInput($personalCode, 'int');
            $role = Security::sanitizeInput($role);

            if (empty($personalCode) || !in_array($role, ['مشرف', 'معلم'])) {
                return [
                    'success' => false,
                    'message' => 'بيانات غير صحيحة'
                ];
            }

            // التحقق من صحة الرمز الشخصي (4 أرقام)
            if (!preg_match('/^\d{4}$/', $personalCode)) {
                return [
                    'success' => false,
                    'message' => 'الرمز الشخصي يجب أن يكون مكون من 4 أرقام'
                ];
            }

            // البحث عن المستخدم
            $user = $this->db->selectOne(
                "SELECT * FROM users WHERE personal_code = ? AND role = ? AND is_active = 1",
                [$personalCode, $role]
            );

            if (!$user) {
                Logger::warning('Failed staff login attempt', [
                    'personal_code' => $personalCode,
                    'role' => $role,
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
                return [
                    'success' => false,
                    'message' => 'الرمز الشخصي غير صحيح أو غير مفعل'
                ];
            }

            // تسجيل الدخول بنجاح
            return $this->createSession($user);

        } catch (Exception $e) {
            Logger::error('Staff login error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'حدث خطأ أثناء تسجيل الدخول'
            ];
        }
    }

    /**
     * إنشاء جلسة المستخدم
     */
    private function createSession($user)
    {
        try {
            // تحديث آخر تسجيل دخول
            $this->db->update(
                "UPDATE users SET last_login = NOW(), login_count = login_count + 1 WHERE id = ?",
                [$user['id']]
            );

            // إنشاء الجلسة
            $_SESSION[$this->sessionKey] = [
                'id' => $user['id'],
                'username' => $user['username'],
                'full_name' => $user['full_name'],
                'role' => $user['role'],
                'permissions' => $this->getUserPermissions($user['role']),
                'login_time' => time(),
                'last_activity' => time(),
                'session_token' => Security::generateSecureToken()
            ];

            $this->currentUser = $_SESSION[$this->sessionKey];

            Logger::info('User logged in successfully', [
                'user_id' => $user['id'],
                'username' => $user['username'],
                'role' => $user['role'],
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);

            return [
                'success' => true,
                'message' => 'تم تسجيل الدخول بنجاح',
                'user' => $this->currentUser
            ];

        } catch (Exception $e) {
            Logger::error('Session creation error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'حدث خطأ أثناء إنشاء الجلسة'
            ];
        }
    }

    /**
     * التحقق من الجلسة الحالية
     */
    public function checkSession()
    {
        if (!isset($_SESSION[$this->sessionKey])) {
            return false;
        }

        $session = $_SESSION[$this->sessionKey];

        // التحقق من انتهاء صلاحية الجلسة
        if (time() - $session['last_activity'] > SESSION_TIMEOUT) {
            $this->logout();
            return false;
        }

        // تحديث آخر نشاط
        $_SESSION[$this->sessionKey]['last_activity'] = time();
        $this->currentUser = $_SESSION[$this->sessionKey];

        return true;
    }

    /**
     * تسجيل الخروج
     */
    public function logout()
    {
        if ($this->currentUser) {
            Logger::info('User logged out', [
                'user_id' => $this->currentUser['id'],
                'username' => $this->currentUser['username']
            ]);
        }

        unset($_SESSION[$this->sessionKey]);
        $this->currentUser = null;

        // تدمير الجلسة إذا كانت فارغة
        if (empty($_SESSION)) {
            session_destroy();
        }

        return true;
    }

    /**
     * الحصول على المستخدم الحالي
     */
    public function getCurrentUser()
    {
        return $this->currentUser;
    }

    /**
     * التحقق من تسجيل الدخول
     */
    public function isLoggedIn()
    {
        return $this->currentUser !== null;
    }

    /**
     * التحقق من الصلاحية
     */
    public function hasPermission($permission)
    {
        if (!$this->isLoggedIn()) {
            return false;
        }

        return in_array($permission, $this->currentUser['permissions']);
    }

    /**
     * التحقق من الدور
     */
    public function hasRole($role)
    {
        if (!$this->isLoggedIn()) {
            return false;
        }

        return $this->currentUser['role'] === $role;
    }

    /**
     * الحصول على صلاحيات المستخدم
     */
    private function getUserPermissions($role)
    {
        $roles = USER_ROLES;
        return $roles[$role] ?? [];
    }

    /**
     * طلب تسجيل الدخول
     */
    public function requireLogin()
    {
        if (!$this->isLoggedIn()) {
            if ($this->isAjaxRequest()) {
                http_response_code(401);
                echo json_encode([
                    'success' => false,
                    'message' => 'يجب تسجيل الدخول أولاً',
                    'redirect' => 'login.php'
                ]);
                exit;
            } else {
                header('Location: login.php');
                exit;
            }
        }

        return $this;
    }

    /**
     * طلب صلاحية معينة
     */
    public function requirePermission($permission)
    {
        $this->requireLogin();

        if (!$this->hasPermission($permission)) {
            if ($this->isAjaxRequest()) {
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'message' => 'ليس لديك صلاحية للوصول إلى هذه الصفحة'
                ]);
                exit;
            } else {
                header('Location: dashboard.php?error=no_permission');
                exit;
            }
        }

        return $this;
    }

    /**
     * الحصول على معرف العميل
     */
    private function getClientIdentifier()
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        return md5($ip . $userAgent);
    }

    /**
     * التحقق من طلب AJAX
     */
    private function isAjaxRequest()
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * تغيير كلمة المرور
     */
    public function changePassword($currentPassword, $newPassword)
    {
        if (!$this->isLoggedIn()) {
            return [
                'success' => false,
                'message' => 'يجب تسجيل الدخول أولاً'
            ];
        }

        try {
            // الحصول على بيانات المستخدم الحالية
            $user = $this->db->selectOne(
                "SELECT password FROM users WHERE id = ?",
                [$this->currentUser['id']]
            );

            // التحقق من كلمة المرور الحالية
            if (!Security::verifyPassword($currentPassword, $user['password'])) {
                return [
                    'success' => false,
                    'message' => 'كلمة المرور الحالية غير صحيحة'
                ];
            }

            // التحقق من قوة كلمة المرور الجديدة
            $strength = Security::checkPasswordStrength($newPassword);
            if ($strength['score'] < 3) {
                return [
                    'success' => false,
                    'message' => 'كلمة المرور ضعيفة. ' . implode(', ', $strength['feedback'])
                ];
            }

            // تحديث كلمة المرور
            $hashedPassword = Security::hashPassword($newPassword);
            $this->db->update(
                "UPDATE users SET password = ?, password_changed_at = NOW() WHERE id = ?",
                [$hashedPassword, $this->currentUser['id']]
            );

            Logger::info('Password changed successfully', [
                'user_id' => $this->currentUser['id']
            ]);

            return [
                'success' => true,
                'message' => 'تم تغيير كلمة المرور بنجاح'
            ];

        } catch (Exception $e) {
            Logger::error('Password change error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'حدث خطأ أثناء تغيير كلمة المرور'
            ];
        }
    }
}

