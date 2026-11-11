@php
    $privacyField = 'composer.network_options.'.$providerKey.'.tt_privacy';
    $commentField = 'composer.network_options.'.$providerKey.'.tt_allow_comment';
    $duetField = 'composer.network_options.'.$providerKey.'.tt_allow_duet';
    $stitchField = 'composer.network_options.'.$providerKey.'.tt_allow_stitch';
    $commercialField = 'composer.network_options.'.$providerKey.'.tt_commercial_content';
    $yourBrandField = 'composer.network_options.'.$providerKey.'.tt_your_brand';
    $brandedField = 'composer.network_options.'.$providerKey.'.tt_branded_content';
    $consentField = 'composer.network_options.'.$providerKey.'.tt_consent';
    $canPostField = 'composer.network_options.'.$providerKey.'.tt_can_post';
    $creatorReadyField = 'composer.network_options.'.$providerKey.'.tt_creator_ready';
    $composerConfigJson = json_encode([
        'creatorInfoUrl' => route('portal.channels.tiktok.profiles.creator-info'),
        'csrfToken' => csrf_token(),
        'texts' => [
            'everyone' => __('Everyone'),
            'friends' => __('Friends'),
            'followers' => __('Followers'),
            'only_me' => __('Only me'),
            'select_profile' => __('Select a TikTok profile'),
            'latest_creator_settings' => __('Latest creator settings will load automatically.'),
            'max_video_duration' => __('Max video duration'),
            'merged_policy' => __('Creator policy merged across selected TikTok profiles.'),
            'loading_creator_settings' => __('Loading TikTok creator settings...'),
            'unable_to_load_creator_settings' => __('Unable to load TikTok creator settings.'),
            'creator_loading_block' => __('TikTok creator settings must finish loading before publishing.'),
            'creator_cannot_post' => __('This TikTok profile cannot post right now. Please try again later.'),
            'privacy_required' => __('TikTok requires a privacy setting.'),
            'commercial_required' => __('Choose at least one commercial disclosure option for TikTok.'),
            'commercial_required_tooltip' => __('You need to indicate if your content promotes yourself, a third party, or both.'),
            'your_brand_label' => __('Your photo/video will be labeled as “Promotional content”.'),
            'branded_content_label' => __('Your photo/video will be labeled as “Paid partnership”.'),
            'consent_required' => __('You must confirm TikTok music and disclosure consent before posting.'),
            'video_only_interactions' => __('Duet and Stitch are only available for TikTok video posts.'),
            'consent_music' => __('By posting, you agree to TikTok Music Usage Confirmation.'),
            'consent_branded' => __('By posting, you agree to TikTok Branded Content Policy and Music Usage Confirmation.'),
            'privacy_hint' => __('TikTok only allows privacy levels returned by the selected profile.'),
            'branded_private_hint' => __('Branded content visibility cannot be set to private.'),
            'interaction_video' => __('Enable only the interactions supported by the selected TikTok profile.'),
            'interaction_image' => __('Duet and Stitch are only available for video posts.'),
            'photo_url_note' => __('TikTok photo posts require publicly accessible image URLs on a verified domain. Video posts upload directly from the selected file.'),
        ],
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
@endphp

<div
    class="space-y-4 px-4 py-4"
    x-data='{
        accountIds: $wire.entangle("composer.account_ids").live,
        mediaItems: $wire.entangle("composer.media_items").live,
        privacyValue: $wire.entangle("{{ $privacyField }}").live,
        allowComment: $wire.entangle("{{ $commentField }}").live,
        allowDuet: $wire.entangle("{{ $duetField }}").live,
        allowStitch: $wire.entangle("{{ $stitchField }}").live,
        commercialContent: $wire.entangle("{{ $commercialField }}").live,
        yourBrand: $wire.entangle("{{ $yourBrandField }}").live,
        brandedContent: $wire.entangle("{{ $brandedField }}").live,
        consent: $wire.entangle("{{ $consentField }}").live,
        canPost: $wire.entangle("{{ $canPostField }}").live,
        creatorReady: $wire.entangle("{{ $creatorReadyField }}").live,
        creatorProfiles: [],
        creatorError: "",
        loadingCreator: false,
        creatorInfoUrl: "",
        csrfToken: "",
        texts: {},
        privacyOptions: [
            "PUBLIC_TO_EVERYONE",
            "MUTUAL_FOLLOW_FRIENDS",
            "FOLLOWER_OF_CREATOR",
            "SELF_ONLY",
        ],
        init() {
            const config = JSON.parse(this.$refs.config.textContent || "{}");
            this.creatorInfoUrl = String(config.creatorInfoUrl || "");
            this.csrfToken = String(config.csrfToken || "");
            this.texts = config.texts || {};
            this.$watch("accountIds", () => this.loadCreatorInfo());
            this.$watch("mediaItems", () => this.syncState());
            this.$watch("brandedContent", () => this.syncState());
            this.$watch("commercialContent", () => this.syncState());
            this.$watch("privacyValue", () => this.syncPublishBlockState());
            this.$watch("canPost", () => this.syncPublishBlockState());
            this.$watch("creatorReady", () => this.syncPublishBlockState());
            this.$watch("allowComment", () => this.syncPublishBlockState());
            this.$watch("allowDuet", () => this.syncPublishBlockState());
            this.$watch("allowStitch", () => this.syncPublishBlockState());
            this.$watch("yourBrand", () => this.syncPublishBlockState());
            this.$watch("consent", () => this.syncPublishBlockState());
            this.loadCreatorInfo();
            this.syncState();
        },
        privacyLabel(option) {
            return {
                PUBLIC_TO_EVERYONE: this.texts.everyone,
                MUTUAL_FOLLOW_FRIENDS: this.texts.friends,
                FOLLOWER_OF_CREATOR: this.texts.followers,
                SELF_ONLY: this.texts.only_me,
            }[option] || option;
        },
        selectedMediaKind() {
            const items = Array.isArray(this.mediaItems) ? this.mediaItems : [];
            let hasVideo = false;
            let hasImage = false;

            items.forEach((item) => {
                const mime = String(item?.mimeType || "").toLowerCase();
                const category = String(item?.category || "").toLowerCase();
                if (mime.startsWith("video/") || category === "video") {
                    hasVideo = true;
                }
                if (Boolean(item?.isImage)) {
                    hasImage = true;
                }
            });

            if (hasVideo && hasImage) return "mixed";
            if (hasVideo) return "video";
            if (hasImage) return "image";
            return "none";
        },
        profileSummary() {
            if (!Array.isArray(this.creatorProfiles) || this.creatorProfiles.length === 0) {
                return null;
            }

            const base = {
                nickname: "",
                username: "",
                avatar: "",
                privacy_level_options: [...this.privacyOptions],
                comment_disabled: false,
                duet_disabled: false,
                stitch_disabled: false,
                max_video_post_duration_sec: 0,
            };

            this.creatorProfiles.forEach((profile, index) => {
                const options = Array.isArray(profile?.privacy_level_options) ? profile.privacy_level_options : [];

                if (index === 0) {
                    base.nickname = String(profile?.nickname || profile?.name || "");
                    base.username = String(profile?.username || "");
                    base.avatar = String(profile?.avatar || "");
                    base.privacy_level_options = options.length ? options : base.privacy_level_options;
                    base.max_video_post_duration_sec = Number(profile?.max_video_post_duration_sec || 0);
                    return;
                }

                base.privacy_level_options = base.privacy_level_options.filter((value) => options.includes(value));
                const nextDuration = Number(profile?.max_video_post_duration_sec || 0);
                if (nextDuration > 0) {
                    base.max_video_post_duration_sec = base.max_video_post_duration_sec > 0
                        ? Math.min(base.max_video_post_duration_sec, nextDuration)
                        : nextDuration;
                }
            });

            base.comment_disabled = this.creatorProfiles.some((profile) => Boolean(profile?.comment_disabled));
            base.duet_disabled = this.creatorProfiles.some((profile) => Boolean(profile?.duet_disabled));
            base.stitch_disabled = this.creatorProfiles.some((profile) => Boolean(profile?.stitch_disabled));

            return base;
        },
        privacyChoices() {
            const summary = this.profileSummary();
            return Array.isArray(summary?.privacy_level_options) && summary.privacy_level_options.length
                ? summary.privacy_level_options
                : this.privacyOptions;
        },
        privateVisibilityIsUnavailable() {
            return this.commercialContent && this.brandedContent;
        },
        disclosureLabel() {
            if (!this.commercialContent || (!this.yourBrand && !this.brandedContent)) {
                return "";
            }

            return this.brandedContent
                ? this.texts.branded_content_label
                : this.texts.your_brand_label;
        },
        hasLoadedCreator() {
            return Array.isArray(this.creatorProfiles) && this.creatorProfiles.length > 0;
        },
        resolvedCanPost() {
            return this.hasLoadedCreator() && this.creatorProfiles.every((profile) => Boolean(profile?.can_post));
        },
        creatorMeta() {
            const summary = this.profileSummary();
            if (!summary) {
                return "";
            }

            const parts = [];
            if (summary.max_video_post_duration_sec > 0) {
                parts.push(`${this.texts.max_video_duration}: ${summary.max_video_post_duration_sec}s`);
            }
            if (this.creatorProfiles.length > 1) {
                parts.push(this.texts.merged_policy);
            }

            return parts.join(" • ");
        },
        consentLabel() {
            return this.commercialContent && this.brandedContent
                ? this.texts.consent_branded
                : this.texts.consent_music;
        },
        syncState() {
            const mediaKind = this.selectedMediaKind();
            const summary = this.profileSummary();
            const availablePrivacy = this.privacyChoices();

            if (!availablePrivacy.includes(this.privacyValue || "")) {
                this.privacyValue = "";
            }

            if (summary?.comment_disabled) {
                this.allowComment = false;
            }

            if (mediaKind !== "video") {
                this.allowDuet = false;
                this.allowStitch = false;
            } else {
                if (summary?.duet_disabled) this.allowDuet = false;
                if (summary?.stitch_disabled) this.allowStitch = false;
            }

            if (!this.commercialContent) {
                this.yourBrand = false;
                this.brandedContent = false;
            }

            if (this.privateVisibilityIsUnavailable() && this.privacyValue === "SELF_ONLY") {
                const nonPrivateOption = availablePrivacy.find((value) => value !== "SELF_ONLY");
                if (nonPrivateOption) {
                    this.privacyValue = nonPrivateOption;
                }
            }

            this.syncPublishBlockState();
        },
        async loadCreatorInfo() {
            const accountIds = (Array.isArray(this.accountIds) ? this.accountIds : [])
                .map((value) => Number(value))
                .filter((value) => Number.isInteger(value) && value > 0);

            if (accountIds.length === 0) {
                this.creatorProfiles = [];
                this.creatorError = "";
                this.creatorReady = false;
                this.canPost = false;
                this.syncState();
                return;
            }

            this.loadingCreator = true;
            this.creatorError = "";
            this.creatorReady = false;
            this.canPost = false;
            this.syncPublishBlockState();

            try {
                const response = await fetch(this.creatorInfoUrl, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "Accept": "application/json",
                        "X-CSRF-TOKEN": String(this.csrfToken || ""),
                    },
                    body: JSON.stringify({ accounts: accountIds }),
                });

                const result = await response.json();
                if (!response.ok || Number(result?.status || 0) !== 1) {
                    this.creatorProfiles = [];
                    this.creatorError = String(result?.message || this.texts.unable_to_load_creator_settings);
                    this.creatorReady = false;
                    this.canPost = false;
                    this.syncState();
                    return;
                }

                this.creatorProfiles = Array.isArray(result?.data?.profiles) ? result.data.profiles : [];
                const errors = Array.isArray(result?.data?.errors) ? result.data.errors.filter(Boolean) : [];
                this.creatorError = errors.length ? errors.join(" ") : "";
                this.creatorReady = this.creatorProfiles.length > 0;
                this.canPost = this.creatorProfiles.length > 0 && this.creatorProfiles.every((profile) => Boolean(profile?.can_post));
                this.syncState();
            } catch (error) {
                this.creatorProfiles = [];
                this.creatorError = this.texts.unable_to_load_creator_settings;
                this.creatorReady = false;
                this.canPost = false;
                this.syncState();
            } finally {
                this.loadingCreator = false;
                this.syncPublishBlockState();
            }
        },
        publishBlockMessage() {
            const mediaKind = this.selectedMediaKind();
            const creatorLoaded = this.hasLoadedCreator();
            const canPost = this.resolvedCanPost();

            if (this.creatorError) return this.creatorError;
            if (this.loadingCreator || !creatorLoaded) return this.texts.creator_loading_block;
            if (!canPost) return this.texts.creator_cannot_post;
            if (!String(this.privacyValue || "").trim()) return this.texts.privacy_required;
            if (this.commercialContent && !this.yourBrand && !this.brandedContent) return this.texts.commercial_required;
            if (!this.consent) return this.texts.consent_required;
            if (mediaKind !== "video" && (this.allowDuet || this.allowStitch)) return this.texts.video_only_interactions;

            return "";
        },
        syncPublishBlockState() {
            const message = this.publishBlockMessage();
            window.dispatchEvent(new CustomEvent("tiktok-composer-policy", {
                detail: {
                    blocked: message !== "",
                    message,
                },
            }));
        },
    }'
    x-init="init()"
>
    <script type="application/json" x-ref="config">{!! $composerConfigJson !!}</script>

    <div class="space-y-2.5">
        <x-ui.label>{{ __('Post To') }}</x-ui.label>
        <div class="inline-flex items-center gap-2 rounded-full border px-3.5 py-2 text-sm font-semibold" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-accent-rgb), 0.08); color: var(--theme-header-text-color);">
            <i class="fa-brands fa-tiktok"></i>
            <span>{{ __('Feed') }}</span>
        </div>
    </div>

    <div class="rounded-[1rem] border p-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: color-mix(in srgb, var(--theme-surface-overlay) 96%, transparent);">
        <div class="flex items-start gap-3">
            <div class="inline-flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-full border" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(17,17,17,0.08); color: #111111;">
                <template x-if="profileSummary()?.avatar">
                    <img x-bind:src="profileSummary().avatar" alt="{{ __('TikTok creator') }}" class="h-full w-full object-cover">
                </template>
                <template x-if="!profileSummary()?.avatar">
                    <i class="fa-brands fa-tiktok text-lg"></i>
                </template>
            </div>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);" x-text="profileSummary()?.nickname || texts.select_profile">{{ __('Select a TikTok profile') }}</p>
                <p class="mt-1 text-sm" style="color: var(--theme-muted-text-color);" x-text="profileSummary()?.username ? `@${profileSummary().username}` : texts.latest_creator_settings">{{ __('Latest creator settings will load automatically.') }}</p>
                <p class="mt-2 text-xs" style="color: var(--theme-muted-text-color);" x-text="creatorMeta()" x-show="creatorMeta() !== ''"></p>
                <p class="mt-2 text-xs" style="color: var(--theme-danger-color);" x-text="creatorError" x-show="creatorError !== ''"></p>
                <p class="mt-2 text-xs" style="color: var(--theme-danger-color);" x-show="hasLoadedCreator() && !resolvedCanPost()" x-text="texts.creator_cannot_post"></p>
                <p class="mt-2 text-xs font-medium" style="color: var(--theme-muted-text-color);" x-show="loadingCreator" x-text="texts.loading_creator_settings"></p>
            </div>
        </div>
    </div>

    <div class="space-y-2">
        <x-ui.label>{{ __('Privacy') }}</x-ui.label>
        <select
            x-model="privacyValue"
            x-on:change="$wire.set(@js($privacyField), privacyValue)"
            wire:model.live="{{ $privacyField }}"
            data-no-loading
            class="w-full rounded-[1rem] border px-4 py-3 text-sm outline-none transition"
            style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: var(--theme-surface-base); color: var(--theme-header-text-color);"
        >
            <option value="">{{ __('Select privacy') }}</option>
            <template x-for="option in privacyChoices()" :key="`tt-privacy-${option}`">
                <option
                    x-bind:value="option"
                    x-bind:disabled="option === 'SELF_ONLY' && privateVisibilityIsUnavailable()"
                    x-bind:title="option === 'SELF_ONLY' && privateVisibilityIsUnavailable() ? texts.branded_private_hint : ''"
                    x-text="privacyLabel(option)"
                ></option>
            </template>
        </select>
        @if ($errors->first($privacyField))
            <p
                class="text-sm font-medium"
                style="color: var(--theme-danger-color);"
                x-data='{ serverError: @js($errors->first($privacyField)) }'
                x-show='!(serverError === texts.creator_loading_block && hasLoadedCreator())'
                x-text="serverError"
            >{{ $errors->first($privacyField) }}</p>
        @endif
        <p class="text-xs" style="color: var(--theme-muted-text-color);" x-text="privateVisibilityIsUnavailable() ? texts.branded_private_hint : texts.privacy_hint"></p>
    </div>

    <div class="space-y-3 rounded-[1rem] border p-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: color-mix(in srgb, var(--theme-surface-overlay) 96%, transparent);">
        <div>
            <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Interaction Settings') }}</p>
            <p class="mt-1 text-xs" style="color: var(--theme-muted-text-color);" x-text="selectedMediaKind() === 'image' ? texts.interaction_image : texts.interaction_video"></p>
        </div>

        <label class="flex items-center gap-3 text-sm" style="color: var(--theme-header-text-color);">
            <input type="checkbox" x-model="allowComment" x-on:change="$wire.set(@js($commentField), allowComment)" wire:model.live="{{ $commentField }}" x-bind:disabled="Boolean(profileSummary()?.comment_disabled)" class="h-4 w-4 rounded border-slate-300">
            <span>{{ __('Allow Comment') }}</span>
        </label>

        <label class="flex items-center gap-3 text-sm" style="color: var(--theme-header-text-color);" x-show="selectedMediaKind() === 'video'">
            <input type="checkbox" x-model="allowDuet" x-on:change="$wire.set(@js($duetField), allowDuet)" wire:model.live="{{ $duetField }}" x-bind:disabled="selectedMediaKind() !== 'video' || Boolean(profileSummary()?.duet_disabled)" class="h-4 w-4 rounded border-slate-300">
            <span>{{ __('Allow Duet') }}</span>
        </label>

        <label class="flex items-center gap-3 text-sm" style="color: var(--theme-header-text-color);" x-show="selectedMediaKind() === 'video'">
            <input type="checkbox" x-model="allowStitch" x-on:change="$wire.set(@js($stitchField), allowStitch)" wire:model.live="{{ $stitchField }}" x-bind:disabled="selectedMediaKind() !== 'video' || Boolean(profileSummary()?.stitch_disabled)" class="h-4 w-4 rounded border-slate-300">
            <span>{{ __('Allow Stitch') }}</span>
        </label>

        @if ($errors->first($duetField))
            <p class="text-sm font-medium" style="color: var(--theme-danger-color);">{{ $errors->first($duetField) }}</p>
        @endif
    </div>

    <div class="space-y-3 rounded-[1rem] border p-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: color-mix(in srgb, var(--theme-surface-overlay) 96%, transparent);">
        <div>
            <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Commercial Content Disclosure') }}</p>
            <p class="mt-1 text-xs" style="color: var(--theme-muted-text-color);">{{ __('Use TikTok disclosure labels when this post promotes your own or another business.') }}</p>
        </div>

        <label class="flex items-start gap-3 text-sm" style="color: var(--theme-header-text-color);">
            <input type="checkbox" x-model="commercialContent" x-on:change="$wire.set(@js($commercialField), commercialContent)" wire:model.live="{{ $commercialField }}" class="mt-0.5 h-4 w-4 rounded border-slate-300">
            <span>{{ __('This content promotes yourself, a brand, product, or service') }}</span>
        </label>

        <div class="space-y-3 pl-7" x-show="commercialContent">
            <label class="flex items-start gap-3 text-sm" style="color: var(--theme-header-text-color);">
                <input type="checkbox" x-model="yourBrand" x-on:change="$wire.set(@js($yourBrandField), yourBrand)" wire:model.live="{{ $yourBrandField }}" class="mt-0.5 h-4 w-4 rounded border-slate-300">
                <span>
                    <span class="block font-medium">{{ __('Your Brand') }}</span>
                    <span class="mt-1 block text-xs" style="color: var(--theme-muted-text-color);">{{ __("Label this as promotional content for the creator's own business.") }}</span>
                </span>
            </label>

            <label class="flex items-start gap-3 text-sm" style="color: var(--theme-header-text-color);">
                <input type="checkbox" x-model="brandedContent" x-on:change="$wire.set(@js($brandedField), brandedContent)" wire:model.live="{{ $brandedField }}" class="mt-0.5 h-4 w-4 rounded border-slate-300">
                <span>
                    <span class="block font-medium">{{ __('Branded Content') }}</span>
                    <span class="mt-1 block text-xs" style="color: var(--theme-muted-text-color);">{{ __('Label this as a paid partnership for a third-party business.') }}</span>
                </span>
            </label>
        </div>

        <p
            class="rounded-xl px-3 py-2 text-xs font-medium"
            style="background-color: rgba(var(--theme-accent-rgb), 0.09); color: var(--theme-header-text-color);"
            x-show="disclosureLabel() !== ''"
            x-text="disclosureLabel()"
        ></p>

        <p
            class="rounded-xl px-3 py-2 text-xs font-medium"
            style="background-color: rgba(245, 158, 11, 0.12); color: #92400e;"
            x-show="commercialContent && !yourBrand && !brandedContent"
            x-text="texts.commercial_required_tooltip"
        ></p>

        @if ($errors->first($yourBrandField))
            <p class="text-sm font-medium" style="color: var(--theme-danger-color);">{{ $errors->first($yourBrandField) }}</p>
        @endif
    </div>

    <div class="space-y-3 rounded-[1rem] border p-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: color-mix(in srgb, var(--theme-surface-overlay) 96%, transparent);">
        <label class="flex items-start gap-3 text-sm" style="color: var(--theme-header-text-color);">
            <input type="checkbox" x-model="consent" x-on:change="$wire.set(@js($consentField), consent)" wire:model.live="{{ $consentField }}" class="mt-0.5 h-4 w-4 rounded border-slate-300">
            <span x-text="consentLabel()">{{ __("By posting, you agree to TikTok's Music Usage Confirmation.") }}</span>
        </label>

        @if ($errors->first($consentField))
            <p class="text-sm font-medium" style="color: var(--theme-danger-color);">{{ $errors->first($consentField) }}</p>
        @endif
    </div>

    <div class="rounded-[1rem] border px-4 py-3 text-sm" style="border-color: rgba(245, 158, 11, 0.18); background-color: rgba(245, 158, 11, 0.08); color: #92400e;">
        <p x-text="texts.photo_url_note">{{ __('TikTok photo posts require publicly accessible image URLs on a verified domain. Video posts upload directly from the selected file.') }}</p>
    </div>
</div>
