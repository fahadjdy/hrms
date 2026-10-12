<?php

// MCP tool definitions for company settings (profile, attendance, payroll, roles, users)
// and the super admin platform area (companies, platform settings).
// Each entry maps one named web route to one tool.
// Fields are read from the controller's validation rules / request reads.

$dateFormats = ['d M Y', 'd/m/Y', 'm/d/Y', 'Y-m-d', 'd-m-Y'];

$permissions = [
    'employees.view', 'employees.manage', 'attendance.view', 'attendance.manage', 'leave.view', 'leave.manage',
    'payroll.view', 'payroll.manage', 'payroll.finalize', 'finance.view', 'finance.manage', 'settlements.manage',
    'reports.view', 'settings.manage', 'roles.manage', 'audit.view',
];

$passwordNote = 'Minimum 8 characters (in production: at least 12 with upper and lower case, numbers, symbols, and not found in known breaches).';

$logoField = [
    'type' => 'file',
    'description' => 'Optional company logo image (jpg, jpeg, png or webp, max 2 MB) as {name, content_base64}. Omit to keep the current logo; a new logo replaces the old file.',
];

$companyFields = [
    'name' => ['type' => 'string', 'required' => true, 'description' => 'Company (display) name, max 255.'],
    'legal_name' => ['type' => 'string', 'description' => 'Registered legal name, max 255.'],
    'logo' => $logoField,
    'email' => ['type' => 'string', 'description' => 'Company contact email.'],
    'phone' => ['type' => 'string', 'description' => 'Phone, max 30.'],
    'address' => ['type' => 'string', 'description' => 'Street address, max 255.'],
    'city' => ['type' => 'string', 'description' => 'City, max 100.'],
    'state' => ['type' => 'string', 'description' => 'State / province, max 100.'],
    'country' => ['type' => 'string', 'description' => 'Country, max 100.'],
    'postal_code' => ['type' => 'string', 'description' => 'Postal / PIN code, max 20.'],
    'tax_id' => ['type' => 'string', 'description' => 'GST / tax number, max 50.'],
    'currency' => ['type' => 'string', 'required' => true, 'description' => 'ISO 4217 currency code, 3 uppercase letters (e.g. INR, USD).'],
    'timezone' => ['type' => 'string', 'required' => true, 'description' => 'PHP timezone identifier (e.g. Asia/Kolkata); the form-options tool returns the full list.'],
    'date_format' => ['type' => 'string', 'required' => true, 'enum' => $dateFormats, 'description' => 'Date display format (PHP date syntax).'],
];

$companyParam = [
    'company' => ['type' => 'integer', 'required' => true, 'description' => 'Company id (from list_companies).'],
];

return [
    // ---------------------------------------------------------------- Company settings
    [
        'name' => 'get_company_profile',
        'route' => 'settings.company.edit',
        'title' => 'Get company profile',
        'description' => 'The signed-in user\'s company profile. Returns companyProfile (name, legal_name, email, phone, address, city, state, country, postal_code, tax_id, currency, timezone, date_format, logo_url), timezones[] and dateFormats[]. Requires permission settings.manage.',
        'read_only' => true,
        'destructive' => false,
        'params' => [],
        'fields' => [],
    ],
    [
        'name' => 'update_company_profile',
        'route' => 'settings.company.update',
        'title' => 'Update company profile',
        'description' => 'Update the company\'s own profile. name, currency, timezone and date_format are always required; an omitted optional field keeps its value (send null to clear it). Call get_company_profile first to read current values. Changes are audit-logged. Requires permission settings.manage.',
        'read_only' => false,
        'destructive' => false,
        'params' => [],
        'fields' => $companyFields,
    ],
    [
        'name' => 'get_attendance_settings',
        'route' => 'settings.attendance.edit',
        'title' => 'Get attendance settings',
        'description' => 'Company attendance settings. Returns settings (attendance_mode, default_work_shift_id, male/female/other_work_shift_id, grace_minutes, lates_per_half_day, short_hours_tolerance_minutes), modes[] options and active shifts[] (id, name, start_time, end_time, required_minutes). Requires permission settings.manage.',
        'read_only' => true,
        'destructive' => false,
        'params' => [],
        'fields' => [],
    ],
    [
        'name' => 'update_attendance_settings',
        'route' => 'settings.attendance.update',
        'title' => 'Update attendance settings',
        'description' => 'Save attendance rules. Send every field; call get_attendance_settings first for current values and shift ids. Recalculation caches are flushed and the change is audit-logged. Requires permission settings.manage.',
        'read_only' => false,
        'destructive' => false,
        'params' => [],
        'fields' => [
            'attendance_mode' => ['type' => 'string', 'required' => true, 'enum' => ['automatic', 'manual'], 'description' => 'How attendance is recorded: automatic or manual.'],
            'default_work_shift_id' => ['type' => 'integer', 'required' => true, 'description' => 'Company default work shift id (from get_attendance_settings shifts[]).'],
            'male_work_shift_id' => ['type' => 'integer', 'description' => 'Shift id for male staff; null to use the default shift.'],
            'female_work_shift_id' => ['type' => 'integer', 'description' => 'Shift id for female staff; null to use the default shift.'],
            'other_work_shift_id' => ['type' => 'integer', 'description' => 'Shift id for other-gender staff; null to use the default shift.'],
            'grace_minutes' => ['type' => 'integer', 'required' => true, 'description' => 'Late-arrival grace period in minutes, 0-240.'],
            'lates_per_half_day' => ['type' => 'integer', 'required' => true, 'description' => 'Number of late marks that count as one half day, 0-31 (0 = off).'],
            'short_hours_tolerance_minutes' => ['type' => 'integer', 'required' => true, 'description' => 'Short-hours tolerance in minutes, 0-240.'],
        ],
    ],
    [
        'name' => 'get_payroll_settings',
        'route' => 'settings.payroll.edit',
        'title' => 'Get payroll settings',
        'description' => 'Company payroll settings. Returns settings (salary_calculation_method, payroll_cycle, payroll_period_start_day, salary_payment_day, overtime_*, short_hours_*, borrow_auto_deduct, borrow_max_deduction_percent), calculationMethods[] and shortHoursModes[] options. Requires permission settings.manage.',
        'read_only' => true,
        'destructive' => false,
        'params' => [],
        'fields' => [],
    ],
    [
        'name' => 'update_payroll_settings',
        'route' => 'settings.payroll.update',
        'title' => 'Update payroll settings',
        'description' => 'Save payroll rules. Send every field; call get_payroll_settings first for current values. Money in company currency. Audit-logged. Requires permission settings.manage.',
        'read_only' => false,
        'destructive' => false,
        'params' => [],
        'fields' => [
            'salary_calculation_method' => ['type' => 'string', 'required' => true, 'enum' => ['calendar_days', 'working_days', 'fixed_30'], 'description' => 'Per-day salary divisor: calendar days in month, working days in month, or fixed 30 days.'],
            'payroll_period_start_day' => ['type' => 'integer', 'required' => true, 'description' => 'Day of month the payroll period starts, 1-28.'],
            'salary_payment_day' => ['type' => 'integer', 'required' => true, 'description' => 'Day of month salary is paid, 1-28.'],
            'overtime_rate_type' => ['type' => 'string', 'required' => true, 'enum' => ['multiplier', 'fixed'], 'description' => 'Overtime pay: multiplier of hourly salary, or fixed rate per hour.'],
            'overtime_multiplier' => ['type' => 'number', 'required' => true, 'description' => 'Overtime multiplier, 0-10 (e.g. 1.5).'],
            'overtime_fixed_rate' => ['type' => 'number', 'description' => 'Fixed overtime rate per hour, 0-999999. Required when overtime_rate_type is fixed.'],
            'overtime_from_attendance' => ['type' => 'boolean', 'required' => true, 'description' => 'Take overtime automatically from attendance.'],
            'short_hours_mode' => ['type' => 'string', 'required' => true, 'enum' => ['deduct', 'record_only', 'manual'], 'description' => 'Short hours: deduct from salary, record only, or manual adjustment.'],
            'short_hours_rate_type' => ['type' => 'string', 'required' => true, 'enum' => ['salary_hourly', 'fixed'], 'description' => 'Short-hours deduction rate: hourly salary rate, or fixed rate per hour.'],
            'short_hours_fixed_rate' => ['type' => 'number', 'description' => 'Fixed short-hours rate per hour, 0-999999. Required when short_hours_rate_type is fixed.'],
            'borrow_auto_deduct' => ['type' => 'boolean', 'required' => true, 'description' => 'Automatically deduct borrow (advance) installments in payroll.'],
            'borrow_max_deduction_percent' => ['type' => 'number', 'description' => 'Max percent of salary that borrow recovery may take, 1-100; null for no cap.'],
        ],
    ],

    // ---------------------------------------------------------------- Roles & users
    [
        'name' => 'list_roles_and_users',
        'route' => 'settings.roles.index',
        'title' => 'List roles and company users',
        'description' => 'All roles of the company and all admin/HR user accounts (no filters, no paging). Returns roles[] (id, name, slug, permissions[], is_system, is_admin, users_count), permissions[] (all permission options) and users[] (id, name, email, role_id, role, is_active, is_self). Use role ids with update_role/delete_role/create_company_user and user ids with update_company_user. Requires permission roles.manage.',
        'read_only' => true,
        'destructive' => false,
        'params' => [],
        'fields' => [],
    ],
    [
        'name' => 'create_role',
        'route' => 'settings.roles.store',
        'title' => 'Create role',
        'description' => 'Create a custom role with a set of permissions (slug generated from the name). Audit-logged. Requires permission roles.manage.',
        'read_only' => false,
        'destructive' => false,
        'params' => [],
        'fields' => [
            'name' => ['type' => 'string', 'required' => true, 'description' => 'Role name, max 100.'],
            'permissions' => ['type' => 'array', 'required' => true, 'items' => ['type' => 'string', 'enum' => $permissions], 'description' => 'Permissions granted (distinct values; may be an empty array).'],
        ],
    ],
    [
        'name' => 'update_role',
        'route' => 'settings.roles.update',
        'title' => 'Update role',
        'description' => 'Rename a role and replace its permission set (send the full list). The Company Admin role cannot be changed. Audit-logged. Requires permission roles.manage.',
        'read_only' => false,
        'destructive' => false,
        'params' => [
            'role' => ['type' => 'integer', 'required' => true, 'description' => 'Role id (from list_roles_and_users).'],
        ],
        'fields' => [
            'name' => ['type' => 'string', 'required' => true, 'description' => 'Role name, max 100.'],
            'permissions' => ['type' => 'array', 'required' => true, 'items' => ['type' => 'string', 'enum' => $permissions], 'description' => 'Complete permission list for the role (distinct; may be empty).'],
        ],
    ],
    [
        'name' => 'delete_role',
        'route' => 'settings.roles.destroy',
        'title' => 'Delete role',
        'description' => 'Delete a custom role. Built-in (system) roles cannot be deleted, and a role still assigned to users cannot be deleted (reassign them first). Audit-logged. Requires permission roles.manage.',
        'read_only' => false,
        'destructive' => true,
        'params' => [
            'role' => ['type' => 'integer', 'required' => true, 'description' => 'Role id (from list_roles_and_users).'],
        ],
        'fields' => [],
    ],
    [
        'name' => 'create_company_user',
        'route' => 'settings.users.store',
        'title' => 'Create company user',
        'description' => 'Add an admin/HR login account to the company (employees never get logins). The email is marked verified; the user signs in with the given password. Audit-logged. Requires permission roles.manage.',
        'read_only' => false,
        'destructive' => false,
        'params' => [],
        'fields' => [
            'name' => ['type' => 'string', 'required' => true, 'description' => 'Full name, max 255.'],
            'email' => ['type' => 'string', 'required' => true, 'description' => 'Login email, lowercase, unique across the whole platform.'],
            'password' => ['type' => 'string', 'required' => true, 'description' => 'Initial password. '.$passwordNote],
            'role_id' => ['type' => 'integer', 'required' => true, 'description' => 'Role id (from list_roles_and_users).'],
        ],
    ],
    [
        'name' => 'update_company_user',
        'route' => 'settings.users.update',
        'title' => 'Update company user',
        'description' => 'Change a company user\'s name, role, active status and optionally password. Send name, role_id and is_active every time (read them from list_roles_and_users). You cannot change your own role or deactivate yourself, and the company must keep at least one active Company Admin. Inactive users cannot sign in. Audit-logged. Requires permission roles.manage.',
        'read_only' => false,
        'destructive' => false,
        'params' => [
            'user' => ['type' => 'integer', 'required' => true, 'description' => 'User id (from list_roles_and_users users[]).'],
        ],
        'fields' => [
            'name' => ['type' => 'string', 'required' => true, 'description' => 'Full name, max 255.'],
            'role_id' => ['type' => 'integer', 'required' => true, 'description' => 'Role id (from list_roles_and_users).'],
            'is_active' => ['type' => 'boolean', 'required' => true, 'description' => 'Whether the account can sign in.'],
            'password' => ['type' => 'string', 'description' => 'New password; omit or null to keep the current one. '.$passwordNote],
        ],
    ],

    // ---------------------------------------------------------------- Super admin: companies
    [
        'name' => 'list_companies',
        'route' => 'admin.companies.index',
        'title' => 'List companies',
        'description' => 'All tenant companies, 15 per page, ordered by name. Returns companies (paginated: data[] with id, name, legal_name, slug, email, phone, address fields, tax_id, currency, timezone, date_format, is_active, logo_url, created_at, users_count, employees_count, active_employees_count; plus links/meta), filters and platform stats (companies, active_companies, employees, users). Use the id with get_company. Super admin only.',
        'read_only' => true,
        'destructive' => false,
        'params' => [],
        'fields' => [
            'search' => ['type' => 'string', 'description' => 'Matches company name or email (partial).'],
            'status' => ['type' => 'string', 'enum' => ['active', 'inactive'], 'description' => 'Filter by company status; omit for all.'],
            'page' => ['type' => 'integer', 'description' => 'Page number (default 1).'],
        ],
    ],
    [
        'name' => 'get_company_form_options',
        'route' => 'admin.companies.create',
        'title' => 'Get new company form options',
        'description' => 'Options for creating a company: timezones[], dateFormats[] and defaults (currency, timezone, date_format from platform settings). Super admin only.',
        'read_only' => true,
        'destructive' => false,
        'params' => [],
        'fields' => [],
    ],
    [
        'name' => 'create_company',
        'route' => 'admin.companies.store',
        'title' => 'Create company',
        'description' => 'Create a tenant company together with its first Company Admin user. The company is provisioned with a General Shift (09:00-18:00), default settings, Sunday weekly holiday, standard leave types (CL, SL, PL, UL) and built-in roles. The admin login credentials (login_url, email, password) are flashed once in the response as `credentials` and never shown again; redirects to the new company page. Super admin only.',
        'read_only' => false,
        'destructive' => false,
        'params' => [],
        'fields' => $companyFields + [
            'admin_name' => ['type' => 'string', 'required' => true, 'description' => 'Company Admin full name, max 255.'],
            'admin_email' => ['type' => 'string', 'required' => true, 'description' => 'Company Admin login email, lowercase, unique across the platform.'],
            'admin_password' => ['type' => 'string', 'required' => true, 'description' => 'Company Admin initial password. '.$passwordNote],
        ],
    ],
    [
        'name' => 'get_company',
        'route' => 'admin.companies.show',
        'title' => 'Get company',
        'description' => 'One company with platform-level totals. Returns managedCompany (profile fields, is_active, logo_url, created_at), stats (employees, active_employees, past_employees, users, last_payroll {label, status, net_payable} or null) and users[] (id, name, email, role, is_active). Super admin only.',
        'read_only' => true,
        'destructive' => false,
        'params' => $companyParam,
        'fields' => [],
    ],
    [
        'name' => 'get_company_for_edit',
        'route' => 'admin.companies.edit',
        'title' => 'Get company edit form',
        'description' => 'Current company profile for editing: managedCompany (all profile fields) plus timezones[] and dateFormats[]. Super admin only.',
        'read_only' => true,
        'destructive' => false,
        'params' => $companyParam,
        'fields' => [],
    ],
    [
        'name' => 'update_company',
        'route' => 'admin.companies.update',
        'title' => 'Update company',
        'description' => 'Update a company\'s profile (not its admin user or status). name, currency, timezone and date_format are always required; an omitted optional field keeps its value (send null to clear it). Call get_company_for_edit first to read current values. Use set_company_status to activate/deactivate. Super admin only.',
        'read_only' => false,
        'destructive' => false,
        'params' => $companyParam,
        'fields' => $companyFields,
    ],
    [
        'name' => 'set_company_status',
        'route' => 'admin.companies.status.update',
        'title' => 'Activate or deactivate company',
        'description' => 'Activate or deactivate a company. Users of a deactivated company cannot sign in. Audit-logged in the company. Super admin only.',
        'read_only' => false,
        'destructive' => false,
        'params' => $companyParam,
        'fields' => [
            'is_active' => ['type' => 'boolean', 'required' => true, 'description' => 'true to activate, false to deactivate.'],
        ],
    ],

    // ---------------------------------------------------------------- Super admin: platform settings
    [
        'name' => 'get_platform_settings',
        'route' => 'admin.settings.edit',
        'title' => 'Get platform settings',
        'description' => 'Platform-wide settings. Returns settings (platform_name, support_email, default_currency, default_timezone, default_date_format), timezones[] and dateFormats[]. Super admin only.',
        'read_only' => true,
        'destructive' => false,
        'params' => [],
        'fields' => [],
    ],
    [
        'name' => 'update_platform_settings',
        'route' => 'admin.settings.update',
        'title' => 'Update platform settings',
        'description' => 'Save platform settings; the regional defaults pre-fill new companies. Send every field; call get_platform_settings first. Super admin only.',
        'read_only' => false,
        'destructive' => false,
        'params' => [],
        'fields' => [
            'platform_name' => ['type' => 'string', 'required' => true, 'description' => 'Platform display name, max 100.'],
            'support_email' => ['type' => 'string', 'description' => 'Support contact email.'],
            'default_currency' => ['type' => 'string', 'required' => true, 'description' => 'Default ISO 4217 currency, 3 uppercase letters.'],
            'default_timezone' => ['type' => 'string', 'required' => true, 'description' => 'Default PHP timezone identifier (e.g. Asia/Kolkata).'],
            'default_date_format' => ['type' => 'string', 'required' => true, 'enum' => $dateFormats, 'description' => 'Default date display format.'],
        ],
    ],
];
