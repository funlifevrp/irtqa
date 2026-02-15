<?php
/**
 * لوحة التحكم الرئيسية
 * Halqat Management System v3.0
 */

// تحميل الإعدادات
require_once __DIR__ . '/config/config.php';

// التحقق من تسجيل الدخول
$auth = Auth::getInstance();
$auth->requireLogin();

$currentUser = $auth->getCurrentUser();
$db = Database::getInstance();

// الحصول على الإحصائيات
try {
    $stats = [
        'total_students' => 0,
        'total_halaqat' => 0,
        'total_teachers' => 0,
        'total_supervisors' => 0,
        'active_students' => 0,
        'today_attendance' => 0
    ];

    // إحصائيات الطلاب
    $result = $db->selectOne("SELECT COUNT(*) as count FROM students WHERE is_active = 1");
    $stats['total_students'] = (int) $result['count'];

    $result = $db->selectOne("SELECT COUNT(*) as count FROM students WHERE is_active = 1 AND status = 'نشط'");
    $stats['active_students'] = (int) $result['count'];

    // إحصائيات الحلقات
    $result = $db->selectOne("SELECT COUNT(*) as count FROM halaqat WHERE is_active = 1");
    $stats['total_halaqat'] = (int) $result['count'];

    // إحصائيات المعلمين والمشرفين
    $result = $db->selectOne("SELECT COUNT(*) as count FROM users WHERE role = 'معلم' AND is_active = 1");
    $stats['total_teachers'] = (int) $result['count'];

    $result = $db->selectOne("SELECT COUNT(*) as count FROM users WHERE role = 'مشرف' AND is_active = 1");
    $stats['total_supervisors'] = (int) $result['count'];

    // حضور اليوم
    $result = $db->selectOne("SELECT COUNT(*) as count FROM attendance WHERE date = CURDATE() AND status = 'حاضر'");
    $stats['today_attendance'] = (int) $result['count'];
    // الحصول على النشاطات الأخيرة
    try {
        $recentActivities = $db->select(
            "SELECT al.*, u.full_name as user_name 
             FROM audit_log al 
             LEFT JOIN users u ON al.user_id = u.id 
             ORDER BY al.created_at DESC 
             LIMIT 10"
        );
        
        // التأكد من أن النتيجة مصفوفة
        if (!is_array($recentActivities)) {
            $recentActivities = [];
        }
    } catch (Exception $e) {
        $recentActivities = [];
        error_log("Dashboard activities error: " . $e->getMessage());
    }

    // الحصول على الحلقات النشطة
    try {
        $activeHalaqat = $db->select(
            "SELECT h.*, 
                    u1.full_name as supervisor_name,
                    u2.full_name as teacher_name,
                    (SELECT COUNT(*) FROM students s WHERE s.halqa_id = h.id AND s.is_active = 1) as student_count
             FROM halaqat h
             LEFT JOIN users u1 ON h.supervisor_id = u1.id
             LEFT JOIN users u2 ON h.teacher_id = u2.id
             WHERE h.is_active = 1
             ORDER BY h.created_at DESC
             LIMIT 5"
        );
        
        // التأكد من أن النتيجة مصفوفة
        if (!is_array($activeHalaqat)) {
            $activeHalaqat = [];
        }
    } catch (Exception $e) {
        $activeHalaqat = [];
        error_log("Dashboard halaqat error: " . $e->getMessage());
    }

} catch (Exception $e) {
    Logger::error('Dashboard stats error: ' . $e->getMessage());
    $stats = array_fill_keys(array_keys($stats), 0);
    $recentActivities = [];
    $activeHalaqat = [];
}

// حساب نسبة الحضور اليوم
$attendancePercentage = 0;
if ($stats['active_students'] > 0) {
    $attendancePercentage = round(($stats['today_attendance'] / $stats['active_students']) * 100, 1);
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة التحكم - <?php echo SYSTEM_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/chart.js@4.0.0/dist/chart.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4f46e5;
            --secondary-color: #7c3aed;
            --success-color: #059669;
            --warning-color: #d97706;
            --danger-color: #dc2626;
            --info-color: #0284c7;
            --dark-color: #1f2937;
            --light-color: #f8fafc;
        }

        body {
            background-color: #f1f5f9;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .sidebar {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            min-height: 100vh;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
        }

        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 12px 20px;
            margin: 2px 10px;
            border-radius: 10px;
            transition: all 0.3s ease;
        }

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: white;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
        }

        .main-content {
            padding: 20px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: none;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
            margin-bottom: 15px;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--dark-color);
            margin: 0;
        }

        .stat-label {
            color: #64748b;
            font-weight: 500;
            margin: 0;
        }

        .chart-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 20px;
        }

        .activity-item {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 10px;
            background: #f8fafc;
            border-right: 4px solid var(--primary-color);
        }

        .activity-time {
            font-size: 0.85rem;
            color: #64748b;
        }

        .halqa-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }

        .halqa-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        .progress-circle {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
            font-size: 1.2rem;
        }

        .navbar-brand {
            font-weight: 700;
            color: var(--dark-color) !important;
        }

        .user-info {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .quick-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .quick-action-btn {
            flex: 1;
            min-width: 120px;
            padding: 12px;
            border-radius: 10px;
            text-decoration: none;
            text-align: center;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .quick-action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                top: 0;
                right: -100%;
                width: 280px;
                z-index: 1050;
                transition: right 0.3s ease;
            }

            .sidebar.show {
                right: 0;
            }

            .main-content {
                margin-right: 0;
            }

            .stat-card {
                margin-bottom: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- الشريط الجانبي -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4">
                        <h5 class="text-white mb-0"><?php echo SYSTEM_NAME; ?></h5>
                        <small class="text-white-50"><?php echo ORGANIZATION_NAME; ?></small>
                    </div>

                    <div class="user-info text-center">
                        <div class="mb-2">
                            <i class="bi bi-person-circle text-primary" style="font-size: 2rem;"></i>
                        </div>
                        <h6 class="mb-1"><?php echo htmlspecialchars($currentUser['full_name']); ?></h6>
                        <small class="text-muted"><?php echo htmlspecialchars($currentUser['role']); ?></small>
                    </div>

                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="dashboard.php">
                                <i class="bi bi-speedometer2 me-2"></i>
                                لوحة التحكم
                            </a>
                        </li>

                        <?php if ($auth->hasPermission('manage_users')): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="users.php">
                                <i class="bi bi-people me-2"></i>
                                إدارة المستخدمين
                            </a>
                        </li>
                        <?php endif; ?>

                        <?php if ($auth->hasPermission('manage_halaqat')): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="halaqat.php">
                                <i class="bi bi-collection me-2"></i>
                                إدارة الحلقات
                            </a>
                        </li>
                        <?php endif; ?>

                        <?php if ($auth->hasPermission('manage_students')): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="students.php">
                                <i class="bi bi-person-badge me-2"></i>
                                إدارة الطلاب
                            </a>
                        </li>
                        <?php endif; ?>

                        <?php if ($auth->hasPermission('manage_courses')): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="courses.php">
                                <i class="bi bi-book me-2"></i>
                                إدارة المقررات
                            </a>
                        </li>
                        <?php endif; ?>

                        <?php if ($auth->hasPermission('manage_attendance')): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="attendance.php">
                                <i class="bi bi-calendar-check me-2"></i>
                                الحضور والغياب
                            </a>
                        </li>
                        <?php endif; ?>

                        <?php if ($auth->hasPermission('manage_grades')): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="grades.php">
                                <i class="bi bi-award me-2"></i>
                                الدرجات والتقييمات
                            </a>
                        </li>
                        <?php endif; ?>

                        <?php if ($auth->hasPermission('view_reports')): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="reports.php">
                                <i class="bi bi-graph-up me-2"></i>
                                التقارير
                            </a>
                        </li>
                        <?php endif; ?>

                        <?php if ($auth->hasPermission('manage_settings')): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="settings.php">
                                <i class="bi bi-gear me-2"></i>
                                الإعدادات
                            </a>
                        </li>
                        <?php endif; ?>

                        <li class="nav-item mt-3">
                            <a class="nav-link" href="logout.php">
                                <i class="bi bi-box-arrow-right me-2"></i>
                                تسجيل الخروج
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- المحتوى الرئيسي -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <!-- شريط التنقل العلوي -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">لوحة التحكم</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-calendar3"></i>
                                <?php echo date('Y-m-d'); ?>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- بطاقات الإحصائيات -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: linear-gradient(135deg, var(--info-color), #0ea5e9);">
                                <i class="bi bi-people-fill"></i>
                            </div>
                            <h3 class="stat-number"><?php echo number_format($stats['total_students']); ?></h3>
                            <p class="stat-label">إجمالي الطلاب</p>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: linear-gradient(135deg, var(--success-color), #10b981);">
                                <i class="bi bi-collection-fill"></i>
                            </div>
                            <h3 class="stat-number"><?php echo number_format($stats['total_halaqat']); ?></h3>
                            <p class="stat-label">الحلقات النشطة</p>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: linear-gradient(135deg, var(--warning-color), #f59e0b);">
                                <i class="bi bi-person-check-fill"></i>
                            </div>
                            <h3 class="stat-number"><?php echo number_format($stats['today_attendance']); ?></h3>
                            <p class="stat-label">حضور اليوم</p>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));">
                                <i class="bi bi-mortarboard-fill"></i>
                            </div>
                            <h3 class="stat-number"><?php echo $stats['total_teachers'] + $stats['total_supervisors']; ?></h3>
                            <p class="stat-label">المعلمين والمشرفين</p>
                        </div>
                    </div>
                </div>

                <!-- نسبة الحضور اليوم -->
                <div class="row mb-4">
                    <div class="col-md-8">
                        <div class="chart-card">
                            <h5 class="mb-3">نسبة الحضور اليوم</h5>
                            <div class="d-flex align-items-center">
                                <div class="progress-circle me-4" style="background: conic-gradient(var(--success-color) <?php echo $attendancePercentage * 3.6; ?>deg, #e5e7eb 0deg);">
                                    <?php echo $attendancePercentage; ?>%
                                </div>
                                <div>
                                    <h6 class="mb-1"><?php echo $stats['today_attendance']; ?> من <?php echo $stats['active_students']; ?> طالب</h6>
                                    <p class="text-muted mb-0">نسبة الحضور الإجمالية لليوم</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="chart-card">
                            <h5 class="mb-3">إجراءات سريعة</h5>
                            <div class="quick-actions">
                                <?php if ($auth->hasPermission('manage_attendance')): ?>
                                <a href="attendance.php" class="quick-action-btn btn btn-primary">
                                    <i class="bi bi-calendar-check me-1"></i>
                                    تسجيل حضور
                                </a>
                                <?php endif; ?>

                                <?php if ($auth->hasPermission('manage_students')): ?>
                                <a href="students.php?action=add" class="quick-action-btn btn btn-success">
                                    <i class="bi bi-person-plus me-1"></i>
                                    إضافة طالب
                                </a>
                                <?php endif; ?>

                                <?php if ($auth->hasPermission('view_reports')): ?>
                                <a href="reports.php" class="quick-action-btn btn btn-info">
                                    <i class="bi bi-graph-up me-1"></i>
                                    عرض التقارير
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- الحلقات النشطة -->
                <?php if (!empty($activeHalaqat)): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="chart-card">
                            <h5 class="mb-3">الحلقات النشطة</h5>
                            <div class="row">
                                <?php foreach ($activeHalaqat as $halqa): ?>
                                <div class="col-md-6 col-lg-4">
                                    <div class="halqa-card">
                                        <h6 class="mb-2"><?php echo htmlspecialchars($halqa['name']); ?></h6>
                                        <p class="text-muted small mb-2">
                                            <i class="bi bi-person me-1"></i>
                                            المشرف: <?php echo htmlspecialchars($halqa['supervisor_name'] ?? 'غير محدد'); ?>
                                        </p>
                                        <p class="text-muted small mb-2">
                                            <i class="bi bi-mortarboard me-1"></i>
                                            المعلم: <?php echo htmlspecialchars($halqa['teacher_name'] ?? 'غير محدد'); ?>
                                        </p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="badge bg-primary"><?php echo $halqa['student_count']; ?> طالب</span>
                                            <span class="badge bg-secondary"><?php echo htmlspecialchars($halqa['session_type']); ?></span>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- آخر الأنشطة -->
                <?php if (!empty($recentActivities)): ?>
                <div class="row">
                    <div class="col-12">
                        <div class="chart-card">
                            <h5 class="mb-3">آخر الأنشطة</h5>
                            <?php foreach (array_slice($recentActivities, 0, 5) as $activity): ?>
                            <div class="activity-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <strong><?php echo htmlspecialchars($activity['action']); ?></strong>
                                        <?php if ($activity['user_name']): ?>
                                        <span class="text-muted">بواسطة <?php echo htmlspecialchars($activity['user_name']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <small class="activity-time">
                                        <?php echo date('H:i', strtotime($activity['created_at'])); ?>
                                    </small>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.0.0/dist/chart.min.js"></script>
    <script>
        // تحديث الوقت كل ثانية
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('ar-SA');
            document.querySelector('.btn-outline-secondary').innerHTML = 
                '<i class="bi bi-calendar3"></i> ' + timeString;
        }
        
        setInterval(updateTime, 1000);
        
        // إضافة تأثيرات تفاعلية للبطاقات
        document.querySelectorAll('.stat-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px) scale(1.02)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });
    </script>
</body>
</html>

