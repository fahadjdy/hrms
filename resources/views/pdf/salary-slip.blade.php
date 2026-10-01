@php
    use App\Support\Format;

    $money = fn ($amount) => Format::money($amount, $currency);
    $hours = fn ($minutes) => Format::minutes($minutes);
    $date = fn ($value) => $value?->format($company->date_format) ?? '-';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Salary Slip {{ $payroll->label() }} - {{ $item->employee_name }}</title>
    <style>
        @page { margin: 28px 32px; }
        * { box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #1c2430; line-height: 1.45; }
        h1 { font-size: 17px; margin: 0; }
        h2 { font-size: 10px; margin: 0 0 6px; color: #0d5c4b; letter-spacing: 0.02em; }
        table { width: 100%; border-collapse: collapse; }
        .header td { vertical-align: top; }
        .logo { max-height: 46px; max-width: 150px; }
        .muted { color: #5c6878; }
        .right { text-align: right; }
        .title-bar { margin: 14px 0 12px; padding: 8px 10px; background: #0d5c4b; color: #ffffff; }
        .title-bar td { font-size: 11px; color: #ffffff; }
        .box { border: 1px solid #d5dbe3; padding: 8px 10px; }
        .meta td { padding: 2px 0; }
        .meta .label { color: #5c6878; width: 34%; }
        .lines th { text-align: left; font-size: 9px; color: #5c6878; padding: 5px 6px; border-bottom: 1px solid #d5dbe3; }
        .lines td { padding: 5px 6px; border-bottom: 1px solid #edf0f4; vertical-align: top; }
        .lines .note { color: #5c6878; font-size: 8.5px; }
        .lines .total td { font-weight: bold; border-top: 1px solid #1c2430; border-bottom: none; }
        .gap { height: 12px; }
        .summary td { padding: 4px 6px; }
        .net { background: #eef6f3; border: 1px solid #0d5c4b; padding: 10px 12px; }
        .net .amount { font-size: 16px; font-weight: bold; color: #0d5c4b; }
        .advance { background: #fff8e8; border: 1px solid #e0b252; padding: 8px 10px; }
        .footer { margin-top: 18px; color: #5c6878; font-size: 8.5px; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td style="width: 62%;">
                @if ($logo)
                    <img src="{{ $logo }}" class="logo" alt="">
                @endif
                <h1>{{ $company->name }}</h1>
                @if ($company->legal_name && $company->legal_name !== $company->name)
                    <div class="muted">{{ $company->legal_name }}</div>
                @endif
                <div class="muted">
                    {{ collect([$company->address, $company->city, $company->state, $company->postal_code, $company->country])->filter()->implode(', ') }}
                </div>
                <div class="muted">
                    {{ collect([$company->email, $company->phone, $company->tax_id ? 'GST / Tax: '.$company->tax_id : null])->filter()->implode('   |   ') }}
                </div>
            </td>
            <td class="right">
                <div class="muted">Salary Slip</div>
                <div style="font-size: 13px; font-weight: bold;">{{ $payroll->label() }}</div>
                <div class="muted">{{ $date($payroll->period_start) }} to {{ $date($payroll->period_end) }}</div>
                @if ($slip)
                    <div class="muted">Slip no. {{ $slip->slip_number }}</div>
                @endif
            </td>
        </tr>
    </table>

    <table class="title-bar">
        <tr>
            <td><strong>{{ $item->employee_name }}</strong> &nbsp; {{ $item->employee_code }}</td>
            <td class="right">{{ collect([$item->designation_name, $item->department_name])->filter()->implode(' - ') }}</td>
        </tr>
    </table>

    <table>
        <tr>
            <td style="width: 49%; vertical-align: top;">
                <div class="box">
                    <h2>Employee details</h2>
                    <table class="meta">
                        <tr><td class="label">Employee ID</td><td>{{ $item->employee_code }}</td></tr>
                        <tr><td class="label">Department</td><td>{{ $item->department_name ?? '-' }}</td></tr>
                        <tr><td class="label">Designation</td><td>{{ $item->designation_name ?? '-' }}</td></tr>
                        <tr><td class="label">Joining date</td><td>{{ $date($employee?->joining_date) }}</td></tr>
                    </table>
                </div>
            </td>
            <td style="width: 2%;"></td>
            <td style="width: 49%; vertical-align: top;">
                <div class="box">
                    <h2>Attendance and working hours</h2>
                    <table class="meta">
                        <tr>
                            <td class="label">Working days</td><td>{{ $summary['working_days'] ?? 0 }}</td>
                            <td class="label">Present</td><td>{{ $summary['present'] ?? 0 }}</td>
                        </tr>
                        <tr>
                            <td class="label">Absent</td><td>{{ ($summary['absent'] ?? 0) + ($summary['unmarked'] ?? 0) }}</td>
                            <td class="label">Half days</td><td>{{ $summary['half_day'] ?? 0 }}</td>
                        </tr>
                        <tr>
                            <td class="label">Paid leave</td><td>{{ $summary['paid_leave'] ?? 0 }}</td>
                            <td class="label">Unpaid leave</td><td>{{ $summary['unpaid_leave'] ?? 0 }}</td>
                        </tr>
                        <tr>
                            <td class="label">Required hours</td><td>{{ $hours($summary['required_minutes'] ?? 0) }}</td>
                            <td class="label">Worked hours</td><td>{{ $hours($summary['worked_minutes'] ?? 0) }}</td>
                        </tr>
                        <tr>
                            <td class="label">Short hours</td><td>{{ $hours($summary['short_minutes'] ?? 0) }}</td>
                            <td class="label">Overtime</td><td>{{ $hours($summary['overtime_minutes'] ?? 0) }}</td>
                        </tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <div class="gap"></div>

    <table>
        <tr>
            <td style="width: 49%; vertical-align: top;">
                <h2>Earnings</h2>
                <table class="lines">
                    <tr><th>Description</th><th class="right">Amount</th></tr>
                    @foreach ($earnings as $line)
                        <tr>
                            <td>{{ $line['label'] }}<div class="note">{{ $line['note'] }}</div></td>
                            <td class="right">{{ $money($line['amount']) }}</td>
                        </tr>
                    @endforeach
                    @foreach ($earningAdjustments as $adjustment)
                        <tr>
                            <td>{{ $adjustment['label'] }}</td>
                            <td class="right">{{ $money($adjustment['amount']) }}</td>
                        </tr>
                    @endforeach
                    <tr class="total">
                        <td>Total earnings</td>
                        <td class="right">{{ $money($totals['total_earnings'] ?? 0) }}</td>
                    </tr>
                </table>
            </td>
            <td style="width: 2%;"></td>
            <td style="width: 49%; vertical-align: top;">
                <h2>Deductions</h2>
                <table class="lines">
                    <tr><th>Description</th><th class="right">Amount</th></tr>
                    @forelse ($deductions->concat($recoveries) as $line)
                        <tr>
                            <td>{{ $line['label'] }}<div class="note">{{ $line['note'] }}</div></td>
                            <td class="right">{{ $money($line['amount']) }}</td>
                        </tr>
                    @empty
                        @if ($deductionAdjustments->isEmpty())
                            <tr><td class="muted" colspan="2">No deductions this month</td></tr>
                        @endif
                    @endforelse
                    @foreach ($deductionAdjustments as $adjustment)
                        <tr>
                            <td>{{ $adjustment['label'] }}</td>
                            <td class="right">{{ $money($adjustment['amount']) }}</td>
                        </tr>
                    @endforeach
                    <tr class="total">
                        <td>Total deductions</td>
                        <td class="right">{{ $money($totals['total_deductions'] ?? 0) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="gap"></div>

    <table>
        <tr>
            <td style="width: 49%; vertical-align: top;">
                <div class="box">
                    <h2>Borrow / advance</h2>
                    <table class="summary">
                        <tr>
                            <td>Borrow given with this salary</td>
                            <td class="right">{{ $money($item->borrow_given) }}</td>
                        </tr>
                        <tr>
                            <td>Borrow recovered from this salary</td>
                            <td class="right">{{ $money($item->borrow_recovery) }}</td>
                        </tr>
                    </table>
                    @if ($item->borrow_given > 0)
                        <div class="advance" style="margin-top: 6px;">
                            A borrow / advance of {{ $money($item->borrow_given) }} is paid with this salary. It is an
                            advance to be repaid, not salary income.
                        </div>
                    @endif
                </div>
            </td>
            <td style="width: 2%;"></td>
            <td style="width: 49%; vertical-align: top;">
                <div class="net">
                    <table class="summary">
                        <tr>
                            <td>Net salary (earnings less deductions)</td>
                            <td class="right">{{ $money($item->net_salary) }}</td>
                        </tr>
                        <tr>
                            <td>New borrow / advance</td>
                            <td class="right">+ {{ $money($item->borrow_given) }}</td>
                        </tr>
                        <tr>
                            <td><strong>Net payable</strong></td>
                            <td class="right amount">{{ $money($item->net_payable) }}</td>
                        </tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <div class="footer">
        This is a computer-generated salary slip and does not require a signature.
        Generated on {{ now($company->timezone)->format($company->date_format.' H:i') }}.
    </div>
</body>
</html>
