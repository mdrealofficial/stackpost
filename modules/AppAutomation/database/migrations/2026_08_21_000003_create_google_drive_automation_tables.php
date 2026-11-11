<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('automation_connections')) {
            Schema::table('automation_connections', function (Blueprint $table): void {
                if (! Schema::hasColumn('automation_connections', 'external_account_id')) {
                    $table->string('external_account_id', 191)->nullable()->after('provider');
                    $table->string('external_account_email', 320)->nullable()->after('external_account_id');
                    $table->timestamp('disconnected_at')->nullable()->after('last_verified_at');
                    $table->index(
                        ['owner_user_id', 'team_id', 'provider', 'external_account_id'],
                        'automation_connections_external_account_index'
                    );
                }
            });
        }

        if (! Schema::hasTable('automation_drive_channels')) {
            Schema::create('automation_drive_channels', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('connection_id')->unique();
                $table->string('channel_id', 191)->unique();
                $table->string('resource_id', 255)->nullable()->index();
                $table->text('resource_uri')->nullable();
                $table->string('token_hash', 64);
                $table->text('page_token');
                $table->string('status', 32)->default('pending');
                $table->unsignedBigInteger('last_message_number')->nullable();
                $table->timestamp('expires_at')->nullable()->index();
                $table->timestamp('last_notification_at')->nullable();
                $table->timestamp('last_polled_at')->nullable();
                $table->timestamp('last_renewed_at')->nullable();
                $table->text('last_error')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['status', 'expires_at'], 'automation_drive_channels_status_expiry_index');
            });
        }

        if (! Schema::hasTable('automation_drive_folder_watches')) {
            Schema::create('automation_drive_folder_watches', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('connection_id');
                $table->unsignedBigInteger('workflow_id');
                $table->string('node_id', 100);
                $table->string('folder_id', 255);
                $table->json('accepted_file_types')->nullable();
                $table->string('status', 24)->default('active');
                $table->timestamp('last_matched_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['workflow_id', 'node_id'], 'automation_drive_folder_watches_workflow_node_unique');
                $table->index(['connection_id', 'folder_id', 'status'], 'automation_drive_folder_watches_folder_index');
            });
        }

        if (! Schema::hasTable('automation_drive_file_events')) {
            Schema::create('automation_drive_file_events', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('watch_id');
                $table->unsignedBigInteger('connection_id');
                $table->string('file_id', 255);
                $table->string('external_event_id', 191)->unique();
                $table->string('name', 500)->nullable();
                $table->string('mime_type', 191)->nullable();
                $table->timestamp('source_modified_at')->nullable();
                $table->json('payload')->nullable();
                $table->timestamp('dispatched_at')->nullable();
                $table->timestamps();

                $table->unique(['watch_id', 'file_id'], 'automation_drive_file_events_watch_file_unique');
                $table->index(['connection_id', 'created_at'], 'automation_drive_file_events_connection_created_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_drive_file_events');
        Schema::dropIfExists('automation_drive_folder_watches');
        Schema::dropIfExists('automation_drive_channels');

        if (Schema::hasTable('automation_connections') && Schema::hasColumn('automation_connections', 'external_account_id')) {
            Schema::table('automation_connections', function (Blueprint $table): void {
                $table->dropIndex('automation_connections_external_account_index');
                $table->dropColumn(['external_account_id', 'external_account_email', 'disconnected_at']);
            });
        }
    }
};
