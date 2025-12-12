# Prompt for Deploying PHP Server Signer

Use this prompt for an agent on a remote server to understand and deploy this project.

---

## Project Overview

This is a PHP server that provides client domain signing for Stellar SEP-10 and SEP-45 authentication protocols. It allows non-custodial wallets to prove ownership of a domain during the Stellar web authentication flow.

**Use Case:** When a wallet authenticates with a Stellar anchor (like an exchange), the anchor may require proof that the wallet is associated with a specific domain. This server signs the authentication challenge on behalf of that domain.

## Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/health` | GET | Health check, returns `{"status":"ok"}` |
| `/.well-known/stellar.toml` | GET | Returns Stellar TOML with SIGNING_KEY |
| `/sign-sep-10` | POST | Signs SEP-10 transaction envelopes |
| `/sign-sep-45` | POST | Signs SEP-45 authorization entries |

All signing endpoints require `Authorization: Bearer <token>` header.

## Deployment Steps

### 1. Prerequisites

- PHP 8.1 or higher with extensions: json, curl, mbstring, openssl
- Composer
- HTTPS certificate (Let's Encrypt recommended)
- Nginx or Apache web server with PHP-FPM
- Process manager (systemd or supervisor)

### 2. Clone and Install

```bash
git clone https://github.com/[USERNAME]/[REPO].git
cd php-server-signer
composer install --no-dev --optimize-autoloader
```

### 3. Create Production Config

Create `config.json`:
```json
{
  "host": "0.0.0.0",
  "port": 5003,
  "account_id": "GBUTDNISXHXBMZE5I4U5INJTY376S5EW2AF4SQA2SWBXUXJY3OIZQHMV",
  "secret": "SBRSOOURG2E24VGDR6NKZJMBOSOHVT6GV7EECUR3ZBE7LGSSVYN5VMOG",
  "bearer_token": "YOUR_SECURE_TOKEN_HERE",
  "network_passphrase": "Test SDF Network ; September 2015",
  "soroban_rpc_url": "https://soroban-testnet.stellar.org"
}
```

**Important:** For production, generate a secure bearer token and consider using a dedicated keypair.

Alternatively, use environment variables:
```bash
export ACCOUNT_ID="GBUTDNISXHXBMZE5I4U5INJTY376S5EW2AF4SQA2SWBXUXJY3OIZQHMV"
export SECRET="SBRSOOURG2E24VGDR6NKZJMBOSOHVT6GV7EECUR3ZBE7LGSSVYN5VMOG"
export BEARER_TOKEN="YOUR_SECURE_TOKEN_HERE"
export NETWORK_PASSPHRASE="Test SDF Network ; September 2015"
export SOROBAN_RPC_URL="https://soroban-testnet.stellar.org"
export HOST="0.0.0.0"
export PORT="5003"
```

### 4. Running the Server

#### Option A: PHP Built-in Server (Development Only)

```bash
php public/index.php -c config.json
```

Or with environment variables:
```bash
php public/index.php
```

#### Option B: Production with Nginx + PHP-FPM (Recommended)

Configure Nginx to serve the application. See section 5 below.

### 5. Set Up Reverse Proxy (Nginx)

Create `/etc/nginx/sites-available/stellar-php-signer`:

```nginx
server {
    listen 80;
    server_name your-domain.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name your-domain.com;

    ssl_certificate /etc/letsencrypt/live/your-domain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/your-domain.com/privkey.pem;

    root /path/to/php-server-signer/public;
    index index.php;

    # Security headers
    add_header X-Content-Type-Options nosniff;
    add_header X-Frame-Options DENY;
    add_header X-XSS-Protection "1; mode=block";

    # Rate limiting (adjust as needed)
    limit_req_zone $binary_remote_addr zone=api:10m rate=10r/s;
    limit_req zone=api burst=20 nodelay;

    location / {
        try_files $uri $uri/ /index.php$is_args$args;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;

        # Pass environment variables
        fastcgi_param ACCOUNT_ID $ACCOUNT_ID;
        fastcgi_param SECRET $SECRET;
        fastcgi_param BEARER_TOKEN $BEARER_TOKEN;
        fastcgi_param NETWORK_PASSPHRASE $NETWORK_PASSPHRASE;
        fastcgi_param SOROBAN_RPC_URL $SOROBAN_RPC_URL;
    }

    location ~ /\. {
        deny all;
    }

    access_log /var/log/nginx/stellar-php-signer-access.log;
    error_log /var/log/nginx/stellar-php-signer-error.log;
}
```

Enable the site:
```bash
sudo ln -s /etc/nginx/sites-available/stellar-php-signer /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### 6. Configure PHP-FPM

Edit `/etc/php/8.1/fpm/pool.d/www.conf`:

```ini
[www]
user = www-data
group = www-data
listen = /var/run/php/php8.1-fpm.sock
listen.owner = www-data
listen.group = www-data

; Performance tuning
pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20

; Environment variables
env[ACCOUNT_ID] = GBUTDNISXHXBMZE5I4U5INJTY376S5EW2AF4SQA2SWBXUXJY3OIZQHMV
env[SECRET] = SBRSOOURG2E24VGDR6NKZJMBOSOHVT6GV7EECUR3ZBE7LGSSVYN5VMOG
env[BEARER_TOKEN] = YOUR_SECURE_TOKEN_HERE
env[NETWORK_PASSPHRASE] = Test SDF Network ; September 2015
env[SOROBAN_RPC_URL] = https://soroban-testnet.stellar.org
```

Restart PHP-FPM:
```bash
sudo systemctl restart php8.1-fpm
```

### 7. Systemd Service (Alternative for Built-in Server)

Create `/etc/systemd/system/stellar-php-signer.service`:

```ini
[Unit]
Description=Stellar PHP Server Signer
After=network.target

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/path/to/php-server-signer
Environment="ACCOUNT_ID=GBUTDNISXHXBMZE5I4U5INJTY376S5EW2AF4SQA2SWBXUXJY3OIZQHMV"
Environment="SECRET=SBRSOOURG2E24VGDR6NKZJMBOSOHVT6GV7EECUR3ZBE7LGSSVYN5VMOG"
Environment="BEARER_TOKEN=YOUR_SECURE_TOKEN_HERE"
Environment="NETWORK_PASSPHRASE=Test SDF Network ; September 2015"
Environment="SOROBAN_RPC_URL=https://soroban-testnet.stellar.org"
Environment="HOST=127.0.0.1"
Environment="PORT=5003"
ExecStart=/usr/bin/php /path/to/php-server-signer/public/index.php
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

Enable and start:
```bash
sudo systemctl daemon-reload
sudo systemctl enable stellar-php-signer
sudo systemctl start stellar-php-signer
```

### 8. Verify Deployment

```bash
# Health check
curl https://your-domain.com/health

# Stellar TOML
curl https://your-domain.com/.well-known/stellar.toml

# Should return:
# ACCOUNTS = ["GBUTDNISXHXBMZE5I4U5INJTY376S5EW2AF4SQA2SWBXUXJY3OIZQHMV"]
# SIGNING_KEY = "GBUTDNISXHXBMZE5I4U5INJTY376S5EW2AF4SQA2SWBXUXJY3OIZQHMV"
# NETWORK_PASSPHRASE = "Test SDF Network ; September 2015"
```

## Docker Deployment (Alternative)

### Create Dockerfile

Create `Dockerfile`:
```dockerfile
FROM php:8.1-fpm-alpine

# Install dependencies
RUN apk add --no-cache \
    git \
    curl \
    libzip-dev \
    zip \
    unzip

# Install PHP extensions
RUN docker-php-ext-install \
    bcmath \
    sockets

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Install dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Expose port
EXPOSE 5003

# Run the application
CMD ["php", "public/index.php"]
```

### Create docker-compose.yml

```yaml
version: '3.8'

services:
  php-server-signer:
    build: .
    ports:
      - "5003:5003"
    environment:
      - ACCOUNT_ID=${ACCOUNT_ID}
      - SECRET=${SECRET}
      - BEARER_TOKEN=${BEARER_TOKEN}
      - NETWORK_PASSPHRASE=${NETWORK_PASSPHRASE}
      - SOROBAN_RPC_URL=${SOROBAN_RPC_URL}
      - HOST=0.0.0.0
      - PORT=5003
    restart: unless-stopped
```

### Build and Run

```bash
# Create .env file with your configuration
cat > .env << EOF
ACCOUNT_ID=GBUTDNISXHXBMZE5I4U5INJTY376S5EW2AF4SQA2SWBXUXJY3OIZQHMV
SECRET=SBRSOOURG2E24VGDR6NKZJMBOSOHVT6GV7EECUR3ZBE7LGSSVYN5VMOG
BEARER_TOKEN=YOUR_SECURE_TOKEN_HERE
NETWORK_PASSPHRASE=Test SDF Network ; September 2015
SOROBAN_RPC_URL=https://soroban-testnet.stellar.org
EOF

# Build and run
docker-compose up -d

# Check logs
docker-compose logs -f
```

## Security Considerations

1. **Bearer Token:** Use a strong, random token for production (minimum 32 characters)
   ```bash
   # Generate secure token
   openssl rand -base64 32
   ```

2. **Secret Key:** Keep the Stellar secret key secure, never commit to version control

3. **HTTPS:** Always use HTTPS in production with valid SSL certificates

4. **Firewall:** Restrict access to necessary ports only
   ```bash
   sudo ufw allow 80/tcp
   sudo ufw allow 443/tcp
   sudo ufw enable
   ```

5. **File Permissions:**
   ```bash
   sudo chown -R www-data:www-data /path/to/php-server-signer
   sudo chmod -R 755 /path/to/php-server-signer
   sudo chmod 600 /path/to/php-server-signer/config.json
   ```

6. **Logging:** Monitor logs for unauthorized access attempts
   ```bash
   tail -f /var/log/nginx/stellar-php-signer-error.log
   ```

7. **Rate Limiting:** Configure nginx or use a WAF to prevent abuse

8. **Updates:** Keep PHP, Composer packages, and system packages up to date
   ```bash
   composer update --with-dependencies
   ```

## API Usage Examples

### SEP-10 Signing

```bash
curl -X POST https://your-domain.com/sign-sep-10 \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "transaction": "BASE64_XDR_ENVELOPE",
    "network_passphrase": "Test SDF Network ; September 2015"
  }'
```

**Success Response:**
```json
{
  "transaction": "SIGNED_BASE64_XDR_ENVELOPE",
  "network_passphrase": "Test SDF Network ; September 2015"
}
```

### SEP-45 Signing

```bash
curl -X POST https://your-domain.com/sign-sep-45 \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "authorization_entries": "BASE64_XDR_ARRAY",
    "network_passphrase": "Test SDF Network ; September 2015"
  }'
```

**Success Response:**
```json
{
  "authorization_entries": "SIGNED_BASE64_XDR_ARRAY",
  "network_passphrase": "Test SDF Network ; September 2015"
}
```

## Troubleshooting

### 1. "Failed to get current ledger"
**Cause:** SOROBAN_RPC_URL is not accessible or invalid

**Solution:**
- Verify the Soroban RPC URL is correct and accessible
- Test with curl: `curl https://soroban-testnet.stellar.org`
- Check firewall rules allow outbound HTTPS connections

### 2. "Unauthenticated" or 401 errors
**Cause:** Bearer token mismatch

**Solution:**
- Verify bearer token matches configuration
- Check Authorization header format: `Authorization: Bearer YOUR_TOKEN`
- Ensure no extra spaces or newlines in token

### 3. "Invalid XDR" errors
**Cause:** Malformed base64-encoded XDR data

**Solution:**
- Verify the XDR data is properly base64-encoded
- Check for any encoding issues in the client
- Test with known valid XDR data

### 4. Connection refused
**Cause:** Server not running or wrong port

**Solution:**
- Check server status: `systemctl status stellar-php-signer`
- Verify port is correct: `netstat -tulpn | grep 5003`
- Check nginx is running: `systemctl status nginx`

### 5. 500 Internal Server Error
**Cause:** PHP errors or missing dependencies

**Solution:**
- Check PHP error logs: `tail -f /var/log/nginx/stellar-php-signer-error.log`
- Verify all Composer dependencies installed: `composer install`
- Check PHP extensions: `php -m`

### 6. Slow response times
**Cause:** Performance issues or resource constraints

**Solution:**
- Enable PHP OPcache in php.ini
- Increase PHP-FPM worker processes
- Monitor server resources: `htop`
- Check Soroban RPC latency

## Monitoring and Maintenance

### Log Rotation

Create `/etc/logrotate.d/stellar-php-signer`:
```
/var/log/nginx/stellar-php-signer*.log {
    daily
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 www-data adm
    sharedscripts
    postrotate
        [ -f /var/run/nginx.pid ] && kill -USR1 `cat /var/run/nginx.pid`
    endscript
}
```

### Health Check Monitoring

Set up automated health checks:
```bash
# Add to crontab
*/5 * * * * curl -f https://your-domain.com/health || systemctl restart stellar-php-signer
```

### Backup Configuration

Regular backup of configuration:
```bash
# Backup script
tar -czf stellar-php-signer-backup-$(date +%Y%m%d).tar.gz \
  config.json \
  .env
```

## Performance Tuning

### PHP OPcache

Add to `/etc/php/8.1/fpm/php.ini`:
```ini
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.revalidate_freq=60
```

### Nginx Optimization

```nginx
# Add to nginx.conf
worker_processes auto;
worker_rlimit_nofile 65535;

events {
    worker_connections 4096;
    use epoll;
}

http {
    sendfile on;
    tcp_nopush on;
    tcp_nodelay on;
    keepalive_timeout 65;
    types_hash_max_size 2048;
    client_max_body_size 10M;
}
```

Restart services after changes:
```bash
sudo systemctl restart php8.1-fpm
sudo systemctl restart nginx
```
