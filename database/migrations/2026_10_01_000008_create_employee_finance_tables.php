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
        Schema::create('employee_borrows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->foreignId('employee_id')->constrained();
            $table->string('reference_no', 40);
            $table->string('kind', 20)->default('new');
            $table->decimal('amount', 14, 2);
            $table->decimal('opening_balance', 14, 2);
            $table->decimal('recovered_amount', 14, 2)->default(0);
            $table->decimal('outstanding_amount', 14, 2);
            $table->date('borrow_date');
            $table->string('reason')->nullable();
            $table->decimal('monthly_deduction', 14, 2)->default(0);
            $table->unsignedSmallInteger('installments_count')->nullable();
            $table->date('deduction_start_month')->nullable();
            $table->string('disbursement_method', 20)->default('direct');
            $table->date('disburse_period')->nullable();
            $table->timestamp('disbursed_at')->nullable();
            $table->string('status', 20)->default('active');
            $table->string('source_reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'reference_no']);
            $table->index(['company_id', 'status']);
            $table->index(['employee_id', 'status']);
        });

        Schema::create('borrow_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->foreignId('employee_borrow_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained();
            $table->unsignedSmallInteger('sequence');
            $table->date('due_month');
            $table->decimal('amount', 14, 2);
            $table->decimal('paid_amount', 14, 2)->default(0);
            $table->string('status', 20)->default('pending');
            $table->foreignId('payroll_item_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status', 'due_month']);
            $table->index(['employee_borrow_id', 'status']);
        });

        Schema::create('borrow_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->foreignId('employee_borrow_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained();
            $table->string('type', 20);
            $table->decimal('amount', 14, 2);
            $table->decimal('balance_after', 14, 2);
            $table->date('transaction_date');
            $table->foreignId('payroll_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'type', 'transaction_date']);
            $table->index(['employee_borrow_id', 'transaction_date']);
        });

        Schema::create('overtimes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->foreignId('employee_id')->constrained();
            $table->date('date');
            $table->string('calculation_type', 20)->default('hourly');
            $table->decimal('hours', 6, 2)->nullable();
            $table->decimal('rate', 12, 2)->nullable();
            $table->decimal('amount', 14, 2);
            $table->string('reason')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('approved');
            $table->foreignId('payroll_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'date']);
            $table->index(['employee_id', 'date']);
        });

        Schema::create('salary_bonuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->foreignId('employee_id')->constrained();
            $table->date('date');
            $table->string('type', 20)->default('bonus');
            $table->string('title');
            $table->decimal('amount', 14, 2);
            $table->string('reason')->nullable();
            $table->foreignId('payroll_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'date']);
            $table->index(['employee_id', 'date']);
        });

        Schema::create('salary_deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->foreignId('employee_id')->constrained();
            $table->date('date');
            $table->string('title');
            $table->decimal('amount', 14, 2);
            $table->string('reason')->nullable();
            $table->foreignId('payroll_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'date']);
            $table->index(['employee_id', 'date']);
        });

        Schema::create('short_hours_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->foreignId('employee_id')->constrained();
            $table->date('period_start');
            $table->decimal('adjusted_amount', 14, 2);
            $table->string('reason');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'period_start']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('short_hours_adjustments');
        Schema::dropIfExists('salary_deductions');
        Schema::dropIfExists('salary_bonuses');
        Schema::dropIfExists('overtimes');
        Schema::dropIfExists('borrow_transactions');
        Schema::dropIfExists('borrow_installments');
        Schema::dropIfExists('employee_borrows');
    }
};
