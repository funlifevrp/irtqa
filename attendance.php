<?php
/**
 * صفحة الحضور والغياب
 * تتضمن تسجيل الحضور اليومي وعرض التقارير والإحصائيات
 */

// تضمين ملف الإعدادات
require_once __DIR__ . '/config/config.php';

// التحقق من تسجيل الدخول
Auth::requireLogin();

// التحقق من الصلاحيات
Auth::requirePermission('manage_attendance');

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
            case 'record_attendance':
                // تسجيل الحضور
                $halqa_id = (int)$_POST['halqa_id'];
                $attendance_date = Security::sanitizeInput($_POST['attendance_date']);
                $attendance_data = $_POST['attendance'] ?? [];
                
                // التحقق من صحة التاريخ
                if (!DateTime::createFromFormat('Y-m-d', $attendance_date)) {
                    throw new Exception("تاريخ غير صحيح");
                }
                
                // التحقق من وجود الحلقة
                $stmt = $conn->prepare("SELECT name FROM halaqat WHERE id = ? AND status = 'active'");
                $stmt->bind_param("i", $halqa_id);
                $stmt->execute();
                $halqa = $stmt->get_result()->fetch_assoc();
                
                if (!$halqa) {
                    throw new Exception("الحلقة غير موجودة أو معطلة");
                }
                
                // بدء المعاملة
                $conn->begin_transaction();
                
                try {
                    // حذف السجلات الموجودة لنفس اليوم والحلقة
                    $stmt = $conn->prepare("DELETE FROM attendance WHERE halqa_id = ? AND attendance_date = ?");
                    $stmt->bind_param("is", $halqa_id, $attendance_date);
                    $stmt->execute();
                    
                    // إدراج السجلات الجديدة
                    $stmt = $conn->prepare("
                        INSERT INTO attendance (student_id, halqa_id, attendance_date, status, notes, recorded_by, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, NOW())
                    ");
                    
                    $recorded_by = Auth::getCurrentUser()['id'];
                    $attendance_count = 0;
                    
                    foreach ($attendance_data as $student_id => $data) {
                        $student_id = (int)$student_id;
                        $status = Security::sanitizeInput($data['status'] ?? 'absent');
                        $notes = Security::sanitizeInput($data['notes'] ?? '');
                        
                        // التحقق من أن الطالب ينتمي للحلقة
                        $check_stmt = $conn->prepare("SELECT id FROM students WHERE id = ? AND halqa_id = ? AND status = 'active'");
                        $check_stmt->bind_param("ii", $student_id, $halqa_id);
                        $check_stmt->execute();
                        
                        if ($check_stmt->get_result()->num_rows > 0) {
                            $stmt->bind_param("iisssi", $student_id, $halqa_id, $attendance_date, $status, $notes, $recorded_by);
                            $stmt->execute();
                            $attendance_count++;
                        }
                    }
                    
                    // تأكيد المعاملة
                    $conn->commit();
                    
                    // تسجيل العملية في السجل
                    Logger::log('info', "تم تسجيل الحضور للحلقة: {$halqa['name']} بتاريخ: $attendance_date ($attendance_count طالب)", [
                        'user_id' => Auth::getCurrentUser()['id'],
                        'halqa_id' => $halqa_id,
                        'attendance_date' => $attendance_date,
                        'student_count' => $attendance_count,
                        'action' => 'record_attendance'
                    ]);
                    
                    $message = "تم تسجيل الحضور بنجاح لـ $attendance_count طالب";
                    $messageType = "success";
                    
                } catch (Exception $e) {
                    $conn->rollback();
                    throw $e;
                }
                break;
                
            case 'bulk_attendance':
                // تسجيل حضور جماعي
                $halqa_id = (int)$_POST['halqa_id'];
                $attendance_date = Security::sanitizeInput($_POST['attendance_date']);
                $bulk_status = Security::sanitizeInput($_POST['bulk_status']);
                
                // التحقق من صحة التاريخ
                if (!DateTime::createFromFormat('Y-m-d', $attendance_date)) {
                    throw new Exception("تاريخ غير صحيح");
                }
                
                // جلب جميع طلاب الحلقة النشطين
                $stmt = $conn->prepare("
                    SELECT s.id, s.full_name 
                    FROM students s 
                    WHERE s.halqa_id = ? AND s.status = 'active'
                ");
                $stmt->bind_param("i", $halqa_id);
                $stmt->execute();
                $students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                
                if (empty($students)) {
                    throw new Exception("لا توجد طلاب نشطين في هذه الحلقة");
                }
                
                // بدء المعاملة
                $conn->begin_transaction();
                
                try {
                    // حذف السجلات الموجودة لنفس اليوم والحلقة
                    $stmt = $conn->prepare("DELETE FROM attendance WHERE halqa_id = ? AND attendance_date = ?");
                    $stmt->bind_param("is", $halqa_id, $attendance_date);
                    $stmt->execute();
                    
                    // إدراج السجلات الجديدة
                    $stmt = $conn->prepare("
                        INSERT INTO attendance (student_id, halqa_id, attendance_date, status, recorded_by, created_at) 
                        VALUES (?, ?, ?, ?, ?, NOW())
                    ");
                    
                    $recorded_by = Auth::getCurrentUser()['id'];
                    
                    foreach ($students as $student) {
                        $stmt->bind_param("iissi", $student['id'], $halqa_id, $attendance_date, $bulk_status, $recorded_by);
                        $stmt->execute();
                    }
                    
                    // تأكيد المعاملة
                    $conn->commit();
                    
                    $student_count = count($students);
                    $status_text = $bulk_status === 'present' ? 'حاضر' : ($bulk_status === 'absent' ? 'غائب' : 'متأخر');
                    
                    // تسجيل العملية في السجل
                    Logger::log('info', "تم تسجيل حضور جماعي ($status_text) للحلقة ID: $halqa_id بتاريخ: $attendance_date ($student_count طالب)", [
                        'user_id' => Auth::getCurrentUser()['id'],
                        'halqa_id' => $halqa_id,
                        'attendance_date' => $attendance_date,
                        'bulk_status' => $bulk_status,
                        'student_count' => $student_count,
                        'action' => 'bulk_attendance'
                    ]);
                    
                    $message = "تم تسجيل الحضور الجماعي بنجاح لـ $student_count طالب كـ $status_text";
                    $messageType = "success";
                    
                } catch (Exception $e) {
                    $conn->rollback();
                    throw $e;
                }
                break;
        }
        
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = "error";
        
        // تسجيل الخطأ
        Logger::log('error', "خطأ في إدارة الحضور: " . $e->getMessage(), [
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
    $halqa_filter = (int)($_GET['halqa_filter'] ?? 0);
    $date_from = Security::sanitizeInput($_GET['date_from'] ?? date('Y-m-01')); // بداية الشهر الحالي
    $date_to = Security::sanitizeInput($_GET['date_to'] ?? date('Y-m-d')); // اليوم الحالي
    $status_filter = Security::sanitizeInput($_GET['status_filter'] ?? '');
    $student_filter = Security::sanitizeInput($_GET['student_filter'] ?? '');
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = 50;
    $offset = ($page - 1) * $limit;
    
    // جلب قائمة الحلقات للمستخدم الحالي
    $current_user = Auth::getCurrentUser();
    $halaqat_where = "";
    $halaqat_params = [];
    $halaqat_types = "";
    
    if ($current_user['role'] === 'teacher') {
        $halaqat_where = "WHERE h.teacher_id = ?";
        $halaqat_params[] = $current_user['id'];
        $halaqat_types = "i";
    } else {
        $halaqat_where = "WHERE h.status = 'active'";
    }
    
    $halaqat_sql = "
        SELECT h.id, h.name, h.teacher_id,
               u.full_name as teacher_name,
               (SELECT COUNT(*) FROM students s WHERE s.halqa_id = h.id AND s.status = 'active') as student_count
        FROM halaqat h
        LEFT JOIN users u ON h.teacher_id = u.id
        $halaqat_where
        ORDER BY h.name
    ";
    
    $stmt = $conn->prepare($halaqat_sql);
    if (!empty($halaqat_params)) {
        $stmt->bind_param($halaqat_types, ...$halaqat_params);
    }
    $stmt->execute();
    $halaqat = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // إذا لم يتم تحديد حلقة وكان المستخدم معلم، اختر أول حلقة له
    if ($halqa_filter == 0 && $current_user['role'] === 'teacher' && !empty($halaqat)) {
        $halqa_filter = $halaqat[0]['id'];
    }
    
    // جلب سجلات الحضور
    $attendance_records = [];
    $total_records = 0;
    
    if ($halqa_filter > 0) {
        // بناء استعلام البحث
        $where_conditions = ["a.halqa_id = ?"];
        $params = [$halqa_filter];
        $types = 'i';
        
        if (!empty($date_from)) {
            $where_conditions[] = "a.attendance_date >= ?";
            $params[] = $date_from;
            $types .= 's';
        }
        
        if (!empty($date_to)) {
            $where_conditions[] = "a.attendance_date <= ?";
            $params[] = $date_to;
            $types .= 's';
        }
        
        if (!empty($status_filter)) {
            $where_conditions[] = "a.status = ?";
            $params[] = $status_filter;
            $types .= 's';
        }
        
        if (!empty($student_filter)) {
            $where_conditions[] = "(s.full_name LIKE ? OR s.national_id LIKE ?)";
            $search_param = "%$student_filter%";
            $params[] = $search_param;
            $params[] = $search_param;
            $types .= 'ss';
        }
        
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        
        // جلب سجلات الحضور
        $sql = "
            SELECT a.*, s.full_name as student_name, s.national_id,
                   h.name as halqa_name,
                   u.full_name as recorded_by_name
            FROM attendance a
            JOIN students s ON a.student_id = s.id
            JOIN halaqat h ON a.halqa_id = h.id
            LEFT JOIN users u ON a.recorded_by = u.id
            $where_clause
            ORDER BY a.attendance_date DESC, s.full_name ASC
            LIMIT ? OFFSET ?
        ";
        
        $stmt = $conn->prepare($sql);
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $attendance_records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // عدد السجلات الإجمالي
        $count_sql = "
            SELECT COUNT(*) as total
            FROM attendance a
            JOIN students s ON a.student_id = s.id
            JOIN halaqat h ON a.halqa_id = h.id
            $where_clause
        ";
        
        $stmt = $conn->prepare($count_sql);
        $count_params = array_slice($params, 0, -2); // إزالة limit و offset
        $count_types = substr($types, 0, -2);
        $stmt->bind_param($count_types, ...$count_params);
        $stmt->execute();
        $total_records = $stmt->get_result()->fetch_assoc()['total'];
    }
    
    $total_pages = ceil($total_records / $limit);
    
    // إحصائيات سريعة
    $stats = [
        'total_today' => 0,
        'present_today' => 0,
        'absent_today' => 0,
        'late_today' => 0,
        'total_week' => 0,
        'present_week' => 0
    ];
    
    if ($halqa_filter > 0) {
        $today = date('Y-m-d');
        $week_start = date('Y-m-d', strtotime('monday this week'));
        
        // إحصائيات اليوم
        $stmt = $conn->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
                SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
                SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late
            FROM attendance 
            WHERE halqa_id = ? AND attendance_date = ?
        ");
        $stmt->bind_param("is", $halqa_filter, $today);
        $stmt->execute();
        $today_stats = $stmt->get_result()->fetch_assoc();
        
        $stats['total_today'] = $today_stats['total'] ?? 0;
        $stats['present_today'] = $today_stats['present'] ?? 0;
        $stats['absent_today'] = $today_stats['absent'] ?? 0;
        $stats['late_today'] = $today_stats['late'] ?? 0;
        
        // إحصائيات الأسبوع
        $stmt = $conn->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present
            FROM attendance 
            WHERE halqa_id = ? AND attendance_date >= ?
        ");
        $stmt->bind_param("is", $halqa_filter, $week_start);
        $stmt->execute();
        $week_stats = $stmt->get_result()->fetch_assoc();
        
        $stats['total_week'] = $week_stats['total'] ?? 0;
        $stats['present_week'] = $week_stats['present'] ?? 0;
    }
    
    // جلب طلاب الحلقة المحددة لتسجيل الحضور
    $students_for_attendance = [];
    if ($halqa_filter > 0) {
        $stmt = $conn->prepare("
            SELECT s.id, s.full_name, s.national_id,
                   a.status as today_status, a.notes as today_notes
            FROM students s
            LEFT JOIN attendance a ON s.id = a.student_id AND a.halqa_id = ? AND a.attendance_date = ?
            WHERE s.halqa_id = ? AND s.status = 'active'
            ORDER BY s.full_name
        ");
        $today = date('Y-m-d');
        $stmt->bind_param("isi", $halqa_filter, $today, $halqa_filter);
        $stmt->execute();
        $students_for_attendance = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
} catch (Exception $e) {
    $message = "خطأ في جلب البيانات: " . $e->getMessage();
    $messageType = "error";
    $halaqat = [];
    $attendance_records = [];
    $students_for_attendance = [];
    $stats = ['total_today' => 0, 'present_today' => 0, 'absent_today' => 0, 'late_today' => 0, 'total_week' => 0, 'present_week' => 0];
    $total_pages = 1;
}

$currentUser = Auth::getCurrentUser();
$pageTitle = "الحضور والغياب";
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
        
        .stats-card.success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }
        
        .stats-card.danger {
            background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
        }
        
        .stats-card.warning {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
            box-shadow: 0 4px 15px rgba(255, 193, 7, 0.3);
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
        
        .attendance-form {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,.1);
            margin-bottom: 20px;
        }
        
        .student-row {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            transition: all 0.3s ease;
        }
        
        .student-row:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }
        
        .status-present {
            background-color: #d4edda !important;
            border-left: 4px solid #28a745;
        }
        
        .status-absent {
            background-color: #f8d7da !important;
            border-left: 4px solid #dc3545;
        }
        
        .status-late {
            background-color: #fff3cd !important;
            border-left: 4px solid #ffc107;
        }
        
        .quick-actions {
            background: white;
            border-radius: 15px;
            padding: 15px;
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
            
            .stats-card {
                margin-bottom: 15px;
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
                            <a class="nav-link" href="courses.php">
                                <i class="fas fa-book me-2"></i>
                                إدارة المقررات
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if (Auth::hasPermission('manage_attendance')): ?>
                        <li class="nav-item">
                            <a class="nav-link active" href="attendance.php">
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
                        <i class="fas fa-calendar-check me-2"></i>
                        الحضور والغياب
                    </h1>
                    <?php if ($halqa_filter > 0): ?>
                    <button type="button" class="btn btn-primary" onclick="showBulkAttendanceModal()">
                        <i class="fas fa-users me-2"></i>
                        تسجيل حضور جماعي
                    </button>
                    <?php endif; ?>
                </div>

                <!-- بطاقات الإحصائيات -->
                <?php if ($halqa_filter > 0): ?>
                <div class="row mb-4">
                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                        <div class="stats-card text-center">
                            <div class="icon mb-2">
                                <i class="fas fa-calendar-day"></i>
                            </div>
                            <h3 class="mb-1"><?php echo number_format($stats['total_today']); ?></h3>
                            <p class="mb-0">إجمالي اليوم</p>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                        <div class="stats-card success text-center">
                            <div class="icon mb-2">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <h3 class="mb-1"><?php echo number_format($stats['present_today']); ?></h3>
                            <p class="mb-0">حاضر اليوم</p>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                        <div class="stats-card danger text-center">
                            <div class="icon mb-2">
                                <i class="fas fa-times-circle"></i>
                            </div>
                            <h3 class="mb-1"><?php echo number_format($stats['absent_today']); ?></h3>
                            <p class="mb-0">غائب اليوم</p>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                        <div class="stats-card warning text-center">
                            <div class="icon mb-2">
                                <i class="fas fa-clock"></i>
                            </div>
                            <h3 class="mb-1"><?php echo number_format($stats['late_today']); ?></h3>
                            <p class="mb-0">متأخر اليوم</p>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                        <div class="stats-card text-center">
                            <div class="icon mb-2">
                                <i class="fas fa-calendar-week"></i>
                            </div>
                            <h3 class="mb-1"><?php echo number_format($stats['total_week']); ?></h3>
                            <p class="mb-0">إجمالي الأسبوع</p>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                        <div class="stats-card success text-center">
                            <div class="icon mb-2">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <h3 class="mb-1">
                                <?php 
                                $attendance_rate = $stats['total_week'] > 0 ? round(($stats['present_week'] / $stats['total_week']) * 100) : 0;
                                echo $attendance_rate; 
                                ?>%
                            </h3>
                            <p class="mb-0">نسبة الحضور</p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- اختيار الحلقة -->
                <div class="search-box">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label for="halqa_filter" class="form-label">اختر الحلقة <span class="text-danger">*</span></label>
                            <select class="form-select" id="halqa_filter" name="halqa_filter" onchange="this.form.submit()">
                                <option value="">-- اختر الحلقة --</option>
                                <?php foreach ($halaqat as $halqa): ?>
                                <option value="<?php echo $halqa['id']; ?>" <?php echo $halqa_filter == $halqa['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($halqa['name']); ?>
                                    (<?php echo $halqa['student_count']; ?> طالب)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="date_from" class="form-label">من تاريخ</label>
                            <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                        </div>
                        <div class="col-md-2">
                            <label for="date_to" class="form-label">إلى تاريخ</label>
                            <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                        </div>
                        <div class="col-md-2">
                            <label for="status_filter" class="form-label">الحالة</label>
                            <select class="form-select" id="status_filter" name="status_filter">
                                <option value="">جميع الحالات</option>
                                <option value="present" <?php echo $status_filter === 'present' ? 'selected' : ''; ?>>حاضر</option>
                                <option value="absent" <?php echo $status_filter === 'absent' ? 'selected' : ''; ?>>غائب</option>
                                <option value="late" <?php echo $status_filter === 'late' ? 'selected' : ''; ?>>متأخر</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="student_filter" class="form-label">الطالب</label>
                            <input type="text" class="form-control" id="student_filter" name="student_filter" 
                                   placeholder="اسم الطالب..." value="<?php echo htmlspecialchars($student_filter); ?>">
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

                <!-- تسجيل الحضور اليومي -->
                <?php if ($halqa_filter > 0 && !empty($students_for_attendance)): ?>
                <div class="attendance-form">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">
                            <i class="fas fa-clipboard-check me-2"></i>
                            تسجيل الحضور لتاريخ <?php echo date('Y-m-d'); ?>
                        </h5>
                        <div class="quick-actions">
                            <button type="button" class="btn btn-sm btn-success me-2" onclick="markAllPresent()">
                                <i class="fas fa-check me-1"></i>الكل حاضر
                            </button>
                            <button type="button" class="btn btn-sm btn-danger me-2" onclick="markAllAbsent()">
                                <i class="fas fa-times me-1"></i>الكل غائب
                            </button>
                            <button type="button" class="btn btn-sm btn-secondary" onclick="clearAll()">
                                <i class="fas fa-eraser me-1"></i>مسح الكل
                            </button>
                        </div>
                    </div>
                    
                    <form method="POST" id="attendanceForm">
                        <input type="hidden" name="action" value="record_attendance">
                        <input type="hidden" name="halqa_id" value="<?php echo $halqa_filter; ?>">
                        <input type="hidden" name="attendance_date" value="<?php echo date('Y-m-d'); ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                        
                        <div class="row">
                            <?php foreach ($students_for_attendance as $student): ?>
                            <div class="col-lg-6 mb-3">
                                <div class="student-row <?php echo !empty($student['today_status']) ? 'status-' . $student['today_status'] : ''; ?>">
                                    <div class="row align-items-center">
                                        <div class="col-md-4">
                                            <strong><?php echo htmlspecialchars($student['full_name']); ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars($student['national_id']); ?></small>
                                        </div>
                                        <div class="col-md-4">
                                            <select class="form-select form-select-sm" name="attendance[<?php echo $student['id']; ?>][status]" 
                                                    onchange="updateStudentRowStyle(this)">
                                                <option value="">-- اختر الحالة --</option>
                                                <option value="present" <?php echo $student['today_status'] === 'present' ? 'selected' : ''; ?>>حاضر</option>
                                                <option value="absent" <?php echo $student['today_status'] === 'absent' ? 'selected' : ''; ?>>غائب</option>
                                                <option value="late" <?php echo $student['today_status'] === 'late' ? 'selected' : ''; ?>>متأخر</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <input type="text" class="form-control form-control-sm" 
                                                   name="attendance[<?php echo $student['id']; ?>][notes]" 
                                                   placeholder="ملاحظات..." 
                                                   value="<?php echo htmlspecialchars($student['today_notes'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="text-center mt-4">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-save me-2"></i>
                                حفظ الحضور
                            </button>
                        </div>
                    </form>
                </div>
                <?php elseif ($halqa_filter > 0): ?>
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-user-graduate fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">لا توجد طلاب في هذه الحلقة</h5>
                        <p class="text-muted">يجب إضافة طلاب للحلقة أولاً لتتمكن من تسجيل الحضور</p>
                        <a href="students.php?halqa_filter=<?php echo $halqa_filter; ?>" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>
                            إضافة طلاب للحلقة
                        </a>
                    </div>
                </div>
                <?php endif; ?>

                <!-- سجلات الحضور -->
                <?php if ($halqa_filter > 0): ?>
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-history me-2"></i>
                            سجلات الحضور
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($attendance_records)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">لا توجد سجلات حضور</h5>
                            <p class="text-muted">لم يتم العثور على سجلات حضور مطابقة لمعايير البحث</p>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>التاريخ</th>
                                        <th>الطالب</th>
                                        <th>الرقم الشخصي</th>
                                        <th>الحالة</th>
                                        <th>الملاحظات</th>
                                        <th>سجل بواسطة</th>
                                        <th>وقت التسجيل</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($attendance_records as $record): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo date('Y-m-d', strtotime($record['attendance_date'])); ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo date('l', strtotime($record['attendance_date'])); ?></small>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($record['student_name']); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($record['national_id']); ?></td>
                                        <td>
                                            <?php
                                            $statusColors = [
                                                'present' => 'success',
                                                'absent' => 'danger',
                                                'late' => 'warning'
                                            ];
                                            $statusNames = [
                                                'present' => 'حاضر',
                                                'absent' => 'غائب',
                                                'late' => 'متأخر'
                                            ];
                                            ?>
                                            <span class="badge bg-<?php echo $statusColors[$record['status']] ?? 'secondary'; ?>">
                                                <?php echo $statusNames[$record['status']] ?? $record['status']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (!empty($record['notes'])): ?>
                                                <small><?php echo htmlspecialchars($record['notes']); ?></small>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small><?php echo htmlspecialchars($record['recorded_by_name'] ?? 'غير محدد'); ?></small>
                                        </td>
                                        <td>
                                            <small><?php echo date('Y-m-d H:i', strtotime($record['created_at'])); ?></small>
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
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- نافذة تسجيل الحضور الجماعي -->
    <div class="modal fade" id="bulkAttendanceModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-users me-2"></i>
                        تسجيل حضور جماعي
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="bulkAttendanceForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="bulk_attendance">
                        <input type="hidden" name="halqa_id" value="<?php echo $halqa_filter; ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                        
                        <div class="mb-3">
                            <label for="bulk_attendance_date" class="form-label">التاريخ <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="bulk_attendance_date" name="attendance_date" 
                                   value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="bulk_status" class="form-label">الحالة <span class="text-danger">*</span></label>
                            <select class="form-select" id="bulk_status" name="bulk_status" required>
                                <option value="">-- اختر الحالة --</option>
                                <option value="present">حاضر</option>
                                <option value="absent">غائب</option>
                                <option value="late">متأخر</option>
                            </select>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>ملاحظة:</strong> سيتم تطبيق الحالة المحددة على جميع الطلاب النشطين في الحلقة.
                            يمكنك تعديل حالة طلاب محددين لاحقاً من خلال نموذج التسجيل اليومي.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>إلغاء
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>تسجيل الحضور الجماعي
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
        
        // تحديث شكل صف الطالب حسب الحالة
        function updateStudentRowStyle(selectElement) {
            const studentRow = selectElement.closest('.student-row');
            const status = selectElement.value;
            
            // إزالة جميع فئات الحالة
            studentRow.classList.remove('status-present', 'status-absent', 'status-late');
            
            // إضافة فئة الحالة الجديدة
            if (status) {
                studentRow.classList.add('status-' + status);
            }
        }
        
        // تحديد الكل كحاضر
        function markAllPresent() {
            const selects = document.querySelectorAll('select[name*="[status]"]');
            selects.forEach(function(select) {
                select.value = 'present';
                updateStudentRowStyle(select);
            });
        }
        
        // تحديد الكل كغائب
        function markAllAbsent() {
            const selects = document.querySelectorAll('select[name*="[status]"]');
            selects.forEach(function(select) {
                select.value = 'absent';
                updateStudentRowStyle(select);
            });
        }
        
        // مسح جميع الاختيارات
        function clearAll() {
            const selects = document.querySelectorAll('select[name*="[status]"]');
            const inputs = document.querySelectorAll('input[name*="[notes]"]');
            
            selects.forEach(function(select) {
                select.value = '';
                updateStudentRowStyle(select);
            });
            
            inputs.forEach(function(input) {
                input.value = '';
            });
        }
        
        // عرض نافذة الحضور الجماعي
        function showBulkAttendanceModal() {
            const modal = new bootstrap.Modal(document.getElementById('bulkAttendanceModal'));
            modal.show();
        }
        
        // التحقق من صحة النماذج
        document.addEventListener('DOMContentLoaded', function() {
            // التحقق من نموذج تسجيل الحضور
            const attendanceForm = document.getElementById('attendanceForm');
            if (attendanceForm) {
                attendanceForm.addEventListener('submit', function(e) {
                    const selects = document.querySelectorAll('select[name*="[status]"]');
                    let hasSelection = false;
                    
                    selects.forEach(function(select) {
                        if (select.value) {
                            hasSelection = true;
                        }
                    });
                    
                    if (!hasSelection) {
                        e.preventDefault();
                        alert('يجب تحديد حالة واحدة على الأقل');
                        return false;
                    }
                });
            }
            
            // التحقق من نموذج الحضور الجماعي
            const bulkForm = document.getElementById('bulkAttendanceForm');
            if (bulkForm) {
                bulkForm.addEventListener('submit', function(e) {
                    const date = document.getElementById('bulk_attendance_date').value;
                    const status = document.getElementById('bulk_status').value;
                    
                    if (!date || !status) {
                        e.preventDefault();
                        alert('يجب ملء جميع الحقول المطلوبة');
                        return false;
                    }
                    
                    const statusText = {
                        'present': 'حاضر',
                        'absent': 'غائب',
                        'late': 'متأخر'
                    };
                    
                    if (!confirm(`هل أنت متأكد من تسجيل جميع الطلاب كـ "${statusText[status]}" بتاريخ ${date}؟`)) {
                        e.preventDefault();
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

