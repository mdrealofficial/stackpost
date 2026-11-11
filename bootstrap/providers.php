<?php

use App\Installer\InstallerServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;

$baseProviders = [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    InstallerServiceProvider::class,
];

$moduleProviders = [];
$modulesPath = base_path('modules');

if (is_dir($modulesPath)) {
    $moduleDirs = glob($modulesPath.DIRECTORY_SEPARATOR.'*', GLOB_ONLYDIR) ?: [];
    sort($moduleDirs);

    $moduleMeta = [];

    foreach ($moduleDirs as $moduleDir) {
        $moduleName = basename($moduleDir);
        $moduleJsonPath = $moduleDir.DIRECTORY_SEPARATOR.'module.json';

        $providers = [];
        $files = [];
        $priority = 0;

        if (is_file($moduleJsonPath)) {
            $decoded = json_decode((string) file_get_contents($moduleJsonPath), true);

            if (is_array($decoded)) {
                $providers = array_values(array_filter($decoded['providers'] ?? [], fn ($item) => is_string($item) && $item !== ''));
                $files = array_values(array_filter($decoded['files'] ?? [], fn ($item) => is_string($item) && $item !== ''));
                $priority = (int) ($decoded['priority'] ?? 0);
            }
        }

        if ($providers === []) {
            $conventionClass = "Modules\\{$moduleName}\\Providers\\{$moduleName}ServiceProvider";
            $conventionPath = $moduleDir.DIRECTORY_SEPARATOR.'Providers'.DIRECTORY_SEPARATOR."{$moduleName}ServiceProvider.php";

            if (is_file($conventionPath)) {
                $providers[] = $conventionClass;
            }
        }

        // Convention fallback for module-level helpers.
        $conventionHelper = $moduleDir.DIRECTORY_SEPARATOR.'Support'.DIRECTORY_SEPARATOR.'helpers.php';
        if (is_file($conventionHelper)) {
            $files[] = 'Support/helpers.php';
        }

        foreach ($files as $relativeFile) {
            $path = $moduleDir.DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($relativeFile, '/\\'));
            if (is_file($path)) {
                require_once $path;
            }
        }

        $moduleMeta[] = [
            'priority' => $priority,
            'name' => $moduleName,
            'providers' => $providers,
        ];
    }

    usort($moduleMeta, function (array $left, array $right): int {
        if ($left['priority'] === $right['priority']) {
            return strcmp($left['name'], $right['name']);
        }

        return $left['priority'] <=> $right['priority'];
    });

    foreach ($moduleMeta as $meta) {
        foreach ($meta['providers'] as $providerClass) {
            $moduleProviders[] = $providerClass;
        }
    }
}

$marketplaceProviders = file_exists(__DIR__.'/providers.marketplace.php')
    ? require __DIR__.'/providers.marketplace.php'
    : [];

return array_values(array_unique(array_merge(
    $baseProviders,
    $moduleProviders,
    is_array($marketplaceProviders) ? $marketplaceProviders : []
)));
