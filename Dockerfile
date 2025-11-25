# ใช้ PHP 8.2 ที่มาพร้อม Apache (คล้าย XAMPP)
FROM php:8.2-apache

# ติดตั้ง Library ที่จำเป็นสำหรับ Linux ภายใน Docker
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libzip-dev \
    zip \
    unzip

# ติดตั้ง PHP Extensions ที่โปรเจคคุณต้องใช้ (gd, zip, mysql)
RUN docker-php-ext-install mysqli pdo pdo_mysql gd zip

# ก๊อปปี้ไฟล์ตั้งค่าการอัปโหลดเข้าไป
COPY uploads.ini /usr/local/etc/php/conf.d/uploads.ini

# เปิดใช้งาน mod_rewrite (สำหรับ .htaccess)
RUN a2enmod rewrite

# ตั้งค่าให้ Apache อ่านไฟล์ในโฟลเดอร์ปัจจุบัน
WORKDIR /var/www/html