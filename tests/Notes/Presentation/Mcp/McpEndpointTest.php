<?php

declare(strict_types=1);

namespace App\Tests\Notes\Presentation\Mcp;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class McpEndpointTest extends WebTestCase
{
    private const string TOKEN = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    public function testTheEndpointRequiresABearerToken(): void
    {
        $client = self::createClient();
        $client->request(
            'POST',
            '/mcp',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json, text/event-stream',
                'HTTP_HOST' => 'localhost',
            ],
            content: '{"jsonrpc":"2.0","id":1,"method":"tools/list"}',
        );

        self::assertResponseStatusCodeSame(401);
    }

    public function testCorsPreflightCanReachTheMcpTransport(): void
    {
        $client = self::createClient();
        $client->request(
            'OPTIONS',
            '/mcp',
            server: [
                'HTTP_HOST' => 'localhost',
                'HTTP_ORIGIN' => 'http://localhost',
            ],
        );

        self::assertResponseStatusCodeSame(204);
    }

    public function testAnAuthenticatedClientCanListTheNoteTools(): void
    {
        $client = self::createClient();
        $client->request(
            'POST',
            '/mcp',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json, text/event-stream',
                'HTTP_AUTHORIZATION' => 'Bearer '.self::TOKEN,
                'HTTP_HOST' => 'localhost',
                'HTTP_MCP_METHOD' => 'tools/list',
                'HTTP_MCP_PROTOCOL_VERSION' => '2026-07-28',
            ],
            content: <<<'JSON'
                {
                    "jsonrpc": "2.0",
                    "id": 1,
                    "method": "tools/list",
                    "params": {
                        "_meta": {
                            "io.modelcontextprotocol/protocolVersion": "2026-07-28",
                            "io.modelcontextprotocol/clientCapabilities": {},
                            "io.modelcontextprotocol/clientInfo": {
                                "name": "sym-notes-tests",
                                "version": "1.0.0"
                            }
                        }
                    }
                }
                JSON,
        );

        self::assertResponseIsSuccessful();

        /** @var array{result: array{tools: list<array{name: string, inputSchema: array<string, mixed>}>}} $response */
        $response = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $names = array_column($response['result']['tools'], 'name');

        self::assertCount(12, $names);
        self::assertContains('notes_list', $names);
        self::assertContains('notes_create', $names);
        self::assertContains('notes_attach', $names);
        self::assertContains('folders_list', $names);
        self::assertContains('folders_create', $names);

        $tools = array_column($response['result']['tools'], null, 'name');
        $attachmentSchema = $tools['notes_attach']['inputSchema'];
        self::assertSame(['id', 'filename', 'mimeType', 'contentBase64'], $attachmentSchema['required']);
        self::assertSame(13_981_016, $attachmentSchema['properties']['contentBase64']['maxLength']);
    }
}
