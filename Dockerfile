FROM php:8.2-apache

# ── System dependencies ───────────────────────────────────────────────────────
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    zip \
    curl \
    libicu-dev \
    libpng-dev \
    libjpeg-dev \
    libzip-dev \
    libonig-dev \
    libxml2-dev \
    python3 \
    python3-pip \
    python3-venv \
    wkhtmltopdf \
    xvfb \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# ── PHP extensions ────────────────────────────────────────────────────────────
RUN docker-php-ext-configure gd --with-jpeg \
    && docker-php-ext-install \
        intl \
        pdo \
        pdo_mysql \
        mbstring \
        zip \
        gd \
        xml \
        ctype \
        opcache

# ── Composer ──────────────────────────────────────────────────────────────────
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ── wkhtmltopdf wrapper (handles display issues on headless servers) ──────────
RUN echo '#!/bin/bash\nxvfb-run -a --server-args="-screen 0, 1024x768x24" /usr/bin/wkhtmltopdf --quiet "$@"' \
    > /usr/local/bin/wkhtmltopdf-wrapper \
    && chmod +x /usr/local/bin/wkhtmltopdf-wrapper

# ── Apache configuration ──────────────────────────────────────────────────────
RUN a2enmod rewrite headers

COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

# ── Application ───────────────────────────────────────────────────────────────
WORKDIR /var/www/html

COPY . .

# ── PHP dependencies ──────────────────────────────────────────────────────────
RUN composer install --no-dev --optimize-autoloader --no-interaction

# ── Python dependencies ───────────────────────────────────────────────────────
RUN pip3 install --break-system-packages pandas scikit-learn joblib

# ── Symfony assets ────────────────────────────────────────────────────────────
RUN php bin/console importmap:install --no-interaction || true
RUN php bin/console asset-map:compile --no-interaction || true

# ── Permissions ───────────────────────────────────────────────────────────────
RUN chown -R www-data:www-data /var/www/html/var \
    && chmod -R 775 /var/www/html/var \
    && mkdir -p /var/www/html/public/uploads/hotels \
    && chown -R www-data:www-data /var/www/html/public/uploads

# ── Entrypoint ────────────────────────────────────────────────────────────────
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80

CMD ["/entrypoint.sh"]
