# Multi-stage build for smaller image size

# Build stage
FROM php:8.2-cli-alpine AS builder

# Install build dependencies
RUN apk add --no-cache \
    git \
    unzip \
    libsodium-dev

# Install PHP extensions
RUN docker-php-ext-install sodium

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy composer files
COPY composer.json composer.lock ./

# Install dependencies (no dev dependencies for production)
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress

# Runtime stage
FROM php:8.2-cli-alpine

# Install runtime dependencies
RUN apk add --no-cache \
    libsodium \
    ca-certificates

# Install PHP extensions
RUN docker-php-ext-install sodium

# Create non-root user
RUN addgroup -g 1000 appuser && \
    adduser -D -u 1000 -G appuser appuser

# Set working directory
WORKDIR /app

# Copy vendor from builder
COPY --from=builder /app/vendor ./vendor

# Copy source code
COPY src ./src
COPY public ./public
COPY bin ./bin

# Copy configuration examples
COPY .env.example ./.env.example
COPY config.example.json ./config.example.json

# Change ownership
RUN chown -R appuser:appuser /app

# Switch to non-root user
USER appuser

# Expose port
EXPOSE 5003

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
  CMD wget --no-verbose --tries=1 --spider http://localhost:5003/health || exit 1

# Run the application using the CLI entry point
ENTRYPOINT ["bin/server"]
