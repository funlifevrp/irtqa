<?php
/**
 * صفحة إدارة المقررات
 * تتضمن إضافة وتعديل وحذف المقررات مع تتبع التقدم والربط بالحلقات
 */

// تضمين ملف الإعدادات
require_once __DIR__ . '/config/config.php';

// التحقق من تسجيل الدخول
Auth::requireLogin();

// التحقق من الصلاحيات
Auth::requirePermission('manage_courses');

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
            case 'add_course':
                // إضافة مقرر جديد
                $name = Security::sanitizeInput($_POST['name']);
                $description = Security::sanitizeInput($_POST['description'] ?? '');
                $total_pages = (int)$_POST['total_pages'];
                $category = Security::sanitizeInput($_POST['category'] ?? 'quran');
                $level = Security::sanitizeInput($_POST['level'] ?? 'beginner');
                $duration_weeks = (int)$_POST['duration_weeks'];
                $objectives = Security::sanitizeInput($_POST['objectives'] ?? '');
                $prerequisites = Security::sanitizeInput($_POST['prerequisites'] ?? '');
                
                // التحقق من عدم تكرار اسم المقرر
                $stmt = $conn->prepare("SELECT id FROM courses WHERE name = ?");
                $stmt->bind_param("s", $name);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    throw new Exception("اسم المقرر موجود مسبقاً");
                }
                
                // إدراج المقرر الجديد
                $stmt = $conn->prepare("
                    INSERT INTO courses (
                        name, description, total_pages, category, level, 
                        duration_weeks, objectives, prerequisites, status, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
                ");
                
                $stmt->bind_param("ssississ", 
                    $name, $description, $total_pages, $category, $level,
                    $duration_weeks, $objectives, $prerequisites
                );
                
                if ($stmt->execute()) {
                    $course_id = $conn->insert_id;
                    
                    // تسجيل العملية في السجل
                    Logger::log('info', "تم إضافة مقرر جديد: $name (ID: $course_id)", [
                        'user_id' => Auth::getCurrentUser()['id'],
                        'course_id' => $course_id,
                        'action' => 'add_course'
                    ]);
                    
                    $message = "تم إضافة المقرر بنجاح";
                    $messageType = "success";
                } else {
                    throw new Exception("فشل في إضافة المقرر");
                }
                break;
                
            case 'edit_course':
                // تعديل بيانات مقرر
                $course_id = (int)$_POST['course_id'];
                $name = Security::sanitizeInput($_POST['name']);
                $description = Security::sanitizeInput($_POST['description'] ?? '');
                $total_pages = (int)$_POST['total_pages'];
                $category = Security::sanitizeInput($_POST['category'] ?? 'quran');
                $level = Security::sanitizeInput($_POST['level'] ?? 'beginner');
                $duration_weeks = (int)$_POST['duration_weeks'];
                $objectives = Security::sanitizeInput($_POST['objectives'] ?? '');
                $prerequisites = Security::sanitizeInput($_POST['prerequisites'] ?? '');
                $status = Security::sanitizeInput($_POST['status']);
                
                // التحقق من عدم تكرار اسم المقرر (باستثناء المقرر الحالي)
                $stmt = $conn->prepare("SELECT id FROM courses WHERE name = ? AND id != ?");
                $stmt->bind_param("si", $name, $course_id);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    throw new Exception("اسم المقرر موجود مسبقاً");
                }
                
                // تحديث بيانات المقرر
                $stmt = $conn->prepare("
                    UPDATE courses SET 
                        name = ?, description = ?, total_pages = ?, category = ?, 
                        level = ?, duration_weeks = ?, objectives = ?, 
                        prerequisites = ?, status = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                
                $stmt->bind_param("ssississsi", 
                    $name, $description, $total_pages, $category, $level,
                    $duration_weeks, $objectives, $prerequisites, $status, $course_id
                );
                
                if ($stmt->execute()) {
                    // تسجيل العملية في السجل
                    Logger::log('info', "تم تعديل بيانات المقرر: $name (ID: $course_id)", [
                        'user_id' => Auth::getCurrentUser()['id'],
                        'course_id' => $course_id,
                        'action' => 'edit_course'
                    ]);
                    
                    $message = "تم تحديث بيانات المقرر بنجاح";
                    $messageType = "success";
                } else {
                    throw new Exception("فشل في تحديث بيانات المقرر");
                }
                break;
                
            case 'delete_course':
                // حذف مقرر (تعطيل فقط)
                $course_id = (int)$_POST['course_id'];
                
                // الحصول على اسم المقرر للسجل
                $stmt = $conn->prepare("SELECT name FROM courses WHERE id = ?");
                $stmt->bind_param("i", $course_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $course = $result->fetch_assoc();
                
                if (!$course) {
                    throw new Exception("المقرر غير موجود");
                }
                
                // التحقق من وجود طلاب يدرسون هذا المقرر
                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM students WHERE course_id = ? AND status = 'active'");
                $stmt->bind_param("i", $course_id);
                $stmt->execute();
                $student_count = $stmt->get_result()->fetch_assoc()['count'];
                
                if ($student_count > 0) {
                    throw new Exception("لا يمكن تعطيل المقرر لوجود $student_count طالب يدرسونه حالياً");
                }
                
                // تعطيل المقرر بدلاً من الحذف
                $stmt = $conn->prepare("UPDATE courses SET status = 'inactive', updated_at = NOW() WHERE id = ?");
                $stmt->bind_param("i", $course_id);
                
                if ($stmt->execute()) {
                    // تسجيل العملية في السجل
                    Logger::log('warning', "تم تعطيل المقرر: {$course['name']} (ID: $course_id)", [
                        'user_id' => Auth::getCurrentUser()['id'],
                        'course_id' => $course_id,
                        'action' => 'delete_course'
                    ]);
                    
                    $message = "تم تعطيل المقرر بنجاح";
                    $messageType = "success";
                } else {
                    throw new Exception("فشل في تعطيل المقرر");
                }
                break;
        }
        
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = "error";
        
        // تسجيل الخطأ
        Logger::log('error', "خطأ في إدارة المقررات: " . $e->getMessage(), [
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
    $category_filter = Security::sanitizeInput($_GET['category_filter'] ?? '');
    $level_filter = Security::sanitizeInput($_GET['level_filter'] ?? '');
    $status_filter = Security::sanitizeInput($_GET['status_filter'] ?? '');
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = 20;
    $offset = ($page - 1) * $limit;
    
    // بناء استعلام البحث
    $where_conditions = [];
    $params = [];
    $types = '';
    
    if (!empty($search)) {
        $where_conditions[] = "(c.name LIKE ? OR c.description LIKE ? OR c.objectives LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $types .= 'sss';
    }
    
    if (!empty($category_filter)) {
        $where_conditions[] = "c.category = ?";
        $params[] = $category_filter;
        $types .= 's';
    }
    
    if (!empty($level_filter)) {
        $where_conditions[] = "c.level = ?";
        $params[] = $level_filter;
        $types .= 's';
    }
    
    if (!empty($status_filter)) {
        $where_conditions[] = "c.status = ?";
        $params[] = $status_filter;
        $types .= 's';
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // جلب المقررات
    $sql = "
        SELECT c.*,
               (SELECT COUNT(*) FROM students s WHERE s.course_id = c.id AND s.status = 'active') as student_count,
               (SELECT COUNT(*) FROM halaqat h WHERE h.id IN (
                   SELECT DISTINCT halqa_id FROM students s WHERE s.course_id = c.id AND s.status = 'active'
               )) as halqa_count
        FROM courses c
        $where_clause
        ORDER BY c.created_at DESC
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
    $courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // عدد المقررات الإجمالي
    $count_sql = "
        SELECT COUNT(*) as total
        FROM courses c
        $where_clause
    ";
    
    $stmt = $conn->prepare($count_sql);
    if (!empty($where_conditions)) {
        $count_params = array_slice($params, 0, -2); // إزالة limit و offset
        $count_types = substr($types, 0, -2);
        $stmt->bind_param($count_types, ...$count_params);
    }
    
    $stmt->execute();
    $total_courses = $stmt->get_result()->fetch_assoc()['total'];
    $total_pages = ceil($total_courses / $limit);
    
    // إحصائيات سريعة
    $stats = [
        'total' => $conn->query("SELECT COUNT(*) as count FROM courses")->fetch_assoc()['count'],
        'active' => $conn->query("SELECT COUNT(*) as count FROM courses WHERE status = 'active'")->fetch_assoc()['count'],
        'inactive' => $conn->query("SELECT COUNT(*) as count FROM courses WHERE status = 'inactive'")->fetch_assoc()['count'],
        'quran' => $conn->query("SELECT COUNT(*) as count FROM courses WHERE category = 'quran' AND status = 'active'")->fetch_assoc()['count'],
        'hadith' => $conn->query("SELECT COUNT(*) as count FROM courses WHERE category = 'hadith' AND status = 'active'")->fetch_assoc()['count'],
        'fiqh' => $conn->query("SELECT COUNT(*) as count FROM courses WHERE category = 'fiqh' AND status = 'active'")->fetch_assoc()['count'],
        'total_students' => $conn->query("
            SELECT COUNT(*) as count 
            FROM students s 
            JOIN courses c ON s.course_id = c.id 
            WHERE s.status = 'active' AND c.status = 'active'
        ")->fetch_assoc()['count']
    ];
    
} catch (Exception $e) {
    $message = "خطأ في جلب البيانات: " . $e->getMessage();
    $messageType = "error";
    $courses = [];
    $stats = ['total' => 0, 'active' => 0, 'inactive' => 0, 'quran' => 0, 'hadith' => 0, 'fiqh' => 0, 'total_students' => 0];
    $total_pages = 1;
}

$currentUser = Auth::getCurrentUser();
$pageTitle = "إدارة المقررات";
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
        
        .course-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,.1);
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }
        
        .course-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,.15);
        }
        
        .course-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0;
            padding: 15px 20px;
        }
        
        .progress {
            height: 8px;
            border-radius: 10px;
        }
        
        .category-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            z-index: 1;
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
                            <a class="nav-link" href="halaqat.php">
                                <i class="fas fa-chalkboard-teacher me-2"></i>
                                إدارة الحلقات
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if (Auth::hasPermission('manage_courses')): ?>
                        <li class="nav-item">
                            <a class="nav-link active" href="courses.php">
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
                        <i class="fas fa-book me-2"></i>
                        إدارة المقررات
                    </h1>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCourseModal">
                        <i class="fas fa-plus me-2"></i>
                        إضافة مقرر جديد
                    </button>
                </div>

                <!-- بطاقات الإحصائيات -->
                <div class="row mb-4">
                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                        <div class="stats-card text-center">
                            <div class="icon mb-2">
                                <i class="fas fa-book"></i>
                            </div>
                            <h3 class="mb-1"><?php echo number_format($stats['total']); ?></h3>
                            <p class="mb-0">إجمالي المقررات</p>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                        <div class="stats-card text-center">
                            <div class="icon mb-2">
                                <i class="fas fa-play-circle"></i>
                            </div>
                            <h3 class="mb-1"><?php echo number_format($stats['active']); ?></h3>
                            <p class="mb-0">مقررات نشطة</p>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                        <div class="stats-card text-center">
                            <div class="icon mb-2">
                                <i class="fas fa-quran"></i>
                            </div>
                            <h3 class="mb-1"><?php echo number_format($stats['quran']); ?></h3>
                            <p class="mb-0">مقررات قرآن</p>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                        <div class="stats-card text-center">
                            <div class="icon mb-2">
                                <i class="fas fa-scroll"></i>
                            </div>
                            <h3 class="mb-1"><?php echo number_format($stats['hadith']); ?></h3>
                            <p class="mb-0">مقررات حديث</p>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                        <div class="stats-card text-center">
                            <div class="icon mb-2">
                                <i class="fas fa-balance-scale"></i>
                            </div>
                            <h3 class="mb-1"><?php echo number_format($stats['fiqh']); ?></h3>
                            <p class="mb-0">مقررات فقه</p>
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
                </div>

                <!-- مربع البحث والتصفية -->
                <div class="search-box">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label for="search" class="form-label">البحث</label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   placeholder="ابحث بالاسم أو الوصف أو الأهداف..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-2">
                            <label for="category_filter" class="form-label">التصنيف</label>
                            <select class="form-select" id="category_filter" name="category_filter">
                                <option value="">جميع التصنيفات</option>
                                <option value="quran" <?php echo $category_filter === 'quran' ? 'selected' : ''; ?>>قرآن كريم</option>
                                <option value="hadith" <?php echo $category_filter === 'hadith' ? 'selected' : ''; ?>>حديث شريف</option>
                                <option value="fiqh" <?php echo $category_filter === 'fiqh' ? 'selected' : ''; ?>>فقه</option>
                                <option value="aqeedah" <?php echo $category_filter === 'aqeedah' ? 'selected' : ''; ?>>عقيدة</option>
                                <option value="seerah" <?php echo $category_filter === 'seerah' ? 'selected' : ''; ?>>سيرة</option>
                                <option value="other" <?php echo $category_filter === 'other' ? 'selected' : ''; ?>>أخرى</option>
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
                            <label for="status_filter" class="form-label">الحالة</label>
                            <select class="form-select" id="status_filter" name="status_filter">
                                <option value="">جميع الحالات</option>
                                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>نشط</option>
                                <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>معطل</option>
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

                <!-- قائمة المقررات -->
                <?php if (empty($courses)): ?>
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-book fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">لا توجد مقررات</h5>
                        <p class="text-muted">لم يتم العثور على أي مقررات مطابقة لمعايير البحث</p>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCourseModal">
                            <i class="fas fa-plus me-2"></i>
                            إضافة مقرر جديد
                        </button>
                    </div>
                </div>
                <?php else: ?>
                <div class="row">
                    <?php foreach ($courses as $course): ?>
                    <div class="col-lg-6 col-xl-4 mb-4">
                        <div class="course-card position-relative">
                            <!-- شارة التصنيف -->
                            <div class="category-badge">
                                <?php
                                $categoryColors = [
                                    'quran' => 'success',
                                    'hadith' => 'info',
                                    'fiqh' => 'warning',
                                    'aqeedah' => 'primary',
                                    'seerah' => 'secondary',
                                    'other' => 'dark'
                                ];
                                $categoryNames = [
                                    'quran' => 'قرآن',
                                    'hadith' => 'حديث',
                                    'fiqh' => 'فقه',
                                    'aqeedah' => 'عقيدة',
                                    'seerah' => 'سيرة',
                                    'other' => 'أخرى'
                                ];
                                ?>
                                <span class="badge bg-<?php echo $categoryColors[$course['category']] ?? 'secondary'; ?>">
                                    <?php echo $categoryNames[$course['category']] ?? $course['category']; ?>
                                </span>
                            </div>
                            
                            <div class="course-header">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h5 class="mb-1"><?php echo htmlspecialchars($course['name']); ?></h5>
                                        <small class="opacity-75">
                                            <i class="fas fa-file-alt me-1"></i>
                                            <?php echo number_format($course['total_pages']); ?> صفحة
                                        </small>
                                    </div>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-light" type="button" data-bs-toggle="dropdown">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li>
                                                <a class="dropdown-item" href="#" onclick="editCourse(<?php echo htmlspecialchars(json_encode($course)); ?>)">
                                                    <i class="fas fa-edit me-2"></i>تعديل
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item" href="#" onclick="viewCourse(<?php echo htmlspecialchars(json_encode($course)); ?>)">
                                                    <i class="fas fa-eye me-2"></i>عرض التفاصيل
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item" href="students.php?course_filter=<?php echo $course['id']; ?>">
                                                    <i class="fas fa-users me-2"></i>عرض الطلاب
                                                </a>
                                            </li>
                                            <?php if ($course['status'] === 'active' && $course['student_count'] == 0): ?>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <a class="dropdown-item text-danger" href="#" onclick="deleteCourse(<?php echo $course['id']; ?>, '<?php echo htmlspecialchars($course['name']); ?>')">
                                                    <i class="fas fa-trash me-2"></i>تعطيل
                                                </a>
                                            </li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($course['description'])): ?>
                                <p class="text-muted small mb-3"><?php echo htmlspecialchars($course['description']); ?></p>
                                <?php endif; ?>
                                
                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-users text-primary me-2"></i>
                                            <small>
                                                <strong><?php echo $course['student_count']; ?></strong> طالب
                                            </small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-chalkboard-teacher text-info me-2"></i>
                                            <small>
                                                <strong><?php echo $course['halqa_count']; ?></strong> حلقة
                                            </small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-layer-group text-warning me-2"></i>
                                            <small>
                                                <?php 
                                                $levels = [
                                                    'beginner' => 'مبتدئ',
                                                    'intermediate' => 'متوسط',
                                                    'advanced' => 'متقدم'
                                                ];
                                                echo $levels[$course['level']] ?? $course['level'];
                                                ?>
                                            </small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <span class="badge bg-<?php echo $course['status'] === 'active' ? 'success' : 'danger'; ?>">
                                            <?php echo $course['status'] === 'active' ? 'نشط' : 'معطل'; ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <?php if (!empty($course['duration_weeks'])): ?>
                                <div class="mb-3">
                                    <small class="text-muted">
                                        <i class="fas fa-calendar me-1"></i>
                                        مدة المقرر: <?php echo $course['duration_weeks']; ?> أسبوع
                                    </small>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($course['objectives'])): ?>
                                <div class="mb-2">
                                    <small class="text-muted">
                                        <i class="fas fa-bullseye me-1"></i>
                                        <strong>الأهداف:</strong>
                                    </small>
                                    <small class="text-muted d-block">
                                        <?php echo htmlspecialchars(substr($course['objectives'], 0, 100)); ?>
                                        <?php if (strlen($course['objectives']) > 100): ?>...<?php endif; ?>
                                    </small>
                                </div>
                                <?php endif; ?>
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

    <!-- نافذة إضافة مقرر جديد -->
    <div class="modal fade" id="addCourseModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-plus me-2"></i>
                        إضافة مقرر جديد
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="addCourseForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_course">
                        <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                        
                        <div class="row">
                            <!-- البيانات الأساسية -->
                            <div class="col-12">
                                <h6 class="text-primary mb-3">
                                    <i class="fas fa-info-circle me-2"></i>
                                    البيانات الأساسية
                                </h6>
                            </div>
                            
                            <div class="col-md-8 mb-3">
                                <label for="add_name" class="form-label">اسم المقرر <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="add_name" name="name" required>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="add_total_pages" class="form-label">عدد الصفحات <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="add_total_pages" name="total_pages" 
                                       min="1" max="1000" required>
                            </div>
                            
                            <div class="col-12 mb-3">
                                <label for="add_description" class="form-label">وصف المقرر</label>
                                <textarea class="form-control" id="add_description" name="description" rows="3" 
                                          placeholder="وصف مختصر عن المقرر ومحتواه..."></textarea>
                            </div>
                            
                            <!-- التصنيف والإعدادات -->
                            <div class="col-12 mt-3">
                                <h6 class="text-primary mb-3">
                                    <i class="fas fa-cogs me-2"></i>
                                    التصنيف والإعدادات
                                </h6>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="add_category" class="form-label">تصنيف المقرر <span class="text-danger">*</span></label>
                                <select class="form-select" id="add_category" name="category" required>
                                    <option value="quran">قرآن كريم</option>
                                    <option value="hadith">حديث شريف</option>
                                    <option value="fiqh">فقه</option>
                                    <option value="aqeedah">عقيدة</option>
                                    <option value="seerah">سيرة</option>
                                    <option value="other">أخرى</option>
                                </select>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="add_level" class="form-label">مستوى المقرر <span class="text-danger">*</span></label>
                                <select class="form-select" id="add_level" name="level" required>
                                    <option value="beginner">مبتدئ</option>
                                    <option value="intermediate">متوسط</option>
                                    <option value="advanced">متقدم</option>
                                </select>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="add_duration_weeks" class="form-label">مدة المقرر (بالأسابيع)</label>
                                <input type="number" class="form-control" id="add_duration_weeks" name="duration_weeks" 
                                       min="1" max="52" placeholder="مثال: 12">
                            </div>
                            
                            <!-- الأهداف والمتطلبات -->
                            <div class="col-12 mt-3">
                                <h6 class="text-primary mb-3">
                                    <i class="fas fa-bullseye me-2"></i>
                                    الأهداف والمتطلبات
                                </h6>
                            </div>
                            
                            <div class="col-12 mb-3">
                                <label for="add_objectives" class="form-label">أهداف المقرر</label>
                                <textarea class="form-control" id="add_objectives" name="objectives" rows="3" 
                                          placeholder="الأهداف التعليمية المراد تحقيقها من هذا المقرر..."></textarea>
                            </div>
                            
                            <div class="col-12 mb-3">
                                <label for="add_prerequisites" class="form-label">المتطلبات السابقة</label>
                                <textarea class="form-control" id="add_prerequisites" name="prerequisites" rows="2" 
                                          placeholder="المعرفة أو المهارات المطلوبة قبل دراسة هذا المقرر..."></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>إلغاء
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>إضافة المقرر
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- نافذة تعديل المقرر -->
    <div class="modal fade" id="editCourseModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>
                        تعديل بيانات المقرر
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editCourseForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit_course">
                        <input type="hidden" name="course_id" id="edit_course_id">
                        <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                        
                        <div class="row">
                            <!-- البيانات الأساسية -->
                            <div class="col-12">
                                <h6 class="text-primary mb-3">
                                    <i class="fas fa-info-circle me-2"></i>
                                    البيانات الأساسية
                                </h6>
                            </div>
                            
                            <div class="col-md-8 mb-3">
                                <label for="edit_name" class="form-label">اسم المقرر <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_name" name="name" required>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="edit_total_pages" class="form-label">عدد الصفحات <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="edit_total_pages" name="total_pages" 
                                       min="1" max="1000" required>
                            </div>
                            
                            <div class="col-12 mb-3">
                                <label for="edit_description" class="form-label">وصف المقرر</label>
                                <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                            </div>
                            
                            <!-- التصنيف والإعدادات -->
                            <div class="col-12 mt-3">
                                <h6 class="text-primary mb-3">
                                    <i class="fas fa-cogs me-2"></i>
                                    التصنيف والإعدادات
                                </h6>
                            </div>
                            
                            <div class="col-md-3 mb-3">
                                <label for="edit_category" class="form-label">تصنيف المقرر <span class="text-danger">*</span></label>
                                <select class="form-select" id="edit_category" name="category" required>
                                    <option value="quran">قرآن كريم</option>
                                    <option value="hadith">حديث شريف</option>
                                    <option value="fiqh">فقه</option>
                                    <option value="aqeedah">عقيدة</option>
                                    <option value="seerah">سيرة</option>
                                    <option value="other">أخرى</option>
                                </select>
                            </div>
                            
                            <div class="col-md-3 mb-3">
                                <label for="edit_level" class="form-label">مستوى المقرر <span class="text-danger">*</span></label>
                                <select class="form-select" id="edit_level" name="level" required>
                                    <option value="beginner">مبتدئ</option>
                                    <option value="intermediate">متوسط</option>
                                    <option value="advanced">متقدم</option>
                                </select>
                            </div>
                            
                            <div class="col-md-3 mb-3">
                                <label for="edit_duration_weeks" class="form-label">مدة المقرر (بالأسابيع)</label>
                                <input type="number" class="form-control" id="edit_duration_weeks" name="duration_weeks" 
                                       min="1" max="52">
                            </div>
                            
                            <div class="col-md-3 mb-3">
                                <label for="edit_status" class="form-label">حالة المقرر <span class="text-danger">*</span></label>
                                <select class="form-select" id="edit_status" name="status" required>
                                    <option value="active">نشط</option>
                                    <option value="inactive">معطل</option>
                                </select>
                            </div>
                            
                            <!-- الأهداف والمتطلبات -->
                            <div class="col-12 mt-3">
                                <h6 class="text-primary mb-3">
                                    <i class="fas fa-bullseye me-2"></i>
                                    الأهداف والمتطلبات
                                </h6>
                            </div>
                            
                            <div class="col-12 mb-3">
                                <label for="edit_objectives" class="form-label">أهداف المقرر</label>
                                <textarea class="form-control" id="edit_objectives" name="objectives" rows="3"></textarea>
                            </div>
                            
                            <div class="col-12 mb-3">
                                <label for="edit_prerequisites" class="form-label">المتطلبات السابقة</label>
                                <textarea class="form-control" id="edit_prerequisites" name="prerequisites" rows="2"></textarea>
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

    <!-- نافذة عرض تفاصيل المقرر -->
    <div class="modal fade" id="viewCourseModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-book me-2"></i>
                        تفاصيل المقرر
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="courseDetails">
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
    <div class="modal fade" id="deleteCourseModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        تأكيد تعطيل المقرر
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="deleteCourseForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete_course">
                        <input type="hidden" name="course_id" id="delete_course_id">
                        <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                        
                        <div class="text-center">
                            <i class="fas fa-book fa-3x text-danger mb-3"></i>
                            <h5>هل أنت متأكد من تعطيل هذا المقرر؟</h5>
                            <p class="text-muted mb-3">
                                سيتم تعطيل المقرر <strong id="delete_course_name"></strong>
                                <br>يمكنك إعادة تفعيله لاحقاً من خلال تعديل بياناته
                            </p>
                            <div class="alert alert-warning">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>ملاحظة:</strong> لن يتم حذف البيانات نهائياً، بل سيتم تعطيل المقرر فقط
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>إلغاء
                        </button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-ban me-2"></i>تعطيل المقرر
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
        
        // تعديل مقرر
        function editCourse(course) {
            // ملء البيانات في النموذج
            document.getElementById('edit_course_id').value = course.id;
            document.getElementById('edit_name').value = course.name || '';
            document.getElementById('edit_description').value = course.description || '';
            document.getElementById('edit_total_pages').value = course.total_pages || '';
            document.getElementById('edit_category').value = course.category || 'quran';
            document.getElementById('edit_level').value = course.level || 'beginner';
            document.getElementById('edit_duration_weeks').value = course.duration_weeks || '';
            document.getElementById('edit_objectives').value = course.objectives || '';
            document.getElementById('edit_prerequisites').value = course.prerequisites || '';
            document.getElementById('edit_status').value = course.status || 'active';
            
            // عرض النافذة
            const modal = new bootstrap.Modal(document.getElementById('editCourseModal'));
            modal.show();
        }
        
        // عرض تفاصيل المقرر
        function viewCourse(course) {
            const categoryNames = {
                'quran': 'قرآن كريم',
                'hadith': 'حديث شريف',
                'fiqh': 'فقه',
                'aqeedah': 'عقيدة',
                'seerah': 'سيرة',
                'other': 'أخرى'
            };
            
            const levelNames = {
                'beginner': 'مبتدئ',
                'intermediate': 'متوسط',
                'advanced': 'متقدم'
            };
            
            const statusText = course.status === 'active' ? 'نشط' : 'معطل';
            
            const details = `
                <div class="row">
                    <div class="col-12 text-center mb-4">
                        <div class="avatar-lg bg-primary rounded-circle d-inline-flex align-items-center justify-content-center mb-3">
                            <i class="fas fa-book fa-2x text-white"></i>
                        </div>
                        <h4>${course.name}</h4>
                        <p class="text-muted">
                            <span class="badge bg-info me-2">${categoryNames[course.category] || course.category}</span>
                            <span class="badge bg-secondary">${levelNames[course.level] || course.level}</span>
                        </p>
                    </div>
                    
                    <div class="col-md-6">
                        <h6 class="text-primary mb-3">
                            <i class="fas fa-info-circle me-2"></i>البيانات الأساسية
                        </h6>
                        <table class="table table-borderless">
                            <tr><td><strong>اسم المقرر:</strong></td><td>${course.name}</td></tr>
                            <tr><td><strong>التصنيف:</strong></td><td>${categoryNames[course.category] || course.category}</td></tr>
                            <tr><td><strong>المستوى:</strong></td><td>${levelNames[course.level] || course.level}</td></tr>
                            <tr><td><strong>عدد الصفحات:</strong></td><td>${course.total_pages} صفحة</td></tr>
                            <tr><td><strong>الحالة:</strong></td><td><span class="badge bg-${course.status === 'active' ? 'success' : 'danger'}">${statusText}</span></td></tr>
                        </table>
                    </div>
                    
                    <div class="col-md-6">
                        <h6 class="text-primary mb-3">
                            <i class="fas fa-chart-bar me-2"></i>الإحصائيات
                        </h6>
                        <table class="table table-borderless">
                            <tr><td><strong>عدد الطلاب:</strong></td><td>${course.student_count || 0}</td></tr>
                            <tr><td><strong>عدد الحلقات:</strong></td><td>${course.halqa_count || 0}</td></tr>
                            ${course.duration_weeks ? `<tr><td><strong>مدة المقرر:</strong></td><td>${course.duration_weeks} أسبوع</td></tr>` : ''}
                        </table>
                    </div>
                    
                    ${course.description ? `
                    <div class="col-12">
                        <h6 class="text-primary mb-3">
                            <i class="fas fa-align-left me-2"></i>وصف المقرر
                        </h6>
                        <p class="text-muted">${course.description}</p>
                    </div>
                    ` : ''}
                    
                    ${course.objectives ? `
                    <div class="col-12">
                        <h6 class="text-primary mb-3">
                            <i class="fas fa-bullseye me-2"></i>أهداف المقرر
                        </h6>
                        <p class="text-muted">${course.objectives}</p>
                    </div>
                    ` : ''}
                    
                    ${course.prerequisites ? `
                    <div class="col-12">
                        <h6 class="text-primary mb-3">
                            <i class="fas fa-list-check me-2"></i>المتطلبات السابقة
                        </h6>
                        <p class="text-muted">${course.prerequisites}</p>
                    </div>
                    ` : ''}
                </div>
            `;
            
            document.getElementById('courseDetails').innerHTML = details;
            
            const modal = new bootstrap.Modal(document.getElementById('viewCourseModal'));
            modal.show();
        }
        
        // حذف مقرر
        function deleteCourse(courseId, courseName) {
            document.getElementById('delete_course_id').value = courseId;
            document.getElementById('delete_course_name').textContent = courseName;
            
            const modal = new bootstrap.Modal(document.getElementById('deleteCourseModal'));
            modal.show();
        }
        
        // التحقق من صحة النماذج
        document.addEventListener('DOMContentLoaded', function() {
            // التحقق من نموذج الإضافة
            const addForm = document.getElementById('addCourseForm');
            if (addForm) {
                addForm.addEventListener('submit', function(e) {
                    const name = document.getElementById('add_name').value.trim();
                    const totalPages = parseInt(document.getElementById('add_total_pages').value);
                    
                    if (name.length < 3) {
                        e.preventDefault();
                        alert('اسم المقرر يجب أن يكون 3 أحرف على الأقل');
                        return false;
                    }
                    
                    if (totalPages < 1 || totalPages > 1000) {
                        e.preventDefault();
                        alert('عدد الصفحات يجب أن يكون بين 1 و 1000');
                        return false;
                    }
                });
            }
            
            // التحقق من نموذج التعديل
            const editForm = document.getElementById('editCourseForm');
            if (editForm) {
                editForm.addEventListener('submit', function(e) {
                    const name = document.getElementById('edit_name').value.trim();
                    const totalPages = parseInt(document.getElementById('edit_total_pages').value);
                    
                    if (name.length < 3) {
                        e.preventDefault();
                        alert('اسم المقرر يجب أن يكون 3 أحرف على الأقل');
                        return false;
                    }
                    
                    if (totalPages < 1 || totalPages > 1000) {
                        e.preventDefault();
                        alert('عدد الصفحات يجب أن يكون بين 1 و 1000');
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

