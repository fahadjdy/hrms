<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * New companies deduct short hours from salary by default (the admin can still
 * change each employee's amount). Existing companies keep the mode they chose.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->string('short_hours_mode', 20)->default('deduct')->change();
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->string('short_hours_mode', 20)->default('record_only')->change();
        });
    }
};
