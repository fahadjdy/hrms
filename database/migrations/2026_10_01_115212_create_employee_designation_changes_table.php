<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('employee_designation_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->foreignId('employee_id')->constrained();
            $table->foreignId('from_designation_id')->nullable()->constrained('designations');
            $table->foreignId('to_designation_id')->constrained('designations');
            $table->string('type', 20);
            $table->date('effective_date');
            $table->string('reason')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'employee_id', 'effective_date'], 'designation_changes_employee_date_index');
            $table->index(['company_id', 'effective_date'], 'designation_changes_company_date_index');
        });

        // Employees that already exist start their history with the designation
        // they hold today, dated from the day they joined. Nothing else is touched.
        $now = now()->toDateTimeString();

        DB::table('employees')
            ->whereNotNull('designation_id')
            ->orderBy('id')
            ->select(['id', 'company_id', 'designation_id', 'joining_date'])
            ->chunk(500, function ($employees) use ($now): void {
                DB::table('employee_designation_changes')->insert(
                    $employees->map(fn (object $employee): array => [
                        'company_id' => $employee->company_id,
                        'employee_id' => $employee->id,
                        'from_designation_id' => null,
                        'to_designation_id' => $employee->designation_id,
                        'type' => 'initial',
                        'effective_date' => $employee->joining_date,
                        'reason' => null,
                        'changed_by' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all(),
                );
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_designation_changes');
    }
};
