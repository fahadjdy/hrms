<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with the platform owner and, unless switched off, two demo companies.
     *
     * Model events stay enabled: tenant-owned models take their company from
     * the tenant context when they are created.
     */
    public function run(): void
    {
        // The same random sequence every run, so the demo data is reproducible.
        mt_srand(20261001);

        // Finalizing a payroll queues salary slip generation. The demo discards
        // those jobs; a slip is generated on demand the first time it is opened.
        $queue = config('queue.default');
        config(['queue.connections.discard' => ['driver' => 'null'], 'queue.default' => 'discard']);

        try {
            $this->createSuperAdmin();

            // A production install seeds only the super admin (SEED_DEMO_DATA=false).
            if (config('hrms.seed.demo_data')) {
                $this->call([
                    DemoCompanySeeder::class,
                    SecondCompanySeeder::class,
                ]);
            }
        } finally {
            config(['queue.default' => $queue]);
            Carbon::setTestNow();
        }
    }

    private function createSuperAdmin(): void
    {
        $user = new User([
            'name' => config('hrms.seed.super_admin_name'),
            'email' => config('hrms.seed.super_admin_email'),
            'password' => config('hrms.seed.super_admin_password'),
        ]);
        $user->is_super_admin = true;
        $user->email_verified_at = now();
        $user->save();
    }
}
