<?php

namespace App\Support\Reports;

/**
 * Builds JSON param schemas for report engine definitions from filter keys.
 */
class ReportParamSchema
{
    private const LABELS = [
        'from_date' => 'From Date',
        'to_date' => 'To Date',
        'shift_id' => 'Shift',
        'shift_date' => 'Shift Date',
        'body_type' => 'Body Type',
        'body_type_id' => 'Body Type',
        'lane' => 'Lane / Booth',
        'lane_id' => 'Lane',
        'operator' => 'Operator (optional)',
        'user_id' => 'Operator (optional)',
        'counter_date' => 'Shift Date',
        'open_counter' => 'Open Counter',
        'close_counter' => 'Close Counter',
        'options' => 'Report Option',
        'collection_type' => 'Collection Type',
        'trans_type' => 'Transaction Type',
        'account_no' => 'Account Number',
        'year' => 'Year',
    ];

    private const INPUT_MAP = [
        'from_date' => 'date',
        'to_date' => 'date',
        'shift_date' => 'date',
        'counter_date' => 'date',
        'open_counter' => 'datetime',
        'close_counter' => 'datetime',
        'shift_id' => 'shift',
        'lane' => 'lane',
        'lane_id' => 'lane',
        'body_type' => 'body_type',
        'body_type_id' => 'body_type',
        'operator' => 'operator',
        'user_id' => 'operator',
        'options' => 'options',
        'collection_type' => 'collection_type',
        'trans_type' => 'trans_type',
        'account_no' => 'text',
        'year' => 'year',
    ];

    public static function fromFilters(array $filters): array
    {
        $params = [];
        foreach ($filters as $filter) {
            $params[$filter] = [
                'type' => 'string',
                'input' => self::INPUT_MAP[$filter] ?? 'text',
                'required' => self::isRequired($filter),
                'label' => self::LABELS[$filter] ?? ucwords(str_replace('_', ' ', $filter)),
            ];
        }

        return $params;
    }

    private static function isRequired(string $filter): bool
    {
        return in_array($filter, ['from_date', 'to_date', 'shift_id', 'shift_date', 'year'], true);
    }

    public static function filterKeys(array $params): array
    {
        return array_keys($params);
    }
}
