<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll Transactions</title>
    <style>
        @page { margin: 10mm; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 7.4pt;
            color: #222;
            line-height: 1.25;
            margin: 0;
            padding-bottom: 16mm;
        }

        table { width: 100%; border-collapse: collapse; }
        th, td { vertical-align: middle; }

        .report { width: 94%; margin: 0 auto; }
        .brand-header td { border: none; text-align: center; vertical-align: middle; }
        .brand-logo-cell { width: 18%; padding: 2px 8px 8px; }
        .brand-logo-box { border: 1px solid #8d8d8d; min-height: 46px; padding: 8px 5px; }
        .brand-logo { width: 54px; max-width: 54px; max-height: 54px; height: auto; }
        .brand-center { width: 64%; padding: 3px 8px 8px; }
        .brand-name { font-size: 8.6pt; font-weight: bold; text-transform: uppercase; }
        .brand-detail { color: #555; font-size: 7pt; margin-top: 3px; }
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
        .period-title { margin: -3px 0 8px 0; text-align: center; font-size: 8pt; font-weight: bold; }
        .bold-text { font-weight: bold; }
        .muted { color: #666; }

        .section-header {
            background-color: #922d2f;
            color: #fff;
            padding: 4px 6px;
            font-size: 7pt;
            font-weight: bold;
            text-transform: uppercase;
            border: 1px solid #922d2f;
            margin-bottom: 4px;
        }

        .meta { margin-bottom: 8px; }
        .meta th, .meta td { border: 1px solid #9d9d9d; padding: 4px 7px; font-size: 7pt; }
        .meta th {
            background: #f2f2f2;
            color: #333;
            text-align: left;
            text-transform: uppercase;
            width: 14%;
        }
        .meta td { font-size: 7.6pt; }

        .grid { table-layout: fixed; }
        .grid th, .grid td { border: 1px solid #9d9d9d; padding: 4px 5px; height: 20px; word-wrap: break-word; }
        .grid th {
            background-color: #922d2f;
            color: #fff;
            font-size: 6.2pt;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
        }
        .grid td { font-size: 6.8pt; }
        .grid thead { display: table-header-group; }
        .grid tr { page-break-inside: avoid; }
        .grid tfoot td {
            background-color: #ffc400;
            color: #111;
            font-weight: bold;
            text-transform: uppercase;
        }

        .num { text-align: right; white-space: nowrap; }
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
@php
    $m = (int) ($payroll_run['payroll_month'] ?? 0);
    $y = (int) ($payroll_run['payroll_year'] ?? 0);
    $period = ($m >= 1 && $m <= 12 && $y > 0)
        ? \Carbon\Carbon::create($y, $m, 1)->format('F Y')
        : '';

    $rows = $transactions ?? [];
    $count = is_countable($rows) ? count($rows) : 0;

    $sumGross = 0.0;
    $sumBasic = 0.0;
    $sumArrears = 0.0;
    $sumPaye = 0.0;
    $sumDeductions = 0.0;
    $sumPsssf = 0.0;
    $sumPsssfEmployer = 0.0;
    $sumNet = 0.0;
@endphp

<div class="report">
    <table class="brand-header">
        <tr>
            <td class="brand-logo-cell">
                <div class="brand-logo-box">
                    <img class="brand-logo" src="{{ !empty($coatImageSrc) ? $coatImageSrc : asset('images/Tanzania Coat of Arms_.png') }}" alt="Tanzania Coat of Arms">
                </div>
            </td>
            <td class="brand-center">
                <div class="brand-name">National Social Security Fund</div>
                <div class="brand-detail">Nyerere Bridge</div>
            </td>
            <td class="brand-logo-cell">
                <div class="brand-logo-box">
                    @if(!empty($logoImageSrc))
                        <img class="brand-logo" src="{{ $logoImageSrc }}" alt="NSSF Logo">
                    @elseif(!empty($logoBase64))
                        <img class="brand-logo" src="{{ $logoBase64 }}" alt="NSSF Logo">
                    @else
                        <img class="brand-logo" src="{{ asset('images/nssf-log1.png') }}" alt="NSSF Logo">
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <div class="document-title">Nyerere Bridge Payroll Transactions</div>
    @if($period !== '')
        <div class="period-title">{{ $period }}</div>
    @endif

    <div class="section-header">Summary</div>
    <table class="meta">
        <tr>
            <th>Payroll Number</th>
            <td class="bold-text">{{ $payroll_run['payroll_number'] ?? '' }}</td>
            <th>Status</th>
            <td class="bold-text">{{ $payroll_run['status'] ?? '' }}</td>
        </tr>
        <tr>
            <th>Employees</th>
            <td class="bold-text" colspan="3">{{ number_format((int) $count) }}</td>
        </tr>
    </table>

    <table class="grid" autosize="1">
        <colgroup>
            <col style="width: 4%;">
            <col style="width: 7%;">
            <col style="width: 18%;">
            <col style="width: 9%;">
            <col style="width: 9%;">
            <col style="width: 8%;">
            <col style="width: 8%;">
            <col style="width: 9%;">
            <col style="width: 9%;">
            <col style="width: 9%;">
            <col style="width: 10%;">
        </colgroup>
        <thead>
        <tr>
            <th>S/N</th>
            <th>PF No.</th>
            <th>Employee Name</th>
            <th>Gross</th>
            <th>Basic Salary</th>
            <th>Arrears</th>
            <th>PAYE</th>
            <th>Deductions</th>
            <th>PSSSF Emp.</th>
            <th>PSSSF Emplr.</th>
            <th>Net Pay</th>
        </tr>
        </thead>
        <tbody>
        @forelse($rows as $i => $t)
            @php
                $gross = (float) ($t->gross_pay ?? 0);
                $basic = (float) ($t->basic_salary ?? 0);
                $arrears = (float) ($t->total_arrears ?? 0);
                $paye = (float) ($t->paye ?? 0);
                $ded = (float) ($t->total_deductions ?? 0);
                $psssf = (float) ($t->psssf_contribution ?? 0);
                $psssfEmployer = (float) ($t->psssf_employer_contribution ?? 0);
                $net = (float) ($t->net_pay ?? 0);

                $sumGross += $gross;
                $sumBasic += $basic;
                $sumArrears += $arrears;
                $sumPaye += $paye;
                $sumDeductions += $ded;
                $sumPsssf += $psssf;
                $sumPsssfEmployer += $psssfEmployer;
                $sumNet += $net;
            @endphp
            <tr>
                <td class="center">{{ (int) $i + 1 }}</td>
                <td class="center">{{ $t->pf_number ?? '' }}</td>
                <td class="employee">{{ $t->employee_name ?? '' }}</td>
                <td class="num">{{ number_format($gross, 2) }}</td>
                <td class="num">{{ number_format($basic, 2) }}</td>
                <td class="num">{{ number_format($arrears, 2) }}</td>
                <td class="num">{{ number_format($paye, 2) }}</td>
                <td class="num">{{ number_format($ded, 2) }}</td>
                <td class="num">{{ number_format($psssf, 2) }}</td>
                <td class="num">{{ number_format($psssfEmployer, 2) }}</td>
                <td class="num">{{ number_format($net, 2) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="11" class="center muted" style="padding: 10px;">No payroll transactions found.</td>
            </tr>
        @endforelse
        </tbody>
        <tfoot>
        <tr>
            <td colspan="3" class="num">Grand Total</td>
            <td class="num">{{ number_format((float) $sumGross, 2) }}</td>
            <td class="num">{{ number_format((float) $sumBasic, 2) }}</td>
            <td class="num">{{ number_format((float) $sumArrears, 2) }}</td>
            <td class="num">{{ number_format((float) $sumPaye, 2) }}</td>
            <td class="num">{{ number_format((float) $sumDeductions, 2) }}</td>
            <td class="num">{{ number_format((float) $sumPsssf, 2) }}</td>
            <td class="num">{{ number_format((float) $sumPsssfEmployer, 2) }}</td>
            <td class="num">{{ number_format((float) $sumNet, 2) }}</td>
        </tr>
        </tfoot>
    </table>
</div>

<div class="generated">
    Generated by {{ $generatedBy ?? 'Unknown' }} on {{ now()->format('d-M-Y H:i:s') }}.
</div>
</body>
</html>
