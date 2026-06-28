FROM php:8.2-apache

# 安装 PDO MySQL 扩展
RUN docker-php-ext-install pdo pdo_mysql mysqli

# 复制项目文件
COPY . /var/www/html/

# 设置权限
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
