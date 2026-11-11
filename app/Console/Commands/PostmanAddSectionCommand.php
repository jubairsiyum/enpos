<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;

class PostmanAddSectionCommand extends Command
{
    protected $signature = 'postman:add-section
                            {name : Display name of the new section (e.g. "Products" or "Contacts (Customers)")}
                            {uri : API route suffix under /api/v1/ (e.g. shop/products or shop/customers)}
                            {--variable= : Collection variable name to store created record ID (e.g. product_id)}
                            {--collection=enPOS — Complete API (User Management & Contacts) : Target Postman collection folder}';

    protected $description = 'Add a new API section folder into the single master Postman collection and sync JSON.';

    public function handle(): int
    {
        $sectionName = trim((string) $this->argument('name'));
        $uri = trim((string) $this->argument('uri'), '/');
        $collectionDir = base_path('postman/collections/' . (string) $this->option('collection'));

        if (! File::isDirectory($collectionDir)) {
            $this->error("Collection directory not found: {$collectionDir}");

            return self::FAILURE;
        }

        $singular = Str::singular(Str::studly(class_basename($uri)));
        $plural = Str::plural($singular);
        $varName = $this->option('variable')
            ? (string) $this->option('variable')
            : Str::snake($singular) . '_id';

        $nextNumber = $this->determineNextSectionNumber($collectionDir);
        $folderName = "{$nextNumber}. {$sectionName}";
        $folderPath = $collectionDir . DIRECTORY_SEPARATOR . $folderName;

        if (File::isDirectory($folderPath)) {
            $this->error("Folder already exists: {$folderName}");

            return self::FAILURE;
        }

        File::ensureDirectoryExists($folderPath . DIRECTORY_SEPARATOR . '.resources');
        File::put(
            $folderPath . DIRECTORY_SEPARATOR . '.resources' . DIRECTORY_SEPARATOR . 'definition.yaml',
            Yaml::dump([
                '$kind' => 'collection',
                'order' => $nextNumber * 1000,
            ], 2, 2)
        );

        $registeredUris = $this->getMatchingApiRoutes("api/v1/{$uri}");
        $hasRestore = $registeredUris->contains(fn (string $r): bool => str_ends_with($r, '/restore'));
        $hasForceDelete = $registeredUris->contains(fn (string $r): bool => str_ends_with($r, '/force-delete'));

        $requests = [
            "List {$plural}" => [
                '$kind'       => 'http-request',
                'url'         => "{{base_url}}/{$uri}?per_page=25",
                'method'      => 'GET',
                'headers'     => $this->authHeaders(false),
                'queryParams' => ['per_page' => '25'],
                'order'       => 1000,
            ],
            "Create {$singular}" => [
                '$kind'   => 'http-request',
                'url'     => "{{base_url}}/{$uri}",
                'method'  => 'POST',
                'headers' => $this->authHeaders(true),
                'body'    => [
                    'type'    => 'text',
                    'content' => "{\n    \"name\": \"Sample {$singular}\"\n}",
                ],
                'scripts' => [
                    [
                        'type'     => 'afterResponse',
                        'code'     => "var jsonData = pm.response.json();\nif (jsonData.data && jsonData.data.id) {\n    pm.collectionVariables.set('{$varName}', jsonData.data.id);\n    console.log('{$singular} ID saved:', jsonData.data.id);\n}",
                        'language' => 'text/javascript',
                    ],
                ],
                'order'   => 2000,
            ],
            "Get {$singular} by ID" => [
                '$kind'   => 'http-request',
                'url'     => "{{base_url}}/{$uri}/{{{$varName}}}",
                'method'  => 'GET',
                'headers' => $this->authHeaders(false),
                'order'   => 3000,
            ],
            "Update {$singular}" => [
                '$kind'   => 'http-request',
                'url'     => "{{base_url}}/{$uri}/{{{$varName}}}",
                'method'  => 'PUT',
                'headers' => $this->authHeaders(true),
                'body'    => [
                    'type'    => 'text',
                    'content' => "{\n    \"name\": \"Updated {$singular}\"\n}",
                ],
                'order'   => 4000,
            ],
            "Delete {$singular}" => [
                '$kind'   => 'http-request',
                'url'     => "{{base_url}}/{$uri}/{{{$varName}}}",
                'method'  => 'DELETE',
                'headers' => $this->authHeaders(false),
                'order'   => 5000,
            ],
        ];

        if ($hasRestore) {
            $requests["List Trashed {$plural}"] = [
                '$kind'       => 'http-request',
                'url'         => "{{base_url}}/{$uri}?only_trashed=1",
                'method'      => 'GET',
                'headers'     => $this->authHeaders(false),
                'queryParams' => ['only_trashed' => '1'],
                'order'       => 6000,
            ];
            $requests["Restore {$singular}"] = [
                '$kind'   => 'http-request',
                'url'     => "{{base_url}}/{$uri}/{{{$varName}}}/restore",
                'method'  => 'POST',
                'headers' => $this->authHeaders(false),
                'order'   => 7000,
            ];
        }

        if ($hasForceDelete) {
            $requests["Force Delete {$singular}"] = [
                '$kind'   => 'http-request',
                'url'     => "{{base_url}}/{$uri}/{{{$varName}}}/force-delete",
                'method'  => 'DELETE',
                'headers' => $this->authHeaders(false),
                'order'   => 8000,
            ];
        }

        foreach ($requests as $reqName => $payload) {
            File::put(
                $folderPath . DIRECTORY_SEPARATOR . "{$reqName}.request.yaml",
                Yaml::dump($payload, 4, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK)
            );
        }

        $this->ensureCollectionVariable($collectionDir, $varName, $folderName);

        $this->call('postman:sync', [
            '--collection' => $this->option('collection'),
        ]);

        $this->info("Added section '{$folderName}' with " . count($requests) . ' requests and synced JSON collection.');

        return self::SUCCESS;
    }

    private function determineNextSectionNumber(string $collectionDir): int
    {
        $max = 0;
        foreach (File::directories($collectionDir) as $dir) {
            $base = basename($dir);
            if ($base === '.resources') {
                continue;
            }
            if (preg_match('/^(\d+)\./', $base, $matches)) {
                $max = max($max, (int) $matches[1]);
            }
        }

        return $max + 1;
    }

    /**
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function getMatchingApiRoutes(string $prefix)
    {
        return collect(RouteFacade::getRoutes()->getRoutes())
            ->map(fn (Route $route): string => $route->uri())
            ->filter(fn (string $routeUri): bool => str_starts_with($routeUri, $prefix))
            ->values();
    }

    /**
     * @return array<string, string>
     */
    private function authHeaders(bool $withContentType): array
    {
        $headers = ['Accept' => 'application/json'];
        if ($withContentType) {
            $headers['Content-Type'] = 'application/json';
        }
        $headers['Authorization'] = 'Bearer {{token}}';
        $headers['X-Business'] = '{{business_id}}';

        return $headers;
    }

    private function ensureCollectionVariable(string $collectionDir, string $varName, string $folderName): void
    {
        $defPath = $collectionDir . DIRECTORY_SEPARATOR . '.resources' . DIRECTORY_SEPARATOR . 'definition.yaml';
        if (! File::exists($defPath)) {
            return;
        }

        $def = Yaml::parse(File::get($defPath)) ?? [];
        $variables = $def['variables'] ?? [];
        if (! array_key_exists($varName, $variables)) {
            $variables[$varName] = '1';
            $def['variables'] = $variables;
        }

        if (! str_contains((string) ($def['description'] ?? ''), $folderName)) {
            $def['description'] = rtrim((string) ($def['description'] ?? '')) . "\n" . $folderName;
        }

        File::put($defPath, Yaml::dump($def, 4, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK));
    }
}
