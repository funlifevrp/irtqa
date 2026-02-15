<?php
/**
 * صفحة المراجع والتقارير
 * Halqat Management System v3.0
 */

// تحميل الإعدادات
require_once __DIR__ . '/config/config.php';

// التحقق من تسجيل الدخول
$auth = Auth::getInstance();
$auth->requireLogin();

$currentUser = $auth->getCurrentUser();
$db = Database::getInstance();

// معالجة طلبات AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        $action = $_POST['action'];
        
        switch ($action) {
            case 'get_report':
                $reportType = $_POST['report_type'] ?? '';
                $dateFrom = $_POST['date_from'] ?? '';
                $dateTo = $_POST['date_to'] ?? '';
                $halqaId = $_POST['halqa_id'] ?? '';
                
                $data = generateReport($reportType, $dateFrom, $dateTo, $halqaId, $db);
                echo json_encode(['success' => true, 'data' => $data]);
                break;
                
            default:
                throw new Exception('إجراء غير صحيح');
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// دالة توليد التقارير
function generateReport($type, $dateFrom, $dateTo, $halqaId, $db) {
    $data = [];
    
    switch ($type) {
        case 'attendance':
            $sql = "SELECT s.full_name, h.name as halqa_name, 
                           COUNT(CASE WHEN a.status = 'حاضر' THEN 1 END) as present_count,
                           COUNT(CASE WHEN a.status = 'غائب' THEN 1 END) as absent_count,
                           COUNT(*) as total_days
                    FROM students s
                    LEFT JOIN halaqat h ON s.halqa_id = h.id
                    LEFT JOIN attendance a ON s.id = a.student_id
                    WHERE 1=1";
            
            $params = [];
            if ($dateFrom && $dateTo) {
                $sql .= " AND a.date BETWEEN ? AND ?";
                $params[] = $dateFrom;
                $params[] = $dateTo;
            }
            if ($halqaId) {
                $sql .= " AND s.halqa_id = ?";
                $params[] = $halqaId;
            }
            
            $sql .= " GROUP BY s.id ORDER BY s.full_name";
            $data = $db->query($sql, $params);
            break;
            
        case 'grades':
            $sql = "SELECT s.full_name, h.name as halqa_name, g.grade_type, 
                           AVG(g.grade) as avg_grade, COUNT(g.id) as grade_count
                    FROM students s
                    LEFT JOIN halaqat h ON s.halqa_id = h.id
                    LEFT JOIN grades g ON s.id = g.student_id
                    WHERE 1=1";
            
            $params = [];
            if ($dateFrom && $dateTo) {
                $sql .= " AND g.date BETWEEN ? AND ?";
                $params[] = $dateFrom;
                $params[] = $dateTo;
            }
            if ($halqaId) {
                $sql .= " AND s.halqa_id = ?";
                $params[] = $halqaId;
            }
            
            $sql .= " GROUP BY s.id, g.grade_type ORDER BY s.full_name";
            $data = $db->query($sql, $params);
            break;
            
        case 'students':
            $sql = "SELECT s.*, h.name as halqa_name, u.full_name as teacher_name
                    FROM students s
                    LEFT JOIN halaqat h ON s.halqa_id = h.id
                    LEFT JOIN users u ON h.teacher_id = u.id
                    WHERE s.is_active = 1";
            
            $params = [];
            if ($halqaId) {
                $sql .= " AND s.halqa_id = ?";
                $params[] = $halqaId;
            }
            
            $sql .= " ORDER BY s.full_name";
            $data = $db->query($sql, $params);
            break;
    }
    
    return $data;
}

// الحصول على قائمة الحلقات للفلترة
$halaqat = $db->select("SELECT id, name FROM halaqat WHERE is_active = 1 ORDER BY name");
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>المراجع والتقارير - <?php echo SYSTEM_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4f46e5;
            --secondary-color: #7c3aed;
            --success-color: #059669;
            --warning-color: #d97706;
            --danger-color: #dc2626;
            --info-color: #0284c7;
            --light-color: #f8fafc;
            --dark-color: #1e293b;
        }

        body {
            background-color: var(--light-color);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .main-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            border-radius: 0 0 20px 20px;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border: none;
            border-radius: 10px;
            padding: 10px 20px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.4);
        }

        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #e2e8f0;
            padding: 12px 16px;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        .table {
            border-radius: 10px;
            overflow: hidden;
        }

        .table thead th {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border: none;
            font-weight: 600;
            padding: 15px;
        }

        .table tbody td {
            padding: 12px 15px;
            vertical-align: middle;
            border-color: #e2e8f0;
        }

        .badge {
            padding: 8px 12px;
            border-radius: 20px;
            font-weight: 500;
        }

        .loading {
            display: none;
            text-align: center;
            padding: 2rem;
        }

        .spinner-border {
            color: var(--primary-color);
        }

        @media (max-width: 768px) {
            .main-header {
                padding: 1rem 0;
                margin-bottom: 1rem;
            }
            
            .card {
                margin-bottom: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="main-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h1 class="mb-0">
                        <i class="bi bi-file-earmark-text me-3"></i>
                        المراجع والتقارير
                    </h1>
                    <p class="mb-0 mt-2 opacity-75">تقارير شاملة وإحصائيات مفصلة</p>
                </div>
                <div class="col-md-6 text-end">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb justify-content-end mb-0">
                            <li class="breadcrumb-item"><a href="dashboard.php" class="text-white-50">الرئيسية</a></li>
                            <li class="breadcrumb-item active text-white">المراجع والتقارير</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- فلاتر التقارير -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-funnel me-2"></i>
                    فلاتر التقارير
                </h5>
            </div>
            <div class="card-body">
                <form id="reportForm">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label for="reportType" class="form-label">نوع التقرير</label>
                            <select class="form-select" id="reportType" name="report_type" required>
                                <option value="">اختر نوع التقرير</option>
                                <option value="attendance">تقرير الحضور والغياب</option>
                                <option value="grades">تقرير الدرجات</option>
                                <option value="students">تقرير الطلاب</option>
                            </select>
                        </div>
                        
                        <div class="col-md-2 mb-3">
                            <label for="dateFrom" class="form-label">من تاريخ</label>
                            <input type="date" class="form-control" id="dateFrom" name="date_from">
                        </div>
                        
                        <div class="col-md-2 mb-3">
                            <label for="dateTo" class="form-label">إلى تاريخ</label>
                            <input type="date" class="form-control" id="dateTo" name="date_to">
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label for="halqaId" class="form-label">الحلقة</label>
                            <select class="form-select" id="halqaId" name="halqa_id">
                                <option value="">جميع الحلقات</option>
                                <?php foreach ($halaqat as $halqa): ?>
                                    <option value="<?php echo $halqa['id']; ?>">
                                        <?php echo htmlspecialchars($halqa['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-2 mb-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-search me-2"></i>
                                إنشاء التقرير
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- منطقة عرض التقارير -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="bi bi-table me-2"></i>
                    نتائج التقرير
                </h5>
                <div>
                    <button type="button" class="btn btn-success btn-sm" id="exportExcel" style="display: none;">
                        <i class="bi bi-file-earmark-excel me-2"></i>
                        تصدير Excel
                    </button>
                    <button type="button" class="btn btn-info btn-sm" id="printReport" style="display: none;">
                        <i class="bi bi-printer me-2"></i>
                        طباعة
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="loading">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">جاري التحميل...</span>
                    </div>
                    <p class="mt-2">جاري إنشاء التقرير...</p>
                </div>
                
                <div id="reportResults" style="display: none;">
                    <div class="table-responsive">
                        <table class="table table-striped" id="reportTable">
                            <thead></thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
                
                <div id="noResults" class="text-center py-5" style="display: none;">
                    <i class="bi bi-inbox display-1 text-muted"></i>
                    <h4 class="text-muted mt-3">لا توجد بيانات</h4>
                    <p class="text-muted">اختر نوع التقرير والفلاتر المطلوبة لعرض النتائج</p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        $(document).ready(function() {
            let reportTable;
            
            // معالجة إرسال النموذج
            $('#reportForm').on('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                formData.append('action', 'get_report');
                
                // إظهار مؤشر التحميل
                $('.loading').show();
                $('#reportResults').hide();
                $('#noResults').hide();
                $('#exportExcel, #printReport').hide();
                
                // تدمير الجدول السابق إن وجد
                if (reportTable) {
                    reportTable.destroy();
                }
                
                $.ajax({
                    url: 'references.php',
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    success: function(response) {
                        $('.loading').hide();
                        
                        if (response.success && response.data.length > 0) {
                            displayReport(response.data, $('#reportType').val());
                            $('#reportResults').show();
                            $('#exportExcel, #printReport').show();
                        } else {
                            $('#noResults').show();
                        }
                    },
                    error: function() {
                        $('.loading').hide();
                        alert('حدث خطأ أثناء إنشاء التقرير');
                    }
                });
            });
            
            // دالة عرض التقرير
            function displayReport(data, reportType) {
                const table = $('#reportTable');
                let headers = '';
                let rows = '';
                
                if (data.length === 0) {
                    $('#noResults').show();
                    return;
                }
                
                // تحديد العناوين حسب نوع التقرير
                switch (reportType) {
                    case 'attendance':
                        headers = `
                            <tr>
                                <th>اسم الطالب</th>
                                <th>الحلقة</th>
                                <th>أيام الحضور</th>
                                <th>أيام الغياب</th>
                                <th>إجمالي الأيام</th>
                                <th>نسبة الحضور</th>
                            </tr>
                        `;
                        
                        data.forEach(row => {
                            const attendanceRate = row.total_days > 0 ? 
                                ((row.present_count / row.total_days) * 100).toFixed(1) : 0;
                            
                            rows += `
                                <tr>
                                    <td>${row.full_name || 'غير محدد'}</td>
                                    <td>${row.halqa_name || 'غير محدد'}</td>
                                    <td><span class="badge bg-success">${row.present_count}</span></td>
                                    <td><span class="badge bg-danger">${row.absent_count}</span></td>
                                    <td>${row.total_days}</td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar" role="progressbar" 
                                                 style="width: ${attendanceRate}%">${attendanceRate}%</div>
                                        </div>
                                    </td>
                                </tr>
                            `;
                        });
                        break;
                        
                    case 'grades':
                        headers = `
                            <tr>
                                <th>اسم الطالب</th>
                                <th>الحلقة</th>
                                <th>نوع الدرجة</th>
                                <th>متوسط الدرجات</th>
                                <th>عدد الدرجات</th>
                            </tr>
                        `;
                        
                        data.forEach(row => {
                            const avgGrade = parseFloat(row.avg_grade || 0).toFixed(1);
                            const gradeClass = avgGrade >= 90 ? 'success' : 
                                             avgGrade >= 80 ? 'warning' : 
                                             avgGrade >= 70 ? 'info' : 'danger';
                            
                            rows += `
                                <tr>
                                    <td>${row.full_name || 'غير محدد'}</td>
                                    <td>${row.halqa_name || 'غير محدد'}</td>
                                    <td>${row.grade_type || 'غير محدد'}</td>
                                    <td><span class="badge bg-${gradeClass}">${avgGrade}</span></td>
                                    <td>${row.grade_count}</td>
                                </tr>
                            `;
                        });
                        break;
                        
                    case 'students':
                        headers = `
                            <tr>
                                <th>اسم الطالب</th>
                                <th>الحلقة</th>
                                <th>المعلم</th>
                                <th>الهاتف</th>
                                <th>الحالة</th>
                                <th>تاريخ التسجيل</th>
                            </tr>
                        `;
                        
                        data.forEach(row => {
                            const statusClass = row.status === 'نشط' ? 'success' : 
                                              row.status === 'معطل' ? 'danger' : 'warning';
                            
                            rows += `
                                <tr>
                                    <td>${row.full_name || 'غير محدد'}</td>
                                    <td>${row.halqa_name || 'غير محدد'}</td>
                                    <td>${row.teacher_name || 'غير محدد'}</td>
                                    <td>${row.phone || 'غير محدد'}</td>
                                    <td><span class="badge bg-${statusClass}">${row.status || 'غير محدد'}</span></td>
                                    <td>${row.created_at ? new Date(row.created_at).toLocaleDateString('ar-SA') : 'غير محدد'}</td>
                                </tr>
                            `;
                        });
                        break;
                }
                
                table.find('thead').html(headers);
                table.find('tbody').html(rows);
                
                // تفعيل DataTables
                reportTable = table.DataTable({
                    language: {
                        url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json'
                    },
                    responsive: true,
                    pageLength: 25,
                    order: [[0, 'asc']]
                });
            }
            
            // تصدير Excel
            $('#exportExcel').on('click', function() {
                if (reportTable) {
                    // يمكن إضافة مكتبة تصدير Excel هنا
                    alert('ميزة تصدير Excel ستكون متاحة قريباً');
                }
            });
            
            // طباعة التقرير
            $('#printReport').on('click', function() {
                window.print();
            });
        });
    </script>
</body>
</html>

