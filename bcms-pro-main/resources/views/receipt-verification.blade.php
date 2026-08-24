<!DOCTYPE html>
<html lang="eng">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
    <meta http-equiv="X-UA-Compatible" content="IE=edge"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <link rel="icon" href={{asset('images/nssf-logo.png')}} type="image/png" sizes="16x16">
    <title>NSSF Receipt Verification</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            color: #333;
            background-color: #f8f9fa;
        }

        .container {
            max-width: 600px;
            margin: 40px auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo {
            height: 80px;
            width: auto;
            margin-bottom: 20px;
        }

        .title {
            color: #2c3e50;
            margin: 0;
            font-size: 24px;
        }

        .verification-result {
            text-align: center;
            padding: 20px;
            margin: 20px 0;
            border-radius: 5px;
        }

        .valid {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .invalid {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .details {
            margin-top: 30px;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }

        .details p {
            margin: 10px 0;
            color: #6c757d;
        }

        .details strong {
            color: #2c3e50;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src={{asset('images/nssf-logo.png')}} alt="NSSF LOGO" class="logo">
            <h1 class="title">Receipt Verification</h1>
        </div>

        <div class="verification-result {{ $is_valid ? 'valid' : 'invalid' }}">
            <h2>{{ $is_valid ? 'Valid Receipt' : 'Invalid Receipt' }}</h2>
            <p>{{ $is_valid ? 'This receipt has been verified as authentic.' : 'This receipt could not be verified.' }}</p>
        </div>

        <div class="details">
            <p><strong>Receipt Number:</strong> {{ $receipt_number }}</p>
            <p><strong>Verification Date:</strong> {{ $verification_date }}</p>
            <p><strong>Status:</strong> {{ $is_valid ? 'Verified' : 'Not Verified' }}</p>
        </div>
    </div>
</body>
</html> 