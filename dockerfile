FROM php:8.2-cli

# 安装 PDO MySQL 扩展
RUN docker-php-ext-install pdo pdo_mysql mysqli

# 设置工作目录
WORKDIR /var/www/html

# 复制所有文件
COPY . .

# 暴露端口
EXPOSE 8080

# 使用硬编码端口启动
CMD ["php", "-S", "0.0.0.0:8080", "-t", "."]
