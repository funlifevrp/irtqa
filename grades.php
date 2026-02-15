<?php
/**
 * صفحة إدارة الدرجات
 * تتضمن تسجيل درجات التسميع والاختبارات وتتبع التقدم الأكاديمي
 */

// تضمين ملف الإعدادات
require_once __DIR__ . '/config/config.php';

// التحقق من تسجيل الدخول
Auth::requireLogin();

// التحقق من الصلاحيات
Auth::requirePermission('manage_grades');

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
            case 'add_grade':
                // إضافة درجة جديدة
                $student_id = (int)$_POST['student_id'];
                $grade_type = Security::sanitizeInput($_POST['grade_type']);
                $grade_value = (float)$_POST['grade_value'];
                $max_grade = (float)$_POST['max_grade'];
                $grade_date = Security::sanitizeInput($_POST['grade_date']);
                $description = Security::sanitizeInput($_POST['description'] ?? '');
                $notes = Security::sanitizeInput($_POST['notes'] ?? '');
                
                // التحقق من صحة البيانات
                if ($grade_value < 0 || $grade_value > $max_grade) {
                    throw new Exception("الدرجة يجب أن تكون بين 0 و $max_grade");
                }
                
                if (!DateTime::createFromFormat('Y-m-d', $grade_date)) {
                    throw new Exception("تاريخ غير صحيح");
                }
                
                // التحقق من وجود الطالب
                $stmt = $conn->prepare("
                    SELECT s.full_name, h.name as halqa_name 
                    FROM students s 
                    JOIN halaqat h ON s.halqa_id = h.id 
                    WHERE s.id = ? AND s.status = 'active'
                ");
                $stmt->bind_param("i", $student_id);
                $stmt->execute();
                $student = $stmt->get_result()->fetch_assoc();
                
                if (!$student) {
                    throw new Exception("الطالب غير موجود أو معطل");
                }
                
                // إدراج الدرجة الجديدة
                $stmt = $conn->prepare("
                    INSERT INTO grades (
                        student_id, grade_type, grade_value, max_grade, 
                        grade_date, description, notes, recorded_by, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                
                $recorded_by = Auth::getCurrentUser()['id'];
                $stmt->bind_param("isddsssi", 
                    $student_id, $grade_type, $grade_value, $max_grade,
                    $grade_date, $description, $notes, $recorded_by
                );
                
                if ($stmt->execute()) {
                    $grade_id = $conn->insert_id;
                    
                    // تسجيل العملية في السجل
                    Logger::log('info', "تم إضافة درجة جديدة للطالب: {$student['full_name']} ($grade_value/$max_grade)", [
                        'user_id' => Auth::getCurrentUser()['id'],
                        'student_id' => $student_id,
                        'grade_id' => $grade_id,
                        'grade_type' => $grade_type,
                        'grade_value' => $grade_value,
                        'action' => 'add_grade'
                    ]);
                    
                    $message = "تم إضافة الدرجة بنجاح للطالب: {$student['full_name']}";
                    $messageType = "success";
                } else {
                    throw new Exception("فشل في إضافة الدرجة");
                }
                break;
                
            case 'edit_grade':
                // تعديل درجة
                $grade_id = (int)$_POST['grade_id'];
                $grade_value = (float)$_POST['grade_value'];
                $max_grade = (float)$_POST['max_grade'];
                $grade_date = Security::sanitizeInput($_POST['grade_date']);
                $description = Security::sanitizeInput($_POST['description'] ?? '');
                $notes = Security::sanitizeInput($_POST['notes'] ?? '');
                
                // التحقق من صحة البيانات
                if ($grade_value < 0 || $grade_value > $max_grade) {
                    throw new Exception("الدرجة يجب أن تكون بين 0 و $max_grade");
                }
                
                if (!DateTime::createFromFormat('Y-m-d', $grade_date)) {
                    throw new Exception("تاريخ غير صحيح");
                }
                
                // التحقق من وجود الدرجة
                $stmt = $conn->prepare("
                    SELECT g.*, s.full_name as student_name 
                    FROM grades g 
                    JOIN students s ON g.student_id = s.id 
                    WHERE g.id = ?
                ");
                $stmt->bind_param("i", $grade_id);
                $stmt->execute();
                $grade = $stmt->get_result()->fetch_assoc();
                
                if (!$grade) {
                    throw new Exception("الدرجة غير موجودة");
                }
                
                // تحديث الدرجة
                $stmt = $conn->prepare("
                    UPDATE grades SET 
                        grade_value = ?, max_grade = ?, grade_date = ?, 
                        description = ?, notes = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                
                $stmt->bind_param("ddsssi", 
                    $grade_value, $max_grade, $grade_date, 
                    $description, $notes, $grade_id
                );
                
                if ($stmt->execute()) {
                    // تسجيل العملية في السجل
                    Logger::log('info', "تم تعديل درجة الطالب: {$grade['student_name']} ($grade_value/$max_grade)", [
                        'user_id' => Auth::getCurrentUser()['id'],
                        'student_id' => $grade['student_id'],
                        'grade_id' => $grade_id,
                        'old_grade' => $grade['grade_value'],
                        'new_grade' => $grade_value,
                        'action' => 'edit_grade'
                    ]);
                    
                    $message = "تم تحديث الدرجة بنجاح";
                    $messageType = "success";
                } else {
                    throw new Exception("فشل في تحديث الدرجة");
                }
                break;
                
            case 'delete_grade':
                // حذف درجة
                $grade_id = (int)$_POST['grade_id'];
                
                // الحصول على بيانات الدرجة للسجل
                $stmt = $conn->prepare("
                    SELECT g.*, s.full_name as student_name 
                    FROM grades g 
                    JOIN students s ON g.student_id = s.id 
                    WHERE g.id = ?
                ");
                $stmt->bind_param("i", $grade_id);
                $stmt->execute();
                $grade = $stmt->get_result()->fetch_assoc();
                
                if (!$grade) {
                    throw new Exception("الدرجة غير موجودة");
                }
                
                // حذف الدرجة
                $stmt = $conn->prepare("DELETE FROM grades WHERE id = ?");
                $stmt->bind_param("i", $grade_id);
                
                if ($stmt->execute()) {
                    // تسجيل العملية في السجل
                    Logger::log('warning', "تم حذف درجة الطالب: {$grade['student_name']} ({$grade['grade_value']}/{$grade['max_grade']})", [
                        'user_id' => Auth::getCurrentUser()['id'],
                        'student_id' => $grade['student_id'],
                        'grade_id' => $grade_id,
                        'grade_type' => $grade['grade_type'],
                        'grade_value' => $grade['grade_value'],
                        'action' => 'delete_grade'
                    ]);
                    
                    $message = "تم حذف الدرجة بنجاح";
                    $messageType = "success";
                } else {
                    throw new Exception("فشل في حذف الدرجة");
                }
                break;
                
            case 'bulk_grades':
                // إضافة درجات جماعية
                $halqa_id = (int)$_POST['halqa_id'];
                $grade_type = Security::sanitizeInput($_POST['grade_type']);
                $max_grade = (float)$_POST['max_grade'];
                $grade_date = Security::sanitizeInput($_POST['grade_date']);
                $description = Security::sanitizeInput($_POST['description'] ?? '');
                $grades_data = $_POST['grades'] ?? [];
                
                // التحقق من صحة التاريخ
                if (!DateTime::createFromFormat('Y-m-d', $grade_date)) {
                    throw new Exception("تاريخ غير صحيح");
                }
                
                // بدء المعاملة
                $conn->begin_transaction();
                
                try {
                    $stmt = $conn->prepare("
                        INSERT INTO grades (
                            student_id, grade_type, grade_value, max_grade, 
                            grade_date, description, notes, recorded_by, created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    
                    $recorded_by = Auth::getCurrentUser()['id'];
                    $grades_count = 0;
                    
                    foreach ($grades_data as $student_id => $data) {
                        $student_id = (int)$student_id;
                        $grade_value = (float)($data['grade_value'] ?? 0);
                        $notes = Security::sanitizeInput($data['notes'] ?? '');
                        
                        // تخطي الطلاب بدون درجات
                        if ($grade_value <= 0) {
                            continue;
                        }
                        
                        // التحقق من صحة الدرجة
                        if ($grade_value > $max_grade) {
                            throw new Exception("درجة الطالب ID: $student_id تتجاوز الحد الأقصى ($max_grade)");
                        }
                        
                        // التحقق من أن الطالب ينتمي للحلقة
                        $check_stmt = $conn->prepare("SELECT id FROM students WHERE id = ? AND halqa_id = ? AND status = 'active'");
                        $check_stmt->bind_param("ii", $student_id, $halqa_id);
                        $check_stmt->execute();
                        
                        if ($check_stmt->get_result()->num_rows > 0) {
                            $stmt->bind_param("isddsssi", 
                                $student_id, $grade_type, $grade_value, $max_grade,
                                $grade_date, $description, $notes, $recorded_by
                            );
                            $stmt->execute();
                            $grades_count++;
                        }
                    }
                    
                    // تأكيد المعاملة
                    $conn->commit();
                    
                    // تسجيل العملية في السجل
                    Logger::log('info', "تم إضافة درجات جماعية ($grade_type) لـ $grades_count طالب بتاريخ: $grade_date", [
                        'user_id' => Auth::getCurrentUser()['id'],
                        'halqa_id' => $halqa_id,
                        'grade_type' => $grade_type,
                        'grade_date' => $grade_date,
                        'grades_count' => $grades_count,
                        'action' => 'bulk_grades'
                    ]);
                    
                    $message = "تم إضافة الدرجات بنجاح لـ $grades_count طالب";
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
        Logger::log('error', "خطأ في إدارة الدرجات: " . $e->getMessage(), [
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
    $student_filter = (int)($_GET['student_filter'] ?? 0);
    $grade_type_filter = Security::sanitizeInput($_GET['grade_type_filter'] ?? '');
    $date_from = Security::sanitizeInput($_GET['date_from'] ?? date('Y-m-01')); // بداية الشهر الحالي
    $date_to = Security::sanitizeInput($_GET['date_to'] ?? date('Y-m-d')); // اليوم الحالي
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
    
    // جلب قائمة الطلاب للحلقة المحددة
    $students = [];
    if ($halqa_filter > 0) {
        $stmt = $conn->prepare("
            SELECT id, full_name, national_id 
            FROM students 
            WHERE halqa_id = ? AND status = 'active' 
            ORDER BY full_name
        ");
        $stmt->bind_param("i", $halqa_filter);
        $stmt->execute();
        $students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    // جلب سجلات الدرجات
    $grades = [];
    $total_records = 0;
    
    if ($halqa_filter > 0 || $student_filter > 0) {
        // بناء استعلام البحث
        $where_conditions = [];
        $params = [];
        $types = '';
        
        if ($halqa_filter > 0) {
            $where_conditions[] = "s.halqa_id = ?";
            $params[] = $halqa_filter;
            $types .= 'i';
        }
        
        if ($student_filter > 0) {
            $where_conditions[] = "g.student_id = ?";
            $params[] = $student_filter;
            $types .= 'i';
        }
        
        if (!empty($grade_type_filter)) {
            $where_conditions[] = "g.grade_type = ?";
            $params[] = $grade_type_filter;
            $types .= 's';
        }
        
        if (!empty($date_from)) {
            $where_conditions[] = "g.grade_date >= ?";
            $params[] = $date_from;
            $types .= 's';
        }
        
        if (!empty($date_to)) {
            $where_conditions[] = "g.grade_date <= ?";
            $params[] = $date_to;
            $types .= 's';
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        // جلب سجلات الدرجات
        $sql = "
            SELECT g.*, s.full_name as student_name, s.national_id,
                   h.name as halqa_name,
                   u.full_name as recorded_by_name
            FROM grades g
            JOIN students s ON g.student_id = s.id
            JOIN halaqat h ON s.halqa_id = h.id
            LEFT JOIN users u ON g.recorded_by = u.id
            $where_clause
            ORDER BY g.grade_date DESC, s.full_name ASC
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
        $grades = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // عدد السجلات الإجمالي
        $count_sql = "
            SELECT COUNT(*) as total
            FROM grades g
            JOIN students s ON g.student_id = s.id
            JOIN halaqat h ON s.halqa_id = h.id
            $where_clause
        ";
        
        $stmt = $conn->prepare($count_sql);
        if (!empty($where_conditions)) {
            $count_params = array_slice($params, 0, -2); // إزالة limit و offset
            $count_types = substr($types, 0, -2);
            $stmt->bind_param($count_types, ...$count_params);
        }
        
        $stmt->execute();
        $total_records = $stmt->get_result()->fetch_assoc()['total'];
    }
    
    $total_pages = ceil($total_records / $limit);
    
    // إحصائيات سريعة
    $stats = [
        'total_grades' => 0,
        'avg_grade' => 0,
        'highest_grade' => 0,
        'lowest_grade' => 0,
        'recitation_count' => 0,
        'exam_count' => 0,
        'homework_count' => 0
    ];
    
    if ($halqa_filter > 0) {
        // إحصائيات عامة
        $stmt = $conn->prepare("
            SELECT 
                COUNT(*) as total_grades,
                AVG(g.grade_value / g.max_grade * 100) as avg_percentage,
                MAX(g.grade_value / g.max_grade * 100) as highest_percentage,
                MIN(g.grade_value / g.max_grade * 100) as lowest_percentage,
                SUM(CASE WHEN g.grade_type = 'recitation' THEN 1 ELSE 0 END) as recitation_count,
                SUM(CASE WHEN g.grade_type = 'exam' THEN 1 ELSE 0 END) as exam_count,
                SUM(CASE WHEN g.grade_type = 'homework' THEN 1 ELSE 0 END) as homework_count
            FROM grades g
            JOIN students s ON g.student_id = s.id
            WHERE s.halqa_id = ?
        ");
        $stmt->bind_param("i", $halqa_filter);
        $stmt->execute();
        $stats_result = $stmt->get_result()->fetch_assoc();
        
        $stats = [
            'total_grades' => $stats_result['total_grades'] ?? 0,
            'avg_grade' => round($stats_result['avg_percentage'] ?? 0, 1),
            'highest_grade' => round($stats_result['highest_percentage'] ?? 0, 1),
            'lowest_grade' => round($stats_result['lowest_percentage'] ?? 0, 1),
            'recitation_count' => $stats_result['recitation_count'] ?? 0,
            'exam_count' => $stats_result['exam_count'] ?? 0,
            'homework_count' => $stats_result['homework_count'] ?? 0
        ];
    }
    
    // جلب طلاب الحلقة لإضافة الدرجات الجماعية
    $students_for_bulk = [];
    if ($halqa_filter > 0) {
        $stmt = $conn->prepare("
            SELECT s.id, s.full_name, s.national_id
            FROM students s
            WHERE s.halqa_id = ? AND s.status = 'active'
            ORDER BY s.full_name
        ");
        $stmt->bind_param("i", $halqa_filter);
        $stmt->execute();
        $students_for_bulk = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
} catch (Exception $e) {
    $message = "خطأ في جلب البيانات: " . $e->getMessage();
    $messageType = "error";
    $halaqat = [];
    $students = [];
    $grades = [];
    $students_for_bulk = [];
    $stats = ['total_grades' => 0, 'avg_grade' => 0, 'highest_grade' => 0, 'lowest_grade' => 0, 'recitation_count' => 0, 'exam_count' => 0, 'homework_count' => 0];
    $total_pages = 1;
}

$currentUser = Auth::getCurrentUser();
$pageTitle = "إدارة الدرجات";
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
        
        .stats-card.warning {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
            box-shadow: 0 4px 15px rgba(255, 193, 7, 0.3);
        }
        
        .stats-card.info {
            background: linear-gradient(135deg, #17a2b8 0%, #6f42c1 100%);
            box-shadow: 0 4px 15px rgba(23, 162, 184, 0.3);
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
        
        .grade-badge {
            font-size: 1.1em;
            font-weight: bold;
            min-width: 60px;
            text-align: center;
        }
        
        .grade-excellent {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        }
        
        .grade-good {
            background: linear-gradient(135deg, #17a2b8 0%, #6f42c1 100%);
        }
        
        .grade-average {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
        }
        
        .grade-poor {
            background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);
        }
        
        .student-grade-row {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            transition: all 0.3s ease;
        }
        
        .student-grade-row:hover {
            background: #e9ecef;
            transform: translateX(5px);
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
                            <a class="nav-link" href="attendance.php">
                                <i class="fas fa-calendar-check me-2"></i>
                                الحضور والغياب
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if (Auth::hasPermission('manage_grades')): ?>
                        <li class="nav-item">
                            <a class="nav-link active" href="grades.php">
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
                        <i class="fas fa-chart-line me-2"></i>
                        إدارة الدرجات
                    </h1>
                    <div>
                        <?php if ($halqa_filter > 0): ?>
                        <button type="button" class="btn btn-success me-2" onclick="showBulkGradesModal()">
                            <i class="fas fa-users me-2"></i>
                            درجات جماعية
                        </button>
                        <?php endif; ?>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addGradeModal">
                            <i class="fas fa-plus me-2"></i>
                            إضافة درجة
                        </button>
                    </div>
                </div>

                <!-- بطاقات الإحصائيات -->
                <?php if ($halqa_filter > 0): ?>
                <div class="row mb-4">
                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                        <div class="stats-card text-center">
                            <div class="icon mb-2">
                                <i class="fas fa-chart-bar"></i>
                            </div>
                            <h3 class="mb-1"><?php echo number_format($stats['total_grades']); ?></h3>
                            <p class="mb-0">إجمالي الدرجات</p>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                        <div class="stats-card success text-center">
                            <div class="icon mb-2">
                                <i class="fas fa-percentage"></i>
                            </div>
                            <h3 class="mb-1"><?php echo $stats['avg_grade']; ?>%</h3>
                            <p class="mb-0">المتوسط العام</p>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                        <div class="stats-card warning text-center">
                            <div class="icon mb-2">
                                <i class="fas fa-arrow-up"></i>
                            </div>
                            <h3 class="mb-1"><?php echo $stats['highest_grade']; ?>%</h3>
                            <p class="mb-0">أعلى درجة</p>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                        <div class="stats-card info text-center">
                            <div class="icon mb-2">
                                <i class="fas fa-microphone"></i>
                            </div>
                            <h3 class="mb-1"><?php echo number_format($stats['recitation_count']); ?></h3>
                            <p class="mb-0">درجات التسميع</p>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                        <div class="stats-card text-center">
                            <div class="icon mb-2">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <h3 class="mb-1"><?php echo number_format($stats['exam_count']); ?></h3>
                            <p class="mb-0">درجات الاختبارات</p>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                        <div class="stats-card info text-center">
                            <div class="icon mb-2">
                                <i class="fas fa-home"></i>
                            </div>
                            <h3 class="mb-1"><?php echo number_format($stats['homework_count']); ?></h3>
                            <p class="mb-0">الواجبات</p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- مربع البحث والتصفية -->
                <div class="search-box">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label for="halqa_filter" class="form-label">الحلقة</label>
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
                            <label for="student_filter" class="form-label">الطالب</label>
                            <select class="form-select" id="student_filter" name="student_filter">
                                <option value="">-- جميع الطلاب --</option>
                                <?php foreach ($students as $student): ?>
                                <option value="<?php echo $student['id']; ?>" <?php echo $student_filter == $student['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($student['full_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="grade_type_filter" class="form-label">نوع الدرجة</label>
                            <select class="form-select" id="grade_type_filter" name="grade_type_filter">
                                <option value="">جميع الأنواع</option>
                                <option value="recitation" <?php echo $grade_type_filter === 'recitation' ? 'selected' : ''; ?>>تسميع</option>
                                <option value="exam" <?php echo $grade_type_filter === 'exam' ? 'selected' : ''; ?>>اختبار</option>
                                <option value="homework" <?php echo $grade_type_filter === 'homework' ? 'selected' : ''; ?>>واجب</option>
                                <option value="participation" <?php echo $grade_type_filter === 'participation' ? 'selected' : ''; ?>>مشاركة</option>
                                <option value="behavior" <?php echo $grade_type_filter === 'behavior' ? 'selected' : ''; ?>>سلوك</option>
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

                <!-- سجلات الدرجات -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-list me-2"></i>
                            سجلات الدرجات
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($grades)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">لا توجد درجات</h5>
                            <p class="text-muted">لم يتم العثور على درجات مطابقة لمعايير البحث</p>
                            <?php if ($halqa_filter > 0): ?>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addGradeModal">
                                <i class="fas fa-plus me-2"></i>
                                إضافة درجة جديدة
                            </button>
                            <?php endif; ?>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>التاريخ</th>
                                        <th>الطالب</th>
                                        <th>الحلقة</th>
                                        <th>نوع الدرجة</th>
                                        <th>الدرجة</th>
                                        <th>النسبة المئوية</th>
                                        <th>الوصف</th>
                                        <th>الملاحظات</th>
                                        <th>سجل بواسطة</th>
                                        <th>الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($grades as $grade): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo date('Y-m-d', strtotime($grade['grade_date'])); ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo date('l', strtotime($grade['grade_date'])); ?></small>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($grade['student_name']); ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars($grade['national_id']); ?></small>
                                        </td>
                                        <td>
                                            <small><?php echo htmlspecialchars($grade['halqa_name']); ?></small>
                                        </td>
                                        <td>
                                            <?php
                                            $gradeTypeColors = [
                                                'recitation' => 'primary',
                                                'exam' => 'success',
                                                'homework' => 'info',
                                                'participation' => 'warning',
                                                'behavior' => 'secondary'
                                            ];
                                            $gradeTypeNames = [
                                                'recitation' => 'تسميع',
                                                'exam' => 'اختبار',
                                                'homework' => 'واجب',
                                                'participation' => 'مشاركة',
                                                'behavior' => 'سلوك'
                                            ];
                                            ?>
                                            <span class="badge bg-<?php echo $gradeTypeColors[$grade['grade_type']] ?? 'secondary'; ?>">
                                                <?php echo $gradeTypeNames[$grade['grade_type']] ?? $grade['grade_type']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong><?php echo $grade['grade_value']; ?> / <?php echo $grade['max_grade']; ?></strong>
                                        </td>
                                        <td>
                                            <?php
                                            $percentage = ($grade['grade_value'] / $grade['max_grade']) * 100;
                                            $badgeClass = 'grade-poor';
                                            if ($percentage >= 90) $badgeClass = 'grade-excellent';
                                            elseif ($percentage >= 75) $badgeClass = 'grade-good';
                                            elseif ($percentage >= 60) $badgeClass = 'grade-average';
                                            ?>
                                            <span class="badge grade-badge <?php echo $badgeClass; ?>">
                                                <?php echo round($percentage, 1); ?>%
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (!empty($grade['description'])): ?>
                                                <small><?php echo htmlspecialchars($grade['description']); ?></small>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($grade['notes'])): ?>
                                                <small><?php echo htmlspecialchars($grade['notes']); ?></small>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small><?php echo htmlspecialchars($grade['recorded_by_name'] ?? 'غير محدد'); ?></small>
                                            <br>
                                            <small class="text-muted"><?php echo date('Y-m-d H:i', strtotime($grade['created_at'])); ?></small>
                                        </td>
                                        <td>
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="dropdown">
                                                    <i class="fas fa-ellipsis-v"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li>
                                                        <a class="dropdown-item" href="#" onclick="editGrade(<?php echo htmlspecialchars(json_encode($grade)); ?>)">
                                                            <i class="fas fa-edit me-2"></i>تعديل
                                                        </a>
                                                    </li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <a class="dropdown-item text-danger" href="#" onclick="deleteGrade(<?php echo $grade['id']; ?>, '<?php echo htmlspecialchars($grade['student_name']); ?>', '<?php echo $grade['grade_value']; ?>/<?php echo $grade['max_grade']; ?>')">
                                                            <i class="fas fa-trash me-2"></i>حذف
                                                        </a>
                                                    </li>
                                                </ul>
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

    <!-- نافذة إضافة درجة جديدة -->
    <div class="modal fade" id="addGradeModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-plus me-2"></i>
                        إضافة درجة جديدة
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="addGradeForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_grade">
                        <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="add_halqa_select" class="form-label">الحلقة <span class="text-danger">*</span></label>
                                <select class="form-select" id="add_halqa_select" onchange="loadStudentsForGrade(this.value, 'add_student_id')" required>
                                    <option value="">-- اختر الحلقة --</option>
                                    <?php foreach ($halaqat as $halqa): ?>
                                    <option value="<?php echo $halqa['id']; ?>" <?php echo $halqa_filter == $halqa['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($halqa['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="add_student_id" class="form-label">الطالب <span class="text-danger">*</span></label>
                                <select class="form-select" id="add_student_id" name="student_id" required>
                                    <option value="">-- اختر الطالب --</option>
                                    <?php if ($halqa_filter > 0): ?>
                                        <?php foreach ($students as $student): ?>
                                        <option value="<?php echo $student['id']; ?>">
                                            <?php echo htmlspecialchars($student['full_name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="add_grade_type" class="form-label">نوع الدرجة <span class="text-danger">*</span></label>
                                <select class="form-select" id="add_grade_type" name="grade_type" required>
                                    <option value="">-- اختر النوع --</option>
                                    <option value="recitation">تسميع</option>
                                    <option value="exam">اختبار</option>
                                    <option value="homework">واجب</option>
                                    <option value="participation">مشاركة</option>
                                    <option value="behavior">سلوك</option>
                                </select>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="add_grade_value" class="form-label">الدرجة المحصلة <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="add_grade_value" name="grade_value" 
                                       min="0" step="0.5" required>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="add_max_grade" class="form-label">الدرجة الكاملة <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="add_max_grade" name="max_grade" 
                                       min="1" step="0.5" value="10" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="add_grade_date" class="form-label">تاريخ الدرجة <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="add_grade_date" name="grade_date" 
                                       value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="add_description" class="form-label">الوصف</label>
                                <input type="text" class="form-control" id="add_description" name="description" 
                                       placeholder="مثال: اختبار الوحدة الأولى">
                            </div>
                            
                            <div class="col-12 mb-3">
                                <label for="add_notes" class="form-label">ملاحظات</label>
                                <textarea class="form-control" id="add_notes" name="notes" rows="3" 
                                          placeholder="ملاحظات إضافية حول الدرجة..."></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>إلغاء
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>إضافة الدرجة
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- نافذة تعديل الدرجة -->
    <div class="modal fade" id="editGradeModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>
                        تعديل الدرجة
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editGradeForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit_grade">
                        <input type="hidden" name="grade_id" id="edit_grade_id">
                        <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                        
                        <div class="row">
                            <div class="col-12 mb-3">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>الطالب:</strong> <span id="edit_student_name"></span>
                                    <br>
                                    <strong>نوع الدرجة:</strong> <span id="edit_grade_type_display"></span>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="edit_grade_value" class="form-label">الدرجة المحصلة <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="edit_grade_value" name="grade_value" 
                                       min="0" step="0.5" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="edit_max_grade" class="form-label">الدرجة الكاملة <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="edit_max_grade" name="max_grade" 
                                       min="1" step="0.5" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="edit_grade_date" class="form-label">تاريخ الدرجة <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="edit_grade_date" name="grade_date" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="edit_description" class="form-label">الوصف</label>
                                <input type="text" class="form-control" id="edit_description" name="description">
                            </div>
                            
                            <div class="col-12 mb-3">
                                <label for="edit_notes" class="form-label">ملاحظات</label>
                                <textarea class="form-control" id="edit_notes" name="notes" rows="3"></textarea>
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

    <!-- نافذة الدرجات الجماعية -->
    <div class="modal fade" id="bulkGradesModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-users me-2"></i>
                        إضافة درجات جماعية
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="bulkGradesForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="bulk_grades">
                        <input type="hidden" name="halqa_id" value="<?php echo $halqa_filter; ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                        
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <label for="bulk_grade_type" class="form-label">نوع الدرجة <span class="text-danger">*</span></label>
                                <select class="form-select" id="bulk_grade_type" name="grade_type" required>
                                    <option value="">-- اختر النوع --</option>
                                    <option value="recitation">تسميع</option>
                                    <option value="exam">اختبار</option>
                                    <option value="homework">واجب</option>
                                    <option value="participation">مشاركة</option>
                                    <option value="behavior">سلوك</option>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <label for="bulk_max_grade" class="form-label">الدرجة الكاملة <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="bulk_max_grade" name="max_grade" 
                                       min="1" step="0.5" value="10" required>
                            </div>
                            
                            <div class="col-md-3">
                                <label for="bulk_grade_date" class="form-label">التاريخ <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="bulk_grade_date" name="grade_date" 
                                       value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="bulk_description" class="form-label">الوصف</label>
                                <input type="text" class="form-control" id="bulk_description" name="description" 
                                       placeholder="مثال: اختبار الوحدة الأولى">
                            </div>
                        </div>
                        
                        <h6 class="mb-3">
                            <i class="fas fa-list me-2"></i>
                            درجات الطلاب
                        </h6>
                        
                        <div class="row">
                            <?php foreach ($students_for_bulk as $student): ?>
                            <div class="col-lg-6 mb-3">
                                <div class="student-grade-row">
                                    <div class="row align-items-center">
                                        <div class="col-md-6">
                                            <strong><?php echo htmlspecialchars($student['full_name']); ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars($student['national_id']); ?></small>
                                        </div>
                                        <div class="col-md-3">
                                            <input type="number" class="form-control form-control-sm" 
                                                   name="grades[<?php echo $student['id']; ?>][grade_value]" 
                                                   placeholder="الدرجة" min="0" step="0.5">
                                        </div>
                                        <div class="col-md-3">
                                            <input type="text" class="form-control form-control-sm" 
                                                   name="grades[<?php echo $student['id']; ?>][notes]" 
                                                   placeholder="ملاحظات">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>ملاحظة:</strong> سيتم إضافة الدرجات للطلاب الذين تم إدخال درجات لهم فقط.
                            الطلاب بدون درجات سيتم تجاهلهم.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>إلغاء
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>حفظ الدرجات
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- نافذة تأكيد الحذف -->
    <div class="modal fade" id="deleteGradeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        تأكيد حذف الدرجة
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="deleteGradeForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete_grade">
                        <input type="hidden" name="grade_id" id="delete_grade_id">
                        <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                        
                        <div class="text-center">
                            <i class="fas fa-chart-line fa-3x text-danger mb-3"></i>
                            <h5>هل أنت متأكد من حذف هذه الدرجة؟</h5>
                            <p class="text-muted mb-3">
                                <strong>الطالب:</strong> <span id="delete_student_name"></span>
                                <br>
                                <strong>الدرجة:</strong> <span id="delete_grade_value"></span>
                            </p>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>تحذير:</strong> هذا الإجراء لا يمكن التراجع عنه
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>إلغاء
                        </button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash me-2"></i>حذف الدرجة
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
        // بيانات الطلاب لكل حلقة
        const halaqatStudents = <?php echo json_encode(array_reduce($halaqat, function($carry, $halqa) use ($conn) {
            $stmt = $conn->prepare("SELECT id, full_name FROM students WHERE halqa_id = ? AND status = 'active' ORDER BY full_name");
            $stmt->bind_param("i", $halqa['id']);
            $stmt->execute();
            $carry[$halqa['id']] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            return $carry;
        }, [])); ?>;
        
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
        
        // تحميل طلاب الحلقة
        function loadStudentsForGrade(halqaId, selectId) {
            const studentSelect = document.getElementById(selectId);
            studentSelect.innerHTML = '<option value="">-- اختر الطالب --</option>';
            
            if (halqaId && halaqatStudents[halqaId]) {
                halaqatStudents[halqaId].forEach(function(student) {
                    const option = document.createElement('option');
                    option.value = student.id;
                    option.textContent = student.full_name;
                    studentSelect.appendChild(option);
                });
            }
        }
        
        // تعديل درجة
        function editGrade(grade) {
            const gradeTypeNames = {
                'recitation': 'تسميع',
                'exam': 'اختبار',
                'homework': 'واجب',
                'participation': 'مشاركة',
                'behavior': 'سلوك'
            };
            
            // ملء البيانات في النموذج
            document.getElementById('edit_grade_id').value = grade.id;
            document.getElementById('edit_student_name').textContent = grade.student_name;
            document.getElementById('edit_grade_type_display').textContent = gradeTypeNames[grade.grade_type] || grade.grade_type;
            document.getElementById('edit_grade_value').value = grade.grade_value;
            document.getElementById('edit_max_grade').value = grade.max_grade;
            document.getElementById('edit_grade_date').value = grade.grade_date;
            document.getElementById('edit_description').value = grade.description || '';
            document.getElementById('edit_notes').value = grade.notes || '';
            
            // عرض النافذة
            const modal = new bootstrap.Modal(document.getElementById('editGradeModal'));
            modal.show();
        }
        
        // حذف درجة
        function deleteGrade(gradeId, studentName, gradeValue) {
            document.getElementById('delete_grade_id').value = gradeId;
            document.getElementById('delete_student_name').textContent = studentName;
            document.getElementById('delete_grade_value').textContent = gradeValue;
            
            const modal = new bootstrap.Modal(document.getElementById('deleteGradeModal'));
            modal.show();
        }
        
        // عرض نافذة الدرجات الجماعية
        function showBulkGradesModal() {
            const modal = new bootstrap.Modal(document.getElementById('bulkGradesModal'));
            modal.show();
        }
        
        // التحقق من صحة النماذج
        document.addEventListener('DOMContentLoaded', function() {
            // التحقق من نموذج إضافة الدرجة
            const addForm = document.getElementById('addGradeForm');
            if (addForm) {
                addForm.addEventListener('submit', function(e) {
                    const gradeValue = parseFloat(document.getElementById('add_grade_value').value);
                    const maxGrade = parseFloat(document.getElementById('add_max_grade').value);
                    
                    if (gradeValue > maxGrade) {
                        e.preventDefault();
                        alert('الدرجة المحصلة لا يمكن أن تكون أكبر من الدرجة الكاملة');
                        return false;
                    }
                });
            }
            
            // التحقق من نموذج تعديل الدرجة
            const editForm = document.getElementById('editGradeForm');
            if (editForm) {
                editForm.addEventListener('submit', function(e) {
                    const gradeValue = parseFloat(document.getElementById('edit_grade_value').value);
                    const maxGrade = parseFloat(document.getElementById('edit_max_grade').value);
                    
                    if (gradeValue > maxGrade) {
                        e.preventDefault();
                        alert('الدرجة المحصلة لا يمكن أن تكون أكبر من الدرجة الكاملة');
                        return false;
                    }
                });
            }
            
            // التحقق من نموذج الدرجات الجماعية
            const bulkForm = document.getElementById('bulkGradesForm');
            if (bulkForm) {
                bulkForm.addEventListener('submit', function(e) {
                    const maxGrade = parseFloat(document.getElementById('bulk_max_grade').value);
                    const gradeInputs = document.querySelectorAll('input[name*="[grade_value]"]');
                    let hasGrades = false;
                    let hasError = false;
                    
                    gradeInputs.forEach(function(input) {
                        const value = parseFloat(input.value);
                        if (value > 0) {
                            hasGrades = true;
                            if (value > maxGrade) {
                                hasError = true;
                                input.style.borderColor = '#dc3545';
                            } else {
                                input.style.borderColor = '';
                            }
                        }
                    });
                    
                    if (!hasGrades) {
                        e.preventDefault();
                        alert('يجب إدخال درجة واحدة على الأقل');
                        return false;
                    }
                    
                    if (hasError) {
                        e.preventDefault();
                        alert('بعض الدرجات تتجاوز الدرجة الكاملة المحددة');
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

