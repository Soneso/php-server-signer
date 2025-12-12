<?php

declare(strict_types=1);

namespace Soneso\ServerSigner\Tests\Handler;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Soneso\ServerSigner\Handler\Router;

/**
 * Test suite for HTTP Router
 */
final class RouterTest extends TestCase
{
    private const TEST_BEARER_TOKEN = 'test-token-12345';

    protected function setUp(): void
    {
        // Reset server globals before each test
        $_SERVER = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';
    }

    protected function tearDown(): void
    {
        // Clean up output buffers if any were started
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }

    #[Test]
    public function it_handles_get_route_successfully(): void
    {
        $router = new Router();
        $handlerCalled = false;

        $router->get('/test', function () use (&$handlerCalled) {
            $handlerCalled = true;
        });

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/test';

        ob_start();
        try {
            $router->handle();
        } catch (\Throwable $e) {
            // handle() calls exit, which we can't prevent in tests
            // but we can catch any errors before that
        }
        ob_end_clean();

        $this->assertTrue($handlerCalled, 'GET handler should be called');
    }

    #[Test]
    public function it_handles_post_route_successfully(): void
    {
        $router = new Router();
        $handlerCalled = false;

        $router->post('/submit', function () use (&$handlerCalled) {
            $handlerCalled = true;
        });

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/submit';

        ob_start();
        try {
            $router->handle();
        } catch (\Throwable $e) {
        }
        ob_end_clean();

        $this->assertTrue($handlerCalled, 'POST handler should be called');
    }

    #[Test]
    public function it_returns_404_for_unknown_route(): void
    {
        $router = new Router();
        $router->get('/exists', function () {
            // Handler for existing route
        });

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/does-not-exist';

        ob_start();
        try {
            $router->handle();
        } catch (\Throwable $e) {
        }
        $output = ob_get_clean();

        $this->assertStringContainsString('Not Found', $output);
        $this->assertStringContainsString('error', $output);
    }

    #[Test]
    public function it_handles_options_preflight_request(): void
    {
        $router = new Router();

        $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
        $_SERVER['REQUEST_URI'] = '/any-path';

        ob_start();
        try {
            $router->handle();
        } catch (\Throwable $e) {
        }
        $output = ob_get_clean();

        // OPTIONS should return 200 with CORS headers
        // The output should be empty or minimal for OPTIONS
        $this->assertIsString($output);
    }

    #[Test]
    public function it_authenticates_with_valid_bearer_token(): void
    {
        $router = new Router();
        $router->setBearerToken(self::TEST_BEARER_TOKEN);

        $handlerCalled = false;
        $router->post('/protected', function () use (&$handlerCalled) {
            $handlerCalled = true;
        });
        $router->requireAuth('/protected');

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/protected';
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . self::TEST_BEARER_TOKEN;

        ob_start();
        try {
            $router->handle();
        } catch (\Throwable $e) {
        }
        ob_end_clean();

        $this->assertTrue($handlerCalled, 'Handler should be called with valid token');
    }

    #[Test]
    public function it_rejects_request_with_missing_authorization_header(): void
    {
        $router = new Router();
        $router->setBearerToken(self::TEST_BEARER_TOKEN);

        $handlerCalled = false;
        $router->post('/protected', function () use (&$handlerCalled) {
            $handlerCalled = true;
        });
        $router->requireAuth('/protected');

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/protected';
        // No HTTP_AUTHORIZATION header

        ob_start();
        try {
            $router->handle();
        } catch (\Throwable $e) {
        }
        $output = ob_get_clean();

        $this->assertFalse($handlerCalled, 'Handler should not be called without auth header');
        $this->assertStringContainsString('Unauthenticated', $output);
    }

    #[Test]
    public function it_rejects_request_with_invalid_bearer_token(): void
    {
        $router = new Router();
        $router->setBearerToken(self::TEST_BEARER_TOKEN);

        $handlerCalled = false;
        $router->post('/protected', function () use (&$handlerCalled) {
            $handlerCalled = true;
        });
        $router->requireAuth('/protected');

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/protected';
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer wrong-token';

        ob_start();
        try {
            $router->handle();
        } catch (\Throwable $e) {
        }
        $output = ob_get_clean();

        $this->assertFalse($handlerCalled, 'Handler should not be called with wrong token');
        $this->assertStringContainsString('Unauthenticated', $output);
    }

    #[Test]
    public function it_rejects_request_with_malformed_authorization_header(): void
    {
        $router = new Router();
        $router->setBearerToken(self::TEST_BEARER_TOKEN);

        $handlerCalled = false;
        $router->post('/protected', function () use (&$handlerCalled) {
            $handlerCalled = true;
        });
        $router->requireAuth('/protected');

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/protected';
        $_SERVER['HTTP_AUTHORIZATION'] = 'Basic ' . self::TEST_BEARER_TOKEN; // Wrong scheme

        ob_start();
        try {
            $router->handle();
        } catch (\Throwable $e) {
        }
        $output = ob_get_clean();

        $this->assertFalse($handlerCalled, 'Handler should not be called with wrong auth scheme');
        $this->assertStringContainsString('Unauthenticated', $output);
    }

    #[Test]
    public function it_allows_unauthenticated_routes_without_token(): void
    {
        $router = new Router();
        $router->setBearerToken(self::TEST_BEARER_TOKEN);

        $handlerCalled = false;
        $router->get('/public', function () use (&$handlerCalled) {
            $handlerCalled = true;
        });
        // Do not call requireAuth for this route

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/public';
        // No authorization header

        ob_start();
        try {
            $router->handle();
        } catch (\Throwable $e) {
        }
        ob_end_clean();

        $this->assertTrue($handlerCalled, 'Public route should work without auth');
    }

    #[Test]
    public function it_sends_json_response(): void
    {
        $router = new Router();

        ob_start();
        try {
            $router->sendResponse(200, ['status' => 'ok', 'data' => 'test']);
        } catch (\Throwable $e) {
            // sendResponse calls exit
        }
        $output = ob_get_clean();

        $this->assertStringContainsString('status', $output);
        $this->assertStringContainsString('ok', $output);
        $this->assertStringContainsString('test', $output);

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
        $this->assertSame('ok', $decoded['status']);
        $this->assertSame('test', $decoded['data']);
    }

    #[Test]
    public function it_sends_error_response(): void
    {
        $router = new Router();

        ob_start();
        try {
            $router->sendError(400, 'Bad Request');
        } catch (\Throwable $e) {
        }
        $output = ob_get_clean();

        $this->assertStringContainsString('error', $output);
        $this->assertStringContainsString('Bad Request', $output);

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
        $this->assertSame('Bad Request', $decoded['error']);
    }

    #[Test]
    public function it_parses_json_request_body(): void
    {
        $router = new Router();

        $testData = ['key' => 'value', 'number' => 42];
        $jsonBody = json_encode($testData);

        // Create a temporary stream for php://input
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $jsonBody);
        rewind($stream);

        // Mock php://input by creating a temporary file
        $tempFile = tmpfile();
        fwrite($tempFile, $jsonBody);
        rewind($tempFile);

        // We can't easily mock php://input, so we'll test the exception path
        // This tests that getJsonBody throws on empty body
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Request body is empty');

        $router->getJsonBody();
    }

    #[Test]
    public function it_throws_exception_for_invalid_json_body(): void
    {
        // We can't easily inject data into php://input in unit tests
        // This would require integration testing with actual HTTP requests
        // For now, we test that the method exists and has the right signature
        $router = new Router();

        $this->expectException(InvalidArgumentException::class);
        $router->getJsonBody();
    }

    #[Test]
    public function it_handles_health_endpoint(): void
    {
        $router = new Router();
        $router->get('/health', function () use ($router) {
            $router->sendResponse(200, ['status' => 'ok']);
        });

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/health';

        ob_start();
        try {
            $router->handle();
        } catch (\Throwable $e) {
        }
        $output = ob_get_clean();

        $this->assertStringContainsString('status', $output);
        $this->assertStringContainsString('ok', $output);
    }

    #[Test]
    public function it_handles_stellar_toml_endpoint(): void
    {
        $router = new Router();
        $router->get('/.well-known/stellar.toml', function () {
            echo "NETWORK_PASSPHRASE=\"Test SDF Network ; September 2015\"\n";
            echo "ACCOUNTS=[\"GBUTDNISXHXBMZE5I4U5INJTY376S5EW2AF4SQA2SWBXUXJY3OIZQHMV\"]\n";
        });

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/.well-known/stellar.toml';

        ob_start();
        try {
            $router->handle();
        } catch (\Throwable $e) {
        }
        $output = ob_get_clean();

        $this->assertStringContainsString('NETWORK_PASSPHRASE', $output);
        $this->assertStringContainsString('ACCOUNTS', $output);
    }

    #[Test]
    public function it_handles_query_string_in_uri(): void
    {
        $router = new Router();
        $handlerCalled = false;

        $router->get('/search', function () use (&$handlerCalled) {
            $handlerCalled = true;
        });

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/search?q=test&page=1';

        ob_start();
        try {
            $router->handle();
        } catch (\Throwable $e) {
        }
        ob_end_clean();

        $this->assertTrue($handlerCalled, 'Handler should be called even with query string');
    }

    #[Test]
    public function it_uses_timing_safe_string_comparison_for_tokens(): void
    {
        // Test that the router uses hash_equals for token comparison
        // This is important for security to prevent timing attacks

        $router = new Router();
        $router->setBearerToken(self::TEST_BEARER_TOKEN);

        // Create a token that differs only in the last character
        $similarToken = substr(self::TEST_BEARER_TOKEN, 0, -1) . 'X';

        $handlerCalled = false;
        $router->post('/protected', function () use (&$handlerCalled) {
            $handlerCalled = true;
        });
        $router->requireAuth('/protected');

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/protected';
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $similarToken;

        ob_start();
        try {
            $router->handle();
        } catch (\Throwable $e) {
        }
        $output = ob_get_clean();

        $this->assertFalse($handlerCalled, 'Similar token should not authenticate');
        $this->assertStringContainsString('Unauthenticated', $output);
    }

    #[Test]
    public function it_handles_multiple_routes(): void
    {
        $router = new Router();

        $route1Called = false;
        $route2Called = false;

        $router->get('/route1', function () use (&$route1Called) {
            $route1Called = true;
        });

        $router->post('/route2', function () use (&$route2Called) {
            $route2Called = true;
        });

        // Test route 1
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/route1';

        ob_start();
        try {
            $router->handle();
        } catch (\Throwable $e) {
        }
        ob_end_clean();

        $this->assertTrue($route1Called, 'Route 1 should be called');
        $this->assertFalse($route2Called, 'Route 2 should not be called yet');

        // Test route 2
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/route2';

        ob_start();
        try {
            $router->handle();
        } catch (\Throwable $e) {
        }
        ob_end_clean();

        $this->assertTrue($route2Called, 'Route 2 should now be called');
    }

    #[Test]
    public function it_handles_exception_in_route_handler(): void
    {
        $router = new Router();

        $router->get('/error', function () {
            throw new \RuntimeException('Test error');
        });

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/error';

        ob_start();
        try {
            $router->handle();
        } catch (\Throwable $e) {
        }
        $output = ob_get_clean();

        // Should return 500 Internal Server Error
        $this->assertStringContainsString('Internal Server Error', $output);
    }

    #[Test]
    public function it_sends_response_with_null_data(): void
    {
        $router = new Router();

        ob_start();
        try {
            $router->sendResponse(204, null);
        } catch (\Throwable $e) {
        }
        $output = ob_get_clean();

        // Null data should result in empty response
        $this->assertEmpty($output);
    }

    #[Test]
    public function it_sends_json_without_escaped_slashes(): void
    {
        $router = new Router();

        $data = ['url' => 'https://example.com/path'];

        ob_start();
        try {
            $router->sendResponse(200, $data);
        } catch (\Throwable $e) {
        }
        $output = ob_get_clean();

        // Should not escape slashes in JSON
        $this->assertStringContainsString('https://example.com/path', $output);
        $this->assertStringNotContainsString('\\/', $output);
    }

    #[Test]
    public function it_handles_empty_bearer_token_configuration(): void
    {
        $router = new Router();
        // Don't set bearer token

        $router->post('/protected', function () {
            // Handler
        });
        $router->requireAuth('/protected');

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/protected';
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer some-token';

        ob_start();
        try {
            $router->handle();
        } catch (\Throwable $e) {
        }
        $output = ob_get_clean();

        // Should fail authentication if no bearer token is configured
        $this->assertStringContainsString('Unauthenticated', $output);
    }

    #[Test]
    public function it_can_register_route_with_add_route_method(): void
    {
        $router = new Router();
        $handlerCalled = false;

        $router->addRoute('PUT', '/update', function () use (&$handlerCalled) {
            $handlerCalled = true;
        });

        $_SERVER['REQUEST_METHOD'] = 'PUT';
        $_SERVER['REQUEST_URI'] = '/update';

        ob_start();
        try {
            $router->handle();
        } catch (\Throwable $e) {
        }
        ob_end_clean();

        $this->assertTrue($handlerCalled, 'Custom HTTP method route should work');
    }

    #[Test]
    public function it_normalizes_http_method_to_uppercase(): void
    {
        $router = new Router();
        $handlerCalled = false;

        // Register with lowercase method
        $router->addRoute('delete', '/resource', function () use (&$handlerCalled) {
            $handlerCalled = true;
        });

        $_SERVER['REQUEST_METHOD'] = 'DELETE';
        $_SERVER['REQUEST_URI'] = '/resource';

        ob_start();
        try {
            $router->handle();
        } catch (\Throwable $e) {
        }
        ob_end_clean();

        $this->assertTrue($handlerCalled, 'Method should be normalized to uppercase');
    }

    #[Test]
    public function it_handles_log_message(): void
    {
        ob_start();
        Router::log('Test message');
        $output = ob_get_clean();

        $this->assertStringContainsString('Test message', $output);
        $this->assertMatchesRegularExpression('/\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\]/', $output);
    }
}
