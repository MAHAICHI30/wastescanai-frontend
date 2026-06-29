FROM php:8.2-cli

# 安装并启用 PHP 连接 MySQL 必须的 PDO 驱动组件
RUN docker-php-ext-install pdo pdo_mysql

# 将前端代码复制到工作目录
COPY . /usr/src/myapp
WORKDIR /usr/src/myapp

# 启动原生内置服务
CMD ["sh", "-c", "php -S 0.0.0.0:$PORT"]
