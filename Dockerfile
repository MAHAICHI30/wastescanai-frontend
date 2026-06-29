FROM php:8.2-cli

# 将前端所有 PHP/HTML 代码复制到容器的工作目录中
COPY . /usr/src/myapp
WORKDIR /usr/src/myapp

# 启动 PHP 官方原生内置的 Web 服务，并动态监听 Railway 分配的任何端口
CMD ["sh", "-c", "php -S 0.0.0.0:$PORT"]
