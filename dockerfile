FROM php:8.2-cli

# 安装 PDO MySQL 扩展
RUN docker-php-ext-install \
    pdo \
    pdo_mysql \
    mysqli

# 设置工作目录
WORKDIR /var/www/html

# 复制项目文件
COPY . .

# 设置权限
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# 使用 PHP 内置服务器
CMD ["php", "-S", "0.0.0.0:8080", "-t", "."]

EXPOSE 8080
