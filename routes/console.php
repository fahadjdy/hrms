<?php

use Illuminate\Support\Facades\Schedule;

// Each company's "today" depends on its timezone, so the command runs hourly
// and covers the last two days; days that are already stored are skipped.
Schedule::command('hrms:generate-attendance')->hourly()->withoutOverlapping();
