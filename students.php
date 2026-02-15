<?php
/**
 * صفحة إدارة الطلاب
 * تتضمن إضافة وتعديل وحذف الطلاب مع جميع الميزات المتقدمة
 */

// تضمين ملف الإعدادات
require_once __DIR__ . '/config/config.php';

// التحقق من تسجيل الدخول
Auth::requireLogin();

// التحقق من الصلاحيات
Auth::requirePermission('manage_students');

// معالجة العمليات
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        // حماية CSRF
        Security::validateCSRFToken($_POST['csrf_token'] ?? '');
        
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'add_student':
                // إضافة طالب جديد
                $name = Security::sanitizeInput($_POST['name']);
                $personal_id = Security::sanitizeInput($_POST['personal_id']);
                $birth_date = Security::sanitizeInput($_POST['birth_date']);
                $gender = Security::sanitizeInput($_POST['gender']);
                $halqa_id = (int)$_POST['halqa_id'];
                $course_id = (int)$_POST['course_id'];
                $phone = Security::sanitizeInput($_POST['phone'] ?? '');
                $email = Security::sanitizeInput($_POST['email'] ?? '');
                $address = Security::sanitizeInput($_POST['address'] ?? '');
                $guardian_name = Security::sanitizeInput($_POST['guardian_name'] ?? '');
                $guardian_phone = Security::sanitizeInput($_POST['guardian_phone'] ?? '');
                $national_id = Security::sanitizeInput($_POST['national_id'] ?? '');
                $bank_account = Security::sanitizeInput($_POST['bank_account'] ?? '');
                $iban = Security::sanitizeInput($_POST['iban'] ?? '');
                $bank_name = Security::sanitizeInput($_POST['bank_name'] ?? '');
                
                // التحقق من عدم تكرار الرقم الشخصي
                $stmt = $conn->prepare("SELECT id FROM students WHERE personal_id = ?");
                $stmt->bind_param("s", $personal_id);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    throw new Exception("الرقم الشخصي موجود مسبقاً");
                }
                
                // إدراج الطالب الجديد
                $stmt = $conn->prepare("
                    INSERT INTO students (
                        name, personal_id, birth_date, gender, halqa_id, course_id,
                        phone, email, address, guardian_name, guardian_phone,
                        national_id, bank_account, iban, bank_name, status, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
                ");
                
                $stmt->bind_param("ssssiissssssss", 
                    $name, $personal_id, $birth_date, $gender, $halqa_id, $course_id,
                    $phone, $email, $address, $guardian_name, $guardian_phone,
                    $national_id, $bank_account, $iban, $bank_name
                );
                
                if ($stmt->execute()) {
                    $student_id = $conn->insert_id;
                    
                    // تسجيل العملية في السجل
                    Logger::log('info', "تم إضافة طالب جديد: $name (ID: $student_id)", [
                        'user_id' => Auth::getCurrentUser()['id'],
                        'student_id' => $student_id,
                        'action' => 'add_student'
                    ]);
                    
                    $message = "تم إضافة الطالب بنجاح";
                    $messageType = "success";
                } else {
                    throw new Exception("فشل في إضافة الطالب");
                }
                break;
                
            case 'edit_student':
                // تعديل بيانات طالب
                $student_id = (int)$_POST['student_id'];
                $name = Security::sanitizeInput($_POST['name']);
                $personal_id = Security::sanitizeInput($_POST['personal_id']);
                $birth_date = Security::sanitizeInput($_POST['birth_date']);
                $gender = Security::sanitizeInput($_POST['gender']);
                $halqa_id = (int)$_POST['halqa_id'];
                $course_id = (int)$_POST['course_id'];
                $phone = Security::sanitizeInput($_POST['phone'] ?? '');
                $email = Security::sanitizeInput($_POST['email'] ?? '');
                $address = Security::sanitizeInput($_POST['address'] ?? '');
                $guardian_name = Security::sanitizeInput($_POST['guardian_name'] ?? '');
                $guardian_phone = Security::sanitizeInput($_POST['guardian_phone'] ?? '');
                $national_id = Security::sanitizeInput($_POST['national_id'] ?? '');
                $bank_account = Security::sanitizeInput($_POST['bank_account'] ?? '');
                $iban = Security::sanitizeInput($_POST['iban'] ?? '');
                $bank_name = Security::sanitizeInput($_POST['bank_name'] ?? '');
                $status = Security::sanitizeInput($_POST['status']);
                
                // التحقق من عدم تكرار الرقم الشخصي (باستثناء الطالب الحالي)
                $stmt = $conn->prepare("SELECT id FROM students WHERE personal_id = ? AND id != ?");
                $stmt->bind_param("si", $personal_id, $student_id);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    throw new Exception("الرقم الشخصي موجود مسبقاً");
                }
                
                // تحديث بيانات الطالب
                $stmt = $conn->prepare("
                    UPDATE students SET 
                        name = ?, personal_id = ?, birth_date = ?, gender = ?, 
                        halqa_id = ?, course_id = ?, phone = ?, email = ?, 
                        address = ?, guardian_name = ?, guardian_phone = ?,
                        national_id = ?, bank_account = ?, iban = ?, bank_name = ?,
                        status = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                
                $stmt->bind_param("ssssiissssssssssi", 
                    $name, $personal_id, $birth_date, $gender, $halqa_id, $course_id,
                    $phone, $email, $address, $guardian_name, $guardian_phone,
                    $national_id, $bank_account, $iban, $bank_name, $status, $student_id
                );
                
                if ($stmt->execute()) {
                    // تسجيل العملية في السجل
                    Logger::log('info', "تم تعديل بيانات الطالب: $name (ID: $student_id)", [
                        'user_id' => Auth::getCurrentUser()['id'],
                        'student_id' => $student_id,
                        'action' => 'edit_student'
                    ]);
                    
                    $message = "تم تحديث بيانات الطالب بنجاح";
                    $messageType = "success";
                } else {
                    throw new Exception("فشل في تحديث بيانات الطالب");
                }
                break;
                
            case 'delete_student':
                // حذف طالب (تعطيل فقط)
                $student_id = (int)$_POST['student_id'];
                
                // الحصول على اسم الطالب للسجل
                $stmt = $conn->prepare("SELECT name FROM students WHERE id = ?");
                $stmt->bind_param("i", $student_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $student = $result->fetch_assoc();
                
                if (!$student) {
                    throw new Exception("الطالب غير موجود");
                }
                
                // تعطيل الطالب بدلاً من الحذف
                $stmt = $conn->prepare("UPDATE students SET status = 'inactive', updated_at = NOW() WHERE id = ?");
                $stmt->bind_param("i", $student_id);
                
                if ($stmt->execute()) {
                    // تسجيل العملية في السجل
                    Logger::log('warning', "تم تعطيل الطالب: {$student['name']} (ID: $student_id)", [
                        'user_id' => Auth::getCurrentUser()['id'],
                        'student_id' => $student_id,
                        'action' => 'delete_student'
                    ]);
                    
                    $message = "تم تعطيل الطالب بنجاح";
                    $messageType = "success";
                } else {
                    throw new Exception("فشل في تعطيل الطالب");
                }
                break;
        }
        
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = "error";
        
        // تسجيل الخطأ
        Logger::log('error', "خطأ في إدارة الطلاب: " . $e->getMessage(), [
            'user_id' => Auth::getCurrentUser()['id'],
            'action' => $action ?? 'unknown',
            'error' => $e->getMessage()
        ]);
    }
}

// جلب البيانات للعرض
try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // معايير البحث والتصفية
    $search = Security::sanitizeInput($_GET['search'] ?? '');
    $halqa_filter = (int)($_GET['halqa_filter'] ?? 0);
    $status_filter = Security::sanitizeInput($_GET['status_filter'] ?? '');
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = 20;
    $offset = ($page - 1) * $limit;
    
    // بناء استعلام البحث
    $where_conditions = [];
    $params = [];
    $types = '';
    
    if (!empty($search)) {
        $where_conditions[] = "(s.name LIKE ? OR s.personal_id LIKE ? OR s.phone LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $types .= 'sss';
    }
    
    if ($halqa_filter > 0) {
        $where_conditions[] = "s.halqa_id = ?";
        $params[] = $halqa_filter;
        $types .= 'i';
    }
    
    if (!empty($status_filter)) {
        $where_conditions[] = "s.status = ?";
        $params[] = $status_filter;
        $types .= 's';
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // جلب الطلاب
    $sql = "
        SELECT s.*, h.name as halqa_name, c.name as course_name
        FROM students s
        LEFT JOIN halaqat h ON s.halqa_id = h.id
        LEFT JOIN courses c ON s.course_id = c.id
        $where_clause
        ORDER BY s.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';
        $stmt->bind_param($types, ...$params);
    } else {
        $stmt->bind_param('ii', $limit, $offset);
    }
    
    $stmt->execute();
    $students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // عدد الطلاب الإجمالي
    $count_sql = "
        SELECT COUNT(*) as total
        FROM students s
        LEFT JOIN halaqat h ON s.halqa_id = h.id
        LEFT JOIN courses c ON s.course_id = c.id
        $where_clause
    ";
    
    $stmt = $conn->prepare($count_sql);
    if (!empty($where_conditions)) {
        $count_params = array_slice($params, 0, -2); // إزالة limit و offset
        $count_types = substr($types, 0, -2);
        $stmt->bind_param($count_types, ...$count_params);
    }
    
    $stmt->execute();
    $total_students = $stmt->get_result()->fetch_assoc()['total'];
    $total_pages = ceil($total_students / $limit);
    
    // جلب الحلقات للتصفية والإضافة
    $halaqat = $conn->query("SELECT id, name FROM halaqat WHERE status = 'active' ORDER BY name")->fetch_all(MYSQLI_ASSOC);
    
    // جلب المقررات للإضافة
    $courses = $conn->query("SELECT id, name FROM courses WHERE status = 'active' ORDER BY name")->fetch_all(MYSQLI_ASSOC);
    
    // إحصائيات سريعة
    $stats = [
        'total' => $conn->query("SELECT COUNT(*) as count FROM students")->fetch_assoc()['count'],
        'active' => $conn->query("SELECT COUNT(*) as count FROM students WHERE status = 'active'")->fetch_assoc()['count'],
        'inactive' => $conn->query("SELECT COUNT(*) as count FROM students WHERE status = 'inactive'")->fetch_assoc()['count'],
        'male' => $conn->query("SELECT COUNT(*) as count FROM students WHERE gender = 'male' AND status = 'active'")->fetch_assoc()['count'],
        'female' => $conn->query("SELECT COUNT(*) as count FROM students WHERE gender = 'female' AND status = 'active'")->fetch_assoc()['count']
    ];
    
} catch (Exception $e) {
    $message = "خطأ في جلب البيانات: " . $e->getMessage();
    $messageType = "error";
    $students = [];
    $halaqat = [];
    $courses = [];
    $stats = ['total' => 0, 'active' => 0, 'inactive' => 0, 'male' => 0, 'female' => 0];
    $total_pages = 1;
}

$currentUser = Auth::getCurrentUser();
$pageTitle = "إدارة الطلاب";
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - نظام إدارة الحلقات</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
        }
        
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 2px 4px rgba(0,0,0,.1);
        }
        
        .sidebar {
            background: #fff;
            box-shadow: 2px 0 5px rgba(0,0,0,.1);
            min-height: calc(100vh - 76px);
        }
        
        .sidebar .nav-link {
            color: #495057;
            padding: 12px 20px;
            border-radius: 8px;
            margin: 2px 10px;
            transition: all 0.3s ease;
        }
        
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            transform: translateX(5px);
        }
        
        .main-content {
            padding: 20px;
        }
        
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
            transition: transform 0.3s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
        }
        
        .stats-card .icon {
            font-size: 2.5rem;
            opacity: 0.8;
        }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,.1);
            transition: transform 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-2px);
        }
        
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 15px 20px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 25px;
            padding: 10px 25px;
            transition: all 0.3s ease;
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
        }
        
        .table tbody tr {
            transition: all 0.3s ease;
        }
        
        .table tbody tr:hover {
            background-color: #f8f9ff;
            transform: scale(1.01);
        }
        
        .badge {
            border-radius: 20px;
            padding: 8px 15px;
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
        
        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .alert {
            border-radius: 10px;
            border: none;
        }
        
        .pagination .page-link {
            border-radius: 10px;
            margin: 0 2px;
            border: none;
            color: #667eea;
        }
        
        .pagination .page-item.active .page-link {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
        
        .search-box {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,.1);
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                top: 76px;
                left: -250px;
                width: 250px;
                height: calc(100vh - 76px);
                z-index: 1000;
                transition: left 0.3s ease;
            }
            
            .sidebar.show {
                left: 0;
            }
            
            .main-content {
                margin-right: 0;
            }
        }
    </style>
</head>
<body>
    <!-- شريط التنقل العلوي -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <button class="navbar-toggler d-lg-none" type="button" onclick="toggleSidebar()">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-graduation-cap me-2"></i>
                نظام إدارة الحلقات
            </a>
            
            <div class="navbar-nav ms-auto">
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle me-1"></i>
                        <?php echo htmlspecialchars($currentUser['full_name']); ?>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>لوحة التحكم</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>تسجيل الخروج</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- القائمة الجانبية -->
            <nav class="col-lg-2 sidebar" id="sidebar">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i>
                                لوحة التحكم
                            </a>
                        </li>
                        
                        <?php if (Auth::hasPermission('manage_students')): ?>
                        <li class="nav-item">
                            <a class="nav-link active" href="students.php">
                                <i class="fas fa-user-graduate me-2"></i>
                                إدارة الطلاب
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if (Auth::hasPermission('manage_users')): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="users.php">
                                <i class="fas fa-users me-2"></i>
                                إدارة المستخدمين
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if (Auth::hasPermission('manage_halaqat')): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="halaqat.php">
                                <i class="fas fa-chalkboard-teacher me-2"></i>
                                إدارة الحلقات
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if (Auth::hasPermission('manage_courses')): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="courses.php">
                                <i class="fas fa-book me-2"></i>
                                إدارة المقررات
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if (Auth::hasPermission('manage_attendance')): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="attendance.php">
                                <i class="fas fa-calendar-check me-2"></i>
                                الحضور والغياب
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if (Auth::hasPermission('manage_grades')): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="grades.php">
                                <i class="fas fa-chart-line me-2"></i>
                                إدارة الدرجات
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </nav>

            <!-- المحتوى الرئيسي -->
            <main class="col-lg-10 ms-sm-auto main-content">
                <!-- رسائل التنبيه -->
                <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $messageType === 'error' ? 'danger' : $messageType; ?> alert-dismissible fade show" role="alert">
                    <i class="fas fa-<?php echo $messageType === 'error' ? 'exclamation-triangle' : ($messageType === 'success' ? 'check-circle' : 'info-circle'); ?> me-2"></i>
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- عنوان الصفحة -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0">
                        <i class="fas fa-user-graduate me-2"></i>
                        إدارة الطلاب
                    </h1>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStudentModal">
                        <i class="fas fa-plus me-2"></i>
                        إضافة طالب جديد
                    </button>
                </div>

                <!-- بطاقات الإحصائيات -->
                <div class="row mb-4">
                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                        <div class="stats-card text-center">
                            <div class="icon mb-2">
                                <i class="fas fa-users"></i>
                            </div>
                            <h3 class="mb-1"><?php echo number_format($stats['total']); ?></h3>
                            <p class="mb-0">إجمالي الطلاب</p>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                        <div class="stats-card text-center">
                            <div class="icon mb-2">
                                <i class="fas fa-user-check"></i>
                            </div>
                            <h3 class="mb-1"><?php echo number_format($stats['active']); ?></h3>
                            <p class="mb-0">طلاب نشطين</p>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                        <div class="stats-card text-center">
                            <div class="icon mb-2">
                                <i class="fas fa-user-times"></i>
                            </div>
                            <h3 class="mb-1"><?php echo number_format($stats['inactive']); ?></h3>
                            <p class="mb-0">طلاب معطلين</p>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                        <div class="stats-card text-center">
                            <div class="icon mb-2">
                                <i class="fas fa-male"></i>
                            </div>
                            <h3 class="mb-1"><?php echo number_format($stats['male']); ?></h3>
                            <p class="mb-0">طلاب ذكور</p>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                        <div class="stats-card text-center">
                            <div class="icon mb-2">
                                <i class="fas fa-female"></i>
                            </div>
                            <h3 class="mb-1"><?php echo number_format($stats['female']); ?></h3>
                            <p class="mb-0">طالبات إناث</p>
                        </div>
                    </div>
                </div>

                <!-- مربع البحث والتصفية -->
                <div class="search-box">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label for="search" class="form-label">البحث</label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   placeholder="ابحث بالاسم أو الرقم الشخصي أو الهاتف..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="halqa_filter" class="form-label">الحلقة</label>
                            <select class="form-select" id="halqa_filter" name="halqa_filter">
                                <option value="">جميع الحلقات</option>
                                <?php foreach ($halaqat as $halqa): ?>
                                <option value="<?php echo $halqa['id']; ?>" <?php echo $halqa_filter == $halqa['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($halqa['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="status_filter" class="form-label">الحالة</label>
                            <select class="form-select" id="status_filter" name="status_filter">
                                <option value="">جميع الحالات</option>
                                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>نشط</option>
                                <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>معطل</option>
                                <option value="graduated" <?php echo $status_filter === 'graduated' ? 'selected' : ''; ?>>متخرج</option>
                                <option value="transferred" <?php echo $status_filter === 'transferred' ? 'selected' : ''; ?>>منقول</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search me-2"></i>بحث
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- جدول الطلاب -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-list me-2"></i>
                            قائمة الطلاب (<?php echo number_format($total_students); ?> طالب)
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($students)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-user-graduate fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">لا توجد بيانات طلاب</h5>
                            <p class="text-muted">لم يتم العثور على أي طلاب مطابقين لمعايير البحث</p>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>الرقم الشخصي</th>
                                        <th>الاسم</th>
                                        <th>الجنس</th>
                                        <th>الحلقة</th>
                                        <th>المقرر</th>
                                        <th>الهاتف</th>
                                        <th>الحالة</th>
                                        <th>الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($students as $student): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($student['personal_id']); ?></strong>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-sm bg-primary rounded-circle d-flex align-items-center justify-content-center me-2">
                                                    <i class="fas fa-user text-white"></i>
                                                </div>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($student['name']); ?></strong>
                                                    <?php if (!empty($student['national_id'])): ?>
                                                    <br><small class="text-muted">هوية: <?php echo htmlspecialchars($student['national_id']); ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <i class="fas fa-<?php echo $student['gender'] === 'male' ? 'male text-primary' : 'female text-danger'; ?> me-1"></i>
                                            <?php echo $student['gender'] === 'male' ? 'ذكر' : 'أنثى'; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($student['halqa_name'])): ?>
                                            <span class="badge bg-info"><?php echo htmlspecialchars($student['halqa_name']); ?></span>
                                            <?php else: ?>
                                            <span class="text-muted">غير محدد</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($student['course_name'])): ?>
                                            <span class="badge bg-secondary"><?php echo htmlspecialchars($student['course_name']); ?></span>
                                            <?php else: ?>
                                            <span class="text-muted">غير محدد</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($student['phone'])): ?>
                                            <a href="tel:<?php echo htmlspecialchars($student['phone']); ?>" class="text-decoration-none">
                                                <i class="fas fa-phone me-1"></i>
                                                <?php echo htmlspecialchars($student['phone']); ?>
                                            </a>
                                            <?php else: ?>
                                            <span class="text-muted">غير محدد</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $statusClass = [
                                                'active' => 'success',
                                                'inactive' => 'danger',
                                                'graduated' => 'primary',
                                                'transferred' => 'warning'
                                            ];
                                            $statusText = [
                                                'active' => 'نشط',
                                                'inactive' => 'معطل',
                                                'graduated' => 'متخرج',
                                                'transferred' => 'منقول'
                                            ];
                                            ?>
                                            <span class="badge bg-<?php echo $statusClass[$student['status']] ?? 'secondary'; ?>">
                                                <?php echo $statusText[$student['status']] ?? $student['status']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button type="button" class="btn btn-sm btn-outline-primary" 
                                                        onclick="editStudent(<?php echo htmlspecialchars(json_encode($student)); ?>)"
                                                        title="تعديل">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-info" 
                                                        onclick="viewStudent(<?php echo htmlspecialchars(json_encode($student)); ?>)"
                                                        title="عرض التفاصيل">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <?php if ($student['status'] === 'active'): ?>
                                                <button type="button" class="btn btn-sm btn-outline-danger" 
                                                        onclick="deleteStudent(<?php echo $student['id']; ?>, '<?php echo htmlspecialchars($student['name']); ?>')"
                                                        title="تعطيل">
                                                    <i class="fas fa-user-times"></i>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- التنقل بين الصفحات -->
                        <?php if ($total_pages > 1): ?>
                        <nav aria-label="تنقل الصفحات" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- نافذة إضافة طالب جديد -->
    <div class="modal fade" id="addStudentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user-plus me-2"></i>
                        إضافة طالب جديد
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="addStudentForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_student">
                        <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                        
                        <div class="row">
                            <!-- البيانات الأساسية -->
                            <div class="col-12">
                                <h6 class="text-primary mb-3">
                                    <i class="fas fa-user me-2"></i>
                                    البيانات الأساسية
                                </h6>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="add_name" class="form-label">الاسم الكامل <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="add_name" name="name" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="add_personal_id" class="form-label">الرقم الشخصي <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="add_personal_id" name="personal_id" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="add_birth_date" class="form-label">تاريخ الميلاد <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="add_birth_date" name="birth_date" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="add_gender" class="form-label">الجنس <span class="text-danger">*</span></label>
                                <select class="form-select" id="add_gender" name="gender" required>
                                    <option value="">اختر الجنس</option>
                                    <option value="male">ذكر</option>
                                    <option value="female">أنثى</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="add_halqa_id" class="form-label">الحلقة <span class="text-danger">*</span></label>
                                <select class="form-select" id="add_halqa_id" name="halqa_id" required>
                                    <option value="">اختر الحلقة</option>
                                    <?php foreach ($halaqat as $halqa): ?>
                                    <option value="<?php echo $halqa['id']; ?>">
                                        <?php echo htmlspecialchars($halqa['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="add_course_id" class="form-label">المقرر <span class="text-danger">*</span></label>
                                <select class="form-select" id="add_course_id" name="course_id" required>
                                    <option value="">اختر المقرر</option>
                                    <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo $course['id']; ?>">
                                        <?php echo htmlspecialchars($course['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- معلومات الاتصال -->
                            <div class="col-12 mt-3">
                                <h6 class="text-primary mb-3">
                                    <i class="fas fa-address-book me-2"></i>
                                    معلومات الاتصال
                                </h6>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="add_phone" class="form-label">رقم الجوال</label>
                                <input type="tel" class="form-control" id="add_phone" name="phone">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="add_email" class="form-label">البريد الإلكتروني</label>
                                <input type="email" class="form-control" id="add_email" name="email">
                            </div>
                            
                            <div class="col-12 mb-3">
                                <label for="add_address" class="form-label">العنوان</label>
                                <textarea class="form-control" id="add_address" name="address" rows="2"></textarea>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="add_national_id" class="form-label">رقم الهوية الوطنية</label>
                                <input type="text" class="form-control" id="add_national_id" name="national_id">
                            </div>
                            
                            <!-- بيانات ولي الأمر -->
                            <div class="col-12 mt-3">
                                <h6 class="text-primary mb-3">
                                    <i class="fas fa-user-friends me-2"></i>
                                    بيانات ولي الأمر
                                </h6>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="add_guardian_name" class="form-label">اسم ولي الأمر</label>
                                <input type="text" class="form-control" id="add_guardian_name" name="guardian_name">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="add_guardian_phone" class="form-label">هاتف ولي الأمر</label>
                                <input type="tel" class="form-control" id="add_guardian_phone" name="guardian_phone">
                            </div>
                            
                            <!-- البيانات البنكية -->
                            <div class="col-12 mt-3">
                                <h6 class="text-primary mb-3">
                                    <i class="fas fa-university me-2"></i>
                                    البيانات البنكية (اختيارية)
                                </h6>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="add_bank_account" class="form-label">رقم الحساب البنكي</label>
                                <input type="text" class="form-control" id="add_bank_account" name="bank_account">
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="add_iban" class="form-label">رقم الآيبان</label>
                                <input type="text" class="form-control" id="add_iban" name="iban">
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="add_bank_name" class="form-label">اسم البنك</label>
                                <input type="text" class="form-control" id="add_bank_name" name="bank_name">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>إلغاء
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>حفظ الطالب
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- نافذة تعديل الطالب -->
    <div class="modal fade" id="editStudentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user-edit me-2"></i>
                        تعديل بيانات الطالب
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editStudentForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit_student">
                        <input type="hidden" name="student_id" id="edit_student_id">
                        <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                        
                        <div class="row">
                            <!-- البيانات الأساسية -->
                            <div class="col-12">
                                <h6 class="text-primary mb-3">
                                    <i class="fas fa-user me-2"></i>
                                    البيانات الأساسية
                                </h6>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="edit_name" class="form-label">الاسم الكامل <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_name" name="name" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="edit_personal_id" class="form-label">الرقم الشخصي <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_personal_id" name="personal_id" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="edit_birth_date" class="form-label">تاريخ الميلاد <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="edit_birth_date" name="birth_date" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="edit_gender" class="form-label">الجنس <span class="text-danger">*</span></label>
                                <select class="form-select" id="edit_gender" name="gender" required>
                                    <option value="">اختر الجنس</option>
                                    <option value="male">ذكر</option>
                                    <option value="female">أنثى</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="edit_halqa_id" class="form-label">الحلقة <span class="text-danger">*</span></label>
                                <select class="form-select" id="edit_halqa_id" name="halqa_id" required>
                                    <option value="">اختر الحلقة</option>
                                    <?php foreach ($halaqat as $halqa): ?>
                                    <option value="<?php echo $halqa['id']; ?>">
                                        <?php echo htmlspecialchars($halqa['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="edit_course_id" class="form-label">المقرر <span class="text-danger">*</span></label>
                                <select class="form-select" id="edit_course_id" name="course_id" required>
                                    <option value="">اختر المقرر</option>
                                    <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo $course['id']; ?>">
                                        <?php echo htmlspecialchars($course['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="edit_status" class="form-label">الحالة <span class="text-danger">*</span></label>
                                <select class="form-select" id="edit_status" name="status" required>
                                    <option value="active">نشط</option>
                                    <option value="inactive">معطل</option>
                                    <option value="graduated">متخرج</option>
                                    <option value="transferred">منقول</option>
                                </select>
                            </div>
                            
                            <!-- معلومات الاتصال -->
                            <div class="col-12 mt-3">
                                <h6 class="text-primary mb-3">
                                    <i class="fas fa-address-book me-2"></i>
                                    معلومات الاتصال
                                </h6>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="edit_phone" class="form-label">رقم الجوال</label>
                                <input type="tel" class="form-control" id="edit_phone" name="phone">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="edit_email" class="form-label">البريد الإلكتروني</label>
                                <input type="email" class="form-control" id="edit_email" name="email">
                            </div>
                            
                            <div class="col-12 mb-3">
                                <label for="edit_address" class="form-label">العنوان</label>
                                <textarea class="form-control" id="edit_address" name="address" rows="2"></textarea>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="edit_national_id" class="form-label">رقم الهوية الوطنية</label>
                                <input type="text" class="form-control" id="edit_national_id" name="national_id">
                            </div>
                            
                            <!-- بيانات ولي الأمر -->
                            <div class="col-12 mt-3">
                                <h6 class="text-primary mb-3">
                                    <i class="fas fa-user-friends me-2"></i>
                                    بيانات ولي الأمر
                                </h6>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="edit_guardian_name" class="form-label">اسم ولي الأمر</label>
                                <input type="text" class="form-control" id="edit_guardian_name" name="guardian_name">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="edit_guardian_phone" class="form-label">هاتف ولي الأمر</label>
                                <input type="tel" class="form-control" id="edit_guardian_phone" name="guardian_phone">
                            </div>
                            
                            <!-- البيانات البنكية -->
                            <div class="col-12 mt-3">
                                <h6 class="text-primary mb-3">
                                    <i class="fas fa-university me-2"></i>
                                    البيانات البنكية (اختيارية)
                                </h6>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="edit_bank_account" class="form-label">رقم الحساب البنكي</label>
                                <input type="text" class="form-control" id="edit_bank_account" name="bank_account">
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="edit_iban" class="form-label">رقم الآيبان</label>
                                <input type="text" class="form-control" id="edit_iban" name="iban">
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="edit_bank_name" class="form-label">اسم البنك</label>
                                <input type="text" class="form-control" id="edit_bank_name" name="bank_name">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>إلغاء
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>حفظ التغييرات
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- نافذة عرض تفاصيل الطالب -->
    <div class="modal fade" id="viewStudentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user me-2"></i>
                        تفاصيل الطالب
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="studentDetails">
                    <!-- سيتم ملء التفاصيل هنا بواسطة JavaScript -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>إغلاق
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- نافذة تأكيد الحذف -->
    <div class="modal fade" id="deleteStudentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        تأكيد تعطيل الطالب
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="deleteStudentForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete_student">
                        <input type="hidden" name="student_id" id="delete_student_id">
                        <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                        
                        <div class="text-center">
                            <i class="fas fa-user-times fa-3x text-danger mb-3"></i>
                            <h5>هل أنت متأكد من تعطيل هذا الطالب؟</h5>
                            <p class="text-muted mb-3">
                                سيتم تعطيل الطالب <strong id="delete_student_name"></strong>
                                <br>يمكنك إعادة تفعيله لاحقاً من خلال تعديل بياناته
                            </p>
                            <div class="alert alert-warning">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>ملاحظة:</strong> لن يتم حذف البيانات نهائياً، بل سيتم تعطيل الطالب فقط
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>إلغاء
                        </button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-user-times me-2"></i>تعطيل الطالب
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
        // تبديل القائمة الجانبية في الجوال
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('show');
        }
        
        // إغلاق القائمة الجانبية عند النقر خارجها
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const toggleButton = document.querySelector('.navbar-toggler');
            
            if (!sidebar.contains(event.target) && !toggleButton.contains(event.target)) {
                sidebar.classList.remove('show');
            }
        });
        
        // تعديل طالب
        function editStudent(student) {
            // ملء البيانات في النموذج
            document.getElementById('edit_student_id').value = student.id;
            document.getElementById('edit_name').value = student.name || '';
            document.getElementById('edit_personal_id').value = student.personal_id || '';
            document.getElementById('edit_birth_date').value = student.birth_date || '';
            document.getElementById('edit_gender').value = student.gender || '';
            document.getElementById('edit_halqa_id').value = student.halqa_id || '';
            document.getElementById('edit_course_id').value = student.course_id || '';
            document.getElementById('edit_status').value = student.status || 'active';
            document.getElementById('edit_phone').value = student.phone || '';
            document.getElementById('edit_email').value = student.email || '';
            document.getElementById('edit_address').value = student.address || '';
            document.getElementById('edit_national_id').value = student.national_id || '';
            document.getElementById('edit_guardian_name').value = student.guardian_name || '';
            document.getElementById('edit_guardian_phone').value = student.guardian_phone || '';
            document.getElementById('edit_bank_account').value = student.bank_account || '';
            document.getElementById('edit_iban').value = student.iban || '';
            document.getElementById('edit_bank_name').value = student.bank_name || '';
            
            // عرض النافذة
            const modal = new bootstrap.Modal(document.getElementById('editStudentModal'));
            modal.show();
        }
        
        // عرض تفاصيل الطالب
        function viewStudent(student) {
            const statusText = {
                'active': 'نشط',
                'inactive': 'معطل',
                'graduated': 'متخرج',
                'transferred': 'منقول'
            };
            
            const genderText = student.gender === 'male' ? 'ذكر' : 'أنثى';
            
            const details = `
                <div class="row">
                    <div class="col-12 text-center mb-4">
                        <div class="avatar-lg bg-primary rounded-circle d-inline-flex align-items-center justify-content-center mb-3">
                            <i class="fas fa-user fa-2x text-white"></i>
                        </div>
                        <h4>${student.name}</h4>
                        <p class="text-muted">${student.personal_id}</p>
                    </div>
                    
                    <div class="col-md-6">
                        <h6 class="text-primary mb-3">
                            <i class="fas fa-user me-2"></i>البيانات الأساسية
                        </h6>
                        <table class="table table-borderless">
                            <tr><td><strong>الاسم:</strong></td><td>${student.name}</td></tr>
                            <tr><td><strong>الرقم الشخصي:</strong></td><td>${student.personal_id}</td></tr>
                            <tr><td><strong>تاريخ الميلاد:</strong></td><td>${student.birth_date || 'غير محدد'}</td></tr>
                            <tr><td><strong>الجنس:</strong></td><td>${genderText}</td></tr>
                            <tr><td><strong>الحالة:</strong></td><td><span class="badge bg-${student.status === 'active' ? 'success' : 'danger'}">${statusText[student.status] || student.status}</span></td></tr>
                        </table>
                    </div>
                    
                    <div class="col-md-6">
                        <h6 class="text-primary mb-3">
                            <i class="fas fa-graduation-cap me-2"></i>البيانات الأكاديمية
                        </h6>
                        <table class="table table-borderless">
                            <tr><td><strong>الحلقة:</strong></td><td>${student.halqa_name || 'غير محدد'}</td></tr>
                            <tr><td><strong>المقرر:</strong></td><td>${student.course_name || 'غير محدد'}</td></tr>
                        </table>
                    </div>
                    
                    ${student.phone || student.email || student.address ? `
                    <div class="col-md-6">
                        <h6 class="text-primary mb-3">
                            <i class="fas fa-address-book me-2"></i>معلومات الاتصال
                        </h6>
                        <table class="table table-borderless">
                            ${student.phone ? `<tr><td><strong>الهاتف:</strong></td><td><a href="tel:${student.phone}">${student.phone}</a></td></tr>` : ''}
                            ${student.email ? `<tr><td><strong>البريد:</strong></td><td><a href="mailto:${student.email}">${student.email}</a></td></tr>` : ''}
                            ${student.address ? `<tr><td><strong>العنوان:</strong></td><td>${student.address}</td></tr>` : ''}
                            ${student.national_id ? `<tr><td><strong>رقم الهوية:</strong></td><td>${student.national_id}</td></tr>` : ''}
                        </table>
                    </div>
                    ` : ''}
                    
                    ${student.guardian_name || student.guardian_phone ? `
                    <div class="col-md-6">
                        <h6 class="text-primary mb-3">
                            <i class="fas fa-user-friends me-2"></i>بيانات ولي الأمر
                        </h6>
                        <table class="table table-borderless">
                            ${student.guardian_name ? `<tr><td><strong>الاسم:</strong></td><td>${student.guardian_name}</td></tr>` : ''}
                            ${student.guardian_phone ? `<tr><td><strong>الهاتف:</strong></td><td><a href="tel:${student.guardian_phone}">${student.guardian_phone}</a></td></tr>` : ''}
                        </table>
                    </div>
                    ` : ''}
                    
                    ${student.bank_account || student.iban || student.bank_name ? `
                    <div class="col-12">
                        <h6 class="text-primary mb-3">
                            <i class="fas fa-university me-2"></i>البيانات البنكية
                        </h6>
                        <table class="table table-borderless">
                            ${student.bank_account ? `<tr><td><strong>رقم الحساب:</strong></td><td>${student.bank_account}</td></tr>` : ''}
                            ${student.iban ? `<tr><td><strong>الآيبان:</strong></td><td>${student.iban}</td></tr>` : ''}
                            ${student.bank_name ? `<tr><td><strong>البنك:</strong></td><td>${student.bank_name}</td></tr>` : ''}
                        </table>
                    </div>
                    ` : ''}
                </div>
            `;
            
            document.getElementById('studentDetails').innerHTML = details;
            
            const modal = new bootstrap.Modal(document.getElementById('viewStudentModal'));
            modal.show();
        }
        
        // حذف طالب
        function deleteStudent(studentId, studentName) {
            document.getElementById('delete_student_id').value = studentId;
            document.getElementById('delete_student_name').textContent = studentName;
            
            const modal = new bootstrap.Modal(document.getElementById('deleteStudentModal'));
            modal.show();
        }
        
        // التحقق من صحة النماذج
        document.addEventListener('DOMContentLoaded', function() {
            // التحقق من نموذج الإضافة
            const addForm = document.getElementById('addStudentForm');
            if (addForm) {
                addForm.addEventListener('submit', function(e) {
                    const personalId = document.getElementById('add_personal_id').value.trim();
                    if (personalId.length < 3) {
                        e.preventDefault();
                        alert('الرقم الشخصي يجب أن يكون 3 أحرف على الأقل');
                        return false;
                    }
                });
            }
            
            // التحقق من نموذج التعديل
            const editForm = document.getElementById('editStudentForm');
            if (editForm) {
                editForm.addEventListener('submit', function(e) {
                    const personalId = document.getElementById('edit_personal_id').value.trim();
                    if (personalId.length < 3) {
                        e.preventDefault();
                        alert('الرقم الشخصي يجب أن يكون 3 أحرف على الأقل');
                        return false;
                    }
                });
            }
        });
        
        // إخفاء رسائل التنبيه تلقائياً
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>

