<!DOCTYPE html>
<html lang="eng">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
    <meta http-equiv="X-UA-Compatible" content="IE=edge"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <title>Receipt</title>
    <style>
        body {
            font-family: sans-serif;
            font-size: 10pt;
            position: relative;
        }

        p {
            margin: 0;
        }

        table.ctable {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            white-space: nowrap;
            font-weight: 500;
            padding: 8px;
        }

        td {
            padding: 8px;
        }

        .text-capitalize {
            text-transform: capitalize;
        }

        h2 {
            margin: 0;
        }

        h3 {
            text-align: center;
            margin: 20px 0;
        }
    </style>
</head>
<body>
<table style="width: 100%">
    <thead>
    <tr>
        <td rowspan="3" style="width: 100px">
            @if(isset($logoBase64) && !empty($logoBase64))
                <img alt="NSSF LOGO" src="{{$logoBase64}}" style="height:110px">
            @else
                <img alt="NSSF LOGO" src="{{asset('images/nssf-log1.png')}}" style="height:110px">
            @endif
        </td>
        <td colspan="2" style="height: 45px"></td>
    </tr>
    <tr>
        <td colspan="2" style="width: 100%">
            <h2 style="font-size: 19px;">NATIONAL SOCIAL SECURITY FUND</h2>
            <h2 style="font-size: 11.35px; font-weight: normal; display: block; justify-content: space-between; width: 100%">
                <span>P.O.BOX 1322, Dar es Salaam, TANZANIA</span>
                <span>Tel: +255 22 2163499-19</span>
            </h2>
        </td>
    </tr>
    <tr>
        <td style="height: 45px"><strong>TIN</strong> 100-208-628</td>
        <td style="height: 45px; text-align: right"><strong>VRN</strong> 100-009429-R</td>
    </tr>
    </thead>
</table>
<h3 style="text-align: center">Standard Receipt</h3>
<table class="ctable" style="font-size: 9pt; margin-top: 25px; width: 100%; border-collapse: collapse;" cellpadding="8">
    <tr>
        <th style="white-space: nowrap">Receipt Number:</th>
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
        <td colspan="3" class="text-capitalize">
            {{ $receipt_data['amount_word'] }}
        </td>
    </tr>
    <tr>
        <th style="vertical-align: top">Receipt Description:</th>
        <td colspan="3">{{ $receipt_data['event_description'] ?? 'Event Payment' }}</td>
    </tr>

    <tr>
        <td colspan="4">&nbsp;</td>
    </tr>
    <tr>
        <th>Payment Mode:</th>
        <td>E-PAYMENT</td>
        <th>Bank Date:</th>
        <td>{{ $receipt_data['trx_dt_tm'] ? strtoupper(\Carbon\Carbon::parse($receipt_data['trx_dt_tm'])->format('d-M-Y')) : '-' }}</td>
    </tr>
    <tr>
        <th>Payment Type:</th>
        <td>Event Payment</td>
    </tr>

    <tr>
        <td colspan="4">&nbsp;</td>
    </tr>
    <tr>
        <th>Event Date:</th>
        <td colspan="3">{{ $receipt_data['event_date'] ? \Carbon\Carbon::parse($receipt_data['event_date'])->format('d-M-Y') : '-' }}</td>
    </tr>
    <tr>
        <th>Payer Name:</th>
        <td colspan="3">{{ $receipt_data['payer_name'] ?? '-' }}</td>
    </tr>
    @if(!empty($receipt_data['receiver_name']))
    <tr>
        <th>Receiver Name:</th>
        <td colspan="3">{{ $receipt_data['receiver_name'] }}</td>
    </tr>
    @endif
</table>
</body>
</html>

