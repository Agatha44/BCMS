<?php

namespace Database\Seeders\ReportEngine;

use App\Support\Reports\ReportParamSchema;

/**
 * Canonical collection-management report definitions (migrated from reportCatalog.js).
 */
class CollectionReportDefinitions
{
    public const CATEGORIES = [
        'collection' => [
            'label' => 'Collection',
            'short_label' => 'Collection',
            'description' => 'Daily toll, booth, body type, bundle, cashless, and passage reports.',
        ],
        'shift' => [
            'label' => 'Shift',
            'short_label' => 'Shift',
            'description' => 'Per-operator shift totals, transaction detail, and body type summaries.',
        ],
        'payment' => [
            'label' => 'Payment',
            'short_label' => 'Payment',
            'description' => 'Cross-module payment and reconciliation listings.',
        ],
        'audit' => [
            'label' => 'Audit',
            'short_label' => 'Audit',
            'description' => 'Body type audit, exempted vehicles, and cancelled transactions.',
        ],
    ];

    public static function all(): array
    {
        $definitions = [];
        $sort = 0;
        foreach (self::rawReports() as $categoryKey => $reports) {
            foreach ($reports as $report) {
                $sort++;
                $definitions[] = self::normalize($categoryKey, $report, $sort);
            }
        }

        return $definitions;
    }

    private static function normalize(string $categoryKey, array $report, int $sort): array
    {
        $script = $report['handler'];
        $key = $report['id'];

        return [
            'category_key' => $categoryKey,
            'name' => $report['label'],
            'key' => $key,
            'description' => $report['description'] ?? null,
            'script' => $script,
            'handler' => $script,
            'query' => CollectionReportQueries::forScript($script),
            'params' => ReportParamSchema::fromFilters($report['filters'] ?? []),
            'output_columns' => $report['columns'] ?? [],
            'options' => [
                'paginated' => (bool) ($report['paginated'] ?? false),
                'nested' => (bool) ($report['nested'] ?? false),
            ],
            'sort_order' => $sort,
            'is_active' => true,
        ];
    }

    private static function rawReports(): array
    {
        return [
            'collection' => [
                self::report('daily-collection', 'Daily Toll Fee Collection', 'daily-collection', ['from_date', 'to_date'], [
                    ['title' => 'Shift', 'key' => 'shift'],
                    ['title' => 'Transactions', 'key' => 'count'],
                    ['title' => 'Amount (TZS)', 'key' => 'amount'],
                ], 'Cash toll totals by shift for a date range, including evening shift window.'),
                self::report('daily-shift-collection', 'Daily Shift Collection', 'daily-shift-collection', ['from_date', 'to_date', 'shift_id'], [
                    ['title' => 'Shift', 'key' => 'name'],
                    ['title' => 'Transactions', 'key' => 'count'],
                    ['title' => 'Amount (TZS)', 'key' => 'amount'],
                ], 'Collection for a single shift across a date range.'),
                self::report('body-type-collection', 'Toll Collection Per Body Type', 'body-type-collection', ['from_date', 'to_date', 'body_type', 'operator'], [
                    ['title' => 'Body Type', 'key' => 'body_type'],
                    ['title' => 'Fee', 'key' => 'fee'],
                    ['title' => 'Count', 'key' => 'count'],
                    ['title' => 'Amount (TZS)', 'key' => 'amount'],
                ]),
                self::report('booth-collection', 'Toll Collection Per Booth', 'booth-collection', ['from_date', 'to_date', 'lane'], [
                    ['title' => 'Lane / Booth', 'key' => 'lane'],
                    ['title' => 'Transactions', 'key' => 'count'],
                    ['title' => 'Amount (TZS)', 'key' => 'amount'],
                ]),
                self::report('daily-cashless', 'Daily Cashless Collection', 'daily-cashless', ['from_date', 'to_date'], [
                    ['title' => 'Date', 'key' => 'Date'],
                    ['title' => 'Vehicles', 'key' => 'Vehicles'],
                    ['title' => 'Amount (TZS)', 'key' => 'AmountCollected'],
                ]),
                self::report('body-cashless', 'Cashless Collection Per Body Type', 'body-cashless', ['from_date', 'to_date'], [
                    ['title' => 'Body Type', 'key' => 'bodyType'],
                    ['title' => 'Vehicles', 'key' => 'Vehicles'],
                    ['title' => 'Amount (TZS)', 'key' => 'AmountCollected'],
                ]),
                self::report('bundle-collection', 'Bundle Collection', 'bundle-collection', ['from_date', 'to_date', 'body_type_id'], [
                    ['title' => 'Body Type', 'key' => 'body_type'],
                    ['title' => 'Daily', 'key' => 'Daily_Bundle'],
                    ['title' => 'Weekly', 'key' => 'Weekly_Bundle'],
                    ['title' => 'Monthly', 'key' => 'Monthly_Bundle'],
                    ['title' => 'Total Vehicles', 'key' => 'Total_Vehicle'],
                    ['title' => 'Total Amount', 'key' => 'total_amount'],
                ]),
                self::report('bundle-registration', 'Bundle Registration', 'bundle-registration', ['from_date', 'to_date', 'options', 'body_type', 'operator'], [
                    ['title' => 'Body Type', 'key' => 'name'],
                    ['title' => 'Vehicle Count', 'key' => 'VehicleCount'],
                ]),
                self::report('bundle-subscription', 'Bundle Subscription', 'bundle-subscription', ['from_date', 'to_date', 'options'], [
                    ['title' => 'Body Type', 'key' => 'name'],
                    ['title' => 'Daily', 'key' => 'Daily_Bundle'],
                    ['title' => 'Weekly', 'key' => 'Weekly_Bundle'],
                    ['title' => 'Monthly', 'key' => 'Monthly_Bundle'],
                    ['title' => 'Total', 'key' => 'Total_Vehicle'],
                ]),
                self::report('vehicle-passage', 'Vehicle Passage', 'vehicle-passage', ['from_date', 'to_date', 'operator'], [
                    ['title' => 'Lane', 'key' => 'lane_no'],
                    ['title' => 'Plate', 'key' => 'plate_no'],
                    ['title' => 'Amount', 'key' => 'charged_amount'],
                    ['title' => 'Type', 'key' => 'trans_type'],
                    ['title' => 'Date', 'key' => 'created_at'],
                    ['title' => 'Operator', 'key' => 'operator_name'],
                ]),
                self::report('vehicle-passage-live', 'Vehicle Passage (Live)', 'vehicle-passage/paginated', ['from_date', 'to_date'], [
                    ['title' => 'Plate', 'key' => 'plate_no'],
                    ['title' => 'Body Type', 'key' => 'body_type'],
                    ['title' => 'Lane', 'key' => 'lane_no'],
                    ['title' => 'Payment', 'key' => 'payment_method'],
                    ['title' => 'Amount', 'key' => 'charged_amount'],
                    ['title' => 'Date', 'key' => 'created_at'],
                    ['title' => 'Operator', 'key' => 'name'],
                ], null, ['paginated' => true]),
                self::report('toll-collection-detail', 'Toll Collection Detail', 'toll-collection-detail', ['from_date', 'to_date', 'shift_id', 'body_type_id', 'lane', 'user_id'], [
                    ['title' => 'Operator', 'key' => 'operator'],
                    ['title' => 'Body Type', 'key' => 'body_type'],
                    ['title' => 'Booth', 'key' => 'booth'],
                    ['title' => 'Shift', 'key' => 'shift'],
                    ['title' => 'Method', 'key' => 'method'],
                    ['title' => 'Amount', 'key' => 'amount'],
                    ['title' => 'Date', 'key' => 'day'],
                ]),
                self::report('toll-collection-summary', 'Toll Collection Summary', 'toll-collection-summary', ['from_date', 'to_date', 'collection_type'], [
                    ['title' => 'Body Type', 'key' => 'name'],
                    ['title' => 'Period', 'key' => 'period'],
                    ['title' => 'Count', 'key' => 'count'],
                    ['title' => 'Amount (TZS)', 'key' => 'amount'],
                ]),
                self::report('incident-collection-summary', 'Incident Collection Summary', 'incident-collection-summary', ['from_date', 'to_date'], [
                    ['title' => 'Incident Type', 'key' => 'name'],
                    ['title' => 'Count', 'key' => 'incident_count'],
                    ['title' => 'Amount (TZS)', 'key' => 'total_amount'],
                ]),
                self::report('overload-collection-summary', 'Overload Collection Summary', 'overload-collection-summary', ['from_date', 'to_date'], [
                    ['title' => 'Category', 'key' => 'name'],
                    ['title' => 'Count', 'key' => 'overload_count'],
                    ['title' => 'Amount (TZS)', 'key' => 'amount_collected'],
                ]),
                self::report('event-collection-summary', 'Event Collection Summary', 'event-collection-summary', ['from_date', 'to_date'], [
                    ['title' => 'Event Type', 'key' => 'name'],
                    ['title' => 'Count', 'key' => 'event_count'],
                    ['title' => 'Amount (TZS)', 'key' => 'amount_collected'],
                ]),
            ],
            'shift' => [
                self::report('shift-collection', 'Shift Collection Per Operator', 'shift-collection', ['shift_id', 'shift_date'], [
                    ['title' => 'Operator', 'key' => 'operator_name'],
                    ['title' => 'Booth', 'key' => 'booth'],
                    ['title' => 'Collection (TZS)', 'key' => 'Collection'],
                ]),
                self::report('end-of-shift-overall', 'End of Shift — Transactions', 'end-of-shift-overall', ['user_id', 'shift_id', 'shift_date', 'lane_id'], [
                    ['title' => 'Operator', 'key' => 'operator_name'],
                    ['title' => 'Plate', 'key' => 'plate_no'],
                    ['title' => 'Body Type', 'key' => 'body_type'],
                    ['title' => 'Lane', 'key' => 'lane_no'],
                    ['title' => 'Amount', 'key' => 'charged_amount'],
                    ['title' => 'Type', 'key' => 'trans_type'],
                    ['title' => 'Date', 'key' => 'created_at'],
                ]),
                self::report('shift-summary', 'End of Shift — Summary', 'shift-summary', ['user_id', 'shift_id', 'shift_date', 'lane_id'], [
                    ['title' => 'Operator', 'key' => 'operator_name'],
                    ['title' => 'Body Type', 'key' => 'name'],
                    ['title' => 'Shift', 'key' => 'shift'],
                    ['title' => 'Lane', 'key' => 'lane_no'],
                    ['title' => 'Count', 'key' => 'count'],
                    ['title' => 'Unit Amount', 'key' => 'charged_amount'],
                    ['title' => 'Total', 'key' => 'TOTAL_TYPE'],
                ], null, ['nested' => true]),
            ],
            'payment' => [
                self::report('monthly-collection-summary', 'Monthly Collection Summary', 'monthly-collection-summary', ['year'], [
                    ['title' => 'Month', 'key' => 'month'],
                    ['title' => 'Toll (TZS)', 'key' => 'toll_collections'],
                    ['title' => 'Incident (TZS)', 'key' => 'incident_fines'],
                    ['title' => 'Overload (TZS)', 'key' => 'overload_fines'],
                    ['title' => 'Events (TZS)', 'key' => 'events'],
                    ['title' => 'Total (TZS)', 'key' => 'total'],
                ]),
                self::report('payment-reconciliation', 'Payment Reconciliation List', 'payment-reconciliation', [], [
                    ['title' => 'Source', 'key' => 'source'],
                    ['title' => 'Receipt Type', 'key' => 'name'],
                    ['title' => 'Amount', 'key' => 'amount'],
                    ['title' => 'Receipt No.', 'key' => 'receipt_number'],
                    ['title' => 'Bank Date', 'key' => 'bank_date'],
                    ['title' => 'Control No.', 'key' => 'control_num'],
                    ['title' => 'Channel', 'key' => 'payment_channel'],
                ]),
            ],
            'audit' => [
                self::report('body-type-audit', 'Body Type Audit', 'body-type-audit', ['from_date', 'to_date', 'operator'], [
                    ['title' => 'Date', 'key' => 'date'],
                    ['title' => 'Operator', 'key' => 'operator'],
                    ['title' => 'Plate', 'key' => 'plateno'],
                    ['title' => 'Previous', 'key' => 'prev_body_type'],
                    ['title' => 'Current', 'key' => 'current_body'],
                    ['title' => 'Difference', 'key' => 'difference'],
                ]),
                self::report('exempted-vehicles', 'Exempted Vehicles', 'exempted-vehicles', ['from_date', 'to_date', 'operator'], [
                    ['title' => 'Operator', 'key' => 'operator'],
                    ['title' => 'Plate', 'key' => 'plate_no'],
                    ['title' => 'Body Type', 'key' => 'body_type'],
                    ['title' => 'Booth', 'key' => 'booth'],
                    ['title' => 'Shift', 'key' => 'shift'],
                    ['title' => 'Method', 'key' => 'method'],
                    ['title' => 'Amount', 'key' => 'amount'],
                    ['title' => 'Date', 'key' => 'day'],
                ]),
                self::report('cancelled-transactions', 'Cancelled Transactions', 'cancelled-transactions', ['from_date', 'to_date', 'operator'], [
                    ['title' => 'Shift', 'key' => 'shift_name'],
                    ['title' => 'Plate', 'key' => 'plate_no'],
                    ['title' => 'Receipt', 'key' => 'receipt_num'],
                    ['title' => 'Amount', 'key' => 'charged_amount'],
                    ['title' => 'Reason', 'key' => 'reason'],
                    ['title' => 'Operator', 'key' => 'operator'],
                    ['title' => 'Cancelled By', 'key' => 'canceled_by'],
                ]),
            ],
        ];
    }

    private static function report(
        string $id,
        string $label,
        string $handler,
        array $filters,
        array $columns,
        ?string $description = null,
        array $options = []
    ): array {
        return [
            'id' => $id,
            'label' => $label,
            'handler' => $handler,
            'filters' => $filters,
            'columns' => $columns,
            'description' => $description,
            'paginated' => $options['paginated'] ?? false,
            'nested' => $options['nested'] ?? false,
        ];
    }
}
