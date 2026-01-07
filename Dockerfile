FROM alpine:3.20

# Install PHP 8.3 and required extensions
RUN apk add --no-cache \
	php83 \
	php83-cli \
	php83-common \
	php83-ctype \
	php83-curl \
	php83-dom \
	php83-fileinfo \
	php83-json \
	php83-mbstring \
	php83-openssl \
	php83-phar \
	php83-tokenizer \
	php83-xml \
	php83-xmlreader \
	php83-simplexml \
	php83-xmlwriter \
	php83-zip \
	php83-pdo \
	php83-pdo_mysql \
	php83-mysqli \
	php83-opcache \
	php83-session \
	php83-iconv \
	php83-intl \
	composer \
	make \
	git \
	rsync \
	zip \
	curl

# Install WordPress CLI
RUN curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar && \
	chmod +x wp-cli.phar && \
	mv wp-cli.phar /usr/local/bin/wp

# Set working directory
WORKDIR /app

# Copy composer files
COPY composer.json composer.lock* ./

# Copy application files
COPY . .

# Set permissions
RUN chmod +x /usr/local/bin/wp

# Default command
CMD ["sh"]

