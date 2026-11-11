<?php

namespace App\Social\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Social\Services\MetaWebhookService;

class MetaWebhookController
{
    public function __invoke(string $provider, Request $request, MetaWebhookService $webhooks): Response
    {
        abort_unless($webhooks->acceptsProvider($provider), 404);

        if ($request->isMethod('get')) {
            $challenge = $webhooks->verify($provider, $request);
            return $challenge === null ? response('Forbidden', 403) : response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        if (! $webhooks->acceptsSignature($provider, $request)) {
            return response('Invalid signature', 403);
        }

        $webhooks->ingest($provider, (array) $request->json()->all());

        return response('EVENT_RECEIVED', 200);
    }
}
