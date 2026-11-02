# ==============================================================================
# BONHAGAN TRANSIT — Production Dockerfile for Render Web Service
# PHP 8.2 + Apache with PostgreSQL PDO Extension
# ==============================================================================

FROM php:8.2-apache

# Install PostgreSQL client and development libraries required for pgsql PDO
RUN apt-get update && apt-get install -y \
    libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql pgsql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Enable Apache mod_rewrite and mod_headers
RUN a2enmod rewrite headers

# Allow .htaccess overrides in Apache directory config
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Render assigns a dynamic port via the $PORT environment variable (defaults to 10000)
ENV PORT=10000
RUN sed -i 's/80/${PORT}/g' /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf

# Copy application files into Apache webroot
COPY . /var/www/html/

# Set working directory and ownership
WORKDIR /var/www/html
RUN chown -R www-data:www-data /var/www/html

EXPOSE ${PORT}

CMD ["apache2-foreground"]
