<div class="developer-section-heading"><div><h2>{{ __('Start with a draft') }}</h2><p>{{ __('Use a server-side integration. Replace the sample IDs with accounts from your workspace.') }}</p></div></div>
<div class="developer-doc-grid">
    <article class="developer-doc-card"><div class="developer-doc-title"><h3>1. {{ __('Check your connection') }}</h3><x-ui.button variant="outline" size="sm" x-on:click="copy($refs.devMe.textContent)">{{ __('Copy') }}</x-ui.button></div><pre class="developer-code" x-ref="devMe">curl "{{ $apiBase }}/me" \
  -H "Authorization: Bearer YOUR_API_KEY"</pre><p>{{ __('Returns the workspace and permissions associated with your key. A missing or revoked key returns HTTP 401.') }}</p></article>
    <article class="developer-doc-card"><div class="developer-doc-title"><h3>2. {{ __('Get publishing accounts') }}</h3><x-ui.button variant="outline" size="sm" x-on:click="copy($refs.devAccounts.textContent)">{{ __('Copy') }}</x-ui.button></div><pre class="developer-code" x-ref="devAccounts">curl "{{ $apiBase }}/accounts" \
  -H "Authorization: Bearer YOUR_API_KEY"</pre><p>{{ __('Requires accounts:read. Use an account ID from the response in your next request.') }}</p></article>
    <article class="developer-doc-card developer-doc-wide"><div class="developer-doc-title"><h3>3. {{ __('Create a draft') }}</h3><x-ui.button variant="outline" size="sm" x-on:click="copy($refs.devDraft.textContent)">{{ __('Copy') }}</x-ui.button></div><pre class="developer-code" x-ref="devDraft">curl -X POST "{{ $apiBase }}/posts" \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Idempotency-Key: YOUR_UNIQUE_REQUEST_ID" \
  -H "Content-Type: application/json" \
  -d '{"account_ids":[123],"caption":"Your caption","mode":"draft"}'</pre><p>{{ __('Requires posts:write. Media and provider-specific options may be required by the selected network. A successful request returns HTTP 201 with created post IDs.') }}</p></article>
    <article class="developer-doc-card"><h3>{{ __('Schedule for later') }}</h3><pre class="developer-code">"mode": "scheduled",
"schedule_at": "{{ $exampleSchedule }}"</pre><p>{{ __('Scheduled requests must include a future date and timezone offset. Missing or past dates return HTTP 422. Immediate publishing uses mode publish_now and requires both posts:write and posts:publish.') }}</p></article>
    <article class="developer-doc-card"><h3>{{ __('Retry without duplicates') }}</h3><p>{{ __('Use a new Idempotency-Key for each new operation. If the connection drops, retry the same operation with the same key and identical payload.') }}</p><p>{{ __('Completed requests replay the saved response. A changed payload or an operation still processing returns HTTP 409. Correcting a rejected payload is a new operation and needs a new key.') }}</p></article>
    <article class="developer-doc-card developer-doc-wide"><h3>{{ __('Webhook verification') }}</h3><p>{{ __('Open Signing secret on your webhook. Verify the raw request body before decoding JSON; reject timestamps older than five minutes. Deduplicate using X-Automation-Request-Id.') }}</p><pre class="developer-code">// PHP receiver: $secret comes from your server configuration.
$timestamp = $_SERVER['HTTP_X_AUTOMATION_TIMESTAMP'] ?? '';
$provided = $_SERVER['HTTP_X_AUTOMATION_SIGNATURE'] ?? '';
$body = file_get_contents('php://input');
$expected = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, $secret);
if (!ctype_digit($timestamp) || abs(time() - (int) $timestamp) > 300
    || !hash_equals($expected, $provided)) {
    http_response_code(401);
    exit;
}
// Store/deduplicate the request ID, enqueue processing, then return HTTP 200.</pre><p>{{ __('Return a 2xx status to acknowledge delivery. Connection failures, HTTP 408, 429 and 5xx responses are retried up to three attempts, after 30 and 120 seconds. Other failures require manual retry. Redirects are blocked.') }}</p><p>{{ __('Send test queues a synthetic webhook.test event without publishing a post. A running Automation worker is required. Pausing or deleting a webhook cancels pending deliveries when the worker processes them.') }}</p></article>
    <article class="developer-doc-card developer-doc-wide">@include('appdeveloperapi::livewire.partials.post-options')</article>
</div>
