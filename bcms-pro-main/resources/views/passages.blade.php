<!DOCTYPE html>
<html lang="eng">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
    <meta http-equiv="X-UA-Compatible" content="IE=edge"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <link rel="icon" href={{asset('images/nssf-logo.png')}} type="image/png" sizes="16x16">
    <title>NSSF Passages</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            color: #475569;
            background-image: url('{{asset('images/nssf-logo.png')}}');
            background-repeat: no-repeat;
            background-position: center center;
            background-size: 300px auto;
            background-opacity: 0.1;
            position: relative;
        }

        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: url('{{asset('images/nssf-logo.png')}}');
            background-repeat: no-repeat;
            background-position: center center;
            background-size: 300px auto;
            opacity: 0.05;
            z-index: -1;
        }

        p {
            margin: 0;
            color: #475569;
        }

        table.ctable {
            width: 100%;
            border-collapse: collapse;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        th {
            text-align: left;
            white-space: nowrap;
            color: #64748b;
            font-weight: 500;
            padding: 12px 16px;
            background: #f1f5f9;
            border-bottom: 1px solid #cbd5e1;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        td {
            padding: 12px 16px;
            border-bottom: 1px solid #e2e8f0;
            color: #475569;
            font-size: 13px;
        }

        tr:last-child td {
            border-bottom: none;
        }

        tr:hover {
            background: #f8fafc;
        }

        .header {
            border-bottom: 1px solid #cbd5e1;
            margin-bottom: 30px;
            padding-bottom: 20px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 8px;
            padding: 20px;
        }

        .logo {
            height: 80px;
            width: auto;
        }

        .title {
            color: #64748b;
            text-align: center;
            margin: 20px 0;
            font-size: 28px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .company-info {
            color: #64748b;
            font-size: 12px;
            font-weight: 400;
        }

        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 12px;
            color: #64748b;
            background: rgba(255, 255, 255, 0.9);
            padding: 15px;
            border-radius: 8px;
        }

        .content-wrapper {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
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
                        <h2 style="font-size: 24px; color: #5c6d7e; margin: 0; text-transform: uppercase; letter-spacing: 1px; font-weight: 500;">NATIONAL SOCIAL SECURITY FUND</h2>
                        <div class="company-info">
                            <p>P.O.BOX 1322, Dar es Salaam, TANZANIA</p>
                            <p>Tel: +255 22 2163499-19</p>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td style="height: 45px; color: #475569; font-weight: 500;">TIN 100-208-628</td>
                    <td style="height: 45px; text-align: right; color: #475569; font-weight: 500;">VRN 100-009429-R</td>
                </tr>
            </thead>
        </table>
    </div>

    <h3 class="title">Passages List</h3>

    <div class="content-wrapper">
        <table class="ctable">
            <thead>
                <tr>
                    <th>S/N</th>
                    <th>Plate No</th>
                    <th>Lane No</th>
                    <th>Passs Date</th>
                </tr>
            </thead>
            <tbody>
                @forelse($passages as $index => $pass)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $pass->plate_no ?? '' }}</td>
                        <td>{{ $pass->lane_no ?? '' }}</td>
                        <td>{{ isset($pass->pass_date) ? (new DateTime($pass->pass_date))->format('Y-m-d H:i:s') : '' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" style="text-align:center; color:#64748b; padding: 20px;">No passages found</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="footer">
        <p>Generated by NSSF System</p>
    </div>
</body>
</html>


