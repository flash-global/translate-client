FROM php:5.5-fpm-alpine

RUN apk --no-cache --update add \
    libxml2-dev \
    curl-dev \
    freetype-dev && \
    rm -rf /tmp/* && \
    rm -rf /var/cache/apk/*

RUN docker-php-ext-configure mbstring && \
    docker-php-ext-configure zip && \
    docker-php-ext-configure curl && \
    docker-php-ext-configure xml

RUN docker-php-ext-install json \
    mbstring \
    zip \
    curl \
    xml

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

RUN touch /usr/local/etc/php/conf.d/timezone.ini && \
    echo "date.timezone = Europe/Paris" > /usr/local/etc/php/conf.d/timezone.ini

COPY . /var/www/html/

EXPOSE 9000
CMD ["php-fpm"]