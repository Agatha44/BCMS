<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Overtime Internal Memo</title>
    <style>
        body {
            font-family: Calibri, Arial, sans-serif;
            font-size: 11pt;
            color: #000;
            margin: 0;
            padding: 15px;
        }

        p {
            margin: 0;
            padding: 0;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
        }

        .header img {
            height: 10px !important;
            max-height: 10px !important;
            width: auto !important;
            max-width: 50px !important;
        }

        .header h1 {
            margin: 10px 0;
            font-size: 16pt;
            font-weight: bold;
        }

        .header h2 {
            margin: 5px 0;
            font-size: 14pt;
            font-weight: normal;
        }

        .invoice-info {
            margin-bottom: 15px;
        }

        .invoice-info p {
            margin: 3px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        table th,
        table td {
            border: 1px solid #000;
            padding: 8px;
            text-align: left;
        }

        table th {
            background-color: #f0f0f0;
            font-weight: bold;
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .footer {
            margin-top: 30px;
        }

        .footer table {
            border: none;
        }

        .footer table td {
            border: none;
            padding: 5px;
        }

        .total-row {
            font-weight: bold;
            background-color: #f0f0f0;
        }
    </style>
</head>
<body>
<div class="header">
    <h1>NATIONAL SOCIAL SECURITY FUND</h1>
    @if(!empty($logoBase64))
        <img src="{{ $logoBase64 }}" alt="NSSF Logo" style="height: 90px; max-height: 90px; width: auto; max-width: 90px;">
    @else
        <img src="{{ asset('images/nssf-log1.png') }}" alt="NSSF Logo" style="height: 90px; max-height: 90px; width: auto; max-width: 90px;">
    @endif
    <h2>INTERNAL MEMORANDUM</h2>
</div>

<div class="invoice-info">
    <p><strong>From:</strong> {{ $batchInfo->reviewed_by ?? 'N/A' }}</p>
    <p><strong>To:</strong> {{ 'INVESTMENT MANAGER' }}</p>
    <p><strong>Date:</strong> {{ $batchInfo->created_at ? \Carbon\Carbon::parse($batchInfo->created_at)->format('d-M-Y') : 'N/A' }}</p>
    <p><strong>Ref:</strong> {{ $batchInfo->batch_number ?? 'N/A' }}</p>
    <p><strong>Subject:</strong> Request for payment of overtime for {{ $batchInfo->month ?? 'the period indicated' }}</p>
</div>

<p style="margin-top: 15px; text-align: justify;">
    This is to submit for your consideration and approval the payment of overtime allowance to staff who
    performed overtime duties during the period of
    <strong>{{ $batchInfo->month }}</strong>. The details of the affected employees and the
    computed overtime amounts are as per the schedule below.
</p>

<p style="margin-top: 10px; text-align: justify;">
    The total overtime amount requested for settlement is
    <strong>TZS {{ number_format($batchInfo->total_batch_amount ?? 0, 2) }}</strong>.
</p>

<p style="margin-top: 10px; margin-bottom: 15px; text-align: justify;">
    Kindly review and approve the payment as per the attached schedule.
</p>

<table>
    <thead>
    <tr>
        <th style="width: 5%;">S/N</th>
        <th style="width: 15%;">PF Number</th>
        <th style="width: 30%;">Employee Name</th>
        <th style="width: 10%;" class="text-right">Daily Rate</th>
        <th style="width: 10%;" class="text-right">Total Days</th>
        <th style="width: 10%;" class="text-right">Overtime Hours</th>
        <th style="width: 15%;" class="text-right">Amount (TZS)</th>
    </tr>
    </thead>
    <tbody>
    @forelse($employees as $index => $employee)
        <tr>
            <td class="text-center">{{ $index + 1 }}</td>
            <td>{{ $employee->pf_number ?? 'N/A' }}</td>
            <td>{{ $employee->full_name ?? ($employee->fname ?? '') . ' ' . ($employee->mname ?? '') . ' ' . ($employee->sname ?? '') }}</td>
            <td class="text-right">{{ number_format($employee->daily_rate ?? 0, 2) }}</td>
            <td class="text-right">{{ $employee->total_days ?? 0 }}</td>
            <td class="text-right">{{ number_format($employee->total_overtime_hours ?? 0, 2) }}</td>
            <td class="text-right">{{ number_format($employee->total_amount ?? 0, 2) }}</td>
        </tr>
    @empty
        <tr>
            <td colspan="7" class="text-center">No employees found in this batch</td>
        </tr>
    @endforelse
    </tbody>
    <tfoot>
    <tr class="total-row">
        <td colspan="6" class="text-right"><strong>TOTAL BATCH AMOUNT:</strong></td>
        <td class="text-right"><strong>{{ number_format($batchInfo->total_batch_amount ?? 0, 2) }}</strong></td>
    </tr>
    </tfoot>
</table>

<div class="footer">
    <table>
        <tr>
            <td style="width: 50%;"><strong>Reviewed By:</strong> {{ $batchInfo->reviewed_by ?? 'N/A' }}</td>
            <td style="width: 50%;"><strong>Date:</strong> {{ $batchInfo->created_at ? \Carbon\Carbon::parse($batchInfo->created_at)->format('d-M-Y') : 'N/A' }}</td>
        </tr>
        <tr>
            <td><strong>Examiner Signature:</strong> _________________</td>
            <td><strong>Date:</strong> _________________</td>
        </tr>
    </table>
</div>
</body>
</html>

