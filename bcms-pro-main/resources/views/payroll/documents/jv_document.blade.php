@php
    $employeeCollection = collect($employees ?? []);
    $employeeCount = $employeeCollection->count();
    $totalBasic = $employeeCollection->sum('basic_salary');
    $totalNet = $employeeCollection->sum('net_pay');
    $totalPaye = $employeeCollection->sum('paye');
    $totalPsssf = $employeeCollection->sum('psssf_contribution');
    $totalPsssfEmployer = $employeeCollection->sum('psssf_employer_contribution');
    $totalHeslb = $employeeCollection->sum('heslb');
    $totalLoans = $employeeCollection->sum('total_loans');
    $generatedDate = now()->format('d M Y, H:i');
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payroll JV Sheet</title>
    <style>
        @page { margin: 10mm; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 7.4pt;
            color: #222;
            margin: 0;
            padding-bottom: 16mm;
        }
        table { width: 100%; border-collapse: collapse; }
        th, td { vertical-align: middle; line-height: 1.25; }
        .report { width: 94%; margin: 0 auto; }
        .header td { border: none; text-align: center; }
        .header-logo-cell { width: 18%; padding: 2px 8px 8px; }
        .logo-box { border: 1px solid #8d8d8d; min-height: 46px; padding: 8px 5px; }
        .header-logo { width: 54px; max-width: 54px; max-height: 54px; height: auto; }
        .header-center { width: 64%; padding: 3px 8px 8px; }
        .company-name { font-size: 8.6pt; font-weight: bold; text-transform: uppercase; }
        .company-detail { color: #555; font-size: 7pt; margin-top: 3px; }
        .document-title {
            background: #922d2f;
            border-bottom: 4px solid #ffc400;
            color: #fff;
            font-size: 11pt;
            font-weight: bold;
            letter-spacing: .3px;
            margin-bottom: 8px;
            padding: 7px 0;
            text-align: center;
            text-transform: uppercase;
        }
        .info { margin-bottom: 10px; }
        .info th, .info td, .grid th, .grid td { border: 1px solid #9d9d9d; padding: 5px 7px; }
        .info th {
            background: #f2f2f2;
            color: #333;
            font-size: 7pt;
            text-align: left;
            text-transform: uppercase;
            width: 13%;
        }
        .info td { font-size: 7.6pt; }
        .grid { table-layout: fixed; }
        .grid th, .grid td { height: 20px; word-wrap: break-word; }
        .grid th {
            background: #922d2f;
            color: #fff;
            font-size: 6.2pt;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
        }
        .grid td { font-size: 6.8pt; }
        .grid thead { display: table-header-group; }
        .grid tr { page-break-inside: avoid; }
        .grid tfoot td { background: #ffc400; color: #111; font-weight: bold; text-transform: uppercase; }
        .right { text-align: right; white-space: nowrap; }
        .center { text-align: center; }
        .employee { white-space: normal; word-wrap: break-word; }
        .generated {
            position: fixed;
            left: 10mm;
            right: 10mm;
            bottom: 6mm;
            border-top: 1px solid #c8c8c8;
            color: #777;
            font-size: 7pt;
            padding-top: 5px;
            text-align: right;
        }
    </style>
</head>
<body>
<div class="report">
    <table class="header">
        <tr>
            <td class="header-logo-cell">
                <div class="logo-box">
                    <img class="header-logo" src="{{ !empty($coatImageSrc) ? $coatImageSrc : asset('images/Tanzania Coat of Arms_.png') }}" alt="Tanzania Coat of Arms">
                </div>
            </td>
            <td class="header-center">
                <div class="company-name">National Social Security Fund</div>
                <div class="company-detail">Nyerere Bridge</div>
            </td>
            <td class="header-logo-cell">
                <div class="logo-box">
                    <img class="header-logo" src="{{ !empty($logoImageSrc) ? $logoImageSrc : asset('images/nssf-log1.png') }}" alt="NSSF Logo">
                </div>
            </td>
        </tr>
    </table>

    <div class="document-title">Payroll Journal Voucher (JV) Sheet</div>

    <table class="info">
        <tr>
            <th>Payroll No.</th>
            <td>{{ $runInfo['payroll_number'] ?? 'N/A' }}</td>
            <th>Period</th>
            <td>{{ $runInfo['period_label'] ?? 'N/A' }}</td>
        </tr>
        <tr>
            <th>Status</th>
            <td>{{ $runInfo['status'] ?? 'N/A' }}</td>
            <th>Total Employees</th>
            <td>{{ number_format($employeeCount) }}</td>
        </tr>
    </table>

    <table class="grid" autosize="1">
        <colgroup>
            <col style="width: 5%;">
            <col style="width: 10%;">
            <col style="width: 24%;">
            <col style="width: 11%;">
            <col style="width: 11%;">
            <col style="width: 10%;">
            <col style="width: 10%;">
            <col style="width: 10%;">
            <col style="width: 9%;">
        </colgroup>
        <thead>
        <tr>
            <th>S/N</th>
            <th>PF No.</th>
            <th>Employee Name</th>
            <th>Basic Salary</th>
            <th>Net Pay</th>
            <th>PAYE</th>
            <th>PSSSF</th>
            <th>PSSSF Employer</th>
            <th>HESLB</th>
            <th>Loans</th>
        </tr>
        </thead>
        <tbody>
        @forelse($employees as $index => $employee)
            @php
                $employeeName = trim((string) ($employee->employee_name ?? ''));
            @endphp
            <tr>
                <td class="center">{{ $index + 1 }}</td>
                <td class="center">{{ $employee->pf_number }}</td>
                <td class="employee">{{ $employeeName }}</td>
                <td class="right">{{ number_format((float) ($employee->basic_salary), 2) }}</td>
                <td class="right">{{ number_format((float) ($employee->net_pay), 2) }}</td>
                <td class="right">{{ number_format((float) ($employee->paye), 2) }}</td>
                <td class="right">{{ number_format((float) ($employee->psssf_contribution), 2) }}</td>
                <td class="right">{{ number_format((float) ($employee->psssf_employer_contribution), 2) }}</td>
                <td class="right">{{ number_format((float) ($employee->heslb), 2) }}</td>
                <td class="right">{{ number_format((float) ($employee->total_loans ?? 0), 2) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="10" class="center">No employees found for this payroll run.</td>
            </tr>
        @endforelse
        </tbody>
        <tfoot>
        <tr>
            <td colspan="3" class="right">Grand Total</td>
            <td class="right">{{ number_format((float) $totalBasic, 2) }}</td>
            <td class="right">{{ number_format((float) $totalNet, 2) }}</td>
            <td class="right">{{ number_format((float) $totalPaye, 2) }}</td>
            <td class="right">{{ number_format((float) $totalPsssf, 2) }}</td>
            <td class="right">{{ number_format((float) $totalPsssfEmployer, 2) }}</td>
            <td class="right">{{ number_format((float) $totalHeslb, 2) }}</td>
            <td class="right">{{ number_format((float) $totalLoans, 2) }}</td>
        </tr>
        </tfoot>
    </table>
</div>

<div class="generated">Generated on {{ $generatedDate }}</div>
</body>
</html>
