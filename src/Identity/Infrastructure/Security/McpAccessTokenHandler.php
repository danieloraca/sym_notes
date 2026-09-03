<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

final class McpAccessTokenHandler implements AccessTokenHandlerInterface
{
    public function __construct(
        #[Autowire(env: 'MCP_TOKEN')]
        private readonly string $expectedToken,
        #[Autowire(env: 'MCP_USER_EMAIL')]
        private readonly string $userEmail,
    ) {
    }

    public function getUserBadgeFrom(#[\SensitiveParameter] string $accessToken): UserBadge
    {
        if (
            strlen($this->expectedToken) < 32
            || '' === trim($this->userEmail)
            || !hash_equals($this->expectedToken, $accessToken)
        ) {
            throw new BadCredentialsException('Invalid MCP access token.');
        }

        return new UserBadge(mb_strtolower(trim($this->userEmail)));
    }
}
