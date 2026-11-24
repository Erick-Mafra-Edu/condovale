<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * RNF04 / UC-T03 — the audit trail says what was done, to which record and in
 * which role, instead of only a sentence.
 *
 * `action` is an App\Enums\AuditAction value; `subject_id` is the id of the
 * record, whose type comes from the type_log_id (TypeLogEnum::entity()). The
 * author's role is copied at the time of the action: a promoted employee's past
 * operations stay recorded as an employee's.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logs', function (Blueprint $table) {
            $table->string('action', 60)->nullable()->after('type_log_id');
            $table->unsignedBigInteger('subject_id')->nullable()->after('action');
            $table->string('user_role', 20)->nullable()->after('user_id');

            // The audit report always filters by period and lists the newest first.
            $table->index('created_at');
            $table->index(['type_log_id', 'subject_id']);
            $table->index(['action', 'created_at']);
        });

        // Entries written before this migration get the author's current role,
        // the closest available to the role they had. Action and record cannot
        // be recovered from the free text reliably and stay empty.
        DB::table('logs')
            ->whereNull('user_role')
            ->whereNotNull('user_id')
            ->update(['user_role' => DB::raw('(select role from users where users.id = logs.user_id)')]);
    }

    public function down(): void
    {
        Schema::table('logs', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['type_log_id', 'subject_id']);
            $table->dropIndex(['action', 'created_at']);
            $table->dropColumn(['action', 'subject_id', 'user_role']);
        });
    }
};
