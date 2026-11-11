<details class="group rounded-[.9rem] border" style="border-color:rgba(var(--theme-border-color-rgb),.56)">
    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3 text-sm font-semibold" style="color:var(--theme-header-text-color)">
        <span>{{ __('Complete scheduled-post request with network options') }}</span>
        <i class="fa-light fa-chevron-down text-xs transition group-open:rotate-180" style="color:var(--theme-muted-text-color)"></i>
    </summary>
    <div class="border-t p-4" style="border-color:rgba(var(--theme-border-color-rgb),.5)">
        <div class="mb-4 grid gap-2 sm:grid-cols-3">
            <div class="rounded-[.7rem] border px-3 py-2.5 text-xs leading-5" style="border-color:rgba(var(--theme-border-color-rgb),.48); background-color:rgba(var(--theme-surface-muted-rgb,248,250,252),.52)"><span class="block font-semibold" style="color:var(--theme-header-text-color)">{{ __('1. Match the account') }}</span><span style="color:var(--theme-muted-text-color)">{{ __('Each key must match the account provider, such as facebook or tiktok.') }}</span></div>
            <div class="rounded-[.7rem] border px-3 py-2.5 text-xs leading-5" style="border-color:rgba(var(--theme-border-color-rgb),.48); background-color:rgba(var(--theme-surface-muted-rgb,248,250,252),.52)"><span class="block font-semibold" style="color:var(--theme-header-text-color)">{{ __('2. Send only what applies') }}</span><span style="color:var(--theme-muted-text-color)">{{ __('Omit unrelated network objects. Channel validation still runs before a post is created.') }}</span></div>
            <div class="rounded-[.7rem] border px-3 py-2.5 text-xs leading-5" style="border-color:rgba(var(--theme-border-color-rgb),.48); background-color:rgba(var(--theme-surface-muted-rgb,248,250,252),.52)"><span class="block font-semibold" style="color:var(--theme-header-text-color)">{{ __('3. Use a fresh key') }}</span><span style="color:var(--theme-muted-text-color)">{{ __('Idempotency-Key must be unique for each new request.') }}</span></div>
        </div>
        <p class="mb-3 text-xs leading-5" style="color:var(--theme-muted-text-color)">{{ __('Full reference payload: it contains every supported provider object so your team can copy the fields they need.') }}</p>
        <pre class="max-h-[34rem] overflow-auto rounded-[.75rem] border p-3 text-xs leading-5" style="border-color:rgba(var(--theme-border-color-rgb),.56); background-color:rgba(var(--theme-surface-muted-rgb,248,250,252),.62); color:var(--theme-header-text-color)">POST {{ $apiBase }}/posts
Authorization: Bearer YOUR_API_KEY
Idempotency-Key: 9a4c3a92-7d2d-4d3a-b9d3-1a8ec0f8d9c2
Content-Type: application/json

{
  "account_ids": [123],
  "caption": "Post created from n8n",
  "media_urls": ["https://example.com/banner.jpg"],
  "mode": "scheduled",
  "schedule_at": "{{ $exampleSchedule }}",
  "network_options": {
  "facebook": { "post_to": "feed" },
  "instagram": { "post_to": "feed" },
  "instagram_unofficial": { "post_to": "feed" },
  "google_business_profile": {
    "post_to": "feed",
    "gbp_action": "LEARN_MORE",
    "gbp_link": "https://example.com"
  },
  "linkedin_profile": { "linkedin_post_type": "auto" },
  "linkedin_page": { "linkedin_post_type": "auto" },
  "x": { "x_post_type": "auto" },
  "youtube": {
    "youtube_title": "Automation API walkthrough",
    "youtube_category": "22",
    "youtube_privacy": "public",
    "youtube_tags": "automation, api, publishing",
    "youtube_thumbnail": "https://example.com/thumbnail.jpg"
  },
  "pinterest": {
    "pinterest_title": "Automation API launch",
    "pinterest_link": "https://example.com/blog/automation-api"
  },
  "reddit": { "reddit_title": "Automation API example" },
  "mastodon": {
    "mastodon_visibility": "public",
    "mastodon_spoiler_text": ""
  },
  "discord": {
    "discord_username": "Automation Bot",
    "discord_avatar_url": "https://example.com/avatar.png"
  },
  "tiktok": {
    "tt_privacy": "PUBLIC_TO_EVERYONE",
    "tt_allow_comment": true,
    "tt_allow_duet": false,
    "tt_allow_stitch": false,
    "tt_commercial_content": false,
    "tt_your_brand": false,
    "tt_branded_content": false,
    "tt_consent": true,
    "tt_can_post": true,
    "tt_creator_ready": true
  },
  "threads": {},
  "telegram": {},
  "vk": { "post_to": "feed" },
  "ok": {}
  }
}</pre>
    </div>
</details>
