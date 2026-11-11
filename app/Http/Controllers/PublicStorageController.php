<?php

namespace App\Http\Controllers;

use App\Support\Storage\StorageDriverManager;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicStorageController extends Controller
{
    public function show(Request $request, string $disk, string $path): BinaryFileResponse|StreamedResponse
    {
        abort_unless(array_key_exists($disk, app(StorageDriverManager::class)->diskOptions()), 404);

        $path = ltrim(trim($path), '/');

        abort_unless($path !== '' && Storage::disk($disk)->exists($path), 404);

        try {
            $mimeType = Storage::disk($disk)->mimeType($path) ?: 'application/octet-stream';
        } catch (\Throwable) {
            $mimeType = 'application/octet-stream';
        }

        $headers = [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="'.basename($path).'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'X-Content-Type-Options' => 'nosniff',
        ];

        try {
            $size = Storage::disk($disk)->size($path);

            if (is_numeric($size)) {
                $headers['Content-Length'] = (string) $size;
            }
        } catch (\Throwable) {
        }

        if ((string) config("filesystems.disks.{$disk}.driver") === 'local') {
            return response()->file(Storage::disk($disk)->path($path), $headers);
        }

        $stream = Storage::disk($disk)->readStream($path);

        abort_unless(is_resource($stream), 404);

        return response()->stream(function () use ($stream): void {
            fpassthru($stream);

            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, $headers);
    }
}
