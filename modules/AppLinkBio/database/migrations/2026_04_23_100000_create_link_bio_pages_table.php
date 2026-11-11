<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('link_bio_pages')) {
            return;
        }

        Schema::create('link_bio_pages', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('owner_user_id')->index();
            $table->unsignedBigInteger('team_id')->nullable()->index();
            $table->string('title', 160);
            $table->string('slug', 160)->unique();
            $table->string('headline', 190)->nullable();
            $table->text('description')->nullable();
            $table->string('accent_color', 24)->nullable();
            $table->text('avatar_url')->nullable();
            $table->text('cover_url')->nullable();
            $table->string('template_key', 60)->default('aurora');
            $table->string('status', 30)->default('draft')->index();
            $table->boolean('is_published')->default(false)->index();
            $table->json('blocks')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('link_bio_pages');
    }
};
