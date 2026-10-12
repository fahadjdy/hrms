<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * An employee has at most one salary revision per effective date. The service
 * has always refused a second one; this makes the database enforce it too.
 */
return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('employee_salary_revisions')
            ->select('employee_id', 'effective_date')
            ->groupBy('employee_id', 'effective_date')
            ->havingRaw('count(*) > 1')
            ->exists();

        if ($duplicates) {
            throw new RuntimeException('Some employees have two salary revisions on the same date. Resolve them before running this migration.');
        }

        Schema::table('employee_salary_revisions', function (Blueprint $table) {
            $table->unique(['employee_id', 'effective_date']);
        });

        Schema::table('employee_salary_revisions', function (Blueprint $table) {
            $table->dropIndex(['employee_id', 'effective_date']);
        });
    }

    public function down(): void
    {
        Schema::table('employee_salary_revisions', function (Blueprint $table) {
            $table->index(['employee_id', 'effective_date']);
        });

        Schema::table('employee_salary_revisions', function (Blueprint $table) {
            $table->dropUnique(['employee_id', 'effective_date']);
        });
    }
};
