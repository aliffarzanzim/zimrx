# ZimRx Container Runtime (FrankenPHP + Alpine)
# Official FrankenPHP image (PHP 8.3; compatible with PHP >=8.2)
FROM dunglas/frankenphp:1-php8.3-alpine

LABEL maintainer="Alif Farzan Zim <aliffarzanzim@gmail.com>"
LABEL description="ZimRx: Open-source, local-first offline prescription & EMR engine"

# Install recommended dependencies and sqlite driver
RUN apk add --no-cache bash curl sqlite && \
    install-php-extensions pdo_sqlite && \
    cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

WORKDIR /app

# Copy web server configuration and application source
COPY Caddyfile /app/Caddyfile
COPY application /app/application

# Create required persistent state directories
RUN mkdir -p /app/application/userdata/database \
    /app/application/userdata/uploads/reports \
    /app/application/userdata/uploads/header-logos \
    /app/application/userdata/uploads/full-body-headers \
    /app/application/userdata/uploads/seal-and-stamps \
    /app/application/userdata/uploads/background-images \
    /app/logs

# Doctor EMR data volume
VOLUME ["/app/application/userdata"]

ENV ZIMRX_HTTP_PORT=8080
EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=5s --retries=3 \
    CMD curl -f http://localhost:8080/ || exit 1

CMD ["frankenphp", "run", "--config", "/app/Caddyfile", "--adapter", "caddyfile"]
