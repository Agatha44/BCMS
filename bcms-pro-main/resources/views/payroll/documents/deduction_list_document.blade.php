@php
    $employeeCollection = collect($employees ?? []);
    $employeeCount = $employeeCollection->count();
    $amountField = (string) ($amountField ?? 'amount');
    $employerAmountField = isset($employerAmountField) ? (string) $employerAmountField : null;
    $basicSalaryField = isset($basicSalaryField) ? (string) $basicSalaryField : null;
    $hasEmployerColumn = $employerAmountField !== null && $employerAmountField !== '';
    $hasBasicSalaryColumn = $basicSalaryField !== null && $basicSalaryField !== '';
    $totalAmount = $employeeCollection->sum($amountField);
    $totalEmployerAmount = $hasEmployerColumn ? $employeeCollection->sum($employerAmountField) : 0;
    $totalBasicSalary = $hasBasicSalaryColumn ? $employeeCollection->sum($basicSalaryField) : 0;
    $grandTotal = $totalAmount + $totalEmployerAmount;
    $totalColumns = 3 + ($hasBasicSalaryColumn ? 1 : 0) + 1 + ($hasEmployerColumn ? 1 : 0);
    $generatedDate = now()->format('d M Y, H:i');
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $documentTitle ?? 'Payroll Deduction Sheet' }}</title>
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
        .summary { margin-bottom: 10px; }
        .info th, .info td, .summary th, .summary td, .grid th, .grid td { border: 1px solid #9d9d9d; padding: 5px 7px; }
        .info th {
            background: #f2f2f2;
            color: #333;
            font-size: 7pt;
            text-align: left;
            text-transform: uppercase;
            width: 13%;
        }
        .summary th {
            background: #f2f2f2;
            color: #333;
            font-size: 7pt;
            text-align: center;
            text-transform: uppercase;
            width: auto;
        }
        .summary td {
            font-size: 8pt;
            font-weight: bold;
            text-align: right;
        }
        .info td { font-size: 7.6pt; }
        .info .total-value { font-weight: bold; }
        .grid { table-layout: fixed; }
        .grid th, .grid td { height: 20px; word-wrap: break-word; }
        .grid th {
            background: #922d2f;
            color: #fff;
            font-size: 6.5pt;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
        }
        .grid td { font-size: 7pt; }
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

    <div class="document-title">{{ $documentTitle ?? 'Payroll Deduction Sheet' }}</div>

    <table class="info">
        <tr>
            <th>Payroll No.</th>
            <td>{{ $runInfo['payroll_number'] }}</td>
            <th>Period</th>
            <td>{{ $runInfo['period_label'] }}</td>
        </tr>
        <tr>
            <th>Status</th>
            <td>{{ $runInfo['status'] }}</td>
            <th>Employees Listed</th>
            <td>{{ number_format($employeeCount) }}</td>
        </tr>
        @if(!$hasEmployerColumn)
        <tr>
            @if($hasBasicSalaryColumn)
            <th>Total {{ $basicSalaryColumnLabel ?? 'Basic Salary' }}</th>
            <td class="total-value">{{ number_format((float) $totalBasicSalary, 2) }}</td>
            @else
            <th></th>
            <td></td>
            @endif
            <th>Total {{ $amountColumnLabel ?? 'Amount' }}</th>
            <td class="total-value">{{ number_format((float) $totalAmount, 2) }}</td>
        </tr>
        @endif
    </table>

    @if($hasEmployerColumn)
    <table class="summary">
        <thead>
        <tr>
            <th>{{ $amountColumnLabel ?? 'Employee Contribution' }}</th>
            <th>{{ $employerAmountColumnLabel ?? 'Employer Contribution' }}</th>
            <th>Total</th>
        </tr>
        </thead>
        <tbody>
        <tr>
            <td>{{ number_format((float) $totalAmount, 2) }}</td>
            <td>{{ number_format((float) $totalEmployerAmount, 2) }}</td>
            <td>{{ number_format((float) $grandTotal, 2) }}</td>
        </tr>
        </tbody>
    </table>
    @endif

    <table class="grid" autosize="1">
        <colgroup>
            @if($hasEmployerColumn && $hasBasicSalaryColumn)
            <col style="width: 5%;">
            <col style="width: 10%;">
            <col style="width: 28%;">
            <col style="width: 15%;">
            <col style="width: 21%;">
            <col style="width: 21%;">
            @elseif($hasEmployerColumn)
            <col style="width: 6%;">
            <col style="width: 12%;">
            <col style="width: 40%;">
            <col style="width: 21%;">
            <col style="width: 21%;">
            @elseif($hasBasicSalaryColumn)
            <col style="width: 6%;">
            <col style="width: 11%;">
            <col style="width: 35%;">
            <col style="width: 18%;">
            <col style="width: 30%;">
            @else
            <col style="width: 8%;">
            <col style="width: 14%;">
            <col style="width: 48%;">
            <col style="width: 30%;">
            @endif
        </colgroup>
        <thead>
        <tr>
            <th>S/N</th>
            <th>PF No.</th>
            <th>Employee Name</th>
            @if($hasBasicSalaryColumn)
            <th>{{ $basicSalaryColumnLabel ?? 'Basic Salary' }}</th>
            @endif
            <th>{{ $amountColumnLabel ?? 'Amount' }}</th>
            @if($hasEmployerColumn)
            <th>{{ $employerAmountColumnLabel ?? 'Employer Contribution' }}</th>
            @endif
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
                @if($hasBasicSalaryColumn)
                <td class="right">{{ number_format((float) ($employee->{$basicSalaryField}), 2) }}</td>
                @endif
                <td class="right">{{ number_format((float) ($employee->{$amountField}), 2) }}</td>
                @if($hasEmployerColumn)
                <td class="right">{{ number_format((float) ($employee->{$employerAmountField}), 2) }}</td>
                @endif
            </tr>
        @empty
            <tr>
                <td colspan="{{ $totalColumns }}" class="center">No employees with amounts for this sheet.</td>
            </tr>
        @endforelse
        </tbody>
        <tfoot>
        <tr>
            <td colspan="3" class="right">Grand Total</td>
            @if($hasBasicSalaryColumn)
            <td class="right">{{ number_format((float) $totalBasicSalary, 2) }}</td>
            @endif
            <td class="right">{{ number_format((float) $totalAmount, 2) }}</td>
            @if($hasEmployerColumn)
            <td class="right">{{ number_format((float) $totalEmployerAmount, 2) }}</td>
            @endif
        </tr>
        </tfoot>
    </table>
</div>

<div class="generated">Generated on {{ $generatedDate }}</div>
</body>
</html>
