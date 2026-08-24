<!DOCTYPE html>
<html lang="eng">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
    <title>Receipt</title>
    <style>
        body { font-family: sans-serif; font-size: 10pt; }
        table.ctable { width: 100%; border-collapse: collapse; }
        th { text-align: left; white-space: nowrap; font-weight: 500; padding: 8px; }
        td { padding: 8px; }
        h2, h3 { margin: 0; }
        h3 { text-align: center; margin: 20px 0; }
    </style>
</head>
<body>
<table style="width: 100%">
    <tr>
        <td rowspan="3" style="width: 100px">
            @if(!empty($logoBase64))
                <img alt="NSSF LOGO" src="{{ $logoBase64 }}" style="height:110px">
            @endif
        </td>
        <td colspan="2" style="height: 45px"></td>
    </tr>
    <tr>
        <td colspan="2">
            <h2 style="font-size: 19px;">NATIONAL SOCIAL SECURITY FUND</h2>
            <h2 style="font-size: 11.35px; font-weight: normal;">P.O.BOX 1322, Dar es Salaam, TANZANIA</h2>
        </td>
    </tr>
</table>
<h3>Standard Receipt</h3>
<table class="ctable" style="font-size: 9pt; margin-top: 25px;">
    <tr>
        <th>Receipt Number:</th>
        <td>{{ $receipt_data['receipt_number'] }}</td>
        <th>Receipt Date:</th>
        <td>{{ strtoupper(\Carbon\Carbon::parse($receipt_data['payment_date'])->format('d-M-Y')) }}</td>
    </tr>
    <tr>
        <th>Bank Reference:</th>
        <td>{{ $receipt_data['psp_receipt_num'] }}</td>
        <th>Receipt Amount:</th>
        <td>{{ number_format($receipt_data['amount'], 2) }}</td>
    </tr>
    <tr>
        <th>Amount in Words:</th>
        <td colspan="3">{{ $receipt_data['amount_word'] }}</td>
    </tr>
    <tr>
        <th>Receipt Description:</th>
        <td colspan="3">{{ $receipt_data['bill_desc'] ?? 'Advertisement Charges' }}</td>
    </tr>
    <tr>
        <th>Payment Mode:</th>
        <td>E-PAYMENT</td>
        <th>Bank Date:</th>
        <td>{{ $receipt_data['trx_dt_tm'] ? strtoupper(\Carbon\Carbon::parse($receipt_data['trx_dt_tm'])->format('d-M-Y')) : '-' }}</td>
    </tr>
    <tr>
        <th>Payment Type:</th>
        <td>Advertisement Billing</td>
        <th>Control Number:</th>
        <td>{{ $receipt_data['contr_num'] ?? '-' }}</td>
    </tr>
    <tr>
        <th>Advertisement Type:</th>
        <td colspan="3">{{ $receipt_data['advertisement_type'] ?? '-' }}</td>
    </tr>
    <tr>
        <th>Payer Name:</th>
        <td colspan="3">{{ $receipt_data['payer_name'] ?? '-' }}</td>
    </tr>
    @if(!empty($receipt_data['phone_number']))
    <tr>
        <th>Phone Number:</th>
        <td colspan="3">{{ $receipt_data['phone_number'] }}</td>
    </tr>
    @endif
</table>
</body>
</html>
