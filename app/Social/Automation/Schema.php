<?php

namespace App\Social\Automation;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema as DB;

class Schema
{
    public static function create(string $key): void
    {
        DB::create($key.'_rules', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('owner_user_id')->index();
            $t->unsignedBigInteger('team_id')->nullable()->index();
            $t->unsignedBigInteger('created_by_user_id');
            $t->string('name', 120);
            $t->boolean('active')->default(false);
            $t->timestamp('activated_at')->nullable();
            $t->integer('priority')->default(10);
            $t->json('settings');
            $t->timestamps();
        });
        DB::create($key.'_runs', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('rule_id')->nullable()->index();
            $t->unsignedBigInteger('owner_user_id')->index();
            $t->unsignedBigInteger('team_id')->nullable()->index();
            $t->unsignedBigInteger('social_account_id')->index();
            $t->unsignedBigInteger('inbound_message_id')->unique();
            $t->string('status', 32)->default('pending')->index();
            $t->string('reason', 240)->nullable();
            $t->json('snapshot');
            $t->text('reply')->nullable();
            $t->unsignedBigInteger('outbound_message_id')->nullable();
            $t->unsignedBigInteger('reviewed_by_user_id')->nullable();
            $t->timestamp('started_at')->nullable();
            $t->timestamp('sent_at')->nullable()->index();
            $t->timestamp('reserved_at')->nullable();
            $t->timestamp('handoff_resolved_at')->nullable();
            $t->timestamps();
        });
    }
}
