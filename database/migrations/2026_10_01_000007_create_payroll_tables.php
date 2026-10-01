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
        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('employee_count')->default(0);
            $table->decimal('total_gross', 16, 2)->default(0);
            $table->decimal('total_earnings', 16, 2)->default(0);
            $table->decimal('total_deductions', 16, 2)->default(0);
            $table->decimal('total_borrow_given', 16, 2)->default(0);
            $table->decimal('total_borrow_recovery', 16, 2)->default(0);
            $table->decimal('total_net_payable', 16, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reopened_at')->nullable();
            $table->foreignId('reopened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reopen_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'period_year', 'period_month']);
        });

        Schema::create('payroll_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->foreignId('payroll_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained();

            // Snapshot of the employee at calculation time, so history stays readable.
            $table->string('employee_name');
            $table->string('employee_code', 50);
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('department_name')->nullable();
            $table->string('designation_name')->nullable();

            $table->decimal('gross_salary', 14, 2)->default(0);
            $table->decimal('overtime_amount', 14, 2)->default(0);
            $table->decimal('bonus_amount', 14, 2)->default(0);
            $table->decimal('other_earnings_amount', 14, 2)->default(0);
            $table->decimal('attendance_deduction', 14, 2)->default(0);
            $table->decimal('unpaid_leave_deduction', 14, 2)->default(0);
            $table->decimal('short_hours_deduction', 14, 2)->default(0);
            $table->decimal('borrow_recovery', 14, 2)->default(0);
            $table->decimal('other_deductions', 14, 2)->default(0);
            $table->decimal('borrow_given', 14, 2)->default(0);
            $table->decimal('net_salary', 14, 2)->default(0);
            $table->decimal('net_payable', 14, 2)->default(0);

            $table->decimal('present_days', 5, 1)->default(0);
            $table->decimal('absent_days', 5, 1)->default(0);
            $table->decimal('leave_days', 5, 1)->default(0);
            $table->unsignedInteger('short_minutes')->default(0);
            $table->unsignedInteger('overtime_minutes')->default(0);

            $table->json('attendance_summary')->nullable();
            $table->json('breakdown')->nullable();
            $table->boolean('is_adjusted')->default(false);
            $table->timestamps();

            $table->unique(['payroll_id', 'employee_id']);
            $table->index(['company_id', 'employee_id']);
        });

        Schema::create('payroll_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->foreignId('payroll_item_id')->constrained()->cascadeOnDelete();
            $table->string('bucket', 40);
            $table->decimal('amount', 14, 2);
            $table->string('reason');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('salary_slips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->foreignId('payroll_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_item_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained();
            $table->string('slip_number', 60);
            $table->string('file_path')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'slip_number']);
            $table->index(['company_id', 'employee_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salary_slips');
        Schema::dropIfExists('payroll_adjustments');
        Schema::dropIfExists('payroll_items');
        Schema::dropIfExists('payrolls');
    }
};
