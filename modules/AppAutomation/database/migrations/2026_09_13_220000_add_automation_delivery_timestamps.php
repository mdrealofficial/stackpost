<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        foreach (['auto_reply_runs','auto_comment_runs'] as $table) {
            if (!Schema::hasTable($table) || Schema::hasColumn($table,'sent_at')) continue;
            Schema::table($table,function(Blueprint $t) {
                $t->timestamp('sent_at')->nullable()->index();
                $t->timestamp('reserved_at')->nullable();
                $t->timestamp('handoff_resolved_at')->nullable();
            });
            DB::table($table)->whereIn('status',['processing','review','unknown'])->update(['reserved_at'=>DB::raw('COALESCE(started_at, created_at)')]);
            DB::table($table)->where('status','sent')->update(['sent_at'=>DB::raw('(SELECT sent_at FROM social_inbox_messages WHERE social_inbox_messages.id = '.$table.'.outbound_message_id)')]);
        }
    }
    public function down(): void
    {
        foreach (['auto_reply_runs','auto_comment_runs'] as $table) {
            if (Schema::hasTable($table)) Schema::table($table,fn(Blueprint $t)=>$t->dropColumn(['sent_at','reserved_at','handoff_resolved_at']));
        }
    }
};
