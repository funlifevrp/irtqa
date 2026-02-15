-- قاعدة بيانات نظام إدارة الحلقات الجديد
-- Halqat Management System v3.0
-- تاريخ الإنشاء: 2024

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- إنشاء قاعدة البيانات
CREATE DATABASE IF NOT EXISTS `halqat_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `halqat_db`;

-- --------------------------------------------------------

-- جدول المستخدمين
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) DEFAULT NULL COMMENT 'اسم المستخدم للمبرمج',
  `password` varchar(255) DEFAULT NULL COMMENT 'كلمة المرور للمبرمج',
  `personal_code` varchar(4) DEFAULT NULL COMMENT 'الرمز الشخصي للمشرف والمعلم',
  `full_name` varchar(100) NOT NULL COMMENT 'الاسم الكامل',
  `role` enum('مبرمج','مشرف','معلم') NOT NULL COMMENT 'الدور',
  `custom_permissions` text DEFAULT NULL COMMENT 'الصلاحيات المخصصة (JSON)',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'حالة التفعيل',
  `last_login` datetime DEFAULT NULL COMMENT 'آخر تسجيل دخول',
  `login_count` int(11) NOT NULL DEFAULT 0 COMMENT 'عدد مرات تسجيل الدخول',
  `password_changed_at` datetime DEFAULT NULL COMMENT 'تاريخ آخر تغيير لكلمة المرور',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'تاريخ الإنشاء',
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'تاريخ التحديث',
  `deleted_at` datetime DEFAULT NULL COMMENT 'تاريخ الحذف',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `personal_code` (`personal_code`),
  KEY `role` (`role`),
  KEY `is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='جدول المستخدمين';

-- --------------------------------------------------------

-- جدول الحلقات
CREATE TABLE `halaqat` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL COMMENT 'اسم الحلقة',
  `description` text DEFAULT NULL COMMENT 'وصف الحلقة',
  `supervisor_id` int(11) DEFAULT NULL COMMENT 'معرف المشرف',
  `teacher_id` int(11) DEFAULT NULL COMMENT 'معرف المعلم',
  `max_students` int(11) NOT NULL DEFAULT 20 COMMENT 'الحد الأقصى للطلاب',
  `current_students` int(11) NOT NULL DEFAULT 0 COMMENT 'عدد الطلاب الحالي',
  `session_type` enum('صباحي','مسائي') NOT NULL COMMENT 'نوع الجلسة',
  `start_time` time NOT NULL COMMENT 'وقت البداية',
  `end_time` time NOT NULL COMMENT 'وقت النهاية',
  `days` varchar(20) NOT NULL COMMENT 'أيام الأسبوع (JSON)',
  `location` varchar(100) DEFAULT NULL COMMENT 'المكان',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'حالة التفعيل',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'تاريخ الإنشاء',
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'تاريخ التحديث',
  `deleted_at` datetime DEFAULT NULL COMMENT 'تاريخ الحذف',
  PRIMARY KEY (`id`),
  KEY `supervisor_id` (`supervisor_id`),
  KEY `teacher_id` (`teacher_id`),
  KEY `session_type` (`session_type`),
  KEY `is_active` (`is_active`),
  CONSTRAINT `halaqat_supervisor_fk` FOREIGN KEY (`supervisor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `halaqat_teacher_fk` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='جدول الحلقات';

-- --------------------------------------------------------

-- جدول الطلاب
CREATE TABLE `students` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_number` varchar(20) NOT NULL COMMENT 'رقم الطالب',
  `full_name` varchar(100) NOT NULL COMMENT 'الاسم الكامل',
  `birth_date` date DEFAULT NULL COMMENT 'تاريخ الميلاد',
  `phone` varchar(20) DEFAULT NULL COMMENT 'رقم الهاتف',
  `guardian_name` varchar(100) DEFAULT NULL COMMENT 'اسم ولي الأمر',
  `guardian_phone` varchar(20) DEFAULT NULL COMMENT 'هاتف ولي الأمر',
  `address` text DEFAULT NULL COMMENT 'العنوان',
  `halqa_id` int(11) DEFAULT NULL COMMENT 'معرف الحلقة',
  `enrollment_date` date NOT NULL COMMENT 'تاريخ التسجيل',
  `status` enum('نشط','متوقف','منقول','متخرج') NOT NULL DEFAULT 'نشط' COMMENT 'حالة الطالب',
  `notes` text DEFAULT NULL COMMENT 'ملاحظات',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'حالة التفعيل',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'تاريخ الإنشاء',
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'تاريخ التحديث',
  `deleted_at` datetime DEFAULT NULL COMMENT 'تاريخ الحذف',
  PRIMARY KEY (`id`),
  UNIQUE KEY `student_number` (`student_number`),
  KEY `halqa_id` (`halqa_id`),
  KEY `status` (`status`),
  KEY `is_active` (`is_active`),
  CONSTRAINT `students_halqa_fk` FOREIGN KEY (`halqa_id`) REFERENCES `halaqat` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='جدول الطلاب';

-- --------------------------------------------------------

-- جدول المقررات
CREATE TABLE `courses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL COMMENT 'اسم المقرر',
  `description` text DEFAULT NULL COMMENT 'وصف المقرر',
  `type` enum('قرآن','حديث','فقه','سيرة','أخلاق','أخرى') NOT NULL COMMENT 'نوع المقرر',
  `level` enum('مبتدئ','متوسط','متقدم') NOT NULL COMMENT 'المستوى',
  `duration_weeks` int(11) DEFAULT NULL COMMENT 'مدة المقرر بالأسابيع',
  `objectives` text DEFAULT NULL COMMENT 'أهداف المقرر',
  `content` text DEFAULT NULL COMMENT 'محتوى المقرر',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'حالة التفعيل',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'تاريخ الإنشاء',
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'تاريخ التحديث',
  `deleted_at` datetime DEFAULT NULL COMMENT 'تاريخ الحذف',
  PRIMARY KEY (`id`),
  KEY `type` (`type`),
  KEY `level` (`level`),
  KEY `is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='جدول المقررات';

-- --------------------------------------------------------

-- جدول الحضور والغياب
CREATE TABLE `attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL COMMENT 'معرف الطالب',
  `halqa_id` int(11) NOT NULL COMMENT 'معرف الحلقة',
  `date` date NOT NULL COMMENT 'التاريخ',
  `session_type` enum('صباحي','مسائي') NOT NULL COMMENT 'نوع الجلسة',
  `status` enum('حاضر','غائب','غائب بعذر','متأخر') NOT NULL COMMENT 'حالة الحضور',
  `arrival_time` time DEFAULT NULL COMMENT 'وقت الوصول',
  `departure_time` time DEFAULT NULL COMMENT 'وقت المغادرة',
  `notes` text DEFAULT NULL COMMENT 'ملاحظات',
  `recorded_by` int(11) NOT NULL COMMENT 'مسجل بواسطة',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'تاريخ التسجيل',
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'تاريخ التحديث',
  PRIMARY KEY (`id`),
  UNIQUE KEY `student_date_session` (`student_id`, `date`, `session_type`),
  KEY `halqa_id` (`halqa_id`),
  KEY `date` (`date`),
  KEY `status` (`status`),
  KEY `recorded_by` (`recorded_by`),
  CONSTRAINT `attendance_student_fk` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attendance_halqa_fk` FOREIGN KEY (`halqa_id`) REFERENCES `halaqat` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attendance_recorder_fk` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='جدول الحضور والغياب';

-- --------------------------------------------------------

-- جدول الدرجات والتقييمات
CREATE TABLE `grades` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL COMMENT 'معرف الطالب',
  `course_id` int(11) NOT NULL COMMENT 'معرف المقرر',
  `halqa_id` int(11) NOT NULL COMMENT 'معرف الحلقة',
  `exam_type` enum('شفهي','تحريري','مشروع','تقييم مستمر') NOT NULL COMMENT 'نوع الامتحان',
  `exam_date` date NOT NULL COMMENT 'تاريخ الامتحان',
  `total_marks` decimal(5,2) NOT NULL COMMENT 'الدرجة الكلية',
  `obtained_marks` decimal(5,2) NOT NULL COMMENT 'الدرجة المحصلة',
  `percentage` decimal(5,2) GENERATED ALWAYS AS ((`obtained_marks` / `total_marks`) * 100) STORED COMMENT 'النسبة المئوية',
  `grade` varchar(5) DEFAULT NULL COMMENT 'التقدير',
  `notes` text DEFAULT NULL COMMENT 'ملاحظات',
  `evaluated_by` int(11) NOT NULL COMMENT 'مقيم بواسطة',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'تاريخ التسجيل',
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'تاريخ التحديث',
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `course_id` (`course_id`),
  KEY `halqa_id` (`halqa_id`),
  KEY `exam_date` (`exam_date`),
  KEY `evaluated_by` (`evaluated_by`),
  CONSTRAINT `grades_student_fk` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `grades_course_fk` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `grades_halqa_fk` FOREIGN KEY (`halqa_id`) REFERENCES `halaqat` (`id`) ON DELETE CASCADE,
  CONSTRAINT `grades_evaluator_fk` FOREIGN KEY (`evaluated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='جدول الدرجات والتقييمات';

-- --------------------------------------------------------

-- جدول الأنشطة والفعاليات
CREATE TABLE `activities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL COMMENT 'عنوان النشاط',
  `description` text DEFAULT NULL COMMENT 'وصف النشاط',
  `type` enum('مسابقة','رحلة','محاضرة','ورشة عمل','احتفال','أخرى') NOT NULL COMMENT 'نوع النشاط',
  `date` date NOT NULL COMMENT 'تاريخ النشاط',
  `start_time` time DEFAULT NULL COMMENT 'وقت البداية',
  `end_time` time DEFAULT NULL COMMENT 'وقت النهاية',
  `location` varchar(200) DEFAULT NULL COMMENT 'المكان',
  `target_audience` enum('جميع الطلاب','حلقة محددة','مستوى محدد') NOT NULL COMMENT 'الجمهور المستهدف',
  `halqa_id` int(11) DEFAULT NULL COMMENT 'معرف الحلقة (إذا كان مخصص لحلقة)',
  `organizer_id` int(11) NOT NULL COMMENT 'معرف المنظم',
  `max_participants` int(11) DEFAULT NULL COMMENT 'الحد الأقصى للمشاركين',
  `registration_required` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'يتطلب تسجيل',
  `registration_deadline` date DEFAULT NULL COMMENT 'آخر موعد للتسجيل',
  `status` enum('مجدول','جاري','مكتمل','ملغي') NOT NULL DEFAULT 'مجدول' COMMENT 'حالة النشاط',
  `notes` text DEFAULT NULL COMMENT 'ملاحظات',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'حالة التفعيل',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'تاريخ الإنشاء',
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'تاريخ التحديث',
  PRIMARY KEY (`id`),
  KEY `date` (`date`),
  KEY `type` (`type`),
  KEY `halqa_id` (`halqa_id`),
  KEY `organizer_id` (`organizer_id`),
  KEY `status` (`status`),
  CONSTRAINT `activities_halqa_fk` FOREIGN KEY (`halqa_id`) REFERENCES `halaqat` (`id`) ON DELETE SET NULL,
  CONSTRAINT `activities_organizer_fk` FOREIGN KEY (`organizer_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='جدول الأنشطة والفعاليات';

-- --------------------------------------------------------

-- جدول مشاركة الطلاب في الأنشطة
CREATE TABLE `activity_participants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `activity_id` int(11) NOT NULL COMMENT 'معرف النشاط',
  `student_id` int(11) NOT NULL COMMENT 'معرف الطالب',
  `registration_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'تاريخ التسجيل',
  `attendance_status` enum('مسجل','حاضر','غائب') NOT NULL DEFAULT 'مسجل' COMMENT 'حالة الحضور',
  `notes` text DEFAULT NULL COMMENT 'ملاحظات',
  PRIMARY KEY (`id`),
  UNIQUE KEY `activity_student` (`activity_id`, `student_id`),
  KEY `student_id` (`student_id`),
  CONSTRAINT `participants_activity_fk` FOREIGN KEY (`activity_id`) REFERENCES `activities` (`id`) ON DELETE CASCADE,
  CONSTRAINT `participants_student_fk` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='جدول مشاركة الطلاب في الأنشطة';

-- --------------------------------------------------------

-- جدول الإعدادات العامة
CREATE TABLE `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL COMMENT 'مفتاح الإعداد',
  `setting_value` text DEFAULT NULL COMMENT 'قيمة الإعداد',
  `setting_type` enum('string','number','boolean','json') NOT NULL DEFAULT 'string' COMMENT 'نوع الإعداد',
  `description` varchar(255) DEFAULT NULL COMMENT 'وصف الإعداد',
  `is_public` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'إعداد عام (يمكن عرضه للمستخدمين)',
  `updated_by` int(11) DEFAULT NULL COMMENT 'محدث بواسطة',
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'تاريخ التحديث',
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`),
  KEY `updated_by` (`updated_by`),
  CONSTRAINT `settings_updater_fk` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='جدول الإعدادات العامة';

-- --------------------------------------------------------

-- جدول سجل النشاطات (Audit Log)
CREATE TABLE `audit_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL COMMENT 'معرف المستخدم',
  `action` varchar(100) NOT NULL COMMENT 'نوع العملية',
  `table_name` varchar(50) DEFAULT NULL COMMENT 'اسم الجدول المتأثر',
  `record_id` int(11) DEFAULT NULL COMMENT 'معرف السجل المتأثر',
  `old_values` text DEFAULT NULL COMMENT 'القيم القديمة (JSON)',
  `new_values` text DEFAULT NULL COMMENT 'القيم الجديدة (JSON)',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'عنوان IP',
  `user_agent` text DEFAULT NULL COMMENT 'معلومات المتصفح',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'تاريخ العملية',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `action` (`action`),
  KEY `table_name` (`table_name`),
  KEY `created_at` (`created_at`),
  CONSTRAINT `audit_log_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='جدول سجل النشاطات';

-- --------------------------------------------------------

-- إدراج البيانات الأساسية

-- إدراج المستخدم الافتراضي (المبرمج)
INSERT INTO `users` (`username`, `password`, `full_name`, `role`, `is_active`, `created_at`) VALUES
('sle', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'مبرمج النظام', 'مبرمج', 1, NOW());

-- كلمة المرور المشفرة أعلاه تعادل: suliman2025

-- تحديث كلمة المرور للمستخدم sle
UPDATE `users` SET `password` = '$2y$10$YourHashedPasswordHere' WHERE `username` = 'sle';

-- إدراج الإعدادات الأساسية
INSERT INTO `settings` (`setting_key`, `setting_value`, `setting_type`, `description`, `is_public`) VALUES
('system_name', 'نظام إدارة الحلقات', 'string', 'اسم النظام', 1),
('organization_name', 'جمعية ارتقاء التعليمية', 'string', 'اسم المؤسسة', 1),
('system_version', '3.0.0', 'string', 'إصدار النظام', 0),
('max_students_per_halqa', '20', 'number', 'الحد الأقصى للطلاب في الحلقة الواحدة', 0),
('session_timeout', '1800', 'number', 'مهلة انتهاء الجلسة بالثواني', 0),
('backup_enabled', 'true', 'boolean', 'تفعيل النسخ الاحتياطي التلقائي', 0),
('maintenance_mode', 'false', 'boolean', 'وضع الصيانة', 0);

-- إدراج مقررات أساسية
INSERT INTO `courses` (`name`, `description`, `type`, `level`, `duration_weeks`, `objectives`) VALUES
('حفظ القرآن الكريم - المستوى الأول', 'حفظ الأجزاء الأولى من القرآن الكريم مع التجويد الأساسي', 'قرآن', 'مبتدئ', 12, 'حفظ جزء عم وتبارك مع أحكام التجويد الأساسية'),
('حفظ القرآن الكريم - المستوى المتوسط', 'حفظ أجزاء متوسطة من القرآن مع التجويد المتقدم', 'قرآن', 'متوسط', 16, 'حفظ 5 أجزاء إضافية مع إتقان أحكام التجويد'),
('الأحاديث النبوية الأساسية', 'حفظ وفهم الأحاديث النبوية الأساسية', 'حديث', 'مبتدئ', 8, 'حفظ 40 حديثاً من الأحاديث الأساسية مع الفهم'),
('السيرة النبوية', 'دراسة سيرة النبي صلى الله عليه وسلم', 'سيرة', 'مبتدئ', 10, 'التعرف على أهم أحداث السيرة النبوية والاستفادة منها'),
('الآداب الإسلامية', 'تعلم الآداب والأخلاق الإسلامية', 'أخلاق', 'مبتدئ', 6, 'تطبيق الآداب الإسلامية في الحياة اليومية');

COMMIT;

