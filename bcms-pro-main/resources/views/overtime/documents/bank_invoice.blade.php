<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Bank Export - Batch {{ $batchNumber }}</title>
    <style>
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 10px;
            margin: 0;
            padding: 10px;
        }
        .header {
            text-align: center;
            margin-bottom: 15px;
        }
        .header h1 {
            font-size: 16px;
            margin: 5px 0;
        }
        .header p {
            margin: 3px 0;
            font-size: 11px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            border: 1px solid #000;
            padding: 4px 6px;
            text-align: left;
            font-size: 9px;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .summary {
            margin-top: 15px;
            padding: 10px;
            border: 1px solid #000;
            font-size: 11px;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin: 5px 0;
        }
    </style>
</head>
<body>
<div class="header">
    <h1>BMS Invoice Export</h1>
    <p><strong>Batch Number:</strong> {{ $batchNumber }}</p>
    @if($bank)
        <p><strong>Bank:</strong> {{ $bank->bank_name }} ({{ $bank->short_name }})</p>
    @endif
    @if($invoiceNumber)
        <p><strong>Invoice Number:</strong> {{ $invoiceNumber }}</p>
    @endif
    <p><strong>Generated:</strong> {{ now()->format('Y-m-d H:i:s') }}</p>
</div>

<table>
    <thead>
    <tr>
        <th>SN</th>
        <th>Employee Name</th>
        <th>Payroll No</th>
        <th>Bank</th>
        <th>Bank Code</th>
        <th>Branch</th>
        <th>Account No</th>
        <th>Net Pay</th>
        <th>Cheque No</th>
        <th>Processing Date</th>
        <th>Description</th>
        <th>Currency</th>
        <th>Channel</th>
        <th>Dest Code</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($rows as $row)
        <tr>
            <td>{{ $row['SN'] }}</td>
            <td>{{ $row['EMPNAME'] }}</td>
            <td>{{ $row['PAYROLLNO'] }}</td>
            <td>{{ $row['BANK'] }}</td>
            <td>{{ $row['BCODE'] }}</td>
            <td>{{ $row['BRANCH'] }}</td>
            <td>{{ $row['ACCNO'] }}</td>
            <td style="text-align: right;">{{ number_format($row['NETPAY'], 2) }}</td>
            <td>{{ $row['CHEQUENO'] }}</td>
            <td>{{ $row['PROCESSINGDATE'] }}</td>
            <td>{{ $row['DESCRIPTION'] }}</td>
            <td>{{ $row['CURRENCY_CODE'] }}</td>
            <td>{{ $row['CHANNEL'] }}</td>
            <td>{{ $row['DESTCODE'] ?? '-' }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<div class="summary">
    <div class="summary-row">
        <strong>Total Records:</strong> {{ $totalCount }}
    </div>
    <div class="summary-row">
        <strong>Total Amount:</strong> TZS {{ number_format($totalAmount, 2) }}
    </div>
</div>
</body>
</html>

