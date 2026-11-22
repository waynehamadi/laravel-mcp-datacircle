<?php

declare(strict_types=1);

namespace Datacircle\LaravelMcp\Tools;

use Datacircle\LaravelMcp\Client;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

/**
 * A Laravel MCP tool on Datacircle's open API. Its result is a JSON object, as text and as structured content. An answer
 * of 4xx is an error result with the API's own words, so that the model reads them and can call again; any other
 * failure is thrown, and Laravel MCP reports it. Each tool is read-only, idempotent and open-world: Laravel MCP reads
 * a tool's annotations on its own class only.
 */
abstract class DatacircleTool extends Tool
{
    protected Client $client;

    /**
     * @param  Client|null  $client  The API's client: the container's Client when Laravel MCP makes the tool.
     */
    public function __construct(?Client $client = null)
    {
        $this->client = $client ?? new Client;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    protected static function json(array $result): ResponseFactory
    {
        return Response::structured($result);
    }

    protected static function error(string $message): Response
    {
        return Response::error($message);
    }

    /**
     * A string argument as the model sent it: trimmed, '' when it sent none or something else.
     */
    protected static function text(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }
}
