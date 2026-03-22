FROM php:7.4-fpm

ENV DEBIAN_FRONTEND=noninteractive

# 1. Встановлюємо ВСІ системні залежності разом (для PHP, Python, MPI та компіляції dlib)
RUN apt-get update && apt-get install -y \
    # Залежності для PHP
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
    # Залежності для Python та системи
    python3 \
    python3-pip \
    python3-dev \
    libgl1-mesa-glx \
    libglib2.0-0 \
    # Інструменти для компіляції (потрібні для MPI)
    build-essential \
    cmake \
    gcc \
    g++ \
    gfortran \
    libopenmpi-dev \
    openmpi-bin \
    mpich \
    libboost-python-dev \
    libboost-thread-dev \
    && rm -rf /var/lib/apt/lists/*

# 2. Налаштовуємо та встановлюємо розширення PHP
RUN docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath gd soap xml xmlrpc xsl intl zip opcache && \
    pecl install imagick && \
    docker-php-ext-enable imagick

# 3. Встановлюємо Composer
COPY --from=composer:2.1.9 /usr/bin/composer /usr/bin/composer

# 4. Встановлюємо Python-бібліотеки
RUN pip3 install --no-cache-dir --upgrade pip && \
    pip3 install --no-cache-dir \
    numpy \
    matplotlib \
    mpi4py \
    numba \
    jupyter \ 
    scipy \ 
    pandas 

# 5. Налаштування робочої директорії
WORKDIR /var/www/html

# Прокидаємо код
COPY ./code /var/www/html

# Надаємо правильні права доступу для веб-сервера
RUN chown -R www-data:www-data /var/www/html

# Залишаємо стандартний CMD від базового образу php:7.4-fpm
# Тобто: CMD ["php-fpm"]