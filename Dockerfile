FROM php:8.2-apache

# 安装 PDO MySQL 扩展
RUN docker-php-ext-install pdo pdo_mysql mysqli

# 禁用冲突的 MPM 模块，只启用 prefork
RUN a2dismod mpm_event mpm_worker && \
    a2enmod mpm_prefork

# 启用 Apache 重写模块
RUN a2enmod rewrite

# 复制项目文件
COPY . /var/www/html/

# 设置权限
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

EXPOSE 80
