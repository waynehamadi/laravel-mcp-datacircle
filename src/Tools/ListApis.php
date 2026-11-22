<?php

declare(strict_types=1);

namespace Datacircle\LaravelMcp\Tools;

use Datacircle\LaravelMcp\ApiError;
use Datacircle\LaravelMcp\Client;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * datacircle_list_apis: GET /apis.
 */
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld]
class ListApis extends DatacircleTool
{
    public const NAME = 'datacircle_list_apis';

    protected string $name = self::NAME;

    protected string $title = 'List the GTM APIs';

    protected string $description = "Lists the GTM APIs on Datacircle's sheet (https://datacircle.dev), with each API's slug, how many plans and providers it has and its best plan. Call it first to find an API's slug.";

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description("Words of the API's name or slug, like email or linkedin profile: the APIs that hold the most of them come first. The sheet's order when left out"),
            'limit' => $schema->integer()->min(1)->max(200)->description('How many APIs to give, 1 to 200. 20 when left out: the sheet has about 170'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        try {
            $apis = Client::plans($this->client->get('/apis'));
        } catch (ApiError $e) {
            return self::error($e->getMessage());
        }

        $words = preg_split('/\s+/', strtolower(str_replace('-', ' ', self::text($request->get('query')))), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $found = [];
        foreach ($apis as $index => $api) {
            $name = strtolower((string) ($api['api'] ?? '').' '.str_replace('-', ' ', (string) ($api['slug'] ?? '')));
            $matched = count(array_filter($words, fn (string $word): bool => str_contains($name, $word)));
            if ($matched > 0 || $words === []) {
                $found[] = [$matched, $index, Client::brief($api, ['api', 'slug', 'plans', 'providers', 'best', 'bestPage', 'sheetPage'])];
            }
        }
        // The most words matched first, the sheet's order within each.
        usort($found, fn (array $a, array $b): int => [$b[0], $a[1]] <=> [$a[0], $b[1]]);

        return self::json([
            'total' => count($found),
            'apis' => array_column(array_slice($found, 0, Client::number($request->get('limit'), 1, 200) ?? 20), 2),
        ]);
    }
}
