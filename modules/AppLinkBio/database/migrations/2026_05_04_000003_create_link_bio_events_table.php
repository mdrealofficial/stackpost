<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('link_bio_events')) {
            return;
        }

        Schema::create('link_bio_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('link_bio_page_id')->constrained('link_bio_pages')->cascadeOnDelete();
            $table->string('type', 24)->index();
            $table->unsignedInteger('block_index')->nullable();
            $table->unsignedInteger('item_index')->nullable();
            $table->text('url')->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index(['link_bio_page_id', 'type']);
            $table->index(['link_bio_page_id', 'block_index', 'item_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('link_bio_events');
    }
};
