# استخدام نسخة PHP الرسمية مع Apache
FROM php:8.2-apache

# تثبيت الإضافات اللازمة لـ Laravel وقاعدة البيانات
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# تفعيل خاصية الـ Rewrite في Apache (ضرورية لـ Laravel)
RUN a2enmod rewrite

# نسخ ملفات المشروع إلى السيرفر
COPY . /var/www/html

# تحديد المجلد الرئيسي للعمل
WORKDIR /var/www/html

# تثبيت Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# تثبيت مكتبات المشروع
RUN composer install --no-dev --optimize-autoloader

# ضبط صلاحيات المجلدات (حل مشكلة الصفحة البيضاء)
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
RUN chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# إعداد Apache ليعمل من مجلد public الخاص بـ Laravel
RUN sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/000-default.conf

EXPOSE 80
