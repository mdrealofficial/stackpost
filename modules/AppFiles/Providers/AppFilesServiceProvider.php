<?php

namespace Modules\AppFiles\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\AppFiles\Models\AppFile;
use Modules\AppFiles\Support\FileManager;
use Modules\AppTeams\Support\TeamPermissionRegistry;

class AppFilesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'modules.appfiles');
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'appfiles');

        TeamPermissionRegistry::registerModule('files', [
            'label' => __('Files'),
            'configurable' => true,
            'enabled_by_default' => true,
            'permissions' => [
                'files.view' => [
                    'label' => __('Access files'),
                    'defaults' => [
                        'admin' => ['files.view'],
                        'editor' => ['files.view'],
                        'member' => ['files.view'],
                    ],
                ],
                'files.upload' => [
                    'label' => __('Upload and manage files'),
                    'defaults' => [
                        'admin' => ['files.upload'],
                        'editor' => ['files.upload'],
                    ],
                ],
            ],
        ]);

        register_setting_item('general', [
            'label' => 'File Manager',
            'description' => 'Configure upload limits, storage disk, allowed extensions, and action menu integrations.',
            'route_name' => 'settings.files',
            'active_when' => ['settings.files'],
            'order' => 15,
        ]);

        register_sidebar_item('main', [
            'label' => 'Files',
            'route_name' => 'admin-files.index',
            'active_when' => ['admin-files.*'],
            'icon' => 'fa-light fa-folder-tree',
            'order' => 18,
        ]);

        register_user_sidebar_item('library', [
            'label' => 'Files',
            'route_name' => 'portal.files.index',
            'active_when' => ['portal.files.index', 'portal.files.publish-preview', 'portal.files.edit-image', 'portal.files.update-image'],
            'icon' => 'fa-light fa-folder-open',
            'order' => 10,
            'visible' => fn () => app(FileManager::class)->filesEnabled(auth()->user()),
        ]);

        register_user_sidebar_item('library', [
            'label' => 'Search Media Online',
            'route_name' => 'portal.files.search-online',
            'active_when' => ['portal.files.search-online'],
            'icon' => 'fa-light fa-photo-film-music',
            'order' => 11,
            'visible' => fn () => app(FileManager::class)->searchMediaOnlineEnabled(auth()->user()),
        ]);

        register_admin_dashboard_item('admin-files.snapshot', [
            'title' => 'Files',
            'view' => 'appfiles::dashboard.snapshot',
            'width' => 'full',
            'order' => 90,
            'data' => fn () => [
                'metrics' => [
                    'total' => AppFile::query()->count(),
                    'folders' => AppFile::query()->where('is_folder', true)->count(),
                    'images' => AppFile::query()->where('is_image', true)->count(),
                    'storage_bytes' => (int) AppFile::query()->where('is_folder', false)->sum('size_bytes'),
                ],
                'route' => route('admin-files.index'),
            ],
        ]);

        register_plan_permission([
            'key' => 'files',
            'label' => __('Files'),
            'type' => 'config',
            'order' => 50,
            'fields' => [
                ['key' => 'file_picker', 'label' => __('File picker'), 'type' => 'checkbox_list', 'options' => [['key' => 'file_google_drive', 'label' => __('Google Drive')], ['key' => 'file_dropbox', 'label' => __('Dropbox')], ['key' => 'file_onedrive', 'label' => __('OneDrive')]]],
                ['key' => 'image_editor', 'label' => __('Image Editor'), 'type' => 'boolean', 'default' => '1'],
                ['key' => 'search_media_online', 'label' => __('Search Media Online'), 'type' => 'boolean', 'default' => '1'],
                ['key' => 'max_storage_size_mb', 'label' => __('Max. storage size (MB)'), 'type' => 'number', 'default' => 120],
                ['key' => 'max_file_size_mb', 'label' => __('Max. file size (MB)'), 'type' => 'number', 'default' => 10000],
            ],
        ]);

        $this->app->booted(function (): void {
            \Pricing::addSubFeatures([
                ['sort' => 140, 'parent' => 'features', 'tab_id' => 'library', 'tab_name' => __('Library'), 'key' => 'files', 'label' => __('Files'), 'check' => true, 'type' => 'boolean', 'raw' => 0],
                ['sort' => 150, 'parent' => 'features', 'tab_id' => 'library', 'tab_name' => __('Library'), 'key' => 'max_storage_size_mb', 'label' => __('Storage (MB)'), 'check' => true, 'type' => 'number', 'raw' => 0],
                ['sort' => 160, 'parent' => 'features', 'tab_id' => 'library', 'tab_name' => __('Library'), 'key' => 'image_editor', 'label' => __('Image editor'), 'check' => true, 'type' => 'boolean', 'raw' => 0],
                ['sort' => 170, 'parent' => 'features', 'tab_id' => 'library', 'tab_name' => __('Library'), 'key' => 'search_media_online', 'label' => __('Search Media Online'), 'check' => true, 'type' => 'boolean', 'raw' => 0],
            ]);
        });
    }
}
