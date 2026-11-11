<?php

namespace Modules\AppFiles\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\AppFiles\Support\OnlineMediaSearchService;
use RuntimeException;

trait HandlesOnlineMediaSearch
{
    protected function performOnlineMediaSearch(Request $request, OnlineMediaSearchService $search, string $type = 'all'): JsonResponse
    {
        $validated = $request->validate([
            'keyword' => ['required', 'string', 'max:255'],
            'source' => ['required', 'string', 'max:50'],
            'type' => ['nullable', 'in:all,image,video'],
            'page' => ['nullable', 'integer', 'min:1', 'max:200'],
            'quality' => ['nullable', 'in:standard,high'],
        ]);

        try {
            $results = $search->search($validated['keyword'], $validated['source'], (string) ($validated['type'] ?? $type), (int) ($validated['page'] ?? 1), (string) ($validated['quality'] ?? 'standard'));
        } catch (\Illuminate\Http\Client\ConnectionException $exception) {
            throw ValidationException::withMessages([
                'keyword' => __('The media source did not respond. Please try again.'),
            ]);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages([
                'keyword' => $exception->getMessage(),
            ]);
        }

        return response()->json([
            'message' => __('Media search completed successfully.'),
            'data' => $results,
            'page' => (int) ($validated['page'] ?? 1),
            'has_more' => count($results) === 24 && (int) ($validated['page'] ?? 1) < 200,
        ]);
    }
}
