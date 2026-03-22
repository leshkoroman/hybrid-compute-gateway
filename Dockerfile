FROM php:7.4-fpm

ENV DEBIAN_FRONTEND=noninteractive

# 1. Встановлюємо ВСІ системні залежності: для PHP, Python та MPI
RUN apt-get update && apt-get install -y \
    # --- Залежності для Laravel (PHP) ---
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libxpm-dev \
    libssl-dev \
    libmagickwand-dev \
    libicu-dev \
    libxslt1-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    # --- Залежності для Python ---
    python3 \
    python3-pip \
    python3-dev \
    # --- Компiлятори та MPI (для mpi4py, scipy, numba) ---
    build-essential \
    cmake \
    gcc \
    g++ \
    gfortran \
    libopenmpi-dev \
    openmpi-bin \
    && rm -rf /var/lib/apt/lists/*

# 2. Налаштовуємо та встановлюємо розширення PHP
RUN docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath gd soap xml xmlrpc xsl intl zip opcache && \
    pecl install imagick && \
    docker-php-ext-enable imagick

# 3. Встановлюємо Composer
COPY --from=composer:2.1.9 /usr/bin/composer /usr/bin/composer

# 4. Встановлюємо Python-бібліотеки
# Використовуємо pip3 для встановлення бібліотек у системне середовище Python
RUN pip3 install --no-cache-dir --upgrade pip && \
    pip3 install --no-cache-dir \
    numpy \
    matplotlib \
    mpi4py \
    numba \
    jupyter \ 
    scipy \ 
    pandas

# 5. Робоча директорія
WORKDIR /var/www/html

# Копіюємо код
COPY ./code /var/www/html

# Надаємо права веб-серверу (щоб Laravel міг писати логі та зберігати результати)
RUN chown -R www-data:www-data /var/www/html

# Запускаємо PHP-FPM (обробка веб-запитів)
CMD ["php-fpm"]