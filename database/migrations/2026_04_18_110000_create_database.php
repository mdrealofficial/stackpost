<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        try {
            Schema::create('account_groups', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('owner_user_id');
                $table->unsignedBigInteger('team_id')->nullable();
                $table->string('name', 255);
                $table->string('slug', 255);
                $table->text('description')->nullable();
                $table->string('color', 24)->default('#2563eb');
                $table->string('status', 32)->default('active');
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->unique(['owner_user_id', 'slug'], 'account_groups_owner_user_id_slug_unique');
                $table->index(['owner_user_id', 'status'], 'account_groups_owner_user_id_status_index');
                $table->index('team_id', 'account_groups_team_id_index');
            });

            Schema::create('account_group_social_account', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('group_id');
                $table->unsignedBigInteger('social_account_id');
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->unique(['group_id', 'social_account_id'], 'account_group_social_account_group_id_social_account_id_unique');
                $table->index('social_account_id', 'account_group_social_account_social_account_id_foreign');
            });

            Schema::create('admin_roles', function (Blueprint $table): void {
                $table->id();
                $table->string('name', 255);
                $table->string('slug', 255);
                $table->text('description')->nullable();
                $table->json('permissions')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->unique('name', 'admin_roles_name_unique');
                $table->unique('slug', 'admin_roles_slug_unique');
            });

            Schema::create('affiliate_commissions', function (Blueprint $table): void {
                $table->id();
                $table->string('id_secure', 40);
                $table->unsignedBigInteger('affiliate_user_id');
                $table->unsignedBigInteger('referred_user_id')->nullable();
                $table->unsignedBigInteger('payment_history_id')->nullable();
                $table->decimal('amount', 12, 2)->default(0);
                $table->decimal('commission_rate', 8, 2)->default(0);
                $table->decimal('commission', 12, 2)->default(0);
                $table->unsignedTinyInteger('status')->default(0);
                $table->json('meta')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->unique('id_secure', 'affiliate_commissions_id_secure_unique');
                $table->index('referred_user_id', 'affiliate_commissions_referred_user_id_foreign');
                $table->index('payment_history_id', 'affiliate_commissions_payment_history_id_foreign');
                $table->index(['affiliate_user_id', 'status'], 'affiliate_commissions_affiliate_user_id_status_index');
            });

            Schema::create('affiliate_profiles', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedInteger('clicks')->default(0);
                $table->unsignedInteger('conversions')->default(0);
                $table->decimal('total_approved', 12, 2)->default(0);
                $table->decimal('total_withdrawal', 12, 2)->default(0);
                $table->decimal('total_balance', 12, 2)->default(0);
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->unique('user_id', 'affiliate_profiles_user_id_unique');
            });

            Schema::create('affiliate_withdrawals', function (Blueprint $table): void {
                $table->id();
                $table->string('id_secure', 40);
                $table->unsignedBigInteger('affiliate_user_id');
                $table->decimal('amount', 12, 2)->default(0);
                $table->string('payment_method', 120);
                $table->text('payment_details')->nullable();
                $table->text('notes')->nullable();
                $table->unsignedTinyInteger('status')->default(0);
                $table->timestamp('processed_at')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->unique('id_secure', 'affiliate_withdrawals_id_secure_unique');
                $table->index(['affiliate_user_id', 'status'], 'affiliate_withdrawals_affiliate_user_id_status_index');
            });

            Schema::create('ai_content_plans', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('owner_user_id');
                $table->unsignedBigInteger('requested_by_user_id');
                $table->unsignedBigInteger('team_id')->nullable();
                $table->string('title', 255)->nullable();
                $table->text('brief');
                $table->date('start_date');
                $table->unsignedSmallInteger('days')->default(14);
                $table->string('source', 30)->default('ai');
                $table->text('overview')->nullable();
                $table->json('items');
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->index(['owner_user_id', 'requested_by_user_id'], 'ai_content_plans_owner_user_id_requested_by_user_id_index');
                $table->index('team_id', 'ai_content_plans_team_id_index');
                $table->index('start_date', 'ai_content_plans_start_date_index');
            });

            Schema::create('ai_image_jobs', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('owner_user_id');
                $table->unsignedBigInteger('requested_by_user_id');
                $table->unsignedBigInteger('team_id')->nullable();
                $table->unsignedBigInteger('file_id')->nullable();
                $table->string('provider', 50)->nullable();
                $table->string('model', 120)->nullable();
                $table->string('status', 30)->default('generated');
                $table->string('style', 40)->nullable();
                $table->string('ratio', 20)->nullable();
                $table->text('prompt');
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->index(['owner_user_id', 'requested_by_user_id'], 'ai_image_jobs_owner_user_id_requested_by_user_id_index');
                $table->index('team_id', 'ai_image_jobs_team_id_index');
                $table->index('file_id', 'ai_image_jobs_file_id_index');
                $table->index('status', 'ai_image_jobs_status_index');
            });

            Schema::create('ai_prompt_histories', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('owner_user_id');
                $table->unsignedBigInteger('requested_by_user_id');
                $table->unsignedBigInteger('team_id')->nullable();
                $table->string('module', 80);
                $table->string('title', 190)->nullable();
                $table->string('language', 20)->nullable();
                $table->string('tone', 40)->nullable();
                $table->longText('prompt');
                $table->json('input_payload')->nullable();
                $table->json('output_payload')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->index(['owner_user_id', 'requested_by_user_id'], 'ai_prompt_histories_owner_user_id_requested_by_user_id_index');
                $table->index('team_id', 'ai_prompt_histories_team_id_index');
                $table->index('module', 'ai_prompt_histories_module_index');
            });

            Schema::create('ai_publishing_prompts', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('owner_user_id');
                $table->string('title', 180)->nullable();
                $table->longText('prompt_text');
                $table->json('metadata')->nullable();
                $table->boolean('is_active')->default(1);
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->index('owner_user_id', 'ai_publishing_prompts_owner_user_id_foreign');
            });

            Schema::create('ai_publishing_runs', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('owner_user_id');
                $table->unsignedBigInteger('team_id')->nullable();
                $table->unsignedBigInteger('workspace_owner_user_id')->nullable();
                $table->string('name', 180)->nullable();
                $table->unsignedBigInteger('campaign_id')->nullable();
                $table->json('label_ids')->nullable();
                $table->json('account_ids')->nullable();
                $table->json('prompt_ids')->nullable();
                $table->json('schedule_config')->nullable();
                $table->json('generation_config')->nullable();
                $table->json('stats')->nullable();
                $table->string('status', 40)->default('draft');
                $table->timestamp('last_processed_at')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->index('owner_user_id', 'ai_publishing_runs_owner_user_id_foreign');
                $table->index('team_id', 'ai_publishing_runs_team_id_foreign');
                $table->index('status', 'ai_publishing_runs_status_index');
            });

            Schema::create('ai_studio_user_settings', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->json('settings')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->unique('user_id', 'ai_studio_user_settings_user_id_unique');
            });

            Schema::create('ai_studio_workspace_settings', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('owner_user_id');
                $table->unsignedBigInteger('team_id')->nullable();
                $table->json('settings')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->unique(['owner_user_id', 'team_id'], 'ai_studio_workspace_settings_owner_user_id_team_id_unique');
                $table->index('team_id', 'ai_studio_workspace_settings_team_id_foreign');
            });

            Schema::create('ai_templates', function (Blueprint $table): void {
                $table->id();
                $table->string('id_secure', 32)->nullable();
                $table->unsignedBigInteger('cate_id')->nullable();
                $table->text('content')->nullable();
                $table->boolean('status')->default(1);
                $table->unsignedBigInteger('changed')->nullable();
                $table->unsignedBigInteger('created')->nullable();
                $table->unique('id_secure', 'ai_templates_id_secure_unique');
                $table->index('cate_id', 'ai_templates_cate_id_foreign');
                $table->index(['status', 'changed'], 'ai_templates_status_changed_index');
            });

            Schema::create('ai_template_categories', function (Blueprint $table): void {
                $table->id();
                $table->string('id_secure', 32)->nullable();
                $table->string('name', 100);
                $table->string('desc', 500)->nullable();
                $table->string('icon', 150)->nullable();
                $table->string('color', 30)->default('primary');
                $table->boolean('status')->default(1);
                $table->unsignedBigInteger('changed')->nullable();
                $table->unsignedBigInteger('created')->nullable();
                $table->unique('name', 'ai_template_categories_name_unique');
                $table->unique('id_secure', 'ai_template_categories_id_secure_unique');
                $table->index(['status', 'changed'], 'ai_template_categories_status_changed_index');
            });

            Schema::create('ai_usage_logs', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('provider', 60);
                $table->string('capability', 60);
                $table->string('model', 120);
                $table->string('status', 30)->default('success');
                $table->string('feature', 120)->nullable();
                $table->string('route_name', 160)->nullable();
                $table->unsignedInteger('prompt_tokens')->nullable();
                $table->unsignedInteger('completion_tokens')->nullable();
                $table->unsignedInteger('total_tokens')->nullable();
                $table->decimal('estimated_cost', 12, 6)->nullable();
                $table->unsignedInteger('latency_ms')->nullable();
                $table->text('error_message')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->index(['provider', 'capability'], 'ai_usage_logs_provider_capability_index');
                $table->index(['status', 'created_at'], 'ai_usage_logs_status_created_at_index');
                $table->index(['user_id', 'created_at'], 'ai_usage_logs_user_id_created_at_index');
            });

            Schema::create('ai_video_jobs', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('owner_user_id');
                $table->unsignedBigInteger('requested_by_user_id')->nullable();
                $table->unsignedBigInteger('team_id')->nullable();
                $table->unsignedBigInteger('file_id')->nullable();
                $table->string('external_video_id', 255);
                $table->string('provider', 40);
                $table->string('model', 120);
                $table->string('status', 40)->default('queued');
                $table->unsignedInteger('progress')->default(0);
                $table->string('duration', 10)->nullable();
                $table->string('format', 40)->nullable();
                $table->string('size', 40)->nullable();
                $table->text('prompt');
                $table->json('metadata')->nullable();
                $table->timestamp('last_polled_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('failed_at')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->unique('external_video_id', 'ai_video_jobs_external_video_id_unique');
                $table->index('requested_by_user_id', 'ai_video_jobs_requested_by_user_id_foreign');
                $table->index('team_id', 'ai_video_jobs_team_id_foreign');
                $table->index('file_id', 'ai_video_jobs_file_id_foreign');
                $table->index(['owner_user_id', 'requested_by_user_id'], 'ai_video_jobs_owner_user_id_requested_by_user_id_index');
                $table->index(['status', 'created_at'], 'ai_video_jobs_status_created_at_index');
            });

            Schema::create('audit_logs', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('causer_user_id')->nullable();
                $table->string('event', 255);
                $table->string('description', 255)->nullable();
                $table->string('subject_type', 255)->nullable();
                $table->unsignedBigInteger('subject_id')->nullable();
                $table->string('route_name', 255)->nullable();
                $table->string('area', 20)->default('admin');
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->index(['subject_type', 'subject_id'], 'audit_logs_subject_type_subject_id_index');
                $table->index(['causer_user_id', 'created_at'], 'audit_logs_causer_user_id_created_at_index');
                $table->index(['event', 'created_at'], 'audit_logs_event_created_at_index');
            });

            Schema::create('automation_api_keys', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('team_id')->nullable();
                $table->string('name', 120);
                $table->string('key_hash', 64);
                $table->string('key_prefix', 24);
                $table->json('permissions')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->unique('key_hash', 'automation_api_keys_key_hash_unique');
                $table->index('user_id', 'automation_api_keys_user_id_foreign');
                $table->index('team_id', 'automation_api_keys_team_id_foreign');
                $table->index('key_prefix', 'automation_api_keys_key_prefix_index');
            });

            Schema::create('automation_logs', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('team_id')->nullable();
                $table->unsignedBigInteger('api_key_id')->nullable();
                $table->unsignedBigInteger('webhook_id')->nullable();
                $table->string('direction', 20);
                $table->string('event', 120);
                $table->string('request_id', 80)->nullable();
                $table->string('status', 40);
                $table->unsignedSmallInteger('status_code')->nullable();
                $table->json('payload')->nullable();
                $table->json('response')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->index('user_id', 'automation_logs_user_id_foreign');
                $table->index('team_id', 'automation_logs_team_id_foreign');
                $table->index('api_key_id', 'automation_logs_api_key_id_foreign');
                $table->index('webhook_id', 'automation_logs_webhook_id_foreign');
                $table->index('direction', 'automation_logs_direction_index');
                $table->index('event', 'automation_logs_event_index');
                $table->index('request_id', 'automation_logs_request_id_index');
                $table->index('status', 'automation_logs_status_index');
            });

            Schema::create('automation_webhooks', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('team_id')->nullable();
                $table->string('name', 120);
                $table->string('url', 1000);
                $table->string('signing_secret', 100);
                $table->json('events')->nullable();
                $table->boolean('is_active')->default(1);
                $table->timestamp('last_sent_at')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->index('user_id', 'automation_webhooks_user_id_foreign');
                $table->index('team_id', 'automation_webhooks_team_id_foreign');
            });

            Schema::create('blogs', function (Blueprint $table): void {
                $table->id();
                $table->string('id_secure', 64);
                $table->unsignedBigInteger('blog_category_id')->nullable();
                $table->string('title', 255);
                $table->json('title_translations')->nullable();
                $table->text('excerpt')->nullable();
                $table->json('excerpt_translations')->nullable();
                $table->longText('content');
                $table->json('content_translations')->nullable();
                $table->string('slug', 255);
                $table->string('meta_title', 255)->nullable();
                $table->text('meta_description')->nullable();
                $table->string('canonical_url', 2000)->nullable();
                $table->text('og_image')->nullable();
                $table->text('thumbnail')->nullable();
                $table->boolean('status')->default(1);
                $table->unsignedBigInteger('published_at')->nullable();
                $table->unsignedBigInteger('changed')->default(0);
                $table->unsignedBigInteger('created')->default(0);
                $table->unique('id_secure', 'blogs_id_secure_unique');
                $table->unique('slug', 'blogs_slug_unique');
                $table->index('blog_category_id', 'blogs_blog_category_id_foreign');
            });

            Schema::create('blog_categories', function (Blueprint $table): void {
                $table->id();
                $table->string('id_secure', 64);
                $table->string('name', 255);
                $table->json('name_translations')->nullable();
                $table->text('description')->nullable();
                $table->json('description_translations')->nullable();
                $table->string('slug', 255);
                $table->string('icon', 255)->nullable();
                $table->string('color', 32)->default('#0f766e');
                $table->boolean('status')->default(1);
                $table->integer('sort_order')->default(0);
                $table->unsignedBigInteger('changed')->default(0);
                $table->unsignedBigInteger('created')->default(0);
                $table->unique('id_secure', 'blog_categories_id_secure_unique');
                $table->unique('slug', 'blog_categories_slug_unique');
            });

            Schema::create('blog_rss_imports', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('blog_rss_source_id');
                $table->unsignedBigInteger('blog_id')->nullable();
                $table->string('external_guid', 255)->nullable();
                $table->text('external_url')->nullable();
                $table->string('content_hash', 64);
                $table->string('title', 255)->nullable();
                $table->unsignedBigInteger('source_published_at')->nullable();
                $table->unsignedBigInteger('changed')->default(0);
                $table->unsignedBigInteger('created')->default(0);
                $table->unique(['blog_rss_source_id', 'content_hash'], 'blog_rss_imports_blog_rss_source_id_content_hash_unique');
                $table->index('blog_id', 'blog_rss_imports_blog_id_foreign');
            });

            Schema::create('blog_rss_sources', function (Blueprint $table): void {
                $table->id();
                $table->string('id_secure', 64);
                $table->string('name', 255);
                $table->text('feed_url');
                $table->unsignedBigInteger('blog_category_id')->nullable();
                $table->json('tag_ids')->nullable();
                $table->boolean('status')->default(1);
                $table->boolean('auto_publish')->default(1);
                $table->boolean('ai_improve')->default(0);
                $table->boolean('ai_auto_translate')->default(0);
                $table->text('ai_prompt')->nullable();
                $table->unsignedInteger('sync_interval_minutes')->default(60);
                $table->unsignedInteger('max_items_per_run')->default(5);
                $table->unsignedBigInteger('last_checked_at')->nullable();
                $table->unsignedBigInteger('last_imported_at')->nullable();
                $table->text('last_error')->nullable();
                $table->unsignedBigInteger('changed')->default(0);
                $table->unsignedBigInteger('created')->default(0);
                $table->unique('id_secure', 'blog_rss_sources_id_secure_unique');
                $table->index('blog_category_id', 'blog_rss_sources_blog_category_id_foreign');
            });

            Schema::create('blog_tags', function (Blueprint $table): void {
                $table->id();
                $table->string('id_secure', 64);
                $table->string('name', 255);
                $table->json('name_translations')->nullable();
                $table->text('description')->nullable();
                $table->json('description_translations')->nullable();
                $table->string('slug', 255);
                $table->string('icon', 255)->nullable();
                $table->string('color', 32)->default('#0f766e');
                $table->boolean('status')->default(1);
                $table->unsignedBigInteger('changed')->default(0);
                $table->unsignedBigInteger('created')->default(0);
                $table->unique('id_secure', 'blog_tags_id_secure_unique');
                $table->unique('slug', 'blog_tags_slug_unique');
            });

            Schema::create('blog_tag_maps', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('blog_id');
                $table->unsignedBigInteger('blog_tag_id');
                $table->unique(['blog_id', 'blog_tag_id'], 'blog_tag_maps_blog_id_blog_tag_id_unique');
                $table->index('blog_tag_id', 'blog_tag_maps_blog_tag_id_foreign');
            });

            Schema::create('bulk_post_batches', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('owner_user_id');
                $table->unsignedBigInteger('team_id')->nullable();
                $table->string('name', 160);
                $table->string('status', 40)->default('draft');
                $table->string('source_filename', 255)->nullable();
                $table->json('meta')->nullable();
                $table->json('stats')->nullable();
                $table->timestamp('last_processed_at')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->index('owner_user_id', 'bulk_post_batches_owner_user_id_foreign');
            });

            Schema::create('bulk_post_rows', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('batch_id');
                $table->unsignedInteger('row_number');
                $table->string('status', 40)->default('pending');
                $table->json('payload')->nullable();
                $table->json('validation_errors')->nullable();
                $table->unsignedBigInteger('publishing_post_id')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->index('batch_id', 'bulk_post_rows_batch_id_foreign');
                $table->index('status', 'bulk_post_rows_status_index');
                $table->index('publishing_post_id', 'bulk_post_rows_publishing_post_id_index');
            });

            Schema::create('cache', function (Blueprint $table): void {
                $table->string('key', 255);
                $table->mediumText('value');
                $table->integer('expiration');
                $table->primary('key');
                $table->index('expiration', 'cache_expiration_index');
            });

            Schema::create('cache_locks', function (Blueprint $table): void {
                $table->string('key', 255);
                $table->string('owner', 255);
                $table->integer('expiration');
                $table->primary('key');
                $table->index('expiration', 'cache_locks_expiration_index');
            });

            Schema::create('coupons', function (Blueprint $table): void {
                $table->id();
                $table->string('id_secure', 32)->nullable();
                $table->string('name', 255);
                $table->string('code', 32);
                $table->unsignedTinyInteger('type')->default(1);
                $table->decimal('discount', 12, 2)->default(0);
                $table->unsignedBigInteger('start_date')->nullable();
                $table->bigInteger('end_date')->nullable();
                $table->json('plans')->nullable();
                $table->integer('usage_limit')->default(-1);
                $table->unsignedInteger('usage_count')->default(0);
                $table->boolean('status')->default(1);
                $table->unsignedBigInteger('changed')->nullable();
                $table->unsignedBigInteger('created')->nullable();
                $table->unique('code', 'coupons_code_unique');
                $table->unique('id_secure', 'coupons_id_secure_unique');
                $table->index(['status', 'created'], 'coupons_status_created_index');
                $table->index('code', 'coupons_code_index');
            });

            Schema::create('credit_packs', function (Blueprint $table): void {
                $table->id();
                $table->string('name', 255);
                $table->string('slug', 255);
                $table->text('description')->nullable();
                $table->unsignedInteger('credits')->default(0);
                $table->decimal('price', 12, 2)->default(0);
                $table->string('currency', 10)->default('USD');
                $table->string('currency_symbol', 10)->default('$');
                $table->boolean('status')->default(1);
                $table->boolean('featured')->default(0);
                $table->unsignedInteger('sort')->default(100);
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->unique('slug', 'credit_packs_slug_unique');
            });

            Schema::create('credit_topup_ledgers', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('credit_pack_id')->nullable();
                $table->unsignedBigInteger('payment_history_id')->nullable();
                $table->string('type', 40);
                $table->integer('amount')->default(0);
                $table->integer('remaining')->default(0);
                $table->timestamp('expires_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->index('credit_pack_id', 'credit_topup_ledgers_credit_pack_id_foreign');
                $table->index('payment_history_id', 'credit_topup_ledgers_payment_history_id_foreign');
                $table->index(['user_id', 'type'], 'credit_topup_ledgers_user_id_type_index');
                $table->index(['user_id', 'remaining'], 'credit_topup_ledgers_user_id_remaining_index');
                $table->index('expires_at', 'credit_topup_ledgers_expires_at_index');
            });

            Schema::create('credit_usage_logs', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('plan_id')->nullable();
                $table->string('action_key', 120);
                $table->string('feature', 160)->nullable();
                $table->unsignedInteger('amount')->default(0);
                $table->unsignedInteger('unit_cost')->default(0);
                $table->unsignedInteger('quantity')->default(1);
                $table->integer('credits_before')->nullable();
                $table->integer('credits_after')->nullable();
                $table->boolean('is_unlimited')->default(0);
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->index(['user_id', 'created_at'], 'credit_usage_logs_user_id_created_at_index');
                $table->index(['plan_id', 'created_at'], 'credit_usage_logs_plan_id_created_at_index');
                $table->index(['action_key', 'created_at'], 'credit_usage_logs_action_key_created_at_index');
            });

            Schema::create('failed_jobs', function (Blueprint $table): void {
                $table->id();
                $table->string('uuid', 255);
                $table->text('connection');
                $table->text('queue');
                $table->longText('payload');
                $table->longText('exception');
                $table->timestamp('failed_at')->useCurrent();
                $table->unique('uuid', 'failed_jobs_uuid_unique');
            });

            Schema::create('faqs', function (Blueprint $table): void {
                $table->id();
                $table->string('id_secure', 32)->nullable();
                $table->string('slug', 255);
                $table->string('title', 255);
                $table->json('title_translations')->nullable();
                $table->longText('content');
                $table->json('content_translations')->nullable();
                $table->boolean('status')->default(1);
                $table->unsignedBigInteger('changed')->default(0);
                $table->unsignedBigInteger('created')->default(0);
                $table->unique('slug', 'faqs_slug_unique');
                $table->index('id_secure', 'faqs_id_secure_index');
                $table->index('status', 'faqs_status_index');
            });

            Schema::create('files', function (Blueprint $table): void {
                $table->id();
                $table->string('id_secure', 40);
                $table->unsignedBigInteger('owner_user_id');
                $table->unsignedBigInteger('team_id')->nullable();
                $table->unsignedBigInteger('parent_id')->nullable();
                $table->string('disk', 50)->default('public');
                $table->string('name', 255);
                $table->string('path', 2048)->nullable();
                $table->string('mime_type', 255)->nullable();
                $table->string('extension', 20)->nullable();
                $table->string('category', 50)->default('other');
                $table->unsignedBigInteger('size_bytes')->default(0);
                $table->boolean('is_folder')->default(0);
                $table->boolean('is_image')->default(0);
                $table->unsignedInteger('width')->nullable();
                $table->unsignedInteger('height')->nullable();
                $table->text('note')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->unique('id_secure', 'files_id_secure_unique');
                $table->index('parent_id', 'files_parent_id_foreign');
                $table->index(['owner_user_id', 'parent_id'], 'files_owner_user_id_parent_id_index');
                $table->index(['team_id', 'parent_id'], 'files_team_id_parent_id_index');
                $table->index(['is_folder', 'category'], 'files_is_folder_category_index');
                $table->index('team_id', 'files_team_id_index');
            });

            Schema::create('jobs', function (Blueprint $table): void {
                $table->id();
                $table->string('queue', 255);
                $table->longText('payload');
                $table->unsignedTinyInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
                $table->index('queue', 'jobs_queue_index');
            });

            Schema::create('job_batches', function (Blueprint $table): void {
                $table->string('id', 255);
                $table->string('name', 255);
                $table->integer('total_jobs');
                $table->integer('pending_jobs');
                $table->integer('failed_jobs');
                $table->longText('failed_job_ids');
                $table->mediumText('options')->nullable();
                $table->integer('cancelled_at')->nullable();
                $table->integer('created_at');
                $table->integer('finished_at')->nullable();
            });

            Schema::create('languages', function (Blueprint $table): void {
                $table->id();
                $table->string('name', 255);
                $table->string('native_name', 255)->nullable();
                $table->string('code', 10);
                $table->string('icon', 16)->nullable();
                $table->string('direction', 3)->default('ltr');
                $table->boolean('is_default')->default(0);
                $table->boolean('is_active')->default(1);
                $table->boolean('auto_translate')->default(1);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->unique('code', 'languages_code_unique');
            });

            Schema::create('language_translations', function (Blueprint $table): void {
                $table->id();
                $table->string('language_code', 10);
                $table->string('key', 255);
                $table->longText('value')->nullable();
                $table->boolean('is_custom')->default(1);
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->unique(['language_code', 'key'], 'language_translations_language_code_key_unique');
                $table->index('language_code', 'language_translations_language_code_index');
            });

            Schema::create('marketplace_packages', function (Blueprint $table): void {
                $table->id();
                $table->string('id_secure', 32);
                $table->string('package_key', 160);
                $table->string('module_name', 160)->nullable();
                $table->string('title', 255);
                $table->text('description')->nullable();
                $table->string('version', 60)->nullable();
                $table->string('source_type', 40)->default('zip');
                $table->unsignedBigInteger('product_id')->nullable();
                $table->string('purchase_code', 191)->nullable();
                $table->string('product_slug', 191)->nullable();
                $table->string('license_type', 100)->nullable();
                $table->string('licensed_domain', 191)->nullable();
                $table->string('install_path', 500)->nullable();
                $table->json('providers')->nullable();
                $table->json('meta')->nullable();
                $table->boolean('is_active')->default(1);
                $table->timestamp('installed_at')->nullable();
                $table->timestamp('last_synced_at')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->unique('id_secure', 'marketplace_packages_id_secure_unique');
                $table->unique('package_key', 'marketplace_packages_package_key_unique');
                $table->index('module_name', 'marketplace_packages_module_name_index');
                $table->index('product_id', 'marketplace_packages_product_id_index');
                $table->index('purchase_code', 'marketplace_packages_purchase_code_index');
                $table->index('is_active', 'marketplace_packages_is_active_index');
            });

            Schema::create('notifications', function (Blueprint $table): void {
                $table->id();
                $table->string('id_secure', 32)->nullable();
                $table->unsignedBigInteger('user_id');
                $table->string('source', 20)->default('auto');
                $table->unsignedBigInteger('mid')->nullable();
                $table->string('type', 50)->default('news');
                $table->string('title', 255)->nullable();
                $table->text('message')->nullable();
                $table->string('url', 255)->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->index('user_id', 'notifications_user_id_index');
                $table->index('mid', 'notifications_mid_index');
                $table->index(['user_id', 'read_at'], 'notifications_user_id_read_at_index');
                $table->index('id_secure', 'notifications_id_secure_index');
            });

            Schema::create('notification_manual', function (Blueprint $table): void {
                $table->id();
                $table->string('id_secure', 32)->nullable();
                $table->string('title', 255)->nullable();
                $table->text('message');
                $table->string('url', 255)->nullable();
                $table->string('type', 50)->default('news');
                $table->boolean('is_global')->default(0);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->index('created_by', 'notification_manual_created_by_index');
                $table->index('id_secure', 'notification_manual_id_secure_index');
            });

            Schema::create('notification_manual_states', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('notification_manual_id');
                $table->unsignedBigInteger('user_id');
                $table->timestamp('read_at')->nullable();
                $table->timestamp('archived_at')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->unique(['notification_manual_id', 'user_id'], 'notif_manual_user_unique');
                $table->index(['user_id', 'archived_at'], 'notification_manual_states_user_id_archived_at_index');
            });

            Schema::create('options', function (Blueprint $table): void {
                $table->id();
                $table->string('name', 255);
                $table->longText('value')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->unique('name', 'options_name_unique');
            });

            Schema::create('password_reset_tokens', function (Blueprint $table): void {
                $table->string('email', 255);
                $table->string('token', 255);
                $table->timestamp('created_at')->nullable();
                $table->primary('email');
            });

            Schema::create('payment_history', function (Blueprint $table): void {
                $table->id();
                $table->string('id_secure', 255);
                $table->unsignedBigInteger('uid')->nullable();
                $table->unsignedBigInteger('plan_id')->nullable();
                $table->string('from', 120)->nullable();
                $table->string('transaction_id', 255);
                $table->string('currency', 10)->default('USD');
                $table->string('by', 120)->nullable();
                $table->decimal('amount', 12, 2)->default(0);
                $table->unsignedTinyInteger('status')->default(1);
                $table->unsignedBigInteger('changed')->nullable();
                $table->unsignedBigInteger('created')->nullable();
                $table->json('meta')->nullable();
                $table->unique('id_secure', 'payment_history_id_secure_unique');
                $table->unique('transaction_id', 'payment_history_transaction_id_unique');
                $table->index('uid', 'payment_history_uid_foreign');
                $table->index('plan_id', 'payment_history_plan_id_foreign');
                $table->index(['status', 'created'], 'payment_history_status_created_index');
                $table->index('from', 'payment_history_from_index');
            });

            Schema::create('payment_manual', function (Blueprint $table): void {
                $table->id();
                $table->string('id_secure', 64)->nullable();
                $table->unsignedBigInteger('uid')->nullable();
                $table->unsignedBigInteger('plan_id')->nullable();
                $table->string('payment_id', 190);
                $table->string('payment_info', 2000)->nullable();
                $table->decimal('amount', 12, 2)->default(0);
                $table->string('currency', 10)->default('USD');
                $table->text('notes')->nullable();
                $table->unsignedTinyInteger('status')->default(0);
                $table->unsignedBigInteger('created')->nullable();
                $table->unsignedBigInteger('changed')->nullable();
                $table->index('uid', 'payment_manual_uid_foreign');
                $table->index('plan_id', 'payment_manual_plan_id_foreign');
                $table->index(['status', 'created'], 'payment_manual_status_created_index');
                $table->index('id_secure', 'payment_manual_id_secure_index');
                $table->index('payment_id', 'payment_manual_payment_id_index');
            });

            Schema::create('payment_subscriptions', function (Blueprint $table): void {
                $table->id();
                $table->string('id_secure', 64)->nullable();
                $table->unsignedBigInteger('uid')->nullable();
                $table->unsignedBigInteger('plan_id')->nullable();
                $table->unsignedTinyInteger('type')->nullable();
                $table->string('service', 120)->nullable();
                $table->string('source', 120)->nullable();
                $table->string('subscription_id', 255)->nullable();
                $table->string('customer_id', 255)->nullable();
                $table->decimal('amount', 12, 2)->default(0);
                $table->string('currency', 10)->nullable();
                $table->unsignedTinyInteger('status')->default(1);
                $table->unsignedBigInteger('changed')->nullable();
                $table->unsignedBigInteger('created')->nullable();
                $table->index('uid', 'payment_subscriptions_uid_foreign');
                $table->index('plan_id', 'payment_subscriptions_plan_id_foreign');
                $table->index(['status', 'created'], 'payment_subscriptions_status_created_index');
                $table->index('source', 'payment_subscriptions_source_index');
                $table->index('id_secure', 'payment_subscriptions_id_secure_index');
                $table->index('subscription_id', 'payment_subscriptions_subscription_id_index');
                $table->index('customer_id', 'payment_subscriptions_customer_id_index');
            });

            Schema::create('plans', function (Blueprint $table): void {
                $table->id();
                $table->string('name', 255);
                $table->string('slug', 255);
                $table->string('status', 20)->default('active');
                $table->boolean('featured')->default(0);
                $table->string('currency', 10)->default('USD');
                $table->decimal('price', 12, 2)->default(0);
                $table->unsignedTinyInteger('type')->default(1);
                $table->boolean('free_plan')->default(0);
                $table->boolean('default_signup_plan')->default(0);
                $table->unsignedInteger('trial_day')->default(0);
                $table->unsignedInteger('position')->default(0);
                $table->text('desc')->nullable();
                $table->json('permissions')->nullable();
                $table->decimal('monthly_price', 12, 2)->default(0);
                $table->decimal('yearly_price', 12, 2)->default(0);
                $table->unsignedInteger('sort_order')->default(0);
                $table->text('description')->nullable();
                $table->json('features')->nullable();
                $table->json('limits')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->unique('slug', 'admin_plans_slug_unique');
            });

            Schema::create('posts', function (Blueprint $table): void {
                $table->id();
                $table->string('id_secure', 32)->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('team_id')->nullable();
                $table->unsignedBigInteger('campaign')->nullable();
                $table->json('labels')->nullable();
                $table->unsignedBigInteger('account_id')->nullable();
                $table->string('social_network', 100)->nullable();
                $table->string('category', 120)->nullable();
                $table->string('module', 120)->nullable();
                $table->string('function', 50)->nullable();
                $table->unsignedTinyInteger('api_type')->nullable()->default(1);
                $table->string('type', 20)->nullable();
                $table->string('method', 15)->default('basic');
                $table->unsignedBigInteger('query_id')->nullable();
                $table->longText('data')->nullable();
                $table->integer('time_post')->nullable();
                $table->integer('delay')->default(0);
                $table->integer('repost_frequency')->default(0);
                $table->integer('repost_until')->nullable();
                $table->longText('result')->nullable();
                $table->string('tmp', 500)->nullable();
                $table->text('custom_data_1')->nullable();
                $table->text('custom_data_2')->nullable();
                $table->text('custom_data_3')->nullable();
                $table->integer('status')->nullable();
                $table->integer('changed')->nullable();
                $table->integer('created')->nullable();
                $table->unique('id_secure', 'posts_id_secure_unique');
                $table->index('user_id', 'posts_user_id_foreign');
                $table->index('account_id', 'posts_account_id_foreign');
                $table->index('social_network', 'posts_social_network_index');
                $table->index('function', 'posts_function_index');
                $table->index('time_post', 'posts_time_post_index');
                $table->index('status', 'posts_status_index');
                $table->index('team_id', 'posts_team_id_index');
            });

            Schema::create('publishing_campaigns', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('owner_user_id');
                $table->string('name', 120);
                $table->string('slug', 160);
                $table->string('status', 24)->default('active');
                $table->string('color', 24)->default('#2563eb');
                $table->date('starts_on')->nullable();
                $table->date('ends_on')->nullable();
                $table->text('description')->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->unique(['owner_user_id', 'slug'], 'publishing_campaigns_owner_user_id_slug_unique');
                $table->index(['owner_user_id', 'status'], 'publishing_campaigns_owner_user_id_status_index');
            });

            Schema::create('publishing_captions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('owner_user_id');
                $table->unsignedBigInteger('team_id')->nullable();
                $table->string('name', 255);
                $table->string('slug', 255);
                $table->string('source_type', 20)->default('manual');
                $table->string('status', 32)->default('active');
                $table->longText('content');
                $table->text('notes')->nullable();
                $table->json('tags')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->unique(['owner_user_id', 'slug'], 'publishing_captions_owner_user_id_slug_unique');
                $table->index(['owner_user_id', 'status'], 'publishing_captions_owner_user_id_status_index');
                $table->index(['owner_user_id', 'source_type'], 'publishing_captions_owner_user_id_source_type_index');
                $table->index('team_id', 'publishing_captions_team_id_index');
            });

            Schema::create('publishing_labels', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('owner_user_id');
                $table->string('name', 80);
                $table->string('slug', 120);
                $table->string('status', 24)->default('active');
                $table->string('color', 24)->default('#2563eb');
                $table->text('description')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->unique(['owner_user_id', 'slug'], 'publishing_labels_owner_user_id_slug_unique');
                $table->index(['owner_user_id', 'status'], 'publishing_labels_owner_user_id_status_index');
            });

            Schema::create('publishing_watermarks', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('owner_user_id');
                $table->unsignedBigInteger('team_id')->nullable();
                $table->unsignedBigInteger('file_id')->nullable();
                $table->string('name', 255);
                $table->string('slug', 255);
                $table->string('type', 20)->default('image');
                $table->string('status', 32)->default('active');
                $table->boolean('is_global')->default(0);
                $table->text('text')->nullable();
                $table->string('position', 32)->default('bottom-right');
                $table->unsignedTinyInteger('opacity_percent')->default(72);
                $table->unsignedTinyInteger('scale_percent')->default(24);
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->unique(['owner_user_id', 'slug'], 'pub_wm_owner_slug_uq');
                $table->index('file_id', 'publishing_watermarks_file_id_foreign');
                $table->index(['owner_user_id', 'status'], 'publishing_watermarks_owner_user_id_status_index');
                $table->index('team_id', 'publishing_watermarks_team_id_index');
            });

            Schema::create('publishing_watermark_social_account', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('watermark_id');
                $table->unsignedBigInteger('social_account_id');
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->unique(['watermark_id', 'social_account_id'], 'pub_wm_account_uq');
                $table->index('social_account_id', 'publishing_watermark_social_account_social_account_id_foreign');
            });

            Schema::create('rss_schedules', function (Blueprint $table): void {
                $table->id();
                $table->string('id_secure', 32);
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('team_id')->nullable();
                $table->string('name', 255);
                $table->string('feed_url', 2000);
                $table->text('description')->nullable();
                $table->json('account_ids');
                $table->json('settings')->nullable();
                $table->json('time_posts');
                $table->json('weekdays');
                $table->unsignedInteger('start_at')->nullable();
                $table->unsignedInteger('end_at')->nullable();
                $table->unsignedInteger('last_checked_at')->nullable();
                $table->unsignedInteger('last_queued_at')->nullable();
                $table->unsignedInteger('next_run_at')->nullable();
                $table->boolean('status')->default(1);
                $table->unsignedInteger('changed')->nullable();
                $table->unsignedInteger('created')->nullable();
                $table->unique('id_secure', 'rss_schedules_id_secure_unique');
                $table->index(['user_id', 'status'], 'rss_schedules_user_id_status_index');
                $table->index(['team_id', 'status'], 'rss_schedules_team_id_status_index');
                $table->index(['next_run_at', 'status'], 'rss_schedules_next_run_at_status_index');
            });

            Schema::create('rss_schedule_histories', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('schedule_id');
                $table->unsignedBigInteger('account_id');
                $table->unsignedBigInteger('publishing_post_id')->nullable();
                $table->string('external_guid', 255)->nullable();
                $table->string('external_url', 2000)->nullable();
                $table->string('content_hash', 64);
                $table->string('title', 500)->nullable();
                $table->unsignedInteger('queued_at')->nullable();
                $table->unsignedInteger('published_at')->nullable();
                $table->unsignedInteger('changed')->nullable();
                $table->unsignedInteger('created')->nullable();
                $table->unique(['schedule_id', 'account_id', 'content_hash'], 'rss_schedule_histories_unique_item');
                $table->index('account_id', 'rss_schedule_histories_account_id_foreign');
                $table->index('publishing_post_id', 'rss_schedule_histories_publishing_post_id_foreign');
            });

            Schema::create('sessions', function (Blueprint $table): void {
                $table->string('id', 255);
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->longText('payload');
                $table->integer('last_activity');
                $table->index('user_id', 'sessions_user_id_index');
                $table->index('last_activity', 'sessions_last_activity_index');
            });

            Schema::create('social_accounts', function (Blueprint $table): void {
                $table->id();
                $table->string('provider_key', 80);
                $table->string('capability_key', 120)->nullable();
                $table->string('display_name', 255);
                $table->string('username', 255)->nullable();
                $table->string('external_id', 255)->nullable();
                $table->string('category', 80)->nullable();
                $table->string('account_type', 50)->default('manual');
                $table->text('profile_url')->nullable();
                $table->text('avatar_url')->nullable();
                $table->string('avatar_disk', 40)->nullable();
                $table->text('avatar_path')->nullable();
                $table->text('reconnect_url')->nullable();
                $table->text('access_token')->nullable();
                $table->text('refresh_token')->nullable();
                $table->text('scopes')->nullable();
                $table->json('auth_data')->nullable();
                $table->json('metadata')->nullable();
                $table->text('notes')->nullable();
                $table->boolean('is_active')->default(1);
                $table->unsignedBigInteger('created_by_user_id')->nullable();
                $table->timestamp('connected_at')->nullable();
                $table->timestamp('last_synced_at')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->index('created_by_user_id', 'social_accounts_created_by_user_id_foreign');
                $table->index('provider_key', 'social_accounts_provider_key_index');
                $table->index('username', 'social_accounts_username_index');
                $table->index('external_id', 'social_accounts_external_id_index');
                $table->index('is_active', 'social_accounts_is_active_index');
                $table->index('capability_key', 'social_accounts_capability_key_index');
            });

            Schema::create('support_categories', function (Blueprint $table): void {
                $table->id();
                $table->string('id_secure', 40);
                $table->string('name', 255);
                $table->string('icon', 120)->default('fa-light');
                $table->string('color', 40)->default('#2563eb');
                $table->boolean('status')->default(1);
                $table->unsignedInteger('changed')->nullable();
                $table->unsignedInteger('created')->nullable();
                $table->unique('id_secure', 'support_categories_id_secure_unique');
            });

            Schema::create('support_comments', function (Blueprint $table): void {
                $table->id();
                $table->string('id_secure', 40);
                $table->unsignedBigInteger('ticket_id');
                $table->unsignedBigInteger('user_id');
                $table->text('comment');
                $table->unsignedInteger('changed')->nullable();
                $table->unsignedInteger('created')->nullable();
                $table->unique('id_secure', 'support_comments_id_secure_unique');
                $table->index('ticket_id', 'support_comments_ticket_id_foreign');
                $table->index('user_id', 'support_comments_user_id_foreign');
            });

            Schema::create('support_labels', function (Blueprint $table): void {
                $table->id();
                $table->string('id_secure', 40);
                $table->string('name', 255);
                $table->string('icon', 120)->default('fa-light');
                $table->string('color', 40)->default('#475569');
                $table->boolean('status')->default(1);
                $table->unsignedInteger('changed')->nullable();
                $table->unsignedInteger('created')->nullable();
                $table->unique('id_secure', 'support_labels_id_secure_unique');
            });

            Schema::create('support_map_labels', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('ticket_id');
                $table->unsignedBigInteger('label_id');
                $table->unique(['ticket_id', 'label_id'], 'support_map_labels_ticket_id_label_id_unique');
                $table->index('label_id', 'support_map_labels_label_id_foreign');
            });

            Schema::create('support_tickets', function (Blueprint $table): void {
                $table->id();
                $table->string('id_secure', 40);
                $table->unsignedBigInteger('uid');
                $table->unsignedBigInteger('open_by');
                $table->unsignedBigInteger('team_id')->nullable();
                $table->unsignedBigInteger('cate_id')->nullable();
                $table->unsignedBigInteger('type_id')->nullable();
                $table->string('title', 255);
                $table->text('content');
                $table->unsignedTinyInteger('status')->default(1);
                $table->boolean('pin')->default(0);
                $table->boolean('user_read')->default(0);
                $table->boolean('admin_read')->default(1);
                $table->unsignedInteger('changed')->nullable();
                $table->unsignedInteger('created')->nullable();
                $table->unique('id_secure', 'support_tickets_id_secure_unique');
                $table->index('uid', 'support_tickets_uid_foreign');
                $table->index('open_by', 'support_tickets_open_by_foreign');
                $table->index('cate_id', 'support_tickets_cate_id_foreign');
                $table->index('type_id', 'support_tickets_type_id_foreign');
                $table->index('team_id', 'support_tickets_team_id_index');
            });

            Schema::create('support_types', function (Blueprint $table): void {
                $table->id();
                $table->string('id_secure', 40);
                $table->string('name', 255);
                $table->string('icon', 120)->default('fa-light');
                $table->string('color', 40)->default('#2563eb');
                $table->boolean('status')->default(1);
                $table->unsignedInteger('changed')->nullable();
                $table->unsignedInteger('created')->nullable();
                $table->unique('id_secure', 'support_types_id_secure_unique');
            });

            Schema::create('teams', function (Blueprint $table): void {
                $table->id();
                $table->string('name', 255);
                $table->string('slug', 255);
                $table->text('description')->nullable();
                $table->json('enabled_modules')->nullable();
                $table->unsignedBigInteger('owner_user_id')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->unique('slug', 'teams_slug_unique');
                $table->unique('owner_user_id', 'teams_owner_user_id_unique');
            });

            Schema::create('team_conversations', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('team_id');
                $table->unsignedBigInteger('created_by_user_id')->nullable();
                $table->string('type', 50)->default('room');
                $table->string('title', 255)->nullable();
                $table->text('description')->nullable();
                $table->timestamp('last_message_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->index('created_by_user_id', 'team_conversations_created_by_user_id_foreign');
                $table->index(['team_id', 'last_message_at'], 'team_conversations_team_id_last_message_at_index');
                $table->index('team_id', 'team_conversations_team_id_index');
            });

            Schema::create('team_conversation_participants', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('conversation_id');
                $table->unsignedBigInteger('user_id');
                $table->string('role', 50)->default('member');
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->unique(['conversation_id', 'user_id'], 'team_conversation_participants_conversation_id_user_id_unique');
                $table->index('user_id', 'team_conversation_participants_user_id_foreign');
            });

            Schema::create('team_invitations', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('team_id');
                $table->unsignedBigInteger('invited_by_user_id')->nullable();
                $table->unsignedBigInteger('accepted_by_user_id')->nullable();
                $table->string('email', 255)->nullable();
                $table->string('invite_code', 24);
                $table->string('role', 50)->default('member');
                $table->json('permissions')->nullable();
                $table->string('status', 50)->default('pending');
                $table->text('message')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('accepted_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->unique('invite_code', 'team_invitations_invite_code_unique');
                $table->index('invited_by_user_id', 'team_invitations_invited_by_user_id_foreign');
                $table->index('accepted_by_user_id', 'team_invitations_accepted_by_user_id_foreign');
                $table->index(['team_id', 'status'], 'team_invitations_team_id_status_index');
                $table->index('team_id', 'team_invitations_team_id_index');
            });

            Schema::create('team_messages', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('conversation_id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->longText('body');
                $table->json('attachments')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->index('user_id', 'team_messages_user_id_foreign');
                $table->index(['conversation_id', 'created_at'], 'team_messages_conversation_id_created_at_index');
            });

            Schema::create('team_post_comments', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('team_id');
                $table->unsignedBigInteger('post_id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->longText('message');
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->index('post_id', 'team_post_comments_post_id_foreign');
                $table->index('user_id', 'team_post_comments_user_id_foreign');
                $table->index(['team_id', 'post_id'], 'team_post_comments_team_id_post_id_index');
                $table->index('team_id', 'team_post_comments_team_id_index');
            });

            Schema::create('team_post_reviews', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('team_id');
                $table->unsignedBigInteger('post_id');
                $table->unsignedBigInteger('submitted_by_user_id')->nullable();
                $table->unsignedBigInteger('decided_by_user_id')->nullable();
                $table->string('status', 50)->default('pending');
                $table->text('decision_note')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('decided_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->index('submitted_by_user_id', 'team_post_reviews_submitted_by_user_id_foreign');
                $table->index('decided_by_user_id', 'team_post_reviews_decided_by_user_id_foreign');
                $table->index(['team_id', 'status'], 'team_post_reviews_team_id_status_index');
                $table->index(['post_id', 'status'], 'team_post_reviews_post_id_status_index');
                $table->index('team_id', 'team_post_reviews_team_id_index');
            });

            Schema::create('team_user', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('team_id');
                $table->unsignedBigInteger('user_id');
                $table->string('role', 50)->default('member');
                $table->json('permissions')->nullable();
                $table->json('managed_account_ids')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->unique(['team_id', 'user_id'], 'team_user_team_id_user_id_unique');
                $table->index('user_id', 'team_user_user_id_index');
                $table->index('team_id', 'team_user_team_id_index');
            });

            Schema::create('users', function (Blueprint $table): void {
                $table->id();
                $table->string('name', 255);
                $table->string('username', 255)->nullable();
                $table->string('email', 255);
                $table->string('avatar_path', 255)->nullable();
                $table->string('avatar_disk', 40)->nullable();
                $table->string('locale', 10)->nullable();
                $table->string('timezone', 100)->nullable();
                $table->unsignedBigInteger('role_id')->nullable();
                $table->boolean('is_super_admin')->default(0);
                $table->unsignedBigInteger('plan_id')->nullable();
                $table->unsignedBigInteger('next_plan_id')->nullable();
                $table->timestamp('plan_started_at')->nullable();
                $table->timestamp('plan_expires_at')->nullable();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password', 255);
                $table->text('two_factor_secret')->nullable();
                $table->text('two_factor_recovery_codes')->nullable();
                $table->timestamp('two_factor_confirmed_at')->nullable();
                $table->string('remember_token', 100)->nullable();
                $table->string('referral_code', 20)->nullable();
                $table->unsignedBigInteger('referred_by_user_id')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->unique('email', 'users_email_unique');
                $table->unique('username', 'users_username_unique');
                $table->unique('referral_code', 'users_referral_code_unique');
                $table->index('role_id', 'users_role_id_foreign');
                $table->index('plan_id', 'users_plan_id_foreign');
                $table->index('referred_by_user_id', 'users_referred_by_user_id_foreign');
                $table->index('next_plan_id', 'users_next_plan_id_foreign');
            });

            Schema::table('account_groups', function (Blueprint $table): void {
                $table->foreign('owner_user_id', 'account_groups_owner_user_id_foreign')->references('id')->on('users')->cascadeOnDelete();
            });

            Schema::table('account_group_social_account', function (Blueprint $table): void {
                $table->foreign('group_id', 'account_group_social_account_group_id_foreign')->references('id')->on('account_groups')->cascadeOnDelete();
                $table->foreign('social_account_id', 'account_group_social_account_social_account_id_foreign')->references('id')->on('social_accounts')->cascadeOnDelete();
            });

            Schema::table('affiliate_commissions', function (Blueprint $table): void {
                $table->foreign('affiliate_user_id', 'affiliate_commissions_affiliate_user_id_foreign')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('payment_history_id', 'affiliate_commissions_payment_history_id_foreign')->references('id')->on('payment_history')->nullOnDelete();
                $table->foreign('referred_user_id', 'affiliate_commissions_referred_user_id_foreign')->references('id')->on('users')->nullOnDelete();
            });

            Schema::table('affiliate_profiles', function (Blueprint $table): void {
                $table->foreign('user_id', 'affiliate_profiles_user_id_foreign')->references('id')->on('users')->cascadeOnDelete();
            });

            Schema::table('affiliate_withdrawals', function (Blueprint $table): void {
                $table->foreign('affiliate_user_id', 'affiliate_withdrawals_affiliate_user_id_foreign')->references('id')->on('users')->cascadeOnDelete();
            });

            Schema::table('ai_publishing_prompts', function (Blueprint $table): void {
                $table->foreign('owner_user_id', 'ai_publishing_prompts_owner_user_id_foreign')->references('id')->on('users')->cascadeOnDelete();
            });

            Schema::table('ai_publishing_runs', function (Blueprint $table): void {
                $table->foreign('owner_user_id', 'ai_publishing_runs_owner_user_id_foreign')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('team_id', 'ai_publishing_runs_team_id_foreign')->references('id')->on('teams')->nullOnDelete();
            });

            Schema::table('ai_studio_user_settings', function (Blueprint $table): void {
                $table->foreign('user_id', 'ai_studio_user_settings_user_id_foreign')->references('id')->on('users')->cascadeOnDelete();
            });

            Schema::table('ai_studio_workspace_settings', function (Blueprint $table): void {
                $table->foreign('owner_user_id', 'ai_studio_workspace_settings_owner_user_id_foreign')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('team_id', 'ai_studio_workspace_settings_team_id_foreign')->references('id')->on('teams')->nullOnDelete();
            });

            Schema::table('ai_templates', function (Blueprint $table): void {
                $table->foreign('cate_id', 'ai_templates_cate_id_foreign')->references('id')->on('ai_template_categories')->nullOnDelete();
            });

            Schema::table('ai_usage_logs', function (Blueprint $table): void {
                $table->foreign('user_id', 'ai_usage_logs_user_id_foreign')->references('id')->on('users')->nullOnDelete();
            });

            Schema::table('ai_video_jobs', function (Blueprint $table): void {
                $table->foreign('file_id', 'ai_video_jobs_file_id_foreign')->references('id')->on('files')->nullOnDelete();
                $table->foreign('owner_user_id', 'ai_video_jobs_owner_user_id_foreign')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('requested_by_user_id', 'ai_video_jobs_requested_by_user_id_foreign')->references('id')->on('users')->nullOnDelete();
                $table->foreign('team_id', 'ai_video_jobs_team_id_foreign')->references('id')->on('teams')->nullOnDelete();
            });

            Schema::table('audit_logs', function (Blueprint $table): void {
                $table->foreign('causer_user_id', 'audit_logs_causer_user_id_foreign')->references('id')->on('users')->nullOnDelete();
            });

            Schema::table('automation_api_keys', function (Blueprint $table): void {
                $table->foreign('team_id', 'automation_api_keys_team_id_foreign')->references('id')->on('teams')->nullOnDelete();
                $table->foreign('user_id', 'automation_api_keys_user_id_foreign')->references('id')->on('users')->cascadeOnDelete();
            });

            Schema::table('automation_logs', function (Blueprint $table): void {
                $table->foreign('api_key_id', 'automation_logs_api_key_id_foreign')->references('id')->on('automation_api_keys')->nullOnDelete();
                $table->foreign('team_id', 'automation_logs_team_id_foreign')->references('id')->on('teams')->nullOnDelete();
                $table->foreign('user_id', 'automation_logs_user_id_foreign')->references('id')->on('users')->nullOnDelete();
                $table->foreign('webhook_id', 'automation_logs_webhook_id_foreign')->references('id')->on('automation_webhooks')->nullOnDelete();
            });

            Schema::table('automation_webhooks', function (Blueprint $table): void {
                $table->foreign('team_id', 'automation_webhooks_team_id_foreign')->references('id')->on('teams')->nullOnDelete();
                $table->foreign('user_id', 'automation_webhooks_user_id_foreign')->references('id')->on('users')->cascadeOnDelete();
            });

            Schema::table('blogs', function (Blueprint $table): void {
                $table->foreign('blog_category_id', 'blogs_blog_category_id_foreign')->references('id')->on('blog_categories')->nullOnDelete();
            });

            Schema::table('blog_rss_imports', function (Blueprint $table): void {
                $table->foreign('blog_id', 'blog_rss_imports_blog_id_foreign')->references('id')->on('blogs')->nullOnDelete();
                $table->foreign('blog_rss_source_id', 'blog_rss_imports_blog_rss_source_id_foreign')->references('id')->on('blog_rss_sources')->cascadeOnDelete();
            });

            Schema::table('blog_rss_sources', function (Blueprint $table): void {
                $table->foreign('blog_category_id', 'blog_rss_sources_blog_category_id_foreign')->references('id')->on('blog_categories')->nullOnDelete();
            });

            Schema::table('blog_tag_maps', function (Blueprint $table): void {
                $table->foreign('blog_id', 'blog_tag_maps_blog_id_foreign')->references('id')->on('blogs')->cascadeOnDelete();
                $table->foreign('blog_tag_id', 'blog_tag_maps_blog_tag_id_foreign')->references('id')->on('blog_tags')->cascadeOnDelete();
            });

            Schema::table('bulk_post_batches', function (Blueprint $table): void {
                $table->foreign('owner_user_id', 'bulk_post_batches_owner_user_id_foreign')->references('id')->on('users')->cascadeOnDelete();
            });

            Schema::table('bulk_post_rows', function (Blueprint $table): void {
                $table->foreign('batch_id', 'bulk_post_rows_batch_id_foreign')->references('id')->on('bulk_post_batches')->cascadeOnDelete();
            });

            Schema::table('credit_topup_ledgers', function (Blueprint $table): void {
                $table->foreign('credit_pack_id', 'credit_topup_ledgers_credit_pack_id_foreign')->references('id')->on('credit_packs')->nullOnDelete();
                $table->foreign('payment_history_id', 'credit_topup_ledgers_payment_history_id_foreign')->references('id')->on('payment_history')->nullOnDelete();
                $table->foreign('user_id', 'credit_topup_ledgers_user_id_foreign')->references('id')->on('users')->cascadeOnDelete();
            });

            Schema::table('credit_usage_logs', function (Blueprint $table): void {
                $table->foreign('plan_id', 'credit_usage_logs_plan_id_foreign')->references('id')->on('plans')->nullOnDelete();
                $table->foreign('user_id', 'credit_usage_logs_user_id_foreign')->references('id')->on('users')->nullOnDelete();
            });

            Schema::table('files', function (Blueprint $table): void {
                $table->foreign('owner_user_id', 'files_owner_user_id_foreign')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('parent_id', 'files_parent_id_foreign')->references('id')->on('files')->cascadeOnDelete();
            });

            Schema::table('payment_history', function (Blueprint $table): void {
                $table->foreign('plan_id', 'payment_history_plan_id_foreign')->references('id')->on('plans')->nullOnDelete();
                $table->foreign('uid', 'payment_history_uid_foreign')->references('id')->on('users')->nullOnDelete();
            });

            Schema::table('payment_manual', function (Blueprint $table): void {
                $table->foreign('plan_id', 'payment_manual_plan_id_foreign')->references('id')->on('plans')->nullOnDelete();
                $table->foreign('uid', 'payment_manual_uid_foreign')->references('id')->on('users')->nullOnDelete();
            });

            Schema::table('payment_subscriptions', function (Blueprint $table): void {
                $table->foreign('plan_id', 'payment_subscriptions_plan_id_foreign')->references('id')->on('plans')->nullOnDelete();
                $table->foreign('uid', 'payment_subscriptions_uid_foreign')->references('id')->on('users')->nullOnDelete();
            });

            Schema::table('posts', function (Blueprint $table): void {
                $table->foreign('account_id', 'posts_account_id_foreign')->references('id')->on('social_accounts')->nullOnDelete();
                $table->foreign('user_id', 'posts_user_id_foreign')->references('id')->on('users')->nullOnDelete();
            });

            Schema::table('publishing_campaigns', function (Blueprint $table): void {
                $table->foreign('owner_user_id', 'publishing_campaigns_owner_user_id_foreign')->references('id')->on('users')->cascadeOnDelete();
            });

            Schema::table('publishing_captions', function (Blueprint $table): void {
                $table->foreign('owner_user_id', 'publishing_captions_owner_user_id_foreign')->references('id')->on('users')->cascadeOnDelete();
            });

            Schema::table('publishing_labels', function (Blueprint $table): void {
                $table->foreign('owner_user_id', 'publishing_labels_owner_user_id_foreign')->references('id')->on('users')->cascadeOnDelete();
            });

            Schema::table('publishing_watermarks', function (Blueprint $table): void {
                $table->foreign('file_id', 'publishing_watermarks_file_id_foreign')->references('id')->on('files')->nullOnDelete();
                $table->foreign('owner_user_id', 'publishing_watermarks_owner_user_id_foreign')->references('id')->on('users')->cascadeOnDelete();
            });

            Schema::table('publishing_watermark_social_account', function (Blueprint $table): void {
                $table->foreign('social_account_id', 'publishing_watermark_social_account_social_account_id_foreign')->references('id')->on('social_accounts')->cascadeOnDelete();
                $table->foreign('watermark_id', 'publishing_watermark_social_account_watermark_id_foreign')->references('id')->on('publishing_watermarks')->cascadeOnDelete();
            });

            Schema::table('rss_schedules', function (Blueprint $table): void {
                $table->foreign('team_id', 'rss_schedules_team_id_foreign')->references('id')->on('teams')->nullOnDelete();
                $table->foreign('user_id', 'rss_schedules_user_id_foreign')->references('id')->on('users')->cascadeOnDelete();
            });

            Schema::table('rss_schedule_histories', function (Blueprint $table): void {
                $table->foreign('account_id', 'rss_schedule_histories_account_id_foreign')->references('id')->on('social_accounts')->cascadeOnDelete();
                $table->foreign('publishing_post_id', 'rss_schedule_histories_publishing_post_id_foreign')->references('id')->on('posts')->nullOnDelete();
                $table->foreign('schedule_id', 'rss_schedule_histories_schedule_id_foreign')->references('id')->on('rss_schedules')->cascadeOnDelete();
            });

            Schema::table('social_accounts', function (Blueprint $table): void {
                $table->foreign('created_by_user_id', 'social_accounts_created_by_user_id_foreign')->references('id')->on('users')->nullOnDelete();
            });

            Schema::table('support_comments', function (Blueprint $table): void {
                $table->foreign('ticket_id', 'support_comments_ticket_id_foreign')->references('id')->on('support_tickets')->cascadeOnDelete();
                $table->foreign('user_id', 'support_comments_user_id_foreign')->references('id')->on('users')->cascadeOnDelete();
            });

            Schema::table('support_map_labels', function (Blueprint $table): void {
                $table->foreign('label_id', 'support_map_labels_label_id_foreign')->references('id')->on('support_labels')->cascadeOnDelete();
                $table->foreign('ticket_id', 'support_map_labels_ticket_id_foreign')->references('id')->on('support_tickets')->cascadeOnDelete();
            });

            Schema::table('support_tickets', function (Blueprint $table): void {
                $table->foreign('cate_id', 'support_tickets_cate_id_foreign')->references('id')->on('support_categories')->nullOnDelete();
                $table->foreign('open_by', 'support_tickets_open_by_foreign')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('type_id', 'support_tickets_type_id_foreign')->references('id')->on('support_types')->nullOnDelete();
                $table->foreign('uid', 'support_tickets_uid_foreign')->references('id')->on('users')->cascadeOnDelete();
            });

            Schema::table('teams', function (Blueprint $table): void {
                $table->foreign('owner_user_id', 'teams_owner_user_id_foreign')->references('id')->on('users')->nullOnDelete();
            });

            Schema::table('team_conversations', function (Blueprint $table): void {
                $table->foreign('created_by_user_id', 'team_conversations_created_by_user_id_foreign')->references('id')->on('users')->nullOnDelete();
            });

            Schema::table('team_conversation_participants', function (Blueprint $table): void {
                $table->foreign('conversation_id', 'team_conversation_participants_conversation_id_foreign')->references('id')->on('team_conversations')->cascadeOnDelete();
                $table->foreign('user_id', 'team_conversation_participants_user_id_foreign')->references('id')->on('users')->cascadeOnDelete();
            });

            Schema::table('team_invitations', function (Blueprint $table): void {
                $table->foreign('accepted_by_user_id', 'team_invitations_accepted_by_user_id_foreign')->references('id')->on('users')->nullOnDelete();
                $table->foreign('invited_by_user_id', 'team_invitations_invited_by_user_id_foreign')->references('id')->on('users')->nullOnDelete();
            });

            Schema::table('team_messages', function (Blueprint $table): void {
                $table->foreign('conversation_id', 'team_messages_conversation_id_foreign')->references('id')->on('team_conversations')->cascadeOnDelete();
                $table->foreign('user_id', 'team_messages_user_id_foreign')->references('id')->on('users')->nullOnDelete();
            });

            Schema::table('team_post_comments', function (Blueprint $table): void {
                $table->foreign('post_id', 'team_post_comments_post_id_foreign')->references('id')->on('posts')->cascadeOnDelete();
                $table->foreign('user_id', 'team_post_comments_user_id_foreign')->references('id')->on('users')->nullOnDelete();
            });

            Schema::table('team_post_reviews', function (Blueprint $table): void {
                $table->foreign('decided_by_user_id', 'team_post_reviews_decided_by_user_id_foreign')->references('id')->on('users')->nullOnDelete();
                $table->foreign('post_id', 'team_post_reviews_post_id_foreign')->references('id')->on('posts')->cascadeOnDelete();
                $table->foreign('submitted_by_user_id', 'team_post_reviews_submitted_by_user_id_foreign')->references('id')->on('users')->nullOnDelete();
            });

            Schema::table('team_user', function (Blueprint $table): void {
                $table->foreign('user_id', 'team_user_user_id_foreign')->references('id')->on('users')->cascadeOnDelete();
            });

            Schema::table('users', function (Blueprint $table): void {
                $table->foreign('next_plan_id', 'users_next_plan_id_foreign')->references('id')->on('plans')->nullOnDelete();
                $table->foreign('plan_id', 'users_plan_id_foreign')->references('id')->on('plans')->nullOnDelete();
                $table->foreign('referred_by_user_id', 'users_referred_by_user_id_foreign')->references('id')->on('users')->nullOnDelete();
                $table->foreign('role_id', 'users_role_id_foreign')->references('id')->on('admin_roles')->nullOnDelete();
            });

        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::dropIfExists('users');
        Schema::dropIfExists('team_user');
        Schema::dropIfExists('team_post_reviews');
        Schema::dropIfExists('team_post_comments');
        Schema::dropIfExists('team_messages');
        Schema::dropIfExists('team_invitations');
        Schema::dropIfExists('team_conversation_participants');
        Schema::dropIfExists('team_conversations');
        Schema::dropIfExists('teams');
        Schema::dropIfExists('support_types');
        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('support_map_labels');
        Schema::dropIfExists('support_labels');
        Schema::dropIfExists('support_comments');
        Schema::dropIfExists('support_categories');
        Schema::dropIfExists('social_accounts');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('rss_schedule_histories');
        Schema::dropIfExists('rss_schedules');
        Schema::dropIfExists('publishing_watermark_social_account');
        Schema::dropIfExists('publishing_watermarks');
        Schema::dropIfExists('publishing_labels');
        Schema::dropIfExists('publishing_captions');
        Schema::dropIfExists('publishing_campaigns');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('plans');
        Schema::dropIfExists('payment_subscriptions');
        Schema::dropIfExists('payment_manual');
        Schema::dropIfExists('payment_history');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('options');
        Schema::dropIfExists('notification_manual_states');
        Schema::dropIfExists('notification_manual');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('marketplace_packages');
        Schema::dropIfExists('language_translations');
        Schema::dropIfExists('languages');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('files');
        Schema::dropIfExists('faqs');
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('credit_usage_logs');
        Schema::dropIfExists('credit_topup_ledgers');
        Schema::dropIfExists('credit_packs');
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
        Schema::dropIfExists('bulk_post_rows');
        Schema::dropIfExists('bulk_post_batches');
        Schema::dropIfExists('blog_tag_maps');
        Schema::dropIfExists('blog_tags');
        Schema::dropIfExists('blog_rss_sources');
        Schema::dropIfExists('blog_rss_imports');
        Schema::dropIfExists('blog_categories');
        Schema::dropIfExists('blogs');
        Schema::dropIfExists('automation_webhooks');
        Schema::dropIfExists('automation_logs');
        Schema::dropIfExists('automation_api_keys');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('ai_video_jobs');
        Schema::dropIfExists('ai_usage_logs');
        Schema::dropIfExists('ai_template_categories');
        Schema::dropIfExists('ai_templates');
        Schema::dropIfExists('ai_studio_workspace_settings');
        Schema::dropIfExists('ai_studio_user_settings');
        Schema::dropIfExists('ai_publishing_runs');
        Schema::dropIfExists('ai_publishing_prompts');
        Schema::dropIfExists('ai_prompt_histories');
        Schema::dropIfExists('ai_image_jobs');
        Schema::dropIfExists('ai_content_plans');
        Schema::dropIfExists('affiliate_withdrawals');
        Schema::dropIfExists('affiliate_profiles');
        Schema::dropIfExists('affiliate_commissions');
        Schema::dropIfExists('admin_roles');
        Schema::dropIfExists('account_group_social_account');
        Schema::dropIfExists('account_groups');

        Schema::enableForeignKeyConstraints();
    }
};
