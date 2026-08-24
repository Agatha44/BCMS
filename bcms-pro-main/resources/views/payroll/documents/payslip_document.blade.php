<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nyerere Bridge Staff Salary Slip</title>
    <style>
        *{ box-sizing: border-box; margin: 0; padding: 0; }
        body { color: #252525; font-family: Arial, sans-serif; font-size: 8.5pt; line-height: 1.25; margin: 0; padding: 6px; }

        .container {
            background-color: #fff;
            border: 1px solid #d8d8d8;
            margin: 0 auto;
            max-width: 800px;
            padding: 9px 12px 10px;
        }
        .bold-text { font-weight: bold; }
        .right { text-align: right; }
        .num { text-align: right; white-space: nowrap; }

        .top-panel { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .top-panel td { border: none; vertical-align: top; }
        .seal-cell { width: 84px; padding-right: 12px; }
        .right-seal-cell { padding-left: 12px; padding-right: 0; text-align: right; }
        .seal-box { border: 1px solid #d6d6d6; height: 58px; padding: 5px; text-align: center; }
        .brand-logo { height: auto; max-height: 46px; max-width: 46px; width: 46px; }
        .brand-cell { padding-top: 3px; text-align: center; }
        .brand-name { color: #922d2f; font-size: 10pt; font-weight: bold; letter-spacing: .2px; text-transform: uppercase; }
        .brand-detail { color: #555; font-size: 7.5pt; margin-top: 2px; }

        .summary-grid { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        .summary-grid td { vertical-align: top; }
        .summary-left { width: 50%; padding-right: 12px; }
        .summary-right { width: 50%; padding-left: 12px; }
        .employee-name {
            border-bottom: 1px solid #e1e1e1;
            color: #922d2f;
            font-size: 11pt;
            font-weight: bold;
            padding: 3px 0 2px;
        }
        .info-table { width: 100%; border-collapse: collapse; }
        .info-table td { border-bottom: 1px solid #ececec; padding: 3px 0; }
        .info-table td:first-child { color: #666; width: 42%; }
        .period-table { width: 100%; border-collapse: collapse; }
        .period-table td {
            border: 1px solid #e4e4e4;
            padding: 4px 7px;
            text-align: center;
        }
        .period-table .label {
            background: #922d2f;
            color: #fff;
            font-size: 7pt;
            font-weight: bold;
            text-transform: uppercase;
        }
        .period-table .value { font-weight: bold; }

        .pay-table { border-collapse: collapse; margin-bottom: 7px; width: 100%; }
        .earnings-table { margin-bottom: 18px; }
        .pay-table th {
            background-color: #922d2f;
            border: 1px solid #922d2f;
            color: #fff;
            font-size: 7.5pt;
            padding: 3px 6px;
            text-align: left;
            text-transform: uppercase;
        }
        .pay-table th.num { padding-right: 12px; text-align: right; }
        .pay-table td {
            border: 1px solid #dedede;
            padding: 3px 6px;
            vertical-align: top;
        }
        .pay-table .type-column { color: #666; width: 95px; }
        .pay-table .amount-column { padding-right: 12px; width: 135px; }
        .total-row td {
            background-color: #f6f6f6;
            border-color: #d8d8d8;
            color: #202020;
            font-weight: bold;
        }
        .summary-row td {
            background-color: #f6f6f6;
            font-weight: bold;
        }
        .net-pay-table { border-collapse: collapse; margin: 7px 0 10px auto; width: 52%; }
        .net-pay-table td { border: 1px solid #922d2f; padding: 5px 9px; }
        .net-pay-label {
            background: #922d2f;
            color: #fff;
            font-size: 9pt;
            font-weight: bold;
            text-transform: uppercase;
        }
        .net-pay-amount {
            background: #fff;
            color: #202020;
            font-size: 11pt;
            font-weight: bold;
            text-align: right;
            white-space: nowrap;
        }
        .print-info { border-top: 1px solid #ddd; color: #666; font-size: 7pt; margin-top: 8px; padding-top: 5px; }
    </style>
</head>
<body>
@php
    $m = (int) ($payroll_run['payroll_month'] ?? 0);
    $y = (int) ($payroll_run['payroll_year'] ?? 0);
    $payrollPeriod = ($m >= 1 && $m <= 12 && $y > 0)
        ? \Carbon\Carbon::create($y, $m, 1)->format('F-Y')
        : '';

    $benefits = $items['benefit'] ?? [];
    $arrears = $items['arrears'] ?? [];
    $deductions = $items['deduction'] ?? [];
    $loans = $items['loan'] ?? [];
    $others = $items['other'] ?? [];

    $coatSrc = !empty($coatImageSrc) ? $coatImageSrc : asset('images/Tanzania Coat of Arms_.png');
    $logoSrc = !empty($logoImageSrc)
        ? $logoImageSrc
        : (!empty($logoBase64) ? $logoBase64 : asset('images/nssf-log1.png'));

    $basicSalary = (float) ($transaction['basic_salary'] ?? 0);
    $grossPay = (float) ($transaction['gross_pay'] ?? 0);
    $paye = (float) ($transaction['paye'] ?? 0);
    $netPay = (float) ($transaction['net_pay'] ?? 0);

    $visibleDeductions = [];
    $deductionTotal = 0.0;
    foreach ($deductions as $deduction) {
        $deductionName = (string) ($deduction->display_name ?? '');
        $isPayeDeduction = stripos($deductionName, 'paye') !== false
            || stripos($deductionName, 'pay as you earn') !== false;

        if (!$isPayeDeduction) {
            $visibleDeductions[] = $deduction;
            $deductionTotal += (float) ($deduction->amount ?? 0);
        }
    }

    $loanTotal = 0.0;
    foreach ($loans as $loan) {
        $loanTotal += (float) ($loan->amount ?? 0);
    }

    $otherTotal = 0.0;
    foreach ($others as $other) {
        $otherTotal += (float) ($other->amount ?? 0);
    }

    $totalDeductions = $paye + $deductionTotal + $loanTotal;
@endphp
<div class="container">
    <table class="top-panel">
        <tr>
            <td class="seal-cell">
                <div class="seal-box">
                    <img class="brand-logo" src="{{ $coatSrc }}" alt="Tanzania Coat of Arms">
                </div>
            </td>
            <td class="brand-cell">
                <div class="brand-name">National Social Security Fund</div>
                <div class="brand-detail">Nyerere Bridge Staff Salary Slip</div>
            </td>
            <td class="seal-cell right-seal-cell">
                <div class="seal-box">
                    <img class="brand-logo" src="{{ $logoSrc }}" alt="NSSF Logo">
                </div>
            </td>
        </tr>
    </table>

    <table class="summary-grid">
        <tr>
            <td class="summary-left">
                <div class="employee-name">Employee: {{ $employee['name'] ?? '' }}</div>
                <table class="info-table">
                    <tr>
                        <td>Employee ID</td>
                        <td class="bold-text">{{ $employee['pf_number'] ?? '' }}</td>
                    </tr>
                    <tr>
                        <td>National ID</td>
                        <td class="bold-text">{{ $employee['national_id'] ?? '' }}</td>
                    </tr>
                </table>
            </td>
            <td class="summary-right">
                <table class="period-table">
                    <tr>
                        <td class="label">Pay Month</td>
                    </tr>
                    <tr>
                        <td class="value">{{ $payrollPeriod }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="pay-table earnings-table">
        <thead>
        <tr>
            <th>Earnings</th>
            <th class="type-column">Type</th>
            <th class="num amount-column">Amount</th>
        </tr>
        </thead>
        <tbody>
        <tr>
            <td>Basic Salary</td>
            <td class="type-column">Salary</td>
            <td class="num amount-column">{{ number_format($basicSalary, 2) }}</td>
        </tr>
        @foreach($benefits as $b)
            <tr>
                <td>{{ $b->display_name ?? '' }}</td>
                <td class="type-column">Benefit</td>
                <td class="num amount-column">{{ number_format((float)($b->amount ?? 0), 2) }}</td>
            </tr>
        @endforeach
        @foreach($arrears as $a)
            <tr>
                <td>{{ $a->display_name ?? '' }}</td>
                <td class="type-column">Allowance</td>
                <td class="num amount-column">{{ number_format((float)($a->amount ?? 0), 2) }}</td>
            </tr>
        @endforeach
        <tr class="total-row">
            <td colspan="2" class="right">Gross Pay</td>
            <td class="num amount-column">{{ number_format($grossPay, 2) }}</td>
        </tr>
        </tbody>
    </table>

    <table class="pay-table">
        <thead>
        <tr>
            <th>Deductions</th>
            <th class="type-column">Type</th>
            <th class="num amount-column">Amount</th>
        </tr>
        </thead>
        <tbody>
        <tr>
            <td>PAYE</td>
            <td class="type-column">Tax</td>
            <td class="num amount-column">{{ number_format($paye, 2) }}</td>
        </tr>
        @foreach($visibleDeductions as $d)
            <tr>
                <td>{{ $d->display_name ?? '' }}</td>
                <td class="type-column">Deduction</td>
                <td class="num amount-column">{{ number_format((float)($d->amount ?? 0), 2) }}</td>
            </tr>
        @endforeach
        @foreach($loans as $l)
            <tr>
                <td>{{ $l->display_name ?? '' }}</td>
                <td class="type-column">Loan</td>
                <td class="num amount-column">{{ number_format((float)($l->amount ?? 0), 2) }}</td>
            </tr>
        @endforeach
        <tr class="total-row">
            <td colspan="2" class="right">Total Deductions</td>
            <td class="num amount-column">{{ number_format($totalDeductions, 2) }}</td>
        </tr>
        </tbody>
    </table>

    @if(!empty($others) && count($others) > 0)
        <table class="pay-table">
            <thead>
            <tr>
                <th>Other Adjustments</th>
                <th class="type-column">Type</th>
                <th class="num amount-column">Amount</th>
            </tr>
            </thead>
            <tbody>
            @foreach($others as $o)
                <tr>
                    <td>{{ $o->display_name ?? '' }}</td>
                    <td class="type-column">Other</td>
                    <td class="num amount-column">{{ number_format((float)($o->amount ?? 0), 2) }}</td>
                </tr>
            @endforeach
            <tr class="summary-row">
                <td colspan="2" class="right">Total Other</td>
                <td class="num amount-column">{{ number_format($otherTotal, 2) }}</td>
            </tr>
            </tbody>
        </table>
    @endif

    <table class="net-pay-table">
        <tr>
            <td class="net-pay-label">Net Pay</td>
            <td class="net-pay-amount">{{ number_format($netPay, 2) }}</td>
        </tr>
    </table>

    <div class="print-info">
        Generated by {{ $generatedBy ?? 'Unknown' }} on {{ now()->format('d-M-Y H:i:s') }}.
    </div>
</div>
</body>
</html>
