<?php

namespace App\Models\Notifications;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;


class Notifications extends Model
{
    use HasFactory;

    public static function pushSmsNotification($SMS_RECIPIENT, $SMS_BODY, $SMS_PROCESS, $expiryMinutes = 30)
    {
        $recipient = self::normalizePhoneNumber($SMS_RECIPIENT);

        $payload = [
            'RECIPIENT' => $recipient,
            'MESSAGE_BODY' => $SMS_BODY,
            'PROCESS' => $SMS_PROCESS,
            'SYSTEM' => 'BMS',
        ];

        $directResponse = null;

        try {
            $directResponse = Http::timeout(15)->post(config('params.paths.direct_sms'), $payload);

            if ($directResponse->successful()) {
                Log::info('Direct SMS successful', [
                    'recipient' => $recipient,
                    'process' => $SMS_PROCESS,
                    'status' => $directResponse->status(),
                    'body' => $directResponse->body(),
                ]);
            } else {
                Log::warning('Direct SMS failed', [
                    'recipient' => $recipient,
                    'process' => $SMS_PROCESS,
                    'status' => $directResponse->status(),
                    'body' => $directResponse->body(),
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('Direct SMS exception', [
                'recipient' => $recipient,
                'process' => $SMS_PROCESS,
                'error' => $e->getMessage(),
            ]);
        }

        $ictmsResponse = self::sendIctmsSmsNotification($recipient, $SMS_BODY, $SMS_PROCESS, $expiryMinutes);

        return ($directResponse && $directResponse->successful()) ? $directResponse : $ictmsResponse;
    }

    protected static function ictmsSmsUrl(): string
    {
        if (app()->environment('local')) {
            return 'https://ictmspre-api.nssf.go.tz/api/send-notification';
        }

        return config('params.paths.ictms_sms_notification');
    }

    protected static function sendIctmsSmsNotification($recipient, $body, $process, $expiryMinutes = 30)
    {
        try {
            $response = Http::timeout(15)->post(self::ictmsSmsUrl(), [
                'notification_type' => 'sms',
                'notification_method' => 'instant',
                'notification_system' => 'BMS',
                'notification_process' => 'MEMBER SMS',
                'notification_recipient' => $recipient,
                'notification_body' => $body,
                'notification_attachment' => null,
                'notification_expiry' => $expiryMinutes,
            ]);

            if ($response->successful()) {
                Log::info('ICTMS SMS successful', [
                    'recipient' => $recipient,
                    'process' => $process,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            } else {
                Log::warning('ICTMS SMS failed', [
                    'recipient' => $recipient,
                    'process' => $process,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }

            return $response;
        } catch (\Exception $e) {
            Log::warning('ICTMS SMS exception', [
                'recipient' => $recipient,
                'process' => $process,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    protected static function normalizePhoneNumber($phone): string
    {
        $digits = preg_replace('/\D+/', '', trim((string) $phone));

        if ($digits === '') {
            return '';
        }

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

        return $digits;
    }

    /**
     * Send email notification
     *
     * @param string $emailRecipient
     * @param string $subject
     * @param string $message
     * @param string $process
     * @param array $data Additional data for email template
     * @return void
     */
    /**
     * @param  string  $message  Plain text or rendered HTML (detected automatically)
     * @param  array<string, mixed>  $data
     */
    public static function pushEmailNotification($emailRecipient, $subject, $message, $process = 'Overtime Request', $data = [])
    {
        try {
            if (empty($emailRecipient)) {
                \Log::warning('Email recipient is empty', ['process' => $process]);
                return;
            }

            $body = $message;
            if (!empty($data['html_body']) && is_string($data['html_body'])) {
                $body = $data['html_body'];
            }

            // Prepare payload for external mail dispatcher API
            $payload = [
                'recipients' => [$emailRecipient],
                'title'  => $subject,
                'body'     => $body,
                'source'     => 'BMS',
//                'process'  => $process,
//                'data'     => $data,
            ];

            // Send email via external mail dispatcher
            $response = self::sendEmail(json_encode($payload));

        } catch (\Exception $e) {
            \Log::error('Failed to send email notification', [
                'recipient' => $emailRecipient,
                'subject' => $subject,
                'process' => $process,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send email through external mail dispatcher service.
     *
     * This accommodates ICTMS pre-API mail-sender settings.
     *
     * @param string $payload JSON encoded payload
     * @return mixed
     * @throws \Exception
     */
    protected static function sendEmail($payload)
    {
        $mail_dispatcher = 'https://ictmspre-api.nssf.go.tz/api/mail-sender';
        $ch = curl_init($mail_dispatcher);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 200);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 200);
        $resultCurlPost = curl_exec($ch);
        curl_close($ch);
        return $resultCurlPost;
    }
}
