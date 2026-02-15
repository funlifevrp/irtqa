<?php
/**
 * صفحة إدارة المستخدمين
 * Halqat Management System v3.0
 */

// تحميل الإعدادات
require_once __DIR__ . '/config/config.php';

// التحقق من تسجيل الدخول والصلاحيات
$auth = Auth::getInstance();
$auth->requireLogin();
$auth->requirePermission('manage_users');

$currentUser = $auth->getCurrentUser();
$db = Database::getInstance();

$error = '';
$success = '';
$action = $_GET['action'] ?? 'list';
$userId = (int) ($_GET['id'] ?? 0);

// معالجة العمليات
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // التحقق من رمز CSRF
        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!Security::verifyCSRFToken($csrfToken, 'users')) {
            throw new Exception('رمز الأمان غير صحيح');
        }

        $postAction = $_POST['action'] ?? '';

        if ($postAction === 'add') {
            // إضافة مستخدم جديد
            $userData = [
                'username' => Security::sanitizeInput($_POST['username'] ?? ''),
                'full_name' => Security::sanitizeInput($_POST['full_name'] ?? ''),
                'role' => Security::sanitizeInput($_POST['role'] ?? ''),
                'personal_code' => Security::sanitizeInput($_POST['personal_code'] ?? '', 'int'),
                'phone' => Security::sanitizeInput($_POST['phone'] ?? ''),
                'email' => Security::sanitizeInput($_POST['email'] ?? ''),
                'is_active' => isset($_POST['is_active']) ? 1 : 0
            ];

            // التحقق من صحة البيانات
            if (empty($userData['username']) || empty($userData['full_name']) || empty($userData['role'])) {
                throw new Exception('جميع الحقول المطلوبة يجب ملؤها');
            }

            // التحقق من عدم تكرار اسم المستخدم
            $existingUser = $db->selectOne("SELECT id FROM users WHERE username = ?", [$userData['username']]);
            if ($existingUser) {
                throw new Exception('اسم المستخدم موجود مسبقاً');
            }

            // التحقق من عدم تكرار الرمز الشخصي للموظفين
            if ($userData['role'] !== 'مبرمج' && $userData['personal_code']) {
                $existingCode = $db->selectOne("SELECT id FROM users WHERE personal_code = ? AND role = ?", 
                    [$userData['personal_code'], $userData['role']]);
                if ($existingCode) {
                    throw new Exception('الرمز الشخصي موجود مسبقاً لهذا الدور');
                }
            }

            // تشفير كلمة المرور للمبرمج
            if ($userData['role'] === 'مبرمج') {
                $password = $_POST['password'] ?? '';
                if (empty($password)) {
                    throw new Exception('كلمة المرور مطلوبة للمبرمج');
                }
                $userData['password'] = password_hash($password, PASSWORD_DEFAULT);
            }

            // إضافة المستخدم
            $userData['created_by'] = $currentUser['id'];
            $newUserId = $db->insert('users', $userData);

            // تسجيل العملية
            $db->insert('audit_log', [
                'user_id' => $currentUser['id'],
                'action' => 'إضافة مستخدم جديد',
                'table_name' => 'users',
                'record_id' => $newUserId,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
            ]);

            $success = 'تم إضافة المستخدم بنجاح';

        } elseif ($postAction === 'edit' && $userId > 0) {
            // تعديل مستخدم
            $userData = [
                'full_name' => Security::sanitizeInput($_POST['full_name'] ?? ''),
                'role' => Security::sanitizeInput($_POST['role'] ?? ''),
                'personal_code' => Security::sanitizeInput($_POST['personal_code'] ?? '', 'int'),
                'phone' => Security::sanitizeInput($_POST['phone'] ?? ''),
                'email' => Security::sanitizeInput($_POST['email'] ?? ''),
                'is_active' => isset($_POST['is_active']) ? 1 : 0
            ];

            // التحقق من صحة البيانات
            if (empty($userData['full_name']) || empty($userData['role'])) {
                throw new Exception('جميع الحقول المطلوبة يجب ملؤها');
            }

            // تحديث كلمة المرور إذا تم إدخالها
            $password = $_POST['password'] ?? '';
            if (!empty($password) && $userData['role'] === 'مبرمج') {
                $userData['password'] = password_hash($password, PASSWORD_DEFAULT);
            }

            $userData['updated_by'] = $currentUser['id'];
            $userData['updated_at'] = date('Y-m-d H:i:s');

            $db->update('users', $userData, ['id' => $userId]);

            // تسجيل العملية
            $db->insert('audit_log', [
                'user_id' => $currentUser['id'],
                'action' => 'تعديل مستخدم',
                'table_name' => 'users',
                'record_id' => $userId,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
            ]);

            $success = 'تم تعديل المستخدم بنجاح';

        } elseif ($postAction === 'delete' && $userId > 0) {
            // حذف مستخدم (إلغاء تفعيل)
            $db->update('users', [
                'is_active' => 0,
                'updated_by' => $currentUser['id'],
                'updated_at' => date('Y-m-d H:i:s')
            ], ['id' => $userId]);

            // تسجيل العملية
            $db->insert('audit_log', [
                'user_id' => $currentUser['id'],
                'action' => 'إلغاء تفعيل مستخدم',
                'table_name' => 'users',
                'record_id' => $userId,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
            ]);

            $success = 'تم إلغاء تفعيل المستخدم بنجاح';
        }

    } catch (Exception $e) {
        $error = $e->getMessage();
        Logger::error('Users page error: ' . $e->getMessage());
    }
}

// الحصول على قائمة المستخدمين
$searchTerm = $_GET['search'] ?? '';
$roleFilter = $_GET['role'] ?? '';
$statusFilter = $_GET['status'] ?? '';

$whereConditions = [];
$params = [];

if (!empty($searchTerm)) {
    $whereConditions[] = "(full_name LIKE ? OR username LIKE ? OR phone LIKE ?)";
    $searchParam = '%' . $searchTerm . '%';
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
}

if (!empty($roleFilter)) {
    $whereConditions[] = "role = ?";
    $params[] = $roleFilter;
}

if ($statusFilter !== '') {
    $whereConditions[] = "is_active = ?";
    $params[] = (int) $statusFilter;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

$users = $db->select("
    SELECT u.*
    FROM users u
    $whereClause
    ORDER BY u.created_at DESC
", $params);

// الحصول على بيانات المستخدم للتعديل
$editUser = null;
if ($action === 'edit' && $userId > 0) {
    $editUser = $db->selectOne("SELECT * FROM users WHERE id = ?", [$userId]);
    if (!$editUser) {
        $error = 'المستخدم غير موجود';
        $action = 'list';
    }
}

// توليد رمز CSRF
$csrfToken = Security::generateCSRFToken('users');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة المستخدمين - <?php echo SYSTEM_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            border-radius: 0 0 20px 20px;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
        }

        .card-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-bottom: 1px solid #dee2e6;
            border-radius: 15px 15px 0 0 !important;
            padding: 1.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 10px 20px;
            font-weight: 500;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }

        .table {
            border-radius: 10px;
            overflow: hidden;
        }

        .table thead th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            font-weight: 600;
            padding: 1rem;
        }

        .table tbody td {
            padding: 1rem;
            vertical-align: middle;
            border-bottom: 1px solid #f1f3f4;
        }

        .table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 500;
        }

        .form-control, .form-select {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .search-filters {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            margin-left: 10px;
        }

        .action-buttons .btn {
            margin: 2px;
            border-radius: 8px;
            padding: 5px 10px;
        }

        .modal-content {
            border-radius: 15px;
            border: none;
        }

        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0;
        }

        @media (max-width: 768px) {
            .table-responsive {
                border-radius: 10px;
            }
            
            .search-filters {
                padding: 1rem;
            }
            
            .action-buttons {
                display: flex;
                flex-direction: column;
                gap: 5px;
            }
        }
    </style>
</head>
<body>
    <!-- رأس الصفحة -->
    <div class="page-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h1 class="mb-0">
                        <i class="bi bi-people-fill me-3"></i>
                        إدارة المستخدمين
                    </h1>
                    <p class="mb-0 mt-2 opacity-75">إدارة حسابات المستخدمين والصلاحيات</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <a href="dashboard.php" class="btn btn-light">
                        <i class="bi bi-arrow-right me-2"></i>
                        العودة للوحة التحكم
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- رسائل التنبيه -->
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>
                <?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($action === 'list'): ?>
            <!-- فلاتر البحث -->
            <div class="search-filters">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">البحث</label>
                        <input type="text" class="form-control" name="search" 
                               value="<?php echo htmlspecialchars($searchTerm); ?>" 
                               placeholder="البحث بالاسم أو اسم المستخدم أو الهاتف">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">الدور</label>
                        <select class="form-select" name="role">
                            <option value="">جميع الأدوار</option>
                            <option value="مبرمج" <?php echo $roleFilter === 'مبرمج' ? 'selected' : ''; ?>>مبرمج</option>
                            <option value="مشرف" <?php echo $roleFilter === 'مشرف' ? 'selected' : ''; ?>>مشرف</option>
                            <option value="معلم" <?php echo $roleFilter === 'معلم' ? 'selected' : ''; ?>>معلم</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">الحالة</label>
                        <select class="form-select" name="status">
                            <option value="">جميع الحالات</option>
                            <option value="1" <?php echo $statusFilter === '1' ? 'selected' : ''; ?>>نشط</option>
                            <option value="0" <?php echo $statusFilter === '0' ? 'selected' : ''; ?>>غير نشط</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-search me-1"></i>
                                بحث
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- قائمة المستخدمين -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-list-ul me-2"></i>
                        قائمة المستخدمين (<?php echo count($users); ?>)
                    </h5>
                    <a href="?action=add" class="btn btn-primary">
                        <i class="bi bi-plus-lg me-2"></i>
                        إضافة مستخدم جديد
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>المستخدم</th>
                                    <th>الدور</th>
                                    <th>الرمز الشخصي</th>
                                    <th>الهاتف</th>
                                    <th>الحالة</th>
                                    <th>تاريخ الإنشاء</th>
                                    <th>الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($users)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4">
                                            <i class="bi bi-inbox display-4 text-muted"></i>
                                            <p class="text-muted mt-2">لا توجد مستخدمين</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="user-avatar">
                                                        <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                                                    </div>
                                                    <div>
                                                        <div class="fw-bold"><?php echo htmlspecialchars($user['full_name']); ?></div>
                                                        <small class="text-muted"><?php echo htmlspecialchars($user['username']); ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $user['role'] === 'مبرمج' ? 'danger' : 
                                                        ($user['role'] === 'مشرف' ? 'warning' : 'info'); 
                                                ?>">
                                                    <?php echo htmlspecialchars($user['role']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($user['personal_code']): ?>
                                                    <code><?php echo htmlspecialchars($user['personal_code']); ?></code>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($user['phone']): ?>
                                                    <a href="tel:<?php echo htmlspecialchars($user['phone']); ?>" class="text-decoration-none">
                                                        <?php echo htmlspecialchars($user['phone']); ?>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $user['is_active'] ? 'success' : 'secondary'; ?>">
                                                    <?php echo $user['is_active'] ? 'نشط' : 'غير نشط'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?php echo date('Y-m-d', strtotime($user['created_at'])); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <a href="?action=edit&id=<?php echo $user['id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary" title="تعديل">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <?php if ($user['is_active']): ?>
                                                        <button type="button" class="btn btn-sm btn-outline-danger" 
                                                                onclick="confirmDelete(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['full_name']); ?>')" 
                                                                title="إلغاء تفعيل">
                                                            <i class="bi bi-x-lg"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php elseif ($action === 'add' || $action === 'edit'): ?>
            <!-- نموذج إضافة/تعديل مستخدم -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-<?php echo $action === 'add' ? 'plus-lg' : 'pencil'; ?> me-2"></i>
                        <?php echo $action === 'add' ? 'إضافة مستخدم جديد' : 'تعديل المستخدم'; ?>
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" class="row g-3">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                        <input type="hidden" name="action" value="<?php echo $action; ?>">

                        <?php if ($action === 'add'): ?>
                            <div class="col-md-6">
                                <label class="form-label">اسم المستخدم <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="username" required 
                                       placeholder="أدخل اسم المستخدم">
                            </div>
                        <?php endif; ?>

                        <div class="col-md-6">
                            <label class="form-label">الاسم الكامل <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="full_name" required 
                                   value="<?php echo htmlspecialchars($editUser['full_name'] ?? ''); ?>"
                                   placeholder="أدخل الاسم الكامل">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">الدور <span class="text-danger">*</span></label>
                            <select class="form-select" name="role" required onchange="togglePasswordField(this.value)">
                                <option value="">اختر الدور</option>
                                <option value="مبرمج" <?php echo ($editUser['role'] ?? '') === 'مبرمج' ? 'selected' : ''; ?>>مبرمج</option>
                                <option value="مشرف" <?php echo ($editUser['role'] ?? '') === 'مشرف' ? 'selected' : ''; ?>>مشرف</option>
                                <option value="معلم" <?php echo ($editUser['role'] ?? '') === 'معلم' ? 'selected' : ''; ?>>معلم</option>
                            </select>
                        </div>

                        <div class="col-md-6" id="personal-code-field">
                            <label class="form-label">الرمز الشخصي</label>
                            <input type="text" class="form-control" name="personal_code" 
                                   value="<?php echo htmlspecialchars($editUser['personal_code'] ?? ''); ?>"
                                   placeholder="4 أرقام" maxlength="4" pattern="[0-9]{4}">
                            <small class="text-muted">مطلوب للمشرفين والمعلمين (4 أرقام)</small>
                        </div>

                        <div class="col-md-6" id="password-field" style="display: none;">
                            <label class="form-label">
                                كلمة المرور 
                                <?php if ($action === 'add'): ?>
                                    <span class="text-danger">*</span>
                                <?php endif; ?>
                            </label>
                            <input type="password" class="form-control" name="password" 
                                   placeholder="<?php echo $action === 'add' ? 'أدخل كلمة المرور' : 'اتركها فارغة إذا لم تريد تغييرها'; ?>">
                            <?php if ($action === 'edit'): ?>
                                <small class="text-muted">اتركها فارغة إذا لم تريد تغيير كلمة المرور</small>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">رقم الهاتف</label>
                            <input type="tel" class="form-control" name="phone" 
                                   value="<?php echo htmlspecialchars($editUser['phone'] ?? ''); ?>"
                                   placeholder="أدخل رقم الهاتف">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">البريد الإلكتروني</label>
                            <input type="email" class="form-control" name="email" 
                                   value="<?php echo htmlspecialchars($editUser['email'] ?? ''); ?>"
                                   placeholder="أدخل البريد الإلكتروني">
                        </div>

                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_active" id="is_active" 
                                       <?php echo ($editUser['is_active'] ?? 1) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_active">
                                    المستخدم نشط
                                </label>
                            </div>
                        </div>

                        <div class="col-12">
                            <hr>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-lg me-2"></i>
                                    <?php echo $action === 'add' ? 'إضافة المستخدم' : 'حفظ التغييرات'; ?>
                                </button>
                                <a href="users.php" class="btn btn-secondary">
                                    <i class="bi bi-x-lg me-2"></i>
                                    إلغاء
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- نموذج تأكيد الحذف -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">تأكيد إلغاء التفعيل</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>هل أنت متأكد من إلغاء تفعيل المستخدم <strong id="deleteUserName"></strong>؟</p>
                    <p class="text-muted">سيتم إلغاء تفعيل المستخدم ولن يتمكن من تسجيل الدخول.</p>
                </div>
                <div class="modal-footer">
                    <form method="POST" id="deleteForm">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                        <input type="hidden" name="action" value="delete">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-danger">إلغاء التفعيل</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // إظهار/إخفاء حقل كلمة المرور حسب الدور
        function togglePasswordField(role) {
            const passwordField = document.getElementById('password-field');
            const personalCodeField = document.getElementById('personal-code-field');
            
            if (role === 'مبرمج') {
                passwordField.style.display = 'block';
                personalCodeField.style.display = 'none';
                document.querySelector('input[name="password"]').required = <?php echo $action === 'add' ? 'true' : 'false'; ?>;
                document.querySelector('input[name="personal_code"]').required = false;
            } else {
                passwordField.style.display = 'none';
                personalCodeField.style.display = 'block';
                document.querySelector('input[name="password"]').required = false;
                document.querySelector('input[name="personal_code"]').required = (role !== '');
            }
        }

        // تأكيد الحذف
        function confirmDelete(userId, userName) {
            document.getElementById('deleteUserName').textContent = userName;
            document.getElementById('deleteForm').action = '?action=delete&id=' + userId;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }

        // تهيئة الصفحة
        document.addEventListener('DOMContentLoaded', function() {
            // تفعيل حقل كلمة المرور حسب الدور المحدد
            const roleSelect = document.querySelector('select[name="role"]');
            if (roleSelect) {
                togglePasswordField(roleSelect.value);
            }

            // التحقق من صحة الرمز الشخصي
            const personalCodeInput = document.querySelector('input[name="personal_code"]');
            if (personalCodeInput) {
                personalCodeInput.addEventListener('input', function() {
                    this.value = this.value.replace(/[^0-9]/g, '');
                    if (this.value.length > 4) {
                        this.value = this.value.slice(0, 4);
                    }
                });
            }

            // إخفاء رسائل التنبيه بعد 5 ثوان
            setTimeout(function() {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    if (alert.classList.contains('alert-success')) {
                        alert.style.transition = 'opacity 0.5s ease';
                        alert.style.opacity = '0';
                        setTimeout(() => alert.remove(), 500);
                    }
                });
            }, 5000);
        });
    </script>
</body>
</html>

