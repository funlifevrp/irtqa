<?php
/**
 * صفحة إدارة الحلقات
 * تتضمن إنشاء وتعديل وحذف الحلقات مع تعيين المعلمين وإدارة الطلاب
 */

// تضمين ملف الإعدادات
require_once __DIR__ . '/config/config.php';

// التحقق من تسجيل الدخول
Auth::requireLogin();

// التحقق من الصلاحيات
Auth::requirePermission('manage_halaqat');

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
            case 'add_halqa':
                // إضافة حلقة جديدة
                $name = Security::sanitizeInput($_POST['name']);
                $description = Security::sanitizeInput($_POST['description'] ?? '');
                $teacher_id = (int)$_POST['teacher_id'];
                $max_students = (int)$_POST['max_students'];
                $schedule_days = Security::sanitizeInput($_POST['schedule_days'] ?? '');
                $schedule_time = Security::sanitizeInput($_POST['schedule_time'] ?? '');
                $location = Security::sanitizeInput($_POST['location'] ?? '');
                $level = Security::sanitizeInput($_POST['level'] ?? 'beginner');
                $gender = Security::sanitizeInput($_POST['gender'] ?? 'mixed');
                
                // التحقق من عدم تكرار اسم الحلقة
                $stmt = $conn->prepare("SELECT id FROM halaqat WHERE name = ?");
                $stmt->bind_param("s", $name);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    throw new Exception("اسم الحلقة موجود مسبقاً");
                }
                
                // إدراج الحلقة الجديدة
                $stmt = $conn->prepare("
                    INSERT INTO halaqat (
                        name, description, teacher_id, max_students, 
                        schedule_days, schedule_time, location, level, 
                        gender, status, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
                ");
                
                $stmt->bind_param("ssiisssss", 
                    $name, $description, $teacher_id, $max_students,
                    $schedule_days, $schedule_time, $location, $level, $gender
                );
                
                if ($stmt->execute()) {
                    $halqa_id = $conn->insert_id;
                    
                    // تسجيل العملية في السجل
                    Logger::log('info', "تم إنشاء حلقة جديدة: $name (ID: $halqa_id)", [
                        'user_id' => Auth::getCurrentUser()['id'],
                        'halqa_id' => $halqa_id,
                        'action' => 'add_halqa'
                    ]);
                    
                    $message = "تم إنشاء الحلقة بنجاح";
                    $messageType = "success";
                } else {
                    throw new Exception("فشل في إنشاء الحلقة");
                }
                break;
                
            case 'edit_halqa':
                // تعديل بيانات حلقة
                $halqa_id = (int)$_POST['halqa_id'];
                $name = Security::sanitizeInput($_POST['name']);
                $description = Security::sanitizeInput($_POST['description'] ?? '');
                $teacher_id = (int)$_POST['teacher_id'];
                $max_students = (int)$_POST['max_students'];
                $schedule_days = Security::sanitizeInput($_POST['schedule_days'] ?? '');
                $schedule_time = Security::sanitizeInput($_POST['schedule_time'] ?? '');
                $location = Security::sanitizeInput($_POST['location'] ?? '');
                $level = Security::sanitizeInput($_POST['level'] ?? 'beginner');
                $gender = Security::sanitizeInput($_POST['gender'] ?? 'mixed');
                $status = Security::sanitizeInput($_POST['status']);
                
                // التحقق من عدم تكرار اسم الحلقة (باستثناء الحلقة الحالية)
                $stmt = $conn->prepare("SELECT id FROM halaqat WHERE name = ? AND id != ?");
                $stmt->bind_param("si", $name, $halqa_id);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    throw new Exception("اسم الحلقة موجود مسبقاً");
                }
                
                // تحديث بيانات الحلقة
                $stmt = $conn->prepare("
                    UPDATE halaqat SET 
                        name = ?, description = ?, teacher_id = ?, max_students = ?,
                        schedule_days = ?, schedule_time = ?, location = ?, 
                        level = ?, gender = ?, status = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                
                $stmt->bind_param("ssiisssssi", 
                    $name, $description, $teacher_id, $max_students,
                    $schedule_days, $schedule_time, $location, $level, 
                    $gender, $status, $halqa_id
                );
                
                if ($stmt->execute()) {
                    // تسجيل العملية في السجل
                    Logger::log('info', "تم تعديل بيانات الحلقة: $name (ID: $halqa_id)", [
                        'user_id' => Auth::getCurrentUser()['id'],
                        'halqa_id' => $halqa_id,
                        'action' => 'edit_halqa'
                    ]);
                    
                    $message = "تم تحديث بيانات الحلقة بنجاح";
                    $messageType = "success";
                } else {
                    throw new Exception("فشل في تحديث بيانات الحلقة");
                }
                break;
                
            case 'delete_halqa':
                // حذف حلقة (تعطيل فقط)
                $halqa_id = (int)$_POST['halqa_id'];
                
                // الحصول على اسم الحلقة للسجل
                $stmt = $conn->prepare("SELECT name FROM halaqat WHERE id = ?");
                $stmt->bind_param("i", $halqa_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $halqa = $result->fetch_assoc();
                
                if (!$halqa) {
                    throw new Exception("الحلقة غير موجودة");
                }
                
                // التحقق من وجود طلاب في الحلقة
                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM students WHERE halqa_id = ? AND status = 'active'");
                $stmt->bind_param("i", $halqa_id);
                $stmt->execute();
                $student_count = $stmt->get_result()->fetch_assoc()['count'];
                
                if ($student_count > 0) {
                    throw new Exception("لا يمكن تعطيل الحلقة لوجود $student_count طالب نشط فيها");
                }
                
                // تعطيل الحلقة بدلاً من الحذف
                $stmt = $conn->prepare("UPDATE halaqat SET status = 'inactive', updated_at = NOW() WHERE id = ?");
                $stmt->bind_param("i", $halqa_id);
                
                if ($stmt->execute()) {
                    // تسجيل العملية في السجل
                    Logger::log('warning', "تم تعطيل الحلقة: {$halqa['name']} (ID: $halqa_id)", [
                        'user_id' => Auth::getCurrentUser()['id'],
                        'halqa_id' => $halqa_id,
                        'action' => 'delete_halqa'
                    ]);
                    
                    $message = "تم تعطيل الحلقة بنجاح";
                    $messageType = "success";
                } else {
                    throw new Exception("فشل في تعطيل الحلقة");
                }
                break;
        }
        
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = "error";
        
        // تسجيل الخطأ
        Logger::log('error', "خطأ في إدارة الحلقات: " . $e->getMessage(), [
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
    $teacher_filter = (int)($_GET['teacher_filter'] ?? 0);
    $status_filter = Security::sanitizeInput($_GET['status_filter'] ?? '');
    $level_filter = Security::sanitizeInput($_GET['level_filter'] ?? '');
    $gender_filter = Security::sanitizeInput($_GET['gender_filter'] ?? '');
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = 20;
    $offset = ($page - 1) * $limit;
    
    // بناء استعلام البحث
    $where_conditions = [];
    $params = [];
    $types = '';
    
    if (!empty($search)) {
        $where_conditions[] = "(h.name LIKE ? OR h.description LIKE ? OR h.location LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $types .= 'sss';
    }
    
    if ($teacher_filter > 0) {
        $where_conditions[] = "h.teacher_id = ?";
        $params[] = $teacher_filter;
        $types .= 'i';
    }
    
    if (!empty($status_filter)) {
        $where_conditions[] = "h.status = ?";
        $params[] = $status_filter;
        $types .= 's';
    }
    
    if (!empty($level_filter)) {
        $where_conditions[] = "h.level = ?";
        $params[] = $level_filter;
        $types .= 's';
    }
    
    if (!empty($gender_filter)) {
        $where_conditions[] = "h.gender = ?";
        $params[] = $gender_filter;
        $types .= 's';
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // جلب الحلقات
    $sql = "
        SELECT h.*, u.full_name as teacher_name,
               (SELECT COUNT(*) FROM students s WHERE s.halqa_id = h.id AND s.status = 'active') as student_count
        FROM halaqat h
        LEFT JOIN users u ON h.teacher_id = u.id
        $where_clause
        ORDER BY h.created_at DESC
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
    $halaqat = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // عدد الحلقات الإجمالي
    $count_sql = "
        SELECT COUNT(*) as total
        FROM halaqat h
        LEFT JOIN users u ON h.teacher_id = u.id
        $where_clause
    ";
    
    $stmt = $conn->prepare($count_sql);
    if (!empty($where_conditions)) {
        $count_params = array_slice($params, 0, -2); // إزالة limit و offset
        $count_types = substr($types, 0, -2);
        $stmt->bind_param($count_types, ...$count_params);
    }
    
    $stmt->execute();
    $total_halaqat = $stmt->get_result()->fetch_assoc()['total'];
    $total_pages = ceil($total_halaqat / $limit);
    
    // جلب المعلمين للتصفية والإضافة
    $teachers = $conn->query("
        SELECT id, full_name 
        FROM users 
        WHERE role IN ('teacher', 'supervisor', 'admin') 
        AND status = 'active' 
        ORDER BY full_name
    ")->fetch_all(MYSQLI_ASSOC);
    
    // إحصائيات سريعة
    $stats = [
        'total' => $conn->query("SELECT COUNT(*) as count FROM halaqat")->fetch_assoc()['count'],
        'active' => $conn->query("SELECT COUNT(*) as count FROM halaqat WHERE status = 'active'")->fetch_assoc()['count'],
        'inactive' => $conn->query("SELECT COUNT(*) as count FROM halaqat WHERE status = 'inactive'")->fetch_assoc()['count'],
        'total_students' => $conn->query("SELECT COUNT(*) as count FROM students WHERE status = 'active'")->fetch_assoc()['count'],
        'avg_students' => $conn->query("
            SELECT ROUND(AVG(student_count), 1) as avg_count 
            FROM (
                SELECT COUNT(*) as student_count 
                FROM students s 
                JOIN halaqat h ON s.halqa_id = h.id 
                WHERE s.status = 'active' AND h.status = 'active'
                GROUP BY h.id
            ) as halqa_stats
        ")->fetch_assoc()['avg_count'] ?? 0
    ];
    
} catch (Exception $e) {
    $message = "خطأ في جلب البيانات: " . $e->getMessage();
    $messageType = "error";
    $halaqat = [];
    $teachers = [];
    $stats = ['total' => 0, 'active' => 0, 'inactive' => 0, 'total_students' => 0, 'avg_students' => 0];
    $total_pages = 1;
}

$currentUser = Auth::getCurrentUser();
$pageTitle = "إدارة الحلقات";
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
        
        .halqa-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,.1);
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }
        
        .halqa-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,.15);
        }
        
        .halqa-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0;
            padding: 15px 20px;
        }
        
        .progress {
            height: 8px;
            border-radius: 10px;
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
                            <a class="nav-link" href="students.php">
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
                            <a class="nav-link active" href="halaqat.php">
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
                        <i class="fas fa-chalkboard-teacher me-2"></i>
                        إدارة الحلقات
                    </h1>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addHalqaModal">
                        <i class="fas fa-plus me-2"></i>
                        إنشاء حلقة جديدة
                    </button>
                </div>

                <!-- بطاقات الإحصائيات -->
                <div class="row mb-4">
                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                        <div class="stats-card text-center">
                            <div class="icon mb-2">
                                <i class="fas fa-chalkboard-teacher"></i>
                            </div>
                            <h3 class="mb-1"><?php echo number_format($stats['total']); ?></h3>
                            <p class="mb-0">إجمالي الحلقات</p>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                        <div class="stats-card text-center">
                            <div class="icon mb-2">
                                <i class="fas fa-play-circle"></i>
                            </div>
                            <h3 class="mb-1"><?php echo number_format($stats['active']); ?></h3>
                            <p class="mb-0">حلقات نشطة</p>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                        <div class="stats-card text-center">
                            <div class="icon mb-2">
                                <i class="fas fa-pause-circle"></i>
                            </div>
                            <h3 class="mb-1"><?php echo number_format($stats['inactive']); ?></h3>
                            <p class="mb-0">حلقات معطلة</p>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                        <div class="stats-card text-center">
                            <div class="icon mb-2">
                                <i class="fas fa-user-graduate"></i>
                            </div>
                            <h3 class="mb-1"><?php echo number_format($stats['total_students']); ?></h3>
                            <p class="mb-0">إجمالي الطلاب</p>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                        <div class="stats-card text-center">
                            <div class="icon mb-2">
                                <i class="fas fa-chart-bar"></i>
                            </div>
                            <h3 class="mb-1"><?php echo number_format($stats['avg_students'], 1); ?></h3>
                            <p class="mb-0">متوسط الطلاب</p>
                        </div>
                    </div>
                </div>

                <!-- مربع البحث والتصفية -->
                <div class="search-box">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label for="search" class="form-label">البحث</label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   placeholder="ابحث بالاسم أو الوصف أو المكان..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-2">
                            <label for="teacher_filter" class="form-label">المعلم</label>
                            <select class="form-select" id="teacher_filter" name="teacher_filter">
                                <option value="">جميع المعلمين</option>
                                <?php foreach ($teachers as $teacher): ?>
                                <option value="<?php echo $teacher['id']; ?>" <?php echo $teacher_filter == $teacher['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($teacher['full_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="status_filter" class="form-label">الحالة</label>
                            <select class="form-select" id="status_filter" name="status_filter">
                                <option value="">جميع الحالات</option>
                                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>نشطة</option>
                                <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>معطلة</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="level_filter" class="form-label">المستوى</label>
                            <select class="form-select" id="level_filter" name="level_filter">
                                <option value="">جميع المستويات</option>
                                <option value="beginner" <?php echo $level_filter === 'beginner' ? 'selected' : ''; ?>>مبتدئ</option>
                                <option value="intermediate" <?php echo $level_filter === 'intermediate' ? 'selected' : ''; ?>>متوسط</option>
                                <option value="advanced" <?php echo $level_filter === 'advanced' ? 'selected' : ''; ?>>متقدم</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="gender_filter" class="form-label">النوع</label>
                            <select class="form-select" id="gender_filter" name="gender_filter">
                                <option value="">جميع الأنواع</option>
                                <option value="male" <?php echo $gender_filter === 'male' ? 'selected' : ''; ?>>ذكور</option>
                                <option value="female" <?php echo $gender_filter === 'female' ? 'selected' : ''; ?>>إناث</option>
                                <option value="mixed" <?php echo $gender_filter === 'mixed' ? 'selected' : ''; ?>>مختلط</option>
                            </select>
                        </div>
                        <div class="col-md-1">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- قائمة الحلقات -->
                <?php if (empty($halaqat)): ?>
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-chalkboard-teacher fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">لا توجد حلقات</h5>
                        <p class="text-muted">لم يتم العثور على أي حلقات مطابقة لمعايير البحث</p>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addHalqaModal">
                            <i class="fas fa-plus me-2"></i>
                            إنشاء حلقة جديدة
                        </button>
                    </div>
                </div>
                <?php else: ?>
                <div class="row">
                    <?php foreach ($halaqat as $halqa): ?>
                    <div class="col-lg-6 col-xl-4 mb-4">
                        <div class="halqa-card">
                            <div class="halqa-header">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h5 class="mb-1"><?php echo htmlspecialchars($halqa['name']); ?></h5>
                                        <small class="opacity-75">
                                            <i class="fas fa-user-tie me-1"></i>
                                            <?php echo htmlspecialchars($halqa['teacher_name'] ?? 'غير محدد'); ?>
                                        </small>
                                    </div>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-light" type="button" data-bs-toggle="dropdown">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li>
                                                <a class="dropdown-item" href="#" onclick="editHalqa(<?php echo htmlspecialchars(json_encode($halqa)); ?>)">
                                                    <i class="fas fa-edit me-2"></i>تعديل
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item" href="#" onclick="viewHalqa(<?php echo htmlspecialchars(json_encode($halqa)); ?>)">
                                                    <i class="fas fa-eye me-2"></i>عرض التفاصيل
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item" href="students.php?halqa_filter=<?php echo $halqa['id']; ?>">
                                                    <i class="fas fa-users me-2"></i>عرض الطلاب
                                                </a>
                                            </li>
                                            <?php if ($halqa['status'] === 'active' && $halqa['student_count'] == 0): ?>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <a class="dropdown-item text-danger" href="#" onclick="deleteHalqa(<?php echo $halqa['id']; ?>, '<?php echo htmlspecialchars($halqa['name']); ?>')">
                                                    <i class="fas fa-trash me-2"></i>تعطيل
                                                </a>
                                            </li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($halqa['description'])): ?>
                                <p class="text-muted small mb-3"><?php echo htmlspecialchars($halqa['description']); ?></p>
                                <?php endif; ?>
                                
                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-users text-primary me-2"></i>
                                            <small>
                                                <strong><?php echo $halqa['student_count']; ?></strong>
                                                / <?php echo $halqa['max_students']; ?> طالب
                                            </small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-layer-group text-info me-2"></i>
                                            <small>
                                                <?php 
                                                $levels = [
                                                    'beginner' => 'مبتدئ',
                                                    'intermediate' => 'متوسط',
                                                    'advanced' => 'متقدم'
                                                ];
                                                echo $levels[$halqa['level']] ?? $halqa['level'];
                                                ?>
                                            </small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-<?php echo $halqa['gender'] === 'male' ? 'male text-primary' : ($halqa['gender'] === 'female' ? 'female text-danger' : 'users text-success'); ?> me-2"></i>
                                            <small>
                                                <?php 
                                                $genders = [
                                                    'male' => 'ذكور',
                                                    'female' => 'إناث',
                                                    'mixed' => 'مختلط'
                                                ];
                                                echo $genders[$halqa['gender']] ?? $halqa['gender'];
                                                ?>
                                            </small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <span class="badge bg-<?php echo $halqa['status'] === 'active' ? 'success' : 'danger'; ?>">
                                            <?php echo $halqa['status'] === 'active' ? 'نشطة' : 'معطلة'; ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <?php if (!empty($halqa['schedule_days']) || !empty($halqa['schedule_time'])): ?>
                                <div class="mb-3">
                                    <small class="text-muted">
                                        <i class="fas fa-calendar me-1"></i>
                                        <?php echo htmlspecialchars($halqa['schedule_days'] ?? ''); ?>
                                        <?php if (!empty($halqa['schedule_time'])): ?>
                                        - <?php echo htmlspecialchars($halqa['schedule_time']); ?>
                                        <?php endif; ?>
                                    </small>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($halqa['location'])): ?>
                                <div class="mb-3">
                                    <small class="text-muted">
                                        <i class="fas fa-map-marker-alt me-1"></i>
                                        <?php echo htmlspecialchars($halqa['location']); ?>
                                    </small>
                                </div>
                                <?php endif; ?>
                                
                                <!-- شريط التقدم لعدد الطلاب -->
                                <div class="mb-2">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <small class="text-muted">امتلاء الحلقة</small>
                                        <small class="text-muted">
                                            <?php echo round(($halqa['student_count'] / max(1, $halqa['max_students'])) * 100); ?>%
                                        </small>
                                    </div>
                                    <div class="progress">
                                        <div class="progress-bar" role="progressbar" 
                                             style="width: <?php echo min(100, ($halqa['student_count'] / max(1, $halqa['max_students'])) * 100); ?>%">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
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
            </main>
        </div>
    </div>

    <!-- نافذة إنشاء حلقة جديدة -->
    <div class="modal fade" id="addHalqaModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-plus me-2"></i>
                        إنشاء حلقة جديدة
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="addHalqaForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_halqa">
                        <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                        
                        <div class="row">
                            <!-- البيانات الأساسية -->
                            <div class="col-12">
                                <h6 class="text-primary mb-3">
                                    <i class="fas fa-info-circle me-2"></i>
                                    البيانات الأساسية
                                </h6>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="add_name" class="form-label">اسم الحلقة <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="add_name" name="name" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="add_teacher_id" class="form-label">المعلم <span class="text-danger">*</span></label>
                                <select class="form-select" id="add_teacher_id" name="teacher_id" required>
                                    <option value="">اختر المعلم</option>
                                    <?php foreach ($teachers as $teacher): ?>
                                    <option value="<?php echo $teacher['id']; ?>">
                                        <?php echo htmlspecialchars($teacher['full_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-12 mb-3">
                                <label for="add_description" class="form-label">وصف الحلقة</label>
                                <textarea class="form-control" id="add_description" name="description" rows="3" 
                                          placeholder="وصف مختصر عن الحلقة وأهدافها..."></textarea>
                            </div>
                            
                            <!-- الإعدادات -->
                            <div class="col-12 mt-3">
                                <h6 class="text-primary mb-3">
                                    <i class="fas fa-cogs me-2"></i>
                                    إعدادات الحلقة
                                </h6>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="add_max_students" class="form-label">الحد الأقصى للطلاب <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="add_max_students" name="max_students" 
                                       min="1" max="50" value="20" required>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="add_level" class="form-label">مستوى الحلقة <span class="text-danger">*</span></label>
                                <select class="form-select" id="add_level" name="level" required>
                                    <option value="beginner">مبتدئ</option>
                                    <option value="intermediate">متوسط</option>
                                    <option value="advanced">متقدم</option>
                                </select>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="add_gender" class="form-label">نوع الحلقة <span class="text-danger">*</span></label>
                                <select class="form-select" id="add_gender" name="gender" required>
                                    <option value="mixed">مختلط</option>
                                    <option value="male">ذكور فقط</option>
                                    <option value="female">إناث فقط</option>
                                </select>
                            </div>
                            
                            <!-- الجدولة والمكان -->
                            <div class="col-12 mt-3">
                                <h6 class="text-primary mb-3">
                                    <i class="fas fa-calendar-alt me-2"></i>
                                    الجدولة والمكان
                                </h6>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="add_schedule_days" class="form-label">أيام الحلقة</label>
                                <input type="text" class="form-control" id="add_schedule_days" name="schedule_days" 
                                       placeholder="مثال: السبت والثلاثاء">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="add_schedule_time" class="form-label">وقت الحلقة</label>
                                <input type="text" class="form-control" id="add_schedule_time" name="schedule_time" 
                                       placeholder="مثال: من 4:00 إلى 6:00 مساءً">
                            </div>
                            
                            <div class="col-12 mb-3">
                                <label for="add_location" class="form-label">مكان الحلقة</label>
                                <input type="text" class="form-control" id="add_location" name="location" 
                                       placeholder="مثال: قاعة رقم 1 - الدور الأول">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>إلغاء
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>إنشاء الحلقة
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- نافذة تعديل الحلقة -->
    <div class="modal fade" id="editHalqaModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>
                        تعديل بيانات الحلقة
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editHalqaForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit_halqa">
                        <input type="hidden" name="halqa_id" id="edit_halqa_id">
                        <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                        
                        <div class="row">
                            <!-- البيانات الأساسية -->
                            <div class="col-12">
                                <h6 class="text-primary mb-3">
                                    <i class="fas fa-info-circle me-2"></i>
                                    البيانات الأساسية
                                </h6>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="edit_name" class="form-label">اسم الحلقة <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_name" name="name" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="edit_teacher_id" class="form-label">المعلم <span class="text-danger">*</span></label>
                                <select class="form-select" id="edit_teacher_id" name="teacher_id" required>
                                    <option value="">اختر المعلم</option>
                                    <?php foreach ($teachers as $teacher): ?>
                                    <option value="<?php echo $teacher['id']; ?>">
                                        <?php echo htmlspecialchars($teacher['full_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-12 mb-3">
                                <label for="edit_description" class="form-label">وصف الحلقة</label>
                                <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                            </div>
                            
                            <!-- الإعدادات -->
                            <div class="col-12 mt-3">
                                <h6 class="text-primary mb-3">
                                    <i class="fas fa-cogs me-2"></i>
                                    إعدادات الحلقة
                                </h6>
                            </div>
                            
                            <div class="col-md-3 mb-3">
                                <label for="edit_max_students" class="form-label">الحد الأقصى للطلاب <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="edit_max_students" name="max_students" 
                                       min="1" max="50" required>
                            </div>
                            
                            <div class="col-md-3 mb-3">
                                <label for="edit_level" class="form-label">مستوى الحلقة <span class="text-danger">*</span></label>
                                <select class="form-select" id="edit_level" name="level" required>
                                    <option value="beginner">مبتدئ</option>
                                    <option value="intermediate">متوسط</option>
                                    <option value="advanced">متقدم</option>
                                </select>
                            </div>
                            
                            <div class="col-md-3 mb-3">
                                <label for="edit_gender" class="form-label">نوع الحلقة <span class="text-danger">*</span></label>
                                <select class="form-select" id="edit_gender" name="gender" required>
                                    <option value="mixed">مختلط</option>
                                    <option value="male">ذكور فقط</option>
                                    <option value="female">إناث فقط</option>
                                </select>
                            </div>
                            
                            <div class="col-md-3 mb-3">
                                <label for="edit_status" class="form-label">حالة الحلقة <span class="text-danger">*</span></label>
                                <select class="form-select" id="edit_status" name="status" required>
                                    <option value="active">نشطة</option>
                                    <option value="inactive">معطلة</option>
                                </select>
                            </div>
                            
                            <!-- الجدولة والمكان -->
                            <div class="col-12 mt-3">
                                <h6 class="text-primary mb-3">
                                    <i class="fas fa-calendar-alt me-2"></i>
                                    الجدولة والمكان
                                </h6>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="edit_schedule_days" class="form-label">أيام الحلقة</label>
                                <input type="text" class="form-control" id="edit_schedule_days" name="schedule_days">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="edit_schedule_time" class="form-label">وقت الحلقة</label>
                                <input type="text" class="form-control" id="edit_schedule_time" name="schedule_time">
                            </div>
                            
                            <div class="col-12 mb-3">
                                <label for="edit_location" class="form-label">مكان الحلقة</label>
                                <input type="text" class="form-control" id="edit_location" name="location">
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

    <!-- نافذة عرض تفاصيل الحلقة -->
    <div class="modal fade" id="viewHalqaModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-chalkboard-teacher me-2"></i>
                        تفاصيل الحلقة
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="halqaDetails">
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
    <div class="modal fade" id="deleteHalqaModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        تأكيد تعطيل الحلقة
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="deleteHalqaForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete_halqa">
                        <input type="hidden" name="halqa_id" id="delete_halqa_id">
                        <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                        
                        <div class="text-center">
                            <i class="fas fa-chalkboard-teacher fa-3x text-danger mb-3"></i>
                            <h5>هل أنت متأكد من تعطيل هذه الحلقة؟</h5>
                            <p class="text-muted mb-3">
                                سيتم تعطيل الحلقة <strong id="delete_halqa_name"></strong>
                                <br>يمكنك إعادة تفعيلها لاحقاً من خلال تعديل بياناتها
                            </p>
                            <div class="alert alert-warning">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>ملاحظة:</strong> لن يتم حذف البيانات نهائياً، بل سيتم تعطيل الحلقة فقط
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>إلغاء
                        </button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-ban me-2"></i>تعطيل الحلقة
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
        
        // تعديل حلقة
        function editHalqa(halqa) {
            // ملء البيانات في النموذج
            document.getElementById('edit_halqa_id').value = halqa.id;
            document.getElementById('edit_name').value = halqa.name || '';
            document.getElementById('edit_teacher_id').value = halqa.teacher_id || '';
            document.getElementById('edit_description').value = halqa.description || '';
            document.getElementById('edit_max_students').value = halqa.max_students || 20;
            document.getElementById('edit_level').value = halqa.level || 'beginner';
            document.getElementById('edit_gender').value = halqa.gender || 'mixed';
            document.getElementById('edit_status').value = halqa.status || 'active';
            document.getElementById('edit_schedule_days').value = halqa.schedule_days || '';
            document.getElementById('edit_schedule_time').value = halqa.schedule_time || '';
            document.getElementById('edit_location').value = halqa.location || '';
            
            // عرض النافذة
            const modal = new bootstrap.Modal(document.getElementById('editHalqaModal'));
            modal.show();
        }
        
        // عرض تفاصيل الحلقة
        function viewHalqa(halqa) {
            const levelText = {
                'beginner': 'مبتدئ',
                'intermediate': 'متوسط',
                'advanced': 'متقدم'
            };
            
            const genderText = {
                'male': 'ذكور فقط',
                'female': 'إناث فقط',
                'mixed': 'مختلط'
            };
            
            const statusText = halqa.status === 'active' ? 'نشطة' : 'معطلة';
            
            const details = `
                <div class="row">
                    <div class="col-12 text-center mb-4">
                        <div class="avatar-lg bg-primary rounded-circle d-inline-flex align-items-center justify-content-center mb-3">
                            <i class="fas fa-chalkboard-teacher fa-2x text-white"></i>
                        </div>
                        <h4>${halqa.name}</h4>
                        <p class="text-muted">
                            <i class="fas fa-user-tie me-1"></i>
                            ${halqa.teacher_name || 'غير محدد'}
                        </p>
                    </div>
                    
                    <div class="col-md-6">
                        <h6 class="text-primary mb-3">
                            <i class="fas fa-info-circle me-2"></i>البيانات الأساسية
                        </h6>
                        <table class="table table-borderless">
                            <tr><td><strong>اسم الحلقة:</strong></td><td>${halqa.name}</td></tr>
                            <tr><td><strong>المعلم:</strong></td><td>${halqa.teacher_name || 'غير محدد'}</td></tr>
                            <tr><td><strong>المستوى:</strong></td><td>${levelText[halqa.level] || halqa.level}</td></tr>
                            <tr><td><strong>النوع:</strong></td><td>${genderText[halqa.gender] || halqa.gender}</td></tr>
                            <tr><td><strong>الحالة:</strong></td><td><span class="badge bg-${halqa.status === 'active' ? 'success' : 'danger'}">${statusText}</span></td></tr>
                        </table>
                    </div>
                    
                    <div class="col-md-6">
                        <h6 class="text-primary mb-3">
                            <i class="fas fa-users me-2"></i>إحصائيات الطلاب
                        </h6>
                        <table class="table table-borderless">
                            <tr><td><strong>عدد الطلاب:</strong></td><td>${halqa.student_count || 0}</td></tr>
                            <tr><td><strong>الحد الأقصى:</strong></td><td>${halqa.max_students}</td></tr>
                            <tr><td><strong>المقاعد المتاحة:</strong></td><td>${halqa.max_students - (halqa.student_count || 0)}</td></tr>
                            <tr><td><strong>نسبة الامتلاء:</strong></td><td>${Math.round(((halqa.student_count || 0) / halqa.max_students) * 100)}%</td></tr>
                        </table>
                    </div>
                    
                    ${halqa.description ? `
                    <div class="col-12">
                        <h6 class="text-primary mb-3">
                            <i class="fas fa-align-left me-2"></i>وصف الحلقة
                        </h6>
                        <p class="text-muted">${halqa.description}</p>
                    </div>
                    ` : ''}
                    
                    ${halqa.schedule_days || halqa.schedule_time || halqa.location ? `
                    <div class="col-12">
                        <h6 class="text-primary mb-3">
                            <i class="fas fa-calendar-alt me-2"></i>الجدولة والمكان
                        </h6>
                        <table class="table table-borderless">
                            ${halqa.schedule_days ? `<tr><td><strong>الأيام:</strong></td><td>${halqa.schedule_days}</td></tr>` : ''}
                            ${halqa.schedule_time ? `<tr><td><strong>الوقت:</strong></td><td>${halqa.schedule_time}</td></tr>` : ''}
                            ${halqa.location ? `<tr><td><strong>المكان:</strong></td><td>${halqa.location}</td></tr>` : ''}
                        </table>
                    </div>
                    ` : ''}
                </div>
            `;
            
            document.getElementById('halqaDetails').innerHTML = details;
            
            const modal = new bootstrap.Modal(document.getElementById('viewHalqaModal'));
            modal.show();
        }
        
        // حذف حلقة
        function deleteHalqa(halqaId, halqaName) {
            document.getElementById('delete_halqa_id').value = halqaId;
            document.getElementById('delete_halqa_name').textContent = halqaName;
            
            const modal = new bootstrap.Modal(document.getElementById('deleteHalqaModal'));
            modal.show();
        }
        
        // التحقق من صحة النماذج
        document.addEventListener('DOMContentLoaded', function() {
            // التحقق من نموذج الإضافة
            const addForm = document.getElementById('addHalqaForm');
            if (addForm) {
                addForm.addEventListener('submit', function(e) {
                    const name = document.getElementById('add_name').value.trim();
                    const maxStudents = parseInt(document.getElementById('add_max_students').value);
                    
                    if (name.length < 3) {
                        e.preventDefault();
                        alert('اسم الحلقة يجب أن يكون 3 أحرف على الأقل');
                        return false;
                    }
                    
                    if (maxStudents < 1 || maxStudents > 50) {
                        e.preventDefault();
                        alert('الحد الأقصى للطلاب يجب أن يكون بين 1 و 50');
                        return false;
                    }
                });
            }
            
            // التحقق من نموذج التعديل
            const editForm = document.getElementById('editHalqaForm');
            if (editForm) {
                editForm.addEventListener('submit', function(e) {
                    const name = document.getElementById('edit_name').value.trim();
                    const maxStudents = parseInt(document.getElementById('edit_max_students').value);
                    
                    if (name.length < 3) {
                        e.preventDefault();
                        alert('اسم الحلقة يجب أن يكون 3 أحرف على الأقل');
                        return false;
                    }
                    
                    if (maxStudents < 1 || maxStudents > 50) {
                        e.preventDefault();
                        alert('الحد الأقصى للطلاب يجب أن يكون بين 1 و 50');
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

