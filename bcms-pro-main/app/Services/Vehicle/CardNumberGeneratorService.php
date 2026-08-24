<?php

namespace App\Services\Vehicle;

use App\Models\CardSequence;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Log;

class CardNumberGeneratorService
{
    private const PREFIX = 'NSSFMWLC';
    private const MAX_ATTEMPTS = 1000;

    /**
     * Generate a unique vehicle card number (e.g. NSSFMWLC123).
     */
    public function generate(): ?string
    {
        $attempts = 0;

        Log::info('Starting unique card number generation', [
            'max_attempts' => self::MAX_ATTEMPTS,
        ]);

        $existingCardNumbers = Vehicle::whereNotNull('card_number')
            ->where('card_number', 'like', self::PREFIX . '%')
            ->pluck('card_number')
            ->toArray();

        Log::info('Found existing card numbers', [
            'count' => count($existingCardNumbers),
        ]);

        while ($attempts < self::MAX_ATTEMPTS) {
            try {
                $cardSeq = new CardSequence();
                $cardSeq->prefix = self::PREFIX;

                if ($cardSeq->save()) {
                    $cardNumber = $cardSeq->prefix . $cardSeq->number;

                    Log::info('Card sequence created', [
                        'attempt' => $attempts + 1,
                        'card_number' => $cardNumber,
                        'sequence_id' => $cardSeq->number,
                    ]);

                    if (!in_array($cardNumber, $existingCardNumbers, true)) {
                        Log::info('Unique card number found', [
                            'card_number' => $cardNumber,
                            'attempts' => $attempts + 1,
                        ]);

                        return $cardNumber;
                    }

                    Log::warning('Card number already exists, retrying', [
                        'card_number' => $cardNumber,
                        'attempt' => $attempts + 1,
                    ]);
                } else {
                    Log::error('Failed to save card sequence', [
                        'attempt' => $attempts + 1,
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Exception during card number generation', [
                    'attempt' => $attempts + 1,
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
            }

            $attempts++;

            if ($attempts % 200 === 0) {
                usleep(100000);
            }
        }

        Log::error('Failed to generate unique card number after all attempts', [
            'max_attempts' => self::MAX_ATTEMPTS,
        ]);

        return null;
    }
}
