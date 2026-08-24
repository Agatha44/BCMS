@php
    $rowCollection = collect($rows ?? []);
    $rowCount = $rowCollection->count();
    $totalPrincipal = $rowCollection->sum('principal_amount');
    $totalInterest = $rowCollection->sum('interest_amount');
    $totalRepayment = $rowCollection->sum('total_repayment');
    $generatedDate = now()->format('d M Y, H:i');
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $documentTitle ?? 'Staff Loans Report' }}</title>
    <style>
        @page { margin: 10mm; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 7pt;
            color: #222;
            margin: 0;
            padding-bottom: 16mm;
        }
        table { width: 100%; border-collapse: collapse; }
        th, td { vertical-align: middle; line-height: 1.25; }
        .report { width: 96%; margin: 0 auto; }
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
        .info th, .info td, .summary th, .summary td, .grid th, .grid td { border: 1px solid #9d9d9d; padding: 4px 6px; }
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
        }
        .summary td { font-size: 8pt; font-weight: bold; text-align: right; }
        .info td { font-size: 7.6pt; }
        .grid { table-layout: fixed; }
        .grid th, .grid td { height: 18px; word-wrap: break-word; }
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
        .employee, .loan-name { white-space: normal; word-wrap: break-word; }
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

    <div class="document-title">{{ $documentTitle ?? 'Staff Loans Report' }}</div>

    <table class="info">
        <tr>
            <th>Payroll No.</th>
            <td>{{ $runInfo['payroll_number'] ?? '' }}</td>
            <th>Period</th>
            <td>{{ $runInfo['period_label'] ?? '' }}</td>
        </tr>
        <tr>
            <th>Status</th>
            <td>{{ $runInfo['status'] ?? '' }}</td>
            <th>Loan Rows</th>
            <td>{{ number_format($rowCount) }}</td>
        </tr>
    </table>

    <table class="summary">
        <thead>
        <tr>
            <th>Total Principal</th>
            <th>Total Interest</th>
            <th>Total Repayment</th>
        </tr>
        </thead>
        <tbody>
        <tr>
            <td>{{ number_format((float) $totalPrincipal, 2) }}</td>
            <td>{{ number_format((float) $totalInterest, 2) }}</td>
            <td>{{ number_format((float) $totalRepayment, 2) }}</td>
        </tr>
        </tbody>
    </table>

    <table class="grid" autosize="1">
        <thead>
        <tr>
            <th>S/N</th>
            <th>PF No.</th>
            <th>Employee Name</th>
            <th>Loan Type</th>
            <th>Reference</th>
            <th>Principal</th>
            <th>Interest</th>
            <th>Repayment</th>
        </tr>
        </thead>
        <tbody>
        @forelse($rows as $index => $row)
            <tr>
                <td class="center">{{ $index + 1 }}</td>
                <td class="center">{{ $row->pf_number ?? '' }}</td>
                <td class="employee">{{ $row->employee_name ?? '' }}</td>
                <td class="loan-name">{{ trim(($row->loan_name ?? '').(($row->loan_code ?? '') !== '' ? ' ('.$row->loan_code.')' : '')) }}</td>
                <td class="center">{{ $row->loan_reference_number ?? '' }}</td>
                <td class="right">{{ number_format((float) ($row->principal_amount ?? 0), 2) }}</td>
                <td class="right">{{ number_format((float) ($row->interest_amount ?? 0), 2) }}</td>
                <td class="right">{{ number_format((float) ($row->total_repayment ?? 0), 2) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="8" class="center">No loan deductions for this payroll period.</td>
            </tr>
        @endforelse
        </tbody>
        <tfoot>
        <tr>
            <td colspan="5" class="right">Grand Total</td>
            <td class="right">{{ number_format((float) $totalPrincipal, 2) }}</td>
            <td class="right">{{ number_format((float) $totalInterest, 2) }}</td>
            <td class="right">{{ number_format((float) $totalRepayment, 2) }}</td>
        </tr>
        </tfoot>
    </table>
</div>

<div class="generated">Generated on {{ $generatedDate }}</div>
</body>
</html>
