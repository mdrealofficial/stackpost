<?php

namespace Modules\AppAutomation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\AppAutomation\Models\AutomationLog;
use Modules\AppAutomation\Models\AutomationApiIdempotencyKey;
use Modules\AppAutomation\Models\AutomationApiKey;
use Modules\AppAutomation\Services\AutomationApiIdempotencyService;
use Modules\AppAutomation\Services\AutomationApiKeyService;
use Modules\AppAutomation\Services\AutomationInboundPostService;
use Modules\AppAutomation\Services\AutomationWebhookDispatcher;
use Modules\AppAutomation\Support\AutomationAccess;
use Modules\AppAutomation\Support\AutomationWorkspaceContext;
use Modules\AppAutomation\Support\AutomationWorkspaceScope;
use Modules\AppChannels\Models\SocialAccount;
use Modules\AppPublishing\Jobs\PublishScheduledPostJob;
use Modules\AppPublishing\Models\PublishingPost;
use Symfony\Component\HttpFoundation\Response;

class AutomationApiController extends Controller
{
    public function __construct(
        protected AutomationApiKeyService $keys,
        protected AutomationApiIdempotencyService $idempotency,
        protected AutomationInboundPostService $posts,
        protected AutomationWebhookDispatcher $webhooks,
        protected AutomationWorkspaceScope $workspaces,
        protected AutomationAccess $access,
    ) {}

    public function me(Request $request): JsonResponse
    {
        [$apiKey, $workspace] = $this->authenticate($request);

        return response()->json([
            'ok' => true,
            'workspace_owner_user_id' => $workspace->ownerUserId(),
            'team_id' => $workspace->teamId(),
            'permissions' => (array) $apiKey->permissions,
        ]);
    }

    public function accounts(Request $request): JsonResponse
    {
        [$apiKey, $workspace] = $this->authenticate($request, 'accounts:read');
        $publishableCapabilityKeys = $this->workspaces->publishableCapabilityKeys($workspace);

        $accounts = $this->workspaces->accountsQuery($workspace)
            ->where(function ($query) use ($publishableCapabilityKeys): void {
                $query->whereIn('capability_key', $publishableCapabilityKeys)
                    ->orWhere(function ($fallbackQuery) use ($publishableCapabilityKeys): void {
                        $fallbackQuery->whereNull('capability_key')
                            ->whereIn('provider_key', $publishableCapabilityKeys);
                    });
            })
            ->where('is_active', true)
            ->orderBy('provider_key')
            ->orderBy('display_name')
            ->get()
            ->map(function (SocialAccount $account): array {
                return [
                    'id' => (int) $account->id,
                    'display_name' => (string) ($account->display_name ?? ''),
                    'username' => (string) ($account->username ?? ''),
                    'provider_key' => (string) ($account->provider_key ?? ''),
                    'capability_key' => (string) ($account->capability_key ?? ''),
                    'avatar_url' => (string) ($account->avatar_url ?? ''),
                    'is_active' => (bool) $account->is_active,
                ];
            })
            ->values();

        $this->logInbound($apiKey, $workspace, 'accounts.read', Response::HTTP_OK, [], ['count' => $accounts->count()]);

        return response()->json([
            'ok' => true,
            'data' => $accounts,
        ]);
    }

    public function listPosts(Request $request): JsonResponse
    {
        [$apiKey, $workspace] = $this->authenticateAny($request, ['posts:read', 'posts:write', 'accounts:read']);

        $status = $request->query('status');
        $limit = min(max((int) $request->query('limit', 20), 1), 100);

        $query = PublishingPost::query()
            ->where('user_id', $workspace->ownerUserId())
            ->with(['account:id,display_name,username,provider_key,avatar_url'])
            ->orderByDesc('id');

        if ($status !== null && is_numeric($status)) {
            $query->where('status', (int) $status);
        } elseif ($status === 'scheduled') {
            $query->where('status', PublishingPost::STATUS_SCHEDULED);
        } elseif ($status === 'published') {
            $query->where('status', PublishingPost::STATUS_PUBLISHED);
        } elseif ($status === 'draft') {
            $query->where('status', PublishingPost::STATUS_DRAFT);
        } elseif ($status === 'failed') {
            $query->where('status', PublishingPost::STATUS_FAILED);
        }

        $items = $query->limit($limit)->get()->map(function (PublishingPost $post): array {
            $data = is_array($post->data) ? $post->data : (json_decode($post->data ?? '[]', true) ?: []);
            return [
                'id' => (int) $post->id,
                'id_secure' => (string) $post->id_secure,
                'account_id' => (int) $post->account_id,
                'account' => $post->account ? [
                    'id' => (int) $post->account->id,
                    'display_name' => (string) ($post->account->display_name ?? ''),
                    'username' => (string) ($post->account->username ?? ''),
                    'provider_key' => (string) ($post->account->provider_key ?? ''),
                ] : null,
                'status' => (int) $post->status,
                'caption' => (string) ($data['caption'] ?? ''),
                'media' => (array) ($data['media'] ?? []),
                'scheduled_at' => $post->time_post ? now()->setTimestamp((int) $post->time_post)->toIso8601String() : null,
            ];
        })->values();

        return response()->json([
            'ok' => true,
            'data' => $items,
        ]);
    }

    public function getPost(Request $request, int|string $id): JsonResponse
    {
        [$apiKey, $workspace] = $this->authenticateAny($request, ['posts:read', 'posts:write', 'accounts:read']);

        $post = PublishingPost::query()
            ->where('user_id', $workspace->ownerUserId())
            ->where(function ($query) use ($id): void {
                if (is_numeric($id)) {
                    $query->where('id', (int) $id)->orWhere('id_secure', (string) $id);
                } else {
                    $query->where('id_secure', (string) $id);
                }
            })
            ->with(['account:id,display_name,username,provider_key,avatar_url'])
            ->first();

        if (! $post) {
            return response()->json([
                'ok' => false,
                'message' => 'Post not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        $data = is_array($post->data) ? $post->data : (json_decode($post->data ?? '[]', true) ?: []);

        return response()->json([
            'ok' => true,
            'data' => [
                'id' => (int) $post->id,
                'id_secure' => (string) $post->id_secure,
                'account_id' => (int) $post->account_id,
                'account' => $post->account ? [
                    'id' => (int) $post->account->id,
                    'display_name' => (string) ($post->account->display_name ?? ''),
                    'username' => (string) ($post->account->username ?? ''),
                    'provider_key' => (string) ($post->account->provider_key ?? ''),
                ] : null,
                'status' => (int) $post->status,
                'caption' => (string) ($data['caption'] ?? ''),
                'media' => (array) ($data['media'] ?? []),
                'result' => $post->result,
                'scheduled_at' => $post->time_post ? now()->setTimestamp((int) $post->time_post)->toIso8601String() : null,
            ],
        ]);
    }

    public function createPost(Request $request): JsonResponse
    {
        [$apiKey, $workspace] = $this->authenticate($request, 'posts:write');

        $idempotencyKey = trim((string) $request->header('Idempotency-Key', ''));

        if (! $this->validIdempotencyKey($idempotencyKey)) {
            return response()->json([
                'ok' => false,
                'message' => 'Idempotency-Key is required and must contain 8 to 255 visible characters without whitespace.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $validated = $request->validate([
            'account_ids' => ['required', 'array', 'min:1'],
            'account_ids.*' => ['integer', 'distinct'],
            'caption' => ['nullable', 'string', 'max:5000'],
            'media_urls' => ['nullable', 'array', 'max:10'],
            'media_urls.*' => ['url:http,https', 'max:1000', 'distinct'],
            'mode' => ['nullable', 'in:draft,scheduled,publish_now,now'],
            'schedule_at' => ['nullable', 'string', 'max:80'],
            'timezone' => ['nullable', 'timezone'],
            'campaign_id' => ['nullable', 'integer'],
            'label_ids' => ['nullable', 'array'],
            'label_ids.*' => ['integer'],
            'network_options' => ['nullable', 'array'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'source' => ['nullable', 'string', 'max:80'],
            'external_reference' => ['nullable', 'string', 'max:255'],
        ]);

        $mode = (string) ($validated['mode'] ?? 'scheduled');

        if (in_array($mode, ['publish_now', 'now'], true)) {
            $this->requirePermission($apiKey, 'posts:publish');
        }

        $reservation = $this->idempotency->reserve($apiKey, $idempotencyKey, $validated);

        if ($reservation['state'] === 'payload_conflict') {
            return response()->json([
                'ok' => false,
                'message' => 'This Idempotency-Key was already used with a different request payload.',
            ], Response::HTTP_CONFLICT);
        }

        if ($reservation['state'] === 'in_progress') {
            return response()->json([
                'ok' => false,
                'message' => 'A request with this Idempotency-Key is already being processed.',
            ], Response::HTTP_CONFLICT)->header('Idempotency-Replayed', 'true');
        }

        if ($reservation['state'] === 'replay') {
            return response()->json(
                (array) ($reservation['record']->response ?? []),
                (int) ($reservation['record']->status_code ?: Response::HTTP_OK),
            )->header('Idempotency-Replayed', 'true');
        }

        /** @var AutomationApiIdempotencyKey $idempotencyRecord */
        $idempotencyRecord = $reservation['record'];

        try {
            $posts = $this->posts->createForWorkspace($workspace, $validated);

            foreach ($posts as $post) {
                $this->webhooks->dispatchForPost('post.created', $post);

                if (in_array($mode, ['publish_now', 'now'], true)) {
                    PublishScheduledPostJob::dispatchSync((int) $post->id);
                    $post->refresh();
                }
            }

            $response = [
                'ok' => true,
                'data' => $posts->map(fn ($post) => [
                    'id' => (int) $post->id,
                    'id_secure' => (string) $post->id_secure,
                    'account_id' => (int) $post->account_id,
                    'status' => (int) $post->status,
                    'scheduled_at' => $post->time_post ? now()->setTimestamp((int) $post->time_post)->toIso8601String() : null,
                ])->values(),
            ];

            $this->idempotency->complete($idempotencyRecord, $response, Response::HTTP_CREATED);
            $this->logInbound($apiKey, $workspace, 'posts.create', Response::HTTP_CREATED, $validated, $response);

            return response()->json($response, Response::HTTP_CREATED)
                ->header('Idempotency-Key', $idempotencyKey);
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $response = ['ok' => false, 'message' => $exception->getMessage(), 'errors' => $exception->errors()];
            $this->idempotency->complete($idempotencyRecord, $response, Response::HTTP_UNPROCESSABLE_ENTITY);
            return response()->json($response, Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Throwable $exception) {
            report($exception);

            $response = [
                'ok' => false,
                'message' => 'The post request could not be completed. No automatic retry was performed.',
            ];

            $this->idempotency->complete($idempotencyRecord, $response, Response::HTTP_UNPROCESSABLE_ENTITY);
            $this->logInbound($apiKey, $workspace, 'posts.create', Response::HTTP_UNPROCESSABLE_ENTITY, $validated, [
                ...$response,
                'error_type' => $exception::class,
            ]);

            return response()->json($response, Response::HTTP_UNPROCESSABLE_ENTITY)
                ->header('Idempotency-Key', $idempotencyKey);
        }
    }

    protected function authenticate(Request $request, ?string $permission = null): array
    {
        $apiKey = $this->keys->resolveFromRequest($request);

        abort_unless($apiKey, Response::HTTP_UNAUTHORIZED, 'Missing or invalid automation API key.');

        $workspace = $this->workspaces->contextForApiKey($apiKey);
        abort_unless($workspace, Response::HTTP_UNAUTHORIZED, 'Automation API key workspace is no longer available.');
        abort_unless(
            $this->access->enabledForWorkspace($workspace->owner, $workspace->team),
            Response::HTTP_FORBIDDEN,
            'Automation is not available for this workspace.',
        );

        if ($permission !== null) {
            $this->requirePermission($apiKey, $permission);
        }

        $this->keys->touch($apiKey);

        return [$apiKey, $workspace];
    }

    protected function authenticateAny(Request $request, array $permissions): array
    {
        $apiKey = $this->keys->resolveFromRequest($request);

        abort_unless($apiKey, Response::HTTP_UNAUTHORIZED, 'Missing or invalid automation API key.');

        $workspace = $this->workspaces->contextForApiKey($apiKey);
        abort_unless($workspace, Response::HTTP_UNAUTHORIZED, 'Automation API key workspace is no longer available.');
        abort_unless(
            $this->access->enabledForWorkspace($workspace->owner, $workspace->team),
            Response::HTTP_FORBIDDEN,
            'Automation is not available for this workspace.',
        );

        if (! empty($permissions)) {
            $this->requireAnyPermission($apiKey, $permissions);
        }

        $this->keys->touch($apiKey);

        return [$apiKey, $workspace];
    }

    protected function requireAnyPermission(AutomationApiKey $apiKey, array $permissions): void
    {
        $keyPermissions = collect((array) $apiKey->permissions)
            ->map(fn ($value) => trim((string) $value))
            ->all();

        $hasMatch = false;
        foreach ($permissions as $p) {
            if (in_array($p, $keyPermissions, true)) {
                $hasMatch = true;
                break;
            }
        }

        abort_unless(
            $hasMatch,
            Response::HTTP_FORBIDDEN,
            'This automation API key does not have any of the required permissions: '.implode(', ', $permissions).'.',
        );
    }

    protected function requirePermission(AutomationApiKey $apiKey, string $permission): void
    {
        $permissions = collect((array) $apiKey->permissions)
            ->map(fn ($value) => trim((string) $value))
            ->all();

        abort_unless(
            in_array($permission, $permissions, true),
            Response::HTTP_FORBIDDEN,
            'This automation API key does not have the required permission: '.$permission.'.',
        );
    }

    protected function validIdempotencyKey(string $key): bool
    {
        $length = strlen($key);

        return $length >= 8
            && $length <= 255
            && preg_match('/[\x00-\x20\x7F]/', $key) !== 1;
    }

    protected function logInbound($apiKey, AutomationWorkspaceContext $workspace, string $event, int $statusCode, array $payload, array $response): void
    {
        AutomationLog::query()->create([
            'user_id' => $workspace->ownerUserId(),
            'team_id' => $workspace->teamId(),
            'api_key_id' => (int) $apiKey->id,
            'webhook_id' => null,
            'direction' => 'inbound',
            'event' => $event,
            'request_id' => (string) request()->header('X-Request-Id', ''),
            'status' => $statusCode >= 400 ? 'failed' : 'accepted',
            'status_code' => $statusCode,
            'payload' => $payload,
            'response' => $response,
        ]);
    }
}
