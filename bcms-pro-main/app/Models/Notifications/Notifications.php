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

        try {
            $response = Http::timeout(30)->post(config('params.paths.direct_sms'), [
                'RECIPIENT' => $recipient,
                'MESSAGE_BODY' => $SMS_BODY,
                'PROCESS' => $SMS_PROCESS,
                'SYSTEM' => 'BMS',
            ]);

            if ($response->successful()) {
                Log::info('Direct SMS successful', [
                    'recipient' => $recipient,
                    'process' => $SMS_PROCESS,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return $response;
            }

            Log::warning('Direct SMS failed, falling back to ICTMS', [
                'recipient' => $recipient,
                'process' => $SMS_PROCESS,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        } catch (\Exception $e) {
            Log::warning('Direct SMS exception, falling back to ICTMS', [
                'recipient' => $recipient,
                'process' => $SMS_PROCESS,
                'error' => $e->getMessage(),
            ]);
        }

        return self::sendIctmsSmsNotification($recipient, $SMS_BODY, $SMS_PROCESS, $expiryMinutes);
    }

    protected static function sendIctmsSmsNotification($recipient, $body, $process, $expiryMinutes = 30)
    {
        return Http::timeout(30)->post(config('params.paths.ictms_sms_notification'), [
            'notification_type' => 'sms',
            'notification_method' => 'instant',
            'notification_system' => 'BMS',
            'notification_process' => $process,
            'notification_recipient' => $recipient,
            'notification_body' => $body,
            'notification_attachment' => null,
            'notification_expiry' => $expiryMinutes,
        ]);
    }

    protected static function normalizePhoneNumber($phone): string
    {
        $phone = preg_replace('/\D+/', '', trim((string) $phone));
    
        if (str_starts_with($phone, '0')) {
            return '255' . substr($phone, 1);
        }
    
        if (str_starts_with($phone, '255')) {
            return $phone;
        }
    
        return $phone;
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
