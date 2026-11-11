<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->replaceTeamForeign('automation_api_keys', 'automation_api_keys_team_id_foreign', cascade: true);
        $this->replaceTeamForeign('automation_webhooks', 'automation_webhooks_team_id_foreign', cascade: true);
    }

    public function down(): void
    {
        $this->replaceTeamForeign('automation_api_keys', 'automation_api_keys_team_id_foreign', cascade: false);
        $this->replaceTeamForeign('automation_webhooks', 'automation_webhooks_team_id_foreign', cascade: false);
    }

    protected function replaceTeamForeign(string $table, string $constraint, bool $cascade): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'team_id')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($constraint): void {
            $blueprint->dropForeign($constraint);
        });

        Schema::table($table, function (Blueprint $blueprint) use ($constraint, $cascade): void {
            $foreign = $blueprint->foreign('team_id', $constraint)
                ->references('id')
                ->on('teams');

            if ($cascade) {
                $foreign->cascadeOnDelete();
            } else {
                $foreign->nullOnDelete();
            }
        });
    }
};
