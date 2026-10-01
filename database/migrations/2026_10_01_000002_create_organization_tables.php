<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'name']);
        });

        Schema::create('designations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'name']);
        });

        Schema::create('work_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->string('name');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('required_minutes');
            $table->unsignedSmallInteger('break_minutes')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'name']);
        });

        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained();

            // Attendance
            $table->string('attendance_mode', 20)->default('manual');
            $table->foreignId('default_work_shift_id')->nullable()->constrained('work_shifts')->nullOnDelete();
            $table->foreignId('male_work_shift_id')->nullable()->constrained('work_shifts')->nullOnDelete();
            $table->foreignId('female_work_shift_id')->nullable()->constrained('work_shifts')->nullOnDelete();
            $table->foreignId('other_work_shift_id')->nullable()->constrained('work_shifts')->nullOnDelete();
            $table->unsignedSmallInteger('grace_minutes')->default(10);
            $table->unsignedSmallInteger('lates_per_half_day')->default(0);
            $table->unsignedSmallInteger('short_hours_tolerance_minutes')->default(0);

            // Payroll
            $table->string('salary_calculation_method', 20)->default('working_days');
            $table->string('payroll_cycle', 20)->default('monthly');
            $table->unsignedTinyInteger('payroll_period_start_day')->default(1);
            $table->unsignedTinyInteger('salary_payment_day')->default(1);
            $table->string('overtime_rate_type', 20)->default('multiplier');
            $table->decimal('overtime_multiplier', 5, 2)->default(1.5);
            $table->decimal('overtime_fixed_rate', 12, 2)->nullable();
            $table->boolean('overtime_from_attendance')->default(false);
            $table->string('short_hours_mode', 20)->default('record_only');
            $table->string('short_hours_rate_type', 20)->default('salary_hourly');
            $table->decimal('short_hours_fixed_rate', 12, 2)->nullable();
            $table->boolean('borrow_auto_deduct')->default(true);
            $table->decimal('borrow_max_deduction_percent', 5, 2)->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_settings');
        Schema::dropIfExists('work_shifts');
        Schema::dropIfExists('designations');
        Schema::dropIfExists('departments');
    }
};
