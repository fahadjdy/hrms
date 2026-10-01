<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Synchronous Processing Limits
    |--------------------------------------------------------------------------
    |
    | Heavy operations run on the queue. For small companies they finish in
    | well under a second, so below these limits they run inside the request
    | and the admin sees the result immediately without a queue worker.
    |
    */

    'sync_payroll_employee_limit' => (int) env('HRMS_SYNC_PAYROLL_EMPLOYEE_LIMIT', 250),

    'sync_attendance_day_limit' => (int) env('HRMS_SYNC_ATTENDANCE_DAY_LIMIT', 62),

    /*
    |--------------------------------------------------------------------------
    | Uploads
    |--------------------------------------------------------------------------
    */

    'document_max_kilobytes' => (int) env('HRMS_DOCUMENT_MAX_KB', 10240),

    'photo_max_kilobytes' => (int) env('HRMS_PHOTO_MAX_KB', 2048),

    /*
    |--------------------------------------------------------------------------
    | Deploy URLs
    |--------------------------------------------------------------------------
    |
    | /deploy/migrate, /deploy/seed, /deploy/storage-link and friends run
    | deployment tasks from the browser, for hosting without SSH. They need
    | no login, so anyone who knows the address can trigger them; they are
    | limited to repeatable, non-destructive tasks for that reason. Switch
    | them off once the site is set up and you do not need them.
    |
    */

    'deploy' => [
        'routes_enabled' => (bool) env('DEPLOY_ROUTES_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Seeding
    |--------------------------------------------------------------------------
    |
    | The seeder always creates the platform super admin. The default password
    | is published in this repository, so change it after the first login (or
    | set SUPER_ADMIN_PASSWORD before seeding). Demo companies are optional and
    | all of their logins use the demo password.
    |
    */

    'seed' => [
        'super_admin_name' => env('SUPER_ADMIN_NAME', 'Platform Owner'),
        'super_admin_email' => env('SUPER_ADMIN_EMAIL', 'fahadjdy12@gmail.com'),
        'super_admin_password' => env('SUPER_ADMIN_PASSWORD', 'password'),
        'demo_data' => (bool) env('SEED_DEMO_DATA', true),
        'demo_password' => env('DEMO_PASSWORD', 'password'),
    ],

];
