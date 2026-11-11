<?php

namespace Modules\AppFiles\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\AppFiles\Http\Controllers\Concerns\ManagesImageEditor;
use Modules\AppFiles\Models\AppFile;
use Modules\AppFiles\Support\FileManager;

#[Title('Edit image')]
class AdminImageEditor extends Component
{
    use ManagesImageEditor;

    public AppFile $file;

    public string $previewUrl = '';

    public string $saveUrl = '';

    public string $backUrl = '';

    public string $editorSourceUrl = '';

    protected FileManager $files;

    public function boot(FileManager $files): void
    {
        $this->files = $files;
    }

    public function mount(Request $request, AppFile $file): void
    {
        abort_if($file->is_folder, 404);
        abort_unless($file->isEditableImage(), 404);

        $this->file = $file;
        $this->previewUrl = route('admin-files.preview', $file);
        $this->saveUrl = route('admin-files.update-image', ['file' => $file, 'return' => $request->query('return')]);
        $this->backUrl = $this->sanitizeReturnUrl(
            $request,
            route('admin-files.index', array_filter(['folder' => $file->parent?->id_secure, 'owner' => $file->owner_user_id]))
        );
        session(['appfiles.image_editor_return_url' => $this->backUrl]);
        $this->editorSourceUrl = $this->resolveEditorSourceUrl($file, $this->previewUrl);
    }

    public function render(): View
    {
        return view('appfiles::editor', [
            'file' => $this->file,
            'previewUrl' => $this->previewUrl,
            'editorSourceUrl' => $this->editorSourceUrl,
            'saveUrl' => $this->saveUrl,
            'backUrl' => $this->backUrl,
            'title' => __('Edit image'),
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('Edit image'),
            'fullWorkspace' => true,
            'showLoadingBackdrop' => false,
        ]);
    }
}
