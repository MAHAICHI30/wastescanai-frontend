FROM php:8.2-apache

# 彻底清理并重新配置 Apache MPM
RUN set -ex \
    && apt-get update \
    && apt-get install -y --no-install-recommends \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
    && docker-php-ext-install \
        pdo \
        pdo_mysql \
        mysqli \
        gd \
    && a2dismod -f mpm_event \
    && a2dismod -f mpm_worker \
    && a2enmod mpm_prefork \
    && rm -rf /var/lib/apt/lists/*

# 强制移除冲突的配置文件
RUN rm -f /etc/apache2/mods-enabled/mpm_event.load \
    && rm -f /etc/apache2/mods-enabled/mpm_worker.load \
    && rm -f /etc/apache2/mods-available/mpm_event.load \
    && rm -f /etc/apache2/mods-available/mpm_worker.load

# 启用必要的模块
RUN a2enmod rewrite

# 设置工作目录
WORKDIR /var/www/html

# 复制项目文件
COPY . .

# 设置权限
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# 暴露端口
EXPOSE 80
