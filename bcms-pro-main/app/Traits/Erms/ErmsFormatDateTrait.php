<?php

namespace App\Traits\Erms;

use Carbon\Carbon;
use DateTimeInterface;

trait ErmsFormatDateTrait
{
    /**
     * Format a value as DD-MON-YYYY (Oracle TO_CHAR equivalent) for ERMS JSON payloads.
     */
    protected function formatErmsDate(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if ($value instanceof DateTimeInterface) {
            $c = Carbon::instance($value);

            return strtoupper($c->format('d-M-Y'));
        }

        if (is_string($value)) {
            try {
                $c = Carbon::parse($value);

                return strtoupper($c->format('d-M-Y'));
            } catch (\Throwable) {
                return '';
            }
        }

        return '';
    }

    /**
     * ERMS invoice receivableDate (YYYY-MM-DD).
     */
    protected function formatReceivableDateIso(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->format('Y-m-d');
        }

        if (is_string($value)) {
            try {
                return Carbon::parse($value)->format('Y-m-d');
            } catch (\Throwable) {
                return '';
            }
        }

        return '';
    }
}
