<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Form for Electronic Funds Transfer</title>
    <style>
        body {
            font-family: Calibri, sans-serif;
            font-size: 11pt;
            color: #475569;
        }

        p {
            margin: 0;
        }

        table.MsoTableGrid {
            border-collapse: collapse;
            border: none;
            width: 100%;
        }

        td {
            padding: 0cm 5.4pt;
            vertical-align: top;
        }

        h1 {
            margin: 12pt 0cm 3pt;
            break-after: avoid;
            font-size: 16pt;
            font-family: 'Calibri Light', sans-serif;
        }

        strong {
            font-weight: 500;
        }
    </style>
</head>
<body>
<div style="color: #475569" class="print-section">
    <table class="MsoTableGrid" style="border-collapse: collapse; border: none;" border="0" cellspacing="0" cellpadding="0">
        <tbody>
        <tr style="height: 105.55pt;">
            <td style="width: 99.6pt; border: solid windowtext 0pt; padding: 0cm 5.4pt 0cm 5.4pt;">
                <h1 style="text-align: center; margin: 12pt 0cm 3pt; break-after: avoid; font-size: 16pt; font-family: 'Calibri Light', sans-serif;" align="center">
                    <span style="font-family: Tahoma, sans-serif; font-weight: normal;">
                        @if(!empty($logoBase641))
                            <img src="{{$logoBase641}}" alt="NSSF" style="height:80px; width:auto;">
                        @else
                            <img src="{{asset('images/nembo.png')}}" alt="NSSF" style="height:80px; width:auto;">
                        @endif
                    </span>
                </h1>
            </td>
            <td style="width: 282.9pt; border: solid windowtext 0pt; border-left: none; padding: 0cm 5.4pt 0cm 5.4pt;">
                <h1 style="text-align: center; margin: 12pt 0cm 3pt; break-after: avoid; font-size: 16pt; font-family: 'Calibri Light', sans-serif;" align="center">
                    <span style="font-size: 14.0pt; font-family: Tahoma, sans-serif;">THE UNITED REPUBLIC OF TANZANIA</span>
                </h1>
                <h1 style="text-align: center; margin: 12pt 0cm 3pt; break-after: avoid; font-size: 16pt; font-family: 'Calibri Light', sans-serif;" align="center">
                    <span style="font-size: 14.0pt; font-family: Tahoma, sans-serif;">NATIONAL SOCIAL SECURITY FUND</span>
                </h1>
            </td>
            <td style="width: 83.65pt; border: solid windowtext 0pt; border-left: none; padding: 0cm 5.4pt 0cm 5.4pt;">
                <h1 style="text-align: center; margin: 12pt 0cm 3pt; break-after: avoid; font-size: 16pt; font-family: 'Calibri Light', sans-serif;" align="center">
                    <span style="font-family: Tahoma, sans-serif; font-weight: normal;">
                        @if(!empty($logoBase642))
                            <img src="{{$logoBase642}}" alt="NSSF" style="height:80px; width:auto;">
                        @else
                            <img src="{{asset('images/nssf-log1.png')}}" alt="NSSF" style="height:80px; width:auto;">
                        @endif
                    </span>
                </h1>
            </td>
        </tr>
        <br>
        <br>
        <tr>
            <td style="width: 466.15pt; border: none; padding: 0cm 5.4pt 0cm 5.4pt;text-align: center" colspan="3" valign="top">
                <p style="margin: 0cm 0cm 0.0001pt; text-align: center; line-height: normal; font-size: 11pt; font-family: Calibri, sans-serif;">
                    <strong><u><h1 style="font-size: 14.0pt; font-family: Tahoma, sans-serif;">ORDER FORM FOR ELECTRONIC FUNDS TRANSFER</h1></u></strong>
                </p>
            </td>
        </tr>
        </tbody>
    </table>

    <p style="margin: 0cm 0cm 8pt; line-height: 107%; font-size: 11pt; font-family: Calibri, sans-serif;">&nbsp;</p>
    <table class="MsoTableGrid" style="border-collapse: collapse; border: none;" border="1" cellspacing="0" cellpadding="0">
        <tbody>
        <tr style="height: 19.85pt;">
            <td style="width: 26.75pt; border: solid windowtext 1.0pt; padding: 0cm 5.4pt 0cm 5.4pt;" valign="top">
                <p style="margin: 0cm 0cm 0.0001pt; text-align: center; line-height: normal; font-size: 11pt; font-family: Calibri, sans-serif;">
                    <strong><span style="font-size: 14.0pt;">1.</span></strong></p>
            </td>
            <td style="width: 439.4pt; border: solid windowtext 1.0pt; border-left: none; padding: 0cm 5.4pt 0cm 5.4pt;" colspan="2" valign="top">
                <p style="margin: 0cm 0cm 0.0001pt; line-height: normal; font-size: 11pt; font-family: Calibri, sans-serif;">
                    <strong><span style="font-size: 14.0pt;">Remitter Details:</span></strong></p>
            </td>
        </tr>
        <tr style="height: 19.85pt;">
            <td style="width: 26.75pt; border: solid windowtext 1.0pt; border-top: none; padding: 0cm 5.4pt 0cm 5.4pt;" rowspan="3" valign="top">
                <p style="margin: 0cm 0cm 0.0001pt; text-align: center; line-height: normal; font-size: 11pt; font-family: Calibri, sans-serif;">
                    <span style="font-size: 14.0pt;">&nbsp;</span></p>
            </td>
            <td style="width: 121.85pt; border-top: none; border-left: none; border-bottom: solid windowtext 1.0pt; border-right: solid windowtext 1.0pt; padding: 0cm 5.4pt 0cm 5.4pt;" valign="top">
                <p style="margin: 0cm 0cm 0.0001pt; line-height: normal; font-size: 11pt; font-family: Calibri, sans-serif;">
                    <span style="font-size: 14.0pt;">Payer Name</span></p>
            </td>
            <td style="width: 317.55pt; border-top: none; border-left: none; border-bottom: solid windowtext 1.0pt; border-right: solid windowtext 1.0pt; padding: 0cm 5.4pt 0cm 5.4pt;" valign="top">
                <p style="margin: 0cm 0cm 0.0001pt; line-height: normal; font-size: 11pt; font-family: Calibri, sans-serif;">
                    <span style="font-size: 14.0pt;">{{ $bill_details['payer_name'] }}</span>
                </p>
            </td>
        </tr>
        <tr style="height: 19.85pt;">
            <td style="width: 121.85pt; border-top: none; border-left: none; border-bottom: solid windowtext 1.0pt; border-right: solid windowtext 1.0pt; padding: 0cm 5.4pt 0cm 5.4pt;" valign="top">
                <p style="margin: 0cm 0cm 0.0001pt; line-height: normal; font-size: 11pt; font-family: Calibri, sans-serif;">
                    <span style="font-size: 14.0pt;">Phone Number</span></p>
            </td>
            <td style="width: 317.55pt; border-top: none; border-left: none; border-bottom: solid windowtext 1.0pt; border-right: solid windowtext 1.0pt; padding: 0cm 5.4pt 0cm 5.4pt;" valign="top">
                <p style="margin: 0cm 0cm 0.0001pt; line-height: normal; font-size: 11pt; font-family: Calibri, sans-serif;">
                    <span style="font-size: 14.0pt;">{{ $bill_details['phone_number'] }}</span>
                </p>
            </td>
        </tr>
        <tr style="height: 19.85pt;">
            <td style="width: 466.15pt; border: solid windowtext 1.0pt; border-top: none; padding: 0cm 5.4pt 0cm 5.4pt;" colspan="3" valign="top">
                <p style="margin: 0cm 0cm 0.0001pt; text-align: center; line-height: normal; font-size: 11pt; font-family: Calibri, sans-serif;">
                    <span style="font-size: 14.0pt;">&nbsp;</span></p>
            </td>
        </tr>
        <tr style="height: 19.85pt;">
            <td style="width: 26.75pt; border: solid windowtext 1.0pt; border-top: none; padding: 0cm 5.4pt 0cm 5.4pt;" valign="top">
                <p style="margin: 0cm 0cm 0.0001pt; text-align: center; line-height: normal; font-size: 11pt; font-family: Calibri, sans-serif;">
                    <strong><span style="font-size: 14.0pt;">2.</span></strong></p>
            </td>
            <td style="width: 439.4pt; border-top: none; border-left: none; border-bottom: solid windowtext 1.0pt; border-right: solid windowtext 1.0pt; padding: 0cm 5.4pt 0cm 5.4pt;" colspan="2" valign="top">
                <p style="margin: 0cm 0cm 0.0001pt; line-height: normal; font-size: 11pt; font-family: Calibri, sans-serif;">
                    <strong><span style="font-size: 14.0pt;">Beneficiary Details</span></strong></p>
            </td>
        </tr>
        <tr style="height: 19.85pt;">
            <td style="width: 26.75pt; border: solid windowtext 1.0pt; border-top: none; padding: 0cm 5.4pt 0cm 5.4pt;" rowspan="17" valign="top">
            </td>
            <td style="width: 121.85pt; border-top: none; border-left: none; border-bottom: solid windowtext 1.0pt; border-right: solid windowtext 1.0pt; padding: 0cm 5.4pt 0cm 5.4pt;" valign="top">
                <p style="margin: 0cm 0cm 0.0001pt; line-height: normal; font-size: 11pt; font-family: Calibri, sans-serif;">
                    <span style="font-size: 14.0pt;">Account Name</span></p>
            </td>
            <td style="width: 317.55pt; border-top: none; border-left: none; border-bottom: solid windowtext 1.0pt; border-right: solid windowtext 1.0pt; padding: 0cm 5.4pt 0cm 5.4pt;" valign="top">
                <p style="margin: 0cm 0cm 0.0001pt; line-height: normal; font-size: 11pt; font-family: Calibri, sans-serif;">
                    <span style="font-size: 14.0pt;">{{ $accountName }}</span>
                </p>
            </td>
        </tr>
        <tr style="height: 19.85pt;">
            <td style="width: 121.85pt; border-top: none; border-left: none; border-bottom: solid windowtext 1.0pt; border-right: solid windowtext 1.0pt; padding: 0cm 5.4pt 0cm 5.4pt;" valign="top">
                <p style="margin: 0cm 0cm 0.0001pt; line-height: normal; font-size: 11pt; font-family: Calibri, sans-serif;">
                    <span style="font-size: 14.0pt;">Currency</span></p>
            </td>
            <td style="width: 317.55pt; border-top: none; border-left: none; border-bottom: solid windowtext 1.0pt; border-right: solid windowtext 1.0pt; padding: 0cm 5.4pt 0cm 5.4pt;" valign="top">
                <p style="margin: 0cm 0cm 0.0001pt; line-height: normal; font-size: 11pt; font-family: Calibri, sans-serif;">
                    <span style="font-size: 14.0pt;">TANZANIAN SHILLINGS</span>
                </p>
            </td>
        </tr>
        <tr style="height: 19.85pt;">
            <td style="width: 121.85pt; border-top: none; border-left: none; border-bottom: solid windowtext 1.0pt; border-right: solid windowtext 1.0pt; padding: 0cm 5.4pt 0cm 5.4pt;" valign="top">
                <p style="margin: 0cm 0cm 0.0001pt; line-height: normal; font-size: 11pt; font-family: Calibri, sans-serif;">
                    <span style="font-size: 14.0pt;">&nbsp;</span></p>
            </td>
            <td style="width: 317.55pt; border-top: none; border-left: none; border-bottom: solid windowtext 1.0pt; border-right: solid windowtext 1.0pt; padding: 0cm 5.4pt 0cm 5.4pt;" valign="top">
                <p style="margin: 0cm 0cm 0.0001pt; line-height: normal; font-size: 11pt; font-family: Calibri, sans-serif;">
                    <span style="font-size: 14.0pt;">&nbsp;</span></p>
            </td>
        </tr>
        <tr style="height: 19.85pt;">
            <td style="width: 121.85pt; border-top: none; border-left: none; border-bottom: solid windowtext 1.0pt; border-right: solid windowtext 1.0pt; padding: 0cm 5.4pt 0cm 5.4pt;" valign="top">
                <p style="margin: 0cm 0cm 0.0001pt; line-height: normal; font-size: 11pt; font-family: Calibri, sans-serif;">
                    <span style="font-size: 14.0pt; white-space: nowrap">CONTROL NUMBER</span></p>
            </td>
            <td style="width: 317.55pt; border-top: none; border-left: none; border-bottom: solid windowtext 1.0pt; border-right: solid windowtext 1.0pt; padding: 0cm 5.4pt 0cm 5.4pt;" valign="top">
                <p style="margin: 0cm 0cm 0.0001pt; line-height: normal; font-size: 11pt; font-family: Calibri, sans-serif;">
                    <span style="font-size: 14.0pt;"><strong>{{ $bill_details['control_num'] }}</strong></span>
                </p>
            </td>
        </tr>
        <tr style="height: 19.85pt;">
            <td style="width: 121.85pt; border-top: none; border-left: none; border-bottom: solid windowtext 1.0pt; border-right: solid windowtext 1.0pt; padding: 0cm 5.4pt 0cm 5.4pt;" valign="top">
                <p style="margin: 0cm 0cm 0.0001pt; line-height: normal; font-size: 11pt; font-family: Calibri, sans-serif;">
                    <span style="font-size: 14.0pt;">Billed To</span></p>
            </td>
            <td style="width: 317.55pt; border-top: none; border-left: none; border-bottom: solid windowtext 1.0pt; border-right: solid windowtext 1.0pt; padding: 0cm 5.4pt 0cm 5.4pt;" valign="top">
                <p style="margin: 0cm 0cm 0.0001pt; line-height: normal; font-size: 11pt; font-family: Calibri, sans-serif;">
                    <span style="font-size: 14.0pt;">{{ $bill_details['payer_name'] }}</span>
                </p>
            </td>
        </tr>
        <tr style="height: 19.85pt;">
            <td style="width: 121.85pt; border-top: none; border-left: none; border-bottom: solid windowtext 1.0pt; border-right: solid windowtext 1.0pt; padding: 0cm 5.4pt 0cm 5.4pt;" valign="top">
                <p style="margin: 0cm 0cm 0.0001pt; line-height: normal; font-size: 11pt; font-family: Calibri, sans-serif;">
                    <span style="font-size: 14.0pt;">Billed Amount</span></p>
            </td>
            <td style="width: 317.55pt; border-top: none; border-left: none; border-bottom: solid windowtext 1.0pt; border-right: solid windowtext 1.0pt; padding: 0cm 5.4pt 0cm 5.4pt;" valign="top">
                <p style="margin: 0cm 0cm 0.0001pt; line-height: normal; font-size: 11pt; font-family: Calibri, sans-serif;">
                    <span style="font-size: 14.0pt;">Tsh {{ number_format($bill_details['amount'], 2) }}</span></p>
            </td>
        </tr>
        <tr style="height: 19.85pt;">
            <td style="width: 121.85pt; border-top: none; border-left: none; border-bottom: solid windowtext 1.0pt; border-right: solid windowtext 1.0pt; padding: 0cm 5.4pt 0cm 5.4pt;" valign="top">
                <p style="margin: 0cm 0cm 0.0001pt; line-height: normal; font-size: 11pt; font-family: Calibri, sans-serif;">
                    <span style="font-size: 14.0pt;">Being payment for</span></p>
            </td>
            <td style="width: 317.55pt; border-top: none; border-left: none; border-bottom: solid windowtext 1.0pt; border-right: solid windowtext 1.0pt; padding: 0cm 5.4pt 0cm 5.4pt;" valign="top">
                <p style="margin: 0cm 0cm 0.0001pt; line-height: normal; font-size: 11pt; font-family: Calibri, sans-serif;">
                    <span style="font-size: 14.0pt;">{{ $payment_for }}</span>
            </td>
        </tr>
        <tr style="height: 19.85pt;">
            <td style="width: 121.85pt; border-top: none; border-left: none; border-bottom: solid windowtext 1.0pt; border-right: solid windowtext 1.0pt; padding: 0cm 5.4pt 0cm 5.4pt;" valign="top">
                <p style="margin: 0cm 0cm 0.0001pt; line-height: normal; font-size: 11pt; font-family: Calibri, sans-serif;">
                    <span style="font-size: 14.0pt;">&nbsp;</span></p>
            </td>
            <td style="width: 317.55pt; border-top: none; border-left: none; border-bottom: solid windowtext 1.0pt; border-right: solid windowtext 1.0pt; padding: 0cm 5.4pt 0cm 5.4pt;" valign="top">
                <p style="margin: 0cm 0cm 0.0001pt; line-height: normal; font-size: 11pt; font-family: Calibri, sans-serif;">
                    <span style="font-size: 14.0pt;">&nbsp;</span></p>
            </td>
        </tr>
        <tr style="height: 19.85pt;">
            <td style="width: 121.85pt; border-top: none; border-left: none; border-bottom: solid windowtext 1.0pt; border-right: solid windowtext 1.0pt; padding: 0cm 5.4pt 0cm 5.4pt;" valign="top">
                <p style="margin: 0cm 0cm 0.0001pt; line-height: normal; font-size: 11pt; font-family: Calibri, sans-serif;">
                    <span style="font-size: 14.0pt;">Bill Due Date</span></p>
            </td>
            <td style="width: 317.55pt; border-top: none; border-left: none; border-bottom: solid windowtext 1.0pt; border-right: solid windowtext 1.0pt; padding: 0cm 5.4pt 0cm 5.4pt;" valign="top">
                <p style="margin: 0cm 0cm 0.0001pt; line-height: normal; font-size: 11pt; font-family: Calibri, sans-serif;">
                    <span style="font-size: 14.0pt;">{{ $bill_details['bill_exp_dt'] }}</span></p>
            </td>
        </tr>
        <tr style="height: 19.85pt;">
            <td style="width: 121.85pt; border-top: none; border-left: none; border-bottom: solid windowtext 1.0pt; border-right: solid windowtext 1.0pt; padding: 0cm 5.4pt 0cm 5.4pt;" valign="top">
                <p style="margin: 0cm 0cm 0.0001pt; line-height: normal; font-size: 11pt; font-family: Calibri, sans-serif;">
                    <span style="font-size: 14.0pt;">Printed By</span></p>
            </td>
            <td style="width: 317.55pt; border-top: none; border-left: none; border-bottom: solid windowtext 1.0pt; border-right: solid windowtext 1.0pt; padding: 0cm 5.4pt 0cm 5.4pt;" valign="top">
                <p style="margin: 0cm 0cm 0.0001pt; line-height: normal; font-size: 11pt; font-family: Calibri, sans-serif;">
                    <span style="font-size: 14.0pt;">{{ $user->name ?? ($user->first_name ?? '') . ' ' . ($user->middle_name ?? '') . ' ' . ($user->surname ?? '') }}</span>
                </p>
            </td>
        </tr>
        <tr style="height: 19.85pt;">
            <td style="width: 121.85pt; border-top: none; border-left: none; border-bottom: solid windowtext 1.0pt; border-right: solid windowtext 1.0pt; padding: 0cm 5.4pt 0cm 5.4pt;" valign="top">
                <p style="margin: 0cm 0cm 0.0001pt; line-height: normal; font-size: 11pt; font-family: Calibri, sans-serif;">
                    <span style="font-size: 14.0pt;">Printed on</span></p>
            </td>
            <td style="width: 317.55pt; border-top: none; border-left: none; border-bottom: solid windowtext 1.0pt; border-right: solid windowtext 1.0pt; padding: 0cm 5.4pt 0cm 5.4pt;" valign="top">
                <p style="margin: 0cm 0cm 0.0001pt; line-height: normal; font-size: 11pt; font-family: Calibri, sans-serif;">
                    <span style="font-size: 14.0pt;"><strong>{{ \Carbon\Carbon::now()->format('d-M-Y') }}</strong></span></p>
            </td>
        </tr>
        </tbody>
    </table>
    <p style="margin: 0cm 0cm 8pt; line-height: 107%; font-size: 11pt; font-family: Calibri, sans-serif;">
        <strong>&nbsp;</strong></p>
    <table class="MsoTableGrid" style="border-collapse: collapse; border: none;" border="0" cellspacing="0" cellpadding="0">
        <tbody>
        <tr>
            <td style="width: 466.15pt; border: solid windowtext 0pt; padding: 0cm 5.4pt 0cm 5.4pt;" colspan="2" valign="top">
                <p style="margin: 0cm 0cm 0.0001pt; line-height: normal; font-size: 12pt; font-family: Calibri, sans-serif;">
                    <strong><span style="font-size: 12.0pt;">Note to Commercial Banks:</span></strong></p>
            </td>
        </tr>

        <tr>
            <td style="width: 28.1pt; border: solid windowtext 0pt; border-top: none; padding: 0cm 5.4pt 0cm 5.4pt;" valign="top">
                <p style="margin: 0cm 0cm 0.0001pt; text-align: center; line-height: normal; font-size: 11pt; font-family: Calibri, sans-serif;">
                    <strong><span style="font-size: 14.0pt;">1.</span></strong></p>
            </td>
            <td style="width: 438.05pt; border-top: none; border-left: none; border-bottom: solid windowtext 0pt; border-right: solid windowtext 0pt; padding: 0cm 5.4pt 0cm 5.4pt;" valign="top">
                <p style="margin: 0cm 0cm 0.0001pt; line-height: normal; font-size: 11pt; font-family: Calibri, sans-serif;">
                    <span style="font-size: 14.0pt;">Field &ldquo;<strong>Control Number</strong>&rdquo; with value:<strong> {{ $bill_details['control_num'] }}</strong> Must be captured correctly.</span>
                </p>
            </td>
        </tr>
        </tbody>
    </table>
</div>
</body>
</html>

