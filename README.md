# Stellar PHP Server Signer

Production-ready PHP server for remote signing of SEP-10 and SEP-45 client domain authentication requests.

## Overview

This server implements remote signing capabilities for Stellar authentication protocols:

- **SEP-10**: Web Authentication - Signs transaction envelopes for user authentication
- **SEP-45**: Web Authentication for Soroban Contracts - Signs authorization entries for contract-based authentication

## Features

- SEP-10 transaction signing endpoint
- SEP-45 authorization entries signing endpoint
- Stellar TOML serving
- Bearer token authentication
- CORS support
- Health check endpoint
- Configuration via JSON file or environment variables

## Requirements

- PHP 8.1 or higher
- Composer
- Stellar PHP SDK 1.8.0 or higher

## Installation

Clone the repository and install dependencies:

```bash
git clone <repository-url>
cd php-server-signer
composer install
```

## Configuration

The server can be configured using either a JSON configuration file or environment variables.

### Configuration File

Create a `config.json` file (see `config.example.json`):

```json
{
  "host": "0.0.0.0",
  "port": 5003,
  "account_id": "GBUTDNISXHXBMZE5I4U5INJTY376S5EW2AF4SQA2SWBXUXJY3OIZQHMV",
  "secret": "SBRSOOURG2E24VGDR6NKZJMBOSOHVT6GV7EECUR3ZBE7LGSSVYN5VMOG",
  "network_passphrase": "Test SDF Network ; September 2015",
  "soroban_rpc_url": "https://soroban-testnet.stellar.org",
  "bearer_token": "987654321"
}
```

### Environment Variables

Alternatively, set these environment variables (see `.env.example`):

```bash
export HOST=0.0.0.0
export PORT=5003
export ACCOUNT_ID=GBUTDNISXHXBMZE5I4U5INJTY376S5EW2AF4SQA2SWBXUXJY3OIZQHMV
export SECRET=SBRSOOURG2E24VGDR6NKZJMBOSOHVT6GV7EECUR3ZBE7LGSSVYN5VMOG
export NETWORK_PASSPHRASE="Test SDF Network ; September 2015"
export SOROBAN_RPC_URL="https://soroban-testnet.stellar.org"
export BEARER_TOKEN=987654321
```

## Running the Server

### With Configuration File

```bash
php public/index.php -c config.json
```

### With Environment Variables

```bash
php public/index.php
```

The server uses PHP's built-in web server for development. For production deployments, use a proper web server like nginx or Apache with PHP-FPM.

## API Reference

### GET /health

Health check endpoint.

**Authentication:** Not required

**Response:**
```json
{
  "status": "ok"
}
```

**Example:**
```bash
curl http://localhost:5003/health
```

### GET /.well-known/stellar.toml

Returns the Stellar TOML file with the signing key.

**Authentication:** Not required

**Response:**
```toml
ACCOUNTS = ["GBUTDNISXHXBMZE5I4U5INJTY376S5EW2AF4SQA2SWBXUXJY3OIZQHMV"]
SIGNING_KEY = "GBUTDNISXHXBMZE5I4U5INJTY376S5EW2AF4SQA2SWBXUXJY3OIZQHMV"
NETWORK_PASSPHRASE = "Test SDF Network ; September 2015"
```

**Example:**
```bash
curl http://localhost:5003/.well-known/stellar.toml
```

### POST /sign-sep-10

Signs a SEP-10 transaction envelope.

**Authentication:** Required (Bearer token)

**Request:**
```json
{
  "transaction": "<base64 XDR envelope>",
  "network_passphrase": "Test SDF Network ; September 2015"
}
```

**Response:**
```json
{
  "transaction": "<signed base64 XDR envelope>",
  "network_passphrase": "Test SDF Network ; September 2015"
}
```

**Example:**
```bash
curl -X POST http://localhost:5003/sign-sep-10 \
  -H "Authorization: Bearer 987654321" \
  -H "Content-Type: application/json" \
  -d '{
    "transaction": "AAAAAgAAAAD...",
    "network_passphrase": "Test SDF Network ; September 2015"
  }'
```

### POST /sign-sep-45

Signs a SEP-45 authorization entry for client domain verification.

**Authentication:** Required (Bearer token)

**Request:**
```json
{
  "authorization_entry": "<base64 XDR of single SorobanAuthorizationEntry>",
  "network_passphrase": "Test SDF Network ; September 2015"
}
```

**Response:**
```json
{
  "authorization_entry": "<signed base64 XDR of entry>",
  "network_passphrase": "Test SDF Network ; September 2015"
}
```

**Errors:**
```json
{
  "error": "entry address does not match signing key"
}
```

**Example:**
```bash
curl -X POST http://localhost:5003/sign-sep-45 \
  -H "Authorization: Bearer 987654321" \
  -H "Content-Type: application/json" \
  -d '{
    "authorization_entry": "AAAAAgAAAAD...",
    "network_passphrase": "Test SDF Network ; September 2015"
  }'
```

## Security Considerations

- Store secrets securely (use environment variables or secure secret management)
- Use HTTPS in production
- Implement rate limiting
- Rotate bearer tokens regularly
- Use strong, randomly generated bearer tokens
- Monitor and log authentication failures
- Consider implementing IP whitelisting
- Never commit `config.json` or `.env` files with real secrets

## Testing

Run the unit tests:

```bash
composer test
```

Or using PHPUnit directly:

```bash
vendor/bin/phpunit
```

Run tests with coverage:

```bash
vendor/bin/phpunit --coverage-html coverage
```

## Project Structure

```
.
├── public/
│   └── index.php          # Main application entry point
├── src/
│   ├── Config/
│   │   └── Config.php     # Configuration management
│   ├── Handler/
│   │   └── Router.php     # HTTP routing and handlers
│   └── Signer/
│       ├── Sep10Signer.php  # SEP-10 signing logic
│       └── Sep45Signer.php  # SEP-45 signing logic
├── tests/
│   ├── Config/
│   │   └── ConfigTest.php
│   ├── Handler/
│   │   └── RouterTest.php
│   └── Signer/
│       ├── Sep10SignerTest.php
│       └── Sep45SignerTest.php
├── .env.example
├── .gitignore
├── composer.json
├── config.example.json
├── phpunit.xml
└── README.md
```

## Error Handling

All endpoints return appropriate HTTP status codes:

- `200 OK` - Successful operation
- `400 Bad Request` - Invalid request parameters or malformed data
- `401 Unauthorized` - Missing or invalid authentication
- `405 Method Not Allowed` - Wrong HTTP method used
- `500 Internal Server Error` - Server error

Error responses include a JSON body with error details:

```json
{
  "error": "error message"
}
```

## SEP-45 Signing Details

The SEP-45 signing process involves:

1. Decoding the base64 XDR of a single `SorobanAuthorizationEntry`
2. Validating that the entry uses address credentials and that the address matches the signing key
3. Fetching the current ledger from Soroban RPC and setting signature expiration ledger
4. Building a `HashIdPreimage` with type `ENVELOPE_TYPE_SOROBAN_AUTHORIZATION` containing:
   - `network_id` (SHA256 hash of network passphrase)
   - `nonce` from address credentials
   - `signature_expiration_ledger` from address credentials
   - `root_invocation` from the entry
5. Computing SHA256 hash of the preimage
6. Signing the hash with the keypair
7. Setting the signature as an `SCVal` Vec containing a Map with `public_key` and `signature` bytes
8. Returning the signed entry as base64 XDR

## Production Deployment

For production deployment:

1. Use a production-grade web server (nginx, Apache) with PHP-FPM
2. Configure reverse proxy with HTTPS
3. Set up monitoring and logging
4. Implement rate limiting at the reverse proxy level
5. Use secure secret management (environment variables, HashiCorp Vault, AWS Secrets Manager, etc.)
6. Enable PHP OPcache for better performance
7. Set appropriate PHP memory limits and timeout values
8. Use a process manager (systemd, supervisor) to ensure server availability

See `PROMPT_DEPLOY_SERVER.md` for detailed deployment instructions.

## Docker

Build and run with Docker:

```bash
docker build -t stellar-php-server-signer .
docker run -p 5003:5003 \
  -e ACCOUNT_ID=GBUTDNISXHXBMZE5I4U5INJTY376S5EW2AF4SQA2SWBXUXJY3OIZQHMV \
  -e SECRET=SBRSOOURG2E24VGDR6NKZJMBOSOHVT6GV7EECUR3ZBE7LGSSVYN5VMOG \
  -e BEARER_TOKEN=987654321 \
  stellar-php-server-signer
```

Or use Docker Compose:

```bash
docker-compose up
```

## License

Apache 2.0

## References

- [SEP-10 Specification](https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0010.md)
- [SEP-45 Specification](https://github.com/stellar/stellar-protocol/blob/master/ecosystem/sep-0045.md)
- [Stellar PHP SDK](https://github.com/Soneso/stellar-php-sdk)
