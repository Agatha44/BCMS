<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;


class GePG
{
    /**
     * Normalize payer cell to exactly 12 digits as required by GePG (255 + 9-digit Tanzania mobile).
     * Handles: 2550712057896 -> 255712057896, 0712057896 -> 255712057896, 712057896 -> 255712057896.
     */
    public static function normalizePayerCell(string $payerCell): string
    {
        $digits = preg_replace('/\D/', '', $payerCell);
        if (strlen($digits) >= 12 && str_starts_with($digits, '255')) {
            $rest = substr($digits, 3);
            $subscriber = strlen($rest) > 9 ? ltrim($rest, '0') : $rest;
            $subscriber = substr(preg_replace('/\D/', '', $subscriber), -9);
            return '255' . str_pad($subscriber ?: '0', 9, '0', STR_PAD_LEFT);
        }
        if (strlen($digits) === 9) {
            return '255' . $digits;
        }
        if (strlen($digits) >= 10) {
            $subscriber = ltrim(substr($digits, -10), '0');
            return '255' . str_pad($subscriber ?: '0', 9, '0', STR_PAD_LEFT);
        }
        return str_pad($digits, 12, '0', STR_PAD_LEFT);
    }

    // Static function to return status message based on GePG response code
    public static function getStatusMessage($code)
    {
        // Define the GePG response codes and their corresponding status messages
        $responseCodes = [
            7101 => 'Successful',
            7201 => 'Failure',
            7202 => 'Required header is not given',
            7203 => 'Unauthorized',
            7204 => 'Bill does not exist',
            7205 => 'Invalid service provider',
            7206 => 'Service provider is not active',
            7207 => 'Duplicate payment',
            7208 => 'Invalid business account',
            7209 => 'Business account is not active',
            7210 => 'Collection account balance limit reached',
            7211 => 'Payment service provider code did not match Bill service provider code',
            7212 => 'Payment currency did not match Bill currency',
            7213 => 'Bill has expired',
            7214 => 'Insufficient amount paid',
            7215 => 'Invalid payment service provider',
            7216 => 'Payment service provider is not active',
            7217 => 'No payer email or phone number',
            7218 => 'Wrong payer identity',
            7219 => 'Wrong currency',
            7220 => 'Sub Service provider is inactive',
            7221 => 'Wrong bill equivalent amount',
            7222 => 'Wrong bill miscellaneous amount',
            7223 => 'Invalid or inactive gfs or service type',
            7224 => 'Wrong bill amount',
            7225 => 'Invalid bill reference number or code',
            7226 => 'Duplicate bill information',
            7227 => 'Blank bill identification number',
            7228 => 'Invalid sub service provider',
            7229 => 'Wrong bill item gfs or payment type code',
            7230 => 'Wrong bill generation date',
            7231 => 'Wrong bill expiry date',
            7232 => 'Consumer already started by another process',
            7233 => 'Consumer already stopped by another process',
            7234 => 'Wrong bill payment option',
            7235 => 'Bill creation completed successfully',
            7236 => 'Bill creation completed with errors',
            7237 => 'Bill detail creation completed successfully',
            7238 => 'Bill detail creation completed with errors',
            7239 => 'No external bill system settings found',
            7240 => 'Failed to save transaction',
            7241 => 'Invalid session',
            7242 => 'Invalid request data',
            7243 => 'Invalid credit account',
            7244 => 'Invalid transfer amount',
            7245 => 'Invalid credit account name',
            7246 => 'Invalid debit account',
            7247 => 'Invalid transfer transaction description',
            7248 => 'Invalid debitor bic',
            7249 => 'Wrong transfer date',
            7250 => 'Invalid value in transfer reserved field one',
            7251 => 'Invalid transfer transaction number',
            7252 => 'Transfer transaction created successfully',
            7253 => 'Transfer transaction created with errors',
            7254 => 'Invalid use payment reference, use "Y" or "N"',
            7255 => 'Invalid item billed amount',
            7256 => 'Invalid item equivalent amount',
            7257 => 'Invalid item miscellaneous amount',
            7258 => 'Total item billed amount mismatches the bill amount',
            7259 => 'Total item equivalent amount mismatches the bill equivalent amount',
            7260 => 'Total item miscellaneous amount mismatches the bill miscellaneous amount',
            7261 => 'Defect bill saved successfully',
            7262 => 'Defect bill saved with errors',
            7263 => 'Defect bill items saved successfully',
            7264 => 'Defect bill items saved with errors',
            7265 => 'Bill items saved successfully',
            7266 => 'Bill items saved with errors',
            7267 => 'Invalid email address',
            7268 => 'Invalid phone number',
            7269 => 'Invalid or inactive Service Provider System Id',
            7270 => 'Transfer transaction update completed successfully',
            7271 => 'Transfer transaction update completed with errors',
            7272 => 'Defect transfer transaction saved successfully',
            7273 => 'Defect transfer transaction saved with errors',
            7274 => 'Duplicate transfer transaction',
            7275 => 'Invalid Service Provider Payer Id',
            7276 => 'Invalid Service Provider Payer Name',
            7277 => 'Invalid bill description',
            7278 => 'Invalid bill approval user',
            7279 => 'Bill already settled',
            7280 => 'Bill expired and bill move process failed',
            7281 => 'Invalid payment transaction date',
            7282 => 'Invalid payer email or phone number',
            7283 => 'Bill has been cancelled',
            7284 => 'Payment currency did not match collection account currency',
            7285 => 'Invalid bill generation user',
            7286 => 'Bill cancellation process failed',
            7287 => 'Bill reference number does not meet required bill control number specifications',
            7288 => 'Disbursement request did not match signature',
            7289 => 'Invalid batch generated date',
            7290 => 'Total batch amount cannot be zero(0)',
            7291 => 'Total batch amount is not equal to summation of items(transactions)',
            7292 => 'Duplicate disbursement batch',
            7293 => 'Invalid disbursement pay option',
            7294 => 'Invalid disbursement batch scheduled date',
            7295 => 'Invalid disbursement notification template',
            7296 => 'Disbursement notification template is not active',
            7297 => 'Inactive currency',
            7298 => 'Invalid currency for disbursement',
            7299 => 'Batch item(recipients) recipients should not exceed',
            7301 => 'Bill has been paid partially',
            7302 => 'Paid amount is not exact billed amount',
            7303 => 'Invalid Signature',
            7304 => 'Invalid Signature Configuration missing one of parameter (passphrase,keyalias,filename)',
            7305 => 'Invalid batch start and end date',
            7306 => 'Batch has no item(transaction)',
            7307 => 'Inconsistency batch start, end and generated date',
            7308 => 'Invalid value in transfer reserved field two',
            7309 => 'Invalid value in transfer reserved field three',
            7310 => 'Invalid transfer credit or debit account',
            7311 => 'Invalid GePG configurations missing one of parameter (gepgKeyFilePath,gepgPassphrase,gepgAlias)',
            7312 => 'Batch does not exist',
            7313 => 'Cancel is only for auto pay batch',
            7314 => 'Batch already on disbursement process, cancellation process failed',
            7315 => 'Batch cancellation process failed',
            7316 => 'Batch already canceled',
            7317 => 'Error on processing request',
            7318 => 'Invalid reconciliation request date',
            7319 => 'Reconciliation request date is out of allowable range',
            7320 => 'Invalid reconciliation request options',
            7321 => 'Request can not be completed at this time, try later',
            7322 => 'Inactive communication protocol',
            7323 => 'Invalid code, mismatch of supplied code on information and header',
            7324 => 'No payment(s) found for specified bill control number',
            7325 => 'Request to partner application composed',
            7326 => 'Request sent to partner application(system)',
            7327 => 'Request sent to partner application(system) with no content response',
            7328 => 'Request not received successful with partner application(system)',
            7329 => 'Processing or communication error on partner application(system)',
            7330 => 'Inactive or Unavailable, bill push to pay for specified Payment service Provider',
            7331 => 'Paid Online Waiting Bank Confirmation',
        ];

        // Check if the provided code exists in the array
        if (array_key_exists($code, $responseCodes)) {
            return $responseCodes[$code];
        }

        // Return a default message for unknown codes
        return 'Unknown Status Code';
    }

    public static function postBill($data)
    {
        $validator = Validator::make($data, [
            'payment_ref' => 'required|string',
            'amount' => 'required|numeric',
            'equiv_amount' => 'required|numeric',
            'bill_desc' => 'required|string',
            'currency' => 'required|string',
            'payment_type' => 'required|numeric',
            'payerid' => 'required|string',
            'payer_name' => 'required|string',
            'payer_cell' => 'required|string|min:12',
            'generated_by' => 'required|string',
            'days_expires_after' => 'required|numeric',
            'payer_email' => 'required|string',
            'bill_gen_date' => 'required|date',
            'bill_exp_date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return [
                'status' => 'invalid_params',
                'message' => 'Invalid request data',
                'errors' => $validator->errors()
            ];
        }

        $gepgConfig = config('app.gepg');

        $data['approved_by'] = $gepgConfig['approved_by'];
        $data['gfs_code'] = $gepgConfig['gfs_code'];
        $data['system_id'] = $gepgConfig['system_id'];
        $data['sub_sp_code'] =$gepgConfig['sub_sp_code'];
        $data['source_name'] = $gepgConfig['source_name'];
        $data['source_id'] = $gepgConfig['source_id'];

        // GePG requires payer_cell to be exactly 12 digits (255 + 9-digit Tanzania mobile)
        $data['payer_cell'] = self::normalizePayerCell($data['payer_cell']);

        Log::info('GePG data', ['data' => $data]);

        $response = Http::post($gepgConfig['url'], $data);

        Log::info('GePG response', [
            'response' => $response->json() ?? $response->body() ?? $response
        ]);

        // check if the HTTP response is successful
        if ($response->status() !== 200) {
            return [
                'status' => 'error',
                'message' => 'HTTP request failed',
                'data' => [
                    'http_status' => $response->status(),
                    'response' => $response->body() ?? $response->json() ?? $response
                ]
            ];
        }

        $responseData = $response->json();
        $responseBody = trim($response->body());

        // GePG may return plain-text validation errors (e.g. "The payer cell must be 12 digits.")
        if (!$responseData) {
            Log::warning('GePG returned non-JSON response', ['response' => $responseBody]);
            return [
                'status' => 'error',
                'message' => $responseBody ?: 'Invalid response from GePG',
                'data' => ['raw' => $responseBody]
            ];
        }

        // check if GePG response indicates success (status: 1)
        if (isset($responseData['status']) && $responseData['status'] == 1) {
            return [
                'status' => 'success',
                'message' => $responseData['message'] ?? 'Bill posted successfully',
                'data' => $responseData['data'] ?? $responseData
            ];
        }

        // GePG returned an error
        return [
            'status' => 'error',
            'message' => $responseData['message'] ?? 'Bill posting failed',
            'data' => $responseData
        ];
    }
}
