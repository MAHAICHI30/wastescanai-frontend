# 使用官方轻量级 PHP 镜像
FROM php:8.2-cli

# 🌟 强力修复：安装 curl 开发底座，并同时编译安装 curl、mysqli、pdo_mysql 扩展
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    pkg-config \
    libssl-dev \
    && docker-php-ext-install curl mysqli pdo pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

# 将前端代码复制到工作目录
COPY . /usr/src/myapp
WORKDIR /usr/src/myapp

# 暴露端口
EXPOSE 8080

# 启动原生内置服务（保持你原有的自适应 $PORT 逻辑）
CMD ["sh", "-c", "php -S 0.0.0.0:$PORT"]
