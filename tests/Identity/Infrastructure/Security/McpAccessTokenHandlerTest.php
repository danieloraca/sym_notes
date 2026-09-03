<?php

declare(strict_types=1);

namespace App\Tests\Identity\Infrastructure\Security;

use App\Identity\Infrastructure\Security\McpAccessTokenHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;

final class McpAccessTokenHandlerTest extends TestCase
{
    public function testItReturnsTheConfiguredUserForAValidToken(): void
    {
        $handler = new McpAccessTokenHandler(str_repeat('a', 64), ' Daniel@Example.COM ');

        self::assertSame('daniel@example.com', $handler->getUserBadgeFrom(str_repeat('a', 64))->getUserIdentifier());
    }

    public function testItRejectsAnInvalidToken(): void
    {
        $handler = new McpAccessTokenHandler(str_repeat('a', 64), 'daniel@example.com');

        $this->expectException(BadCredentialsException::class);
        $handler->getUserBadgeFrom(str_repeat('b', 64));
    }

    public function testItRejectsAnUnsafeEmptyConfiguration(): void
    {
        $handler = new McpAccessTokenHandler('', '');

        $this->expectException(BadCredentialsException::class);
        $handler->getUserBadgeFrom('');
    }
}
