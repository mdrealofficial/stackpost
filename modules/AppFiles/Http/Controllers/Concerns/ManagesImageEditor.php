<?php

namespace Modules\AppFiles\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\AppFiles\Models\AppFile;

trait ManagesImageEditor
{
    protected function renderImageEditor(AppFile $file, string $previewUrl, string $saveUrl, string $backUrl, string $title): \Illuminate\Contracts\View\View
    {
        abort_if($file->is_folder, 404);
        abort_unless($file->isEditableImage(), 404);

        $editorSourceUrl = $this->resolveEditorSourceUrl($file, $previewUrl);

        return view('appfiles::editor', [
            'file' => $file,
            'previewUrl' => $previewUrl,
            'editorSourceUrl' => $editorSourceUrl,
            'saveUrl' => $saveUrl,
            'backUrl' => $backUrl,
            'title' => $title,
        ]);
    }

    protected function resolveEditorSourceUrl(AppFile $file, string $fallbackUrl): string
    {
        if (! filled($file->path) || ! Storage::disk($file->disk)->exists($file->path)) {
            return $fallbackUrl;
        }

        $sizeBytes = (int) $file->size_bytes;
        if ($sizeBytes <= 0 || $sizeBytes > (8 * 1024 * 1024)) {
            return $fallbackUrl;
        }

        $contents = Storage::disk($file->disk)->get($file->path);
        if ($contents === '') {
            return $fallbackUrl;
        }

        $mimeType = $file->mime_type ?: 'image/'.strtolower((string) $file->extension ?: 'png');

        return 'data:'.$mimeType.';base64,'.base64_encode($contents);
    }

    protected function updateEditedImage(Request $request, AppFile $file, int $maxUploadMb, callable $authorize): JsonResponse
    {
        abort_if($file->is_folder, 404);
        abort_unless($file->isEditableImage(), 404);
        $authorize($file);

        $validator = Validator::make($request->all(), [
            'image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.($maxUploadMb * 1024)],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        /** @var UploadedFile $image */
        $image = $request->file('image');
        $updated = $this->files->createEditedCopy($file, $image);

        return response()->json([
            'message' => __('Edited image saved as a new file.'),
            'file' => [
                'id_secure' => $updated->id_secure,
                'name' => $updated->name,
                'width' => $updated->width,
                'height' => $updated->height,
                'preview_url' => $request->routeIs('portal.files.*')
                    ? route('portal.files.preview', $updated)
                    : route('admin-files.preview', $updated),
            ],
        ]);
    }

    protected function sanitizeReturnUrl(Request $request, string $fallback): string
    {
        $fallbackParsed = parse_url($fallback);
        $fallbackPath = (string) ($fallbackParsed['path'] ?? '');
        $fallbackQuery = isset($fallbackParsed['query']) ? '?'.$fallbackParsed['query'] : '';
        $normalizedFallback = $fallbackPath !== '' ? $fallbackPath.$fallbackQuery : $fallback;

        $candidates = array_filter([
            (string) $request->query('return', $request->input('return', '')),
            (string) $request->headers->get('referer', ''),
            url()->previous(),
        ]);

        foreach ($candidates as $candidate) {
            $parsed = parse_url($candidate);
            $path = (string) ($parsed['path'] ?? '');

            if ($path === '' || ! str_starts_with($path, '/')) {
                continue;
            }

            if ($path === $request->getPathInfo()) {
                continue;
            }

            if ($this->isInternalLivewirePath($path)) {
                continue;
            }

            $query = isset($parsed['query']) ? '?'.$parsed['query'] : '';

            return $path.$query;
        }

        return $normalizedFallback;
    }

    protected function isInternalLivewirePath(string $path): bool
    {
        return (bool) preg_match('#(?:^|/)livewire(?:-[^/]+)?/(?:update|upload-file|preview-file)(?:/.*)?$#', $path);
    }
}
