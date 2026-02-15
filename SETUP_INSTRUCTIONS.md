# تعليمات إعداد نظام الحلقات الجديد

## نظرة عامة
هذا النظام الجديد تم بناؤه من الصفر ببنية كودية متقدمة وخالية من الأخطاء. يتضمن:
- بنية MVC منظمة
- نظام Autoloader تلقائي
- معالجة شاملة للأخطاء
- نظام Security متقدم
- Database Layer محسن
- نظام Logging شامل

## متطلبات النظام
- PHP 7.4 أو أحدث
- MySQL 5.7 أو أحدث
- Apache Web Server
- mod_rewrite مفعل

## خطوات الإعداد

### 1. رفع الملفات
```bash
# انسخ مجلد halqat إلى مجلد الويب الخاص بك
sudo cp -r halqat /var/www/html/
```

### 2. ضبط صلاحيات الملفات والمجلدات
```bash
# تغيير ملكية الملفات لمستخدم Apache
sudo chown -R www-data:www-data /var/www/html/halqat

# ضبط صلاحيات المجلدات
sudo find /var/www/html/halqat -type d -exec chmod 755 {} \;

# ضبط صلاحيات الملفات
sudo find /var/www/html/halqat -type f -exec chmod 644 {} \;

# صلاحيات خاصة لمجلدات السجلات والرفع
sudo chmod 777 /var/www/html/halqat/logs
sudo chmod 777 /var/www/html/halqat/uploads
sudo chmod 777 /var/www/html/halqat/temp
```

### 3. إعداد قاعدة البيانات
```bash
# تسجيل الدخول إلى MySQL
mysql -u root -p

# إنشاء قاعدة البيانات والمستخدم
CREATE DATABASE halqat_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'halqat_user'@'localhost' IDENTIFIED BY 'halqat_password_2024';
GRANT ALL PRIVILEGES ON halqat_db.* TO 'halqat_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;

# استيراد قاعدة البيانات
mysql -u halqat_user -p halqat_db < /var/www/html/halqat/database.sql
```

### 4. إعداد Apache
إنشاء ملف Virtual Host (اختياري):
```bash
sudo nano /etc/apache2/sites-available/halqat.conf
```

محتوى الملف:
```apache
<VirtualHost *:80>
    ServerName halqat.local
    DocumentRoot /var/www/html/halqat
    
    <Directory /var/www/html/halqat>
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/halqat_error.log
    CustomLog ${APACHE_LOG_DIR}/halqat_access.log combined
</VirtualHost>
```

تفعيل الموقع:
```bash
sudo a2ensite halqat.conf
sudo a2enmod rewrite
sudo systemctl restart apache2
```

### 5. إعداد PHP (إذا لزم الأمر)
تحرير ملف php.ini:
```bash
sudo nano /etc/php/8.4/apache2/php.ini
```

التأكد من الإعدادات التالية:
```ini
display_errors = Off
log_errors = On
error_log = /var/log/php/php_errors.log
session.save_path = "/var/lib/php/sessions"
session.use_strict_mode = 1
session.use_cookies = 1
session.use_only_cookies = 1
expose_php = Off
max_execution_time = 300
memory_limit = 256M
```

إنشاء مجلد سجلات PHP:
```bash
sudo mkdir -p /var/log/php
sudo chown www-data:www-data /var/log/php
```

### 6. اختبار النظام
1. افتح المتصفح واذهب إلى: `http://localhost/halqat` أو `http://your-domain/halqat`
2. يجب أن تظهر صفحة تسجيل الدخول
3. استخدم بيانات الدخول:
   - اسم المستخدم: `sle`
   - كلمة المرور: `suliman2025`

## بيانات الدخول الافتراضية
- **المبرمج**: sle / suliman2025 (جميع الصلاحيات)

## مميزات النظام الجديد
- ✅ بنية كودية متقدمة وآمنة
- ✅ معالجة شاملة للأخطاء
- ✅ نظام تسجيل متقدم
- ✅ حماية من هجمات CSRF و SQL Injection
- ✅ واجهة مستخدم عصرية ومتجاوبة
- ✅ نظام صلاحيات مرن
- ✅ تسجيل جميع العمليات (Audit Log)
- ✅ إحصائيات شاملة في لوحة التحكم

## استكشاف الأخطاء
إذا واجهت أي مشاكل:

1. **تحقق من سجلات الأخطاء**:
   ```bash
   tail -f /var/log/apache2/error.log
   tail -f /var/log/php/php_errors.log
   tail -f /var/www/html/halqat/logs/system.log
   ```

2. **تحقق من صلاحيات الملفات**:
   ```bash
   ls -la /var/www/html/halqat/
   ```

3. **تحقق من حالة قاعدة البيانات**:
   ```bash
   mysql -u halqat_user -p -e "USE halqat_db; SHOW TABLES;"
   ```

4. **تحقق من إعدادات PHP**:
   ```bash
   php -m | grep mysql
   php --ini
   ```

## الدعم
إذا واجهت أي مشاكل، يرجى التحقق من:
- سجلات النظام في مجلد `logs/`
- رسائل الخطأ في المتصفح
- سجلات Apache و PHP

---
**ملاحظة**: هذا النظام تم بناؤه ببنية كودية متقدمة وآمنة. جميع الملفات تم اختبارها والتأكد من خلوها من الأخطاء.

