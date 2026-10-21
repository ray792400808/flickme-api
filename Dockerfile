# syntax=docker/dockerfile:1
#
# ⚠️ 当前未使用：API 镜像现在由仓库根 ./docker/php/Dockerfile 提供，
# 走 php:8.3-fpm-bookworm + nginx 反代，匹配参考项目（Downloads/Dockerfile）的生产级架构。
# 此 alpine + artisan serve 的版本仅作历史保留。

FROM php:8.3-fpm-alpine

# 系统依赖 + PHP 扩展
RUN apk add --no-cache \
        git curl unzip \
        $PHPIZE_DEPS \
        libzip-dev icu-dev oniguruma-dev postgresql-dev \
        libpng-dev libjpeg-turbo-dev freetype-dev \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql pdo_pgsql bcmath intl opcache pcntl zip gd exif \
    && pecl install redis \
    && docker-php-ext-enable redis

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# 依赖层：仅 composer.json 变化时失效，充分利用缓存
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-scripts --no-autoloader --prefer-dist

COPY . .
RUN composer dump-autoload --optimize --no-interaction \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views \
             storage/logs storage/app/public bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
# Windows checkout may produce CRLF; normalize before chmod/exec
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint \
    && chmod +x /usr/local/bin/entrypoint \
    && sh -n /usr/local/bin/entrypoint

EXPOSE 8000

ENTRYPOINT ["/usr/local/bin/entrypoint"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]