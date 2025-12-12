# Docker Deployment Guide

This guide covers Docker deployment for the PHP Stellar Server Signer.

## Quick Start

### Using Docker Compose (Recommended)

1. Create `.env` file from example:
```bash
cp .env.example .env
# Edit .env with your values
```

2. Start the service:
```bash
docker-compose up -d
```

3. Check logs:
```bash
docker-compose logs -f
```

4. Stop the service:
```bash
docker-compose down
```

### Using Docker Directly

1. Build the image:
```bash
docker build -t stellar-php-remote-signer:latest .
```

2. Run the container:
```bash
docker run -p 5003:5003 --env-file .env stellar-php-remote-signer:latest
```

## Using Makefile

The project includes a Makefile for common tasks:

```bash
# Install dependencies
make install

# Run tests
make test

# Run tests with coverage
make test-coverage

# Check PHP syntax
make lint

# Build Docker image
make docker-build

# Run Docker container
make docker-run

# Start with Docker Compose
make docker-compose-up

# Stop Docker Compose
make docker-compose-down

# Show all available commands
make help
```

## Configuration

### Environment Variables

Required variables:
- `ACCOUNT_ID` - Stellar account ID
- `SECRET` - Stellar secret key
- `NETWORK_PASSPHRASE` - Network passphrase (default: "Test SDF Network ; September 2015")
- `BEARER_TOKEN` - API authentication token (default: "987654321")
- `HOST` - Server host (default: "0.0.0.0")
- `PORT` - Server port (default: "5003")
- `SOROBAN_RPC_URL` - Soroban RPC endpoint (default: "https://soroban-testnet.stellar.org")

### Using Config File

Uncomment the volume mount in `docker-compose.yml`:
```yaml
volumes:
  - ./config.json:/app/config.json:ro
```

## Docker Image Details

### Multi-Stage Build

The Dockerfile uses a multi-stage build for optimal image size:

1. **Builder Stage** - Installs Composer and dependencies
2. **Runtime Stage** - Contains only production dependencies

### Security Features

- Runs as non-root user (appuser:1000)
- Minimal Alpine Linux base
- Only production dependencies included
- Health check endpoint configured

### Image Size

The final image is approximately 100-150MB.

## Health Checks

The container includes a built-in health check:

```bash
# Check container health
docker ps

# Test health endpoint
curl http://localhost:5003/health
```

## Production Deployment

### Build Production Image

```bash
make build-prod
make docker-build
```

### Push to Registry

```bash
# Tag for your registry
docker tag stellar-php-remote-signer:latest your-registry/stellar-php-remote-signer:1.0.0

# Push to registry
docker push your-registry/stellar-php-remote-signer:1.0.0
```

### Deploy to Production

```bash
# Pull and run
docker pull your-registry/stellar-php-remote-signer:1.0.0
docker run -d \
  --name stellar-signer \
  --restart unless-stopped \
  -p 5003:5003 \
  --env-file /etc/stellar-signer/.env \
  your-registry/stellar-php-remote-signer:1.0.0
```

## Troubleshooting

### View Logs

```bash
# Docker Compose
docker-compose logs -f

# Docker direct
docker logs -f stellar-php-remote-signer
```

### Access Container Shell

```bash
# Docker Compose
docker-compose exec remote-signer /bin/sh

# Docker direct
docker exec -it stellar-php-remote-signer /bin/sh
```

### Check Configuration

```bash
docker exec stellar-php-remote-signer cat .env.example
```

### Rebuild Image

```bash
# With Docker Compose
docker-compose build --no-cache

# With Make
make docker-build
```

## Resource Limits

Add resource limits to `docker-compose.yml`:

```yaml
services:
  remote-signer:
    deploy:
      resources:
        limits:
          cpus: '0.5'
          memory: 256M
        reservations:
          cpus: '0.25'
          memory: 128M
```

## Monitoring

### Prometheus Metrics

The server exposes basic metrics at the health endpoint. For advanced monitoring, integrate with:

- Prometheus
- Grafana
- ELK Stack

### Log Aggregation

Forward logs to a centralized logging system:

```bash
docker run \
  --log-driver=syslog \
  --log-opt syslog-address=tcp://logserver:514 \
  stellar-php-remote-signer:latest
```

## Security Considerations

1. **Never commit secrets** - Use environment variables or secret management
2. **Use HTTPS** - Deploy behind a reverse proxy with SSL/TLS
3. **Restrict network access** - Use firewall rules to limit access
4. **Regular updates** - Keep base image and dependencies updated
5. **Scan for vulnerabilities** - Use tools like Trivy or Snyk

## Support

For issues or questions, refer to:
- README.md - General documentation
- docs/TESTING.md - Testing guide
- docs/SEP10_IMPLEMENTATION.md - SEP-10 implementation details
