FROM php:8.2-apache

# 开启 Apache 的重写和核心模块
RUN a2enmod rewrite

# 将当前仓库所有的前端代码复制到 Apache 的标准运行根目录下
COPY . /var/www/html/
