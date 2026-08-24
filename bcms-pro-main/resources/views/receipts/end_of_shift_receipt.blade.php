<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
    <title>{{ $receipt_data['receipt_number'] ?? 'Receipt' }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10pt;
            color: #000;
            margin: 0;
            padding: 0;
        }

        p {
            margin: 0;
        }

        table.ctable {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        table.ctable th {
            text-align: left;
            font-weight: 600;
            padding: 7px 8px;
            vertical-align: top;
            width: 22%;
            white-space: nowrap;
        }

        table.ctable td {
            text-align: left;
            padding: 7px 8px;
            vertical-align: top;
            width: 28%;
            word-wrap: break-word;
        }

        table.ctable tr.row-full th {
            width: 22%;
        }

        table.ctable tr.row-full td {
            width: 78%;
        }

        h2 {
            margin: 0;
        }

        h3 {
            text-align: center;
            margin: 18px 0 14px;
            font-size: 13pt;
            font-weight: 700;
        }

        .header-meta {
            font-size: 9pt;
            line-height: 1.35;
        }
    </style>
</head>
<body>
<table style="width: 100%; border-collapse: collapse;">
    <tr>
        <td style="width: 22%; vertical-align: top;">
            @if(!empty($logoBase64))
                <img alt="NSSF" src="{{ $logoBase64 }}" style="height: 90px; width: auto;">
            @endif
        </td>
        <td style="width: 56%; vertical-align: top; padding: 0 8px;">
            <h2 style="font-size: 16px; font-weight: 700;">NATIONAL SOCIAL SECURITY FUND</h2>
            <p class="header-meta" style="margin-top: 6px;">
                P.O. BOX 1322, Dar es Salaam, TANZANIA<br/>
                Tel: +255 22 2163499-19
            </p>
        </td>
        <td style="width: 22%; vertical-align: top; text-align: right;">
            <p class="header-meta"><strong>TIN</strong> 100-208-628</p>
            <p class="header-meta" style="margin-top: 4px;"><strong>VRN</strong> 100-009429-R</p>
        </td>
    </tr>
</table>

<h3>Miscellaneous Receipt</h3>

<table class="ctable" style="font-size: 9pt;" cellpadding="0" cellspacing="0">
    <tr>
        <th>Receipt Number:</th>
        <td>{{ $receipt_data['receipt_number'] ?? '' }}</td>
        <th>Receipt Date:</th>
        <td>{{ strtoupper($receipt_date_formatted) }}</td>
    </tr>
    <tr>
        <th>Bank Reference:</th>
        <td>{{ $receipt_data['bank_receipt'] ?? '' }}</td>
        <th>Receipt Amount:</th>
        <td>{{ number_format($amount, 2) }}</td>
    </tr>
    <tr class="row-full">
        <th>Amount in Words:</th>
        <td colspan="3">{{ $amount_word }}</td>
    </tr>
    <tr class="row-full">
        <th>Receipt Description:</th>
        <td colspan="3">Nyerere Bridge Toll Collection</td>
    </tr>
    <tr class="row-full">
        <th>Shift Name:</th>
        <td colspan="3">{{ $shift_name }}</td>
    </tr>
    <tr>
        <th>Payment Mode:</th>
        <td>DIRECT DEPOSIT</td>
        <th>Bank Date:</th>
        <td>{{ strtoupper($bank_date_formatted) }}</td>
    </tr>
    <tr>
        <th>Payment Type:</th>
        <td>Toll Fees</td>
        <th></th>
        <td></td>
    </tr>
    <tr class="row-full">
        <th>Issued By:</th>
        <td colspan="3">{{ $receipt_data['accountant'] ?? '' }}</td>
    </tr>
</table>
</body>
</html>
