<!DOCTYPE html>
<html lang="eng">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
    <meta http-equiv="X-UA-Compatible" content="IE=edge"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <link rel="icon" href={{asset('images/nssf-logo.png')}} type="image/png" sizes="16x16">
    <title>NSSF Receipt</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            color: #475569;
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
            color: #5c6d7e;
            font-weight: 500;
            padding: 8px;
            border-bottom: 1px solid #e2e8f0;
        }

        td {
            padding: 8px;
            border-bottom: 1px solid #e2e8f0;
        }

        .header {
            border-bottom: 1px solid #cbd5e1;
            margin-bottom: 20px;
            padding-bottom: 20px;
        }

        .logo {
            height: 110px;
            width: auto;
        }

        .title {
            color: #64748b;
            text-align: center;
            margin: 20px 0;
            font-size: 24px;
            font-weight: 500;
        }

        .company-info {
            color: #64748b;
            font-size: 12px;
        }

        .receipt-info {
            background-color: #f1f5f9;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .amount {
            font-weight: 500;
            color: #5c6d7e;
        }

        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 12px;
            color: #64748b;
        }

        .qr-section {
            text-align: center;
            margin-top: 30px;
            padding: 20px;
            border-top: 1px solid #e2e8f0;
        }

        .qr-code {
            display: inline-block;
            padding: 10px;
            background: white;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .verification-text {
            margin-top: 10px;
            font-size: 10px;
            color: #94a3b8;
        }
    </style>
</head>
<body>
    <div class="header">
        <table style="width: 100%">
            <thead>
                <tr>
                    <td rowspan="3" style="width: 100px">
                        <img alt="NSSF LOGO" src={{asset('images/nssf-logo.png')}} class="logo">
                    </td>
                    <td colspan="2" style="height: 45px"></td>
                </tr>
                <tr>
                    <td colspan="2" style="width: 100%">
                        <h2 style="font-size: 24px; color: #5c6d7e; margin: 0; font-weight: 500;">NATIONAL SOCIAL SECURITY FUND</h2>
                        <div class="company-info">
                            <p>P.O.BOX 1322, Dar es Salaam, TANZANIA</p>
                            <p>Tel: +255 22 2163499-19</p>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td style="height: 45px; font-weight: 500;">TIN 100-208-628</td>
                    <td style="height: 45px; text-align: right; font-weight: 500;">VRN 100-009429-R</td>
                </tr>
            </thead>
        </table>
    </div>

    <h3 class="title">Standard Receipt</h3>

    <div class="receipt-info">
        <table class="ctable" style="font-size: 11pt; width: 100%;" cellpadding="8">
            <tr>
                <th style="white-space: nowrap">Receipt Number:</th>
                <td>{{$receipt->receipt_number}}</td>
                <th>Receipt Date:</th>
                <td>{{strtoupper((new DateTime($receipt->receipt_date))->format('d-M-Y'))}}</td>
            </tr>
            <tr>
                <th>Bank Reference:</th>
                <td><?= $receipt->bank_receipt_no ?></td>
                <th>Receipt Amount:</th>
                <td class="amount"><?= number_format($receipt->paid_amount, 2) ?></td>
            </tr>
            <tr>
                <th>Amount in Words:</th>
                <td colspan="3" class="text-capitalize">
                    <?= $receipt->amount_in_words ?>
                </td>
            </tr>
            <tr>
                <th style="vertical-align: top">Receipt Description:</th>
                <td colspan="3"><?= $receipt->description ?></td>
            </tr>
        </table>
    </div>

    <table class="ctable" style="font-size: 11pt; width: 100%;" cellpadding="8">
        <tr>
            <th>Payment Mode:</th>
            <td><?= $receipt->mode_of_payment ?></td>
            <th>Bank Date:</th>
            <td><?= strtoupper((new DateTime($receipt->receipt_date))->format('d-M-Y')) ?></td>
        </tr>
        <tr>
            <th>Payment Type:</th>
            <td><?= $receipt->payment_type ?></td>
        </tr>
    </table>

    <div class="receipt-info" style="margin-top: 20px;">
        <table class="ctable" style="font-size: 11pt; width: 100%;" cellpadding="8">
            <tr>
                <th>Account Number:</th>
                <td colspan="3"><?= $receipt->payer_id ?></td>
            </tr>
            <tr>
                <th>Payer Name:</th>
                <td colspan="3"><?= $receipt->payer_name ?></td>
            </tr>
            <tr>
                <th>Plate Number:</th>
                <td colspan="3"><?= $receipt->plate_no ?></td>
            </tr>
        </table>
    </div>

    <div class="qr-section">
        <div class="qr-code">
            {!! str_replace('<?xml version="1.0" encoding="UTF-8"?>', '', QrCode::size(100)->generate(trim(config('app.url') . '/verify-receipt/' . $receipt->receipt_number))) !!}
        </div>
        <div class="verification-text">
            Scan to verify receipt authenticity
        </div>
    </div>

    <div class="footer">
        <p>This is a computer generated receipt and does not require a signature</p>
        <p>Thank you for your business!</p>
    </div>
</body>
</html>
