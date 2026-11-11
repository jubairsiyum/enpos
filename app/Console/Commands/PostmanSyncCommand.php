<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

class PostmanSyncCommand extends Command
{
    protected $signature = 'postman:sync
                            {--collection=enPOS — Complete API (User Management & Contacts) : Folder name inside postman/collections}
                            {--output=postman/enPOS_Complete_API.postman_collection.json : Output Postman v2.1 JSON path}';

    protected $description = 'Sync modular Postman YAML collection folders into the single master Postman v2.1 JSON collection.';

    public function handle(): int
    {
        $collectionDir = base_path('postman/collections/' . $this->argumentOrOption('collection'));
        $outputPath = base_path((string) $this->option('output'));

        if (! File::isDirectory($collectionDir)) {
            $this->error("Collection directory not found: {$collectionDir}");

            return self::FAILURE;
        }

        $rootDefPath = $collectionDir . DIRECTORY_SEPARATOR . '.resources' . DIRECTORY_SEPARATOR . 'definition.yaml';
        $rootDef = File::exists($rootDefPath)
            ? (Yaml::parse(File::get($rootDefPath)) ?? [])
            : [];

        $variables = [];
        foreach (($rootDef['variables'] ?? []) as $key => $value) {
            $variables[] = [
                'key'   => (string) $key,
                'value' => (string) $value,
                'type'  => 'string',
            ];
        }

        $folders = collect(File::directories($collectionDir))
            ->reject(fn (string $dir): bool => basename($dir) === '.resources')
            ->map(function (string $dir): array {
                $defFile = $dir . DIRECTORY_SEPARATOR . '.resources' . DIRECTORY_SEPARATOR . 'definition.yaml';
                $def = File::exists($defFile) ? (Yaml::parse(File::get($defFile)) ?? []) : [];

                return [
                    'path'  => $dir,
                    'name'  => basename($dir),
                    'order' => (int) ($def['order'] ?? 999999),
                ];
            })
            ->sortBy([['order', 'asc'], ['name', 'asc']])
            ->values();

        $items = [];
        foreach ($folders as $folder) {
            $requests = collect(File::files($folder['path']))
                ->filter(fn (\SplFileInfo $file): bool => str_ends_with($file->getFilename(), '.request.yaml'))
                ->map(function (\SplFileInfo $file): array {
                    $parsed = Yaml::parse(File::get($file->getPathname())) ?? [];
                    $name = preg_replace('/\.request\.yaml$/', '', $file->getFilename());

                    return [
                        'name'  => $name,
                        'order' => (int) ($parsed['order'] ?? 999999),
                        'data'  => $parsed,
                    ];
                })
                ->sortBy([['order', 'asc'], ['name', 'asc']])
                ->values();

            $folderItems = [];
            foreach ($requests as $req) {
                $folderItems[] = $this->convertYamlRequestToPostmanItem($req['name'], $req['data']);
            }

            $items[] = [
                'name' => $folder['name'],
                'item' => $folderItems,
            ];
        }

        $collection = [
            'info' => [
                '_postman_id' => 'enpos-complete-api-v1',
                'name'        => basename($collectionDir),
                'description' => (string) ($rootDef['description'] ?? ''),
                'schema'      => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
            ],
            'variable' => $variables,
            'item'     => $items,
        ];

        File::ensureDirectoryExists(dirname($outputPath));
        File::put($outputPath, json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL);

        $this->info("Synced {$folders->count()} sections into {$outputPath}");

        return self::SUCCESS;
    }

    private function argumentOrOption(string $key): string
    {
        return (string) $this->option($key);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function convertYamlRequestToPostmanItem(string $name, array $data): array
    {
        $headers = [];
        foreach (($data['headers'] ?? []) as $key => $value) {
            $headers[] = [
                'key'   => (string) $key,
                'value' => (string) $value,
            ];
        }

        $rawUrl = (string) ($data['url'] ?? '');
        $urlObject = $this->buildUrlObject($rawUrl, $data['queryParams'] ?? []);

        $request = [
            'method' => strtoupper((string) ($data['method'] ?? 'GET')),
            'header' => $headers,
        ];

        if (($data['body']['type'] ?? null) === 'formdata' && is_array($data['body']['content'] ?? null)) {
            $formdata = array_map(function (array $entry): array {
                if (array_key_exists('src', $entry) && ($entry['src'] === '' || $entry['src'] === null)) {
                    unset($entry['src']);
                }

                return $entry;
            }, array_values($data['body']['content']));

            $request['body'] = [
                'mode'     => 'formdata',
                'formdata' => $formdata,
            ];
        } elseif (isset($data['body']['content'])) {
            $request['body'] = [
                'mode' => 'raw',
                'raw'  => (string) $data['body']['content'],
            ];
        }

        $request['url'] = $urlObject;

        $item = [
            'name'    => $name,
            'request' => $request,
        ];

        if (! empty($data['scripts']) && is_array($data['scripts'])) {
            $events = [];
            foreach ($data['scripts'] as $script) {
                $listen = ($script['type'] ?? 'afterResponse') === 'beforeRequest' ? 'prerequest' : 'test';
                $lines = preg_split("/\r\n|\n|\r/", (string) ($script['code'] ?? ''));
                $events[] = [
                    'listen' => $listen,
                    'script' => [
                        'exec' => $lines ?: [],
                        'type' => 'text/javascript',
                    ],
                ];
            }
            $item['event'] = $events;
        }

        return $item;
    }

    /**
     * @param  array<string, mixed>  $queryParams
     * @return array<string, mixed>
     */
    private function buildUrlObject(string $rawUrl, array $queryParams): array
    {
        $withoutQuery = explode('?', $rawUrl, 2)[0];
        $trimmed = str_replace('{{base_url}}/', '', $withoutQuery);
        $pathSegments = $trimmed !== '' ? array_values(array_filter(explode('/', $trimmed))) : [];

        $url = [
            'raw'  => $rawUrl,
            'host' => ['{{base_url}}'],
            'path' => $pathSegments,
        ];

        if (! empty($queryParams)) {
            $query = [];
            foreach ($queryParams as $key => $value) {
                $query[] = [
                    'key'   => (string) $key,
                    'value' => (string) $value,
                ];
            }
            $url['query'] = $query;
        }

        return $url;
    }
}
