/** @typedef {'from_date'|'to_date'|'shift_id'|'shift_date'|'body_type'|'body_type_id'|'lane'|'operator'|'user_id'|'lane_id'|'counter_date'|'open_counter'|'close_counter'|'options'|'collection_type'|'year'} ReportFilterKey */

/**
 * @typedef {Object} ReportColumn
 * @property {string} title
 * @property {string} key
 * @property {(row: Record<string, unknown>) => unknown} [render]
 */

/**
 * @typedef {Object} ReportDefinition
 * @property {string} id
 * @property {string} label
 * @property {string} description
 * @property {string} [apiSlug] - path under /api/collection-reports/
 * @property {ReportFilterKey[]} filters
 * @property {ReportColumn[]} columns
 * @property {boolean} [paginated]
 * @property {boolean} [nested] - shift summary style (data + cancelled_receipts)
 */

/** @type {ReportDefinition[]} */
export const COLLECTION_REPORTS = [
  {
    id: 'daily-collection',
    label: 'Daily Toll Fee Collection',
    description: 'Cash toll totals by shift for a date range, including evening shift window.',
    apiSlug: 'daily-collection',
    filters: ['from_date', 'to_date'],
    columns: [
      { title: 'Shift', key: 'shift' },
      { title: 'Transactions', key: 'count' },
      { title: 'Amount (TZS)', key: 'amount' },
    ],
  },
  {
    id: 'daily-shift-collection',
    label: 'Daily Shift Collection',
    description: 'Collection for a single shift across a date range.',
    apiSlug: 'daily-shift-collection',
    filters: ['from_date', 'to_date', 'shift_id'],
    columns: [
      { title: 'Shift', key: 'name' },
      { title: 'Transactions', key: 'count' },
      { title: 'Amount (TZS)', key: 'amount' },
    ],
  },
  {
    id: 'body-type-collection',
    label: 'Toll Collection Per Body Type',
    description: 'Transactions grouped by body type and tariff.',
    apiSlug: 'body-type-collection',
    filters: ['from_date', 'to_date', 'body_type', 'operator'],
    columns: [
      { title: 'Body Type', key: 'body_type' },
      { title: 'Fee', key: 'fee' },
      { title: 'Count', key: 'count' },
      { title: 'Amount (TZS)', key: 'amount' },
    ],
  },
  {
    id: 'booth-collection',
    label: 'Toll Collection Per Booth',
    description: 'Totals grouped by lane/booth.',
    apiSlug: 'booth-collection',
    filters: ['from_date', 'to_date', 'lane'],
    columns: [
      { title: 'Lane / Booth', key: 'lane' },
      { title: 'Transactions', key: 'count' },
      { title: 'Amount (TZS)', key: 'amount' },
    ],
  },
  {
    id: 'daily-cashless',
    label: 'Daily Cashless Collection',
    description: 'Cashless transactions grouped by day.',
    apiSlug: 'daily-cashless',
    filters: ['from_date', 'to_date'],
    columns: [
      { title: 'Date', key: 'Date' },
      { title: 'Vehicles', key: 'Vehicles' },
      { title: 'Amount (TZS)', key: 'AmountCollected' },
    ],
  },
  {
    id: 'body-cashless',
    label: 'Cashless Collection Per Body Type',
    description: 'Cashless totals grouped by vehicle body type.',
    apiSlug: 'body-cashless',
    filters: ['from_date', 'to_date'],
    columns: [
      { title: 'Body Type', key: 'bodyType' },
      { title: 'Vehicles', key: 'Vehicles' },
      { title: 'Amount (TZS)', key: 'AmountCollected' },
    ],
  },
  {
    id: 'bundle-collection',
    label: 'Bundle Collection',
    description: 'Paid bundle bills by body type and bundle period.',
    apiSlug: 'bundle-collection',
    filters: ['from_date', 'to_date', 'body_type_id'],
    columns: [
      { title: 'Body Type', key: 'body_type' },
      { title: 'Daily', key: 'Daily_Bundle' },
      { title: 'Weekly', key: 'Weekly_Bundle' },
      { title: 'Monthly', key: 'Monthly_Bundle' },
      { title: 'Total Vehicles', key: 'Total_Vehicle' },
      { title: 'Total Amount', key: 'total_amount' },
    ],
  },
  {
    id: 'bundle-registration',
    label: 'Bundle Registration',
    description: 'New or updated vehicle registrations with RFID cards.',
    apiSlug: 'bundle-registration',
    filters: ['from_date', 'to_date', 'options', 'body_type', 'operator'],
    columns: [
      { title: 'Body Type', key: 'name' },
      { title: 'Vehicle Count', key: 'VehicleCount' },
    ],
  },
  {
    id: 'bundle-subscription',
    label: 'Bundle Subscription',
    description: 'Active bundle subscriptions created in the period.',
    apiSlug: 'bundle-subscription',
    filters: ['from_date', 'to_date', 'options'],
    columns: [
      { title: 'Body Type', key: 'name' },
      { title: 'Daily', key: 'Daily_Bundle' },
      { title: 'Weekly', key: 'Weekly_Bundle' },
      { title: 'Monthly', key: 'Monthly_Bundle' },
      { title: 'Total', key: 'Total_Vehicle' },
    ],
  },
  {
    id: 'vehicle-passage',
    label: 'Vehicle Passage',
    description: 'Toll passages for a date range (export-friendly list).',
    apiSlug: 'vehicle-passage',
    filters: ['from_date', 'to_date', 'operator'],
    columns: [
      { title: 'Lane', key: 'lane_no' },
      { title: 'Plate', key: 'plate_no' },
      { title: 'Amount', key: 'charged_amount' },
      { title: 'Type', key: 'trans_type' },
      { title: 'Date', key: 'created_at' },
      { title: 'Operator', key: 'operator_name' },
    ],
  },
  {
    id: 'vehicle-passage-live',
    label: 'Vehicle Passage (Live)',
    description: 'Paginated passage register with search (today by default).',
    apiSlug: 'vehicle-passage/paginated',
    filters: ['from_date', 'to_date'],
    paginated: true,
    columns: [
      { title: 'Plate', key: 'plate_no' },
      { title: 'Body Type', key: 'body_type' },
      { title: 'Lane', key: 'lane_no' },
      { title: 'Payment', key: 'payment_method' },
      { title: 'Amount', key: 'charged_amount' },
      { title: 'Date', key: 'created_at' },
      { title: 'Operator', key: 'name' },
    ],
  },
  {
    id: 'toll-collection-detail',
    label: 'Toll Collection Detail',
    description: 'Line-level toll transactions with optional booth/shift/operator filters.',
    apiSlug: 'toll-collection-detail',
    filters: ['from_date', 'to_date', 'shift_id', 'body_type_id', 'lane', 'user_id'],
    columns: [
      { title: 'Operator', key: 'operator' },
      { title: 'Body Type', key: 'body_type' },
      { title: 'Booth', key: 'booth' },
      { title: 'Shift', key: 'shift' },
      { title: 'Method', key: 'method' },
      { title: 'Amount', key: 'amount' },
      { title: 'Date', key: 'day' },
    ],
  },
  {
    id: 'toll-collection-summary',
    label: 'Toll Collection Summary',
    description: 'Bundle, cash, or prepayment totals by body type.',
    apiSlug: 'toll-collection-summary',
    filters: ['from_date', 'to_date', 'collection_type'],
    columns: [
      { title: 'Body Type', key: 'name' },
      { title: 'Period', key: 'period' },
      { title: 'Count', key: 'count' },
      { title: 'Amount (TZS)', key: 'amount' },
    ],
  },
  {
    id: 'incident-collection-summary',
    label: 'Incident Collection Summary',
    description: 'Incident fines grouped by nature.',
    apiSlug: 'incident-collection-summary',
    filters: ['from_date', 'to_date'],
    columns: [
      { title: 'Incident Type', key: 'name' },
      { title: 'Count', key: 'incident_count' },
      { title: 'Amount (TZS)', key: 'total_amount' },
    ],
  },
  {
    id: 'overload-collection-summary',
    label: 'Overload Collection Summary',
    description: 'Overload fines collected in the period.',
    apiSlug: 'overload-collection-summary',
    filters: ['from_date', 'to_date'],
    columns: [
      { title: 'Category', key: 'name' },
      { title: 'Count', key: 'overload_count' },
      { title: 'Amount (TZS)', key: 'amount_collected' },
    ],
  },
  {
    id: 'event-collection-summary',
    label: 'Event Collection Summary',
    description: 'Event payments collected in the period.',
    apiSlug: 'event-collection-summary',
    filters: ['from_date', 'to_date'],
    columns: [
      { title: 'Event Type', key: 'name' },
      { title: 'Count', key: 'event_count' },
      { title: 'Amount (TZS)', key: 'amount_collected' },
    ],
  },
];

/** @type {ReportDefinition[]} */
export const SHIFT_REPORTS = [
  {
    id: 'shift-collection',
    label: 'Shift Collection Per Operator',
    description: 'All operators and booths with cash totals for a shift and date.',
    apiSlug: 'shift-collection',
    filters: ['shift_id', 'shift_date'],
    columns: [
      { title: 'Operator', key: 'operator_name' },
      { title: 'Booth', key: 'booth' },
      { title: 'Collection (TZS)', key: 'Collection' },
    ],
  },
  {
    id: 'end-of-shift-overall',
    label: 'End of Shift — Transactions',
    description: 'Line-level cash transactions for a shift window, with optional operator and lane filters.',
    apiSlug: 'end-of-shift-overall',
    filters: ['user_id', 'shift_id', 'shift_date', 'lane_id'],
    columns: [
      { title: 'Operator', key: 'operator_name' },
      { title: 'Plate', key: 'plate_no' },
      { title: 'Body Type', key: 'body_type' },
      { title: 'Lane', key: 'lane_no' },
      { title: 'Amount', key: 'charged_amount' },
      { title: 'Type', key: 'trans_type' },
      { title: 'Date', key: 'created_at' },
    ],
  },
  {
    id: 'shift-summary',
    label: 'End of Shift — Summary',
    description: 'Body type breakdown for a shift window.',
    apiSlug: 'shift-summary',
    filters: ['user_id', 'shift_id', 'shift_date', 'lane_id'],
    nested: true,
    columns: [
      { title: 'Operator', key: 'operator_name' },
      { title: 'Body Type', key: 'name' },
      { title: 'Shift', key: 'shift' },
      { title: 'Lane', key: 'lane_no' },
      { title: 'Count', key: 'count' },
      { title: 'Unit Amount', key: 'charged_amount' },
      { title: 'Total', key: 'TOTAL_TYPE' },
    ],
  },
];

/** @type {ReportDefinition[]} */
export const PAYMENT_REPORTS = [
  {
    id: 'monthly-collection-summary',
    label: 'Monthly Collection Summary',
    description: 'Toll, incident, overload, and event totals by month for a calendar year.',
    apiSlug: 'monthly-collection-summary',
    filters: ['year'],
    columns: [
      { title: 'Month', key: 'month' },
      { title: 'Toll (TZS)', key: 'toll_collections' },
      { title: 'Incident (TZS)', key: 'incident_fines' },
      { title: 'Overload (TZS)', key: 'overload_fines' },
      { title: 'Events (TZS)', key: 'events' },
      { title: 'Total (TZS)', key: 'total' },
    ],
  },
  {
    id: 'payment-reconciliation',
    label: 'Payment Reconciliation List',
    description: 'Incident, event, and top-up receipts posted to PSP (non-cancelled).',
    apiSlug: 'payment-reconciliation',
    filters: [],
    columns: [
      { title: 'Source', key: 'source' },
      { title: 'Receipt Type', key: 'name' },
      { title: 'Amount', key: 'amount' },
      { title: 'Receipt No.', key: 'receipt_number' },
      { title: 'Bank Date', key: 'bank_date' },
      { title: 'Control No.', key: 'control_num' },
      { title: 'Channel', key: 'payment_channel' },
    ],
  },
];

/** @type {ReportDefinition[]} */
export const AUDIT_REPORTS = [
  {
    id: 'body-type-audit',
    label: 'Body Type Audit',
    description: 'Vehicle body type changes with fee difference.',
    apiSlug: 'body-type-audit',
    filters: ['from_date', 'to_date', 'operator'],
    columns: [
      { title: 'Date', key: 'date' },
      { title: 'Operator', key: 'operator' },
      { title: 'Plate', key: 'plateno' },
      { title: 'Previous', key: 'prev_body_type' },
      { title: 'Current', key: 'current_body' },
      { title: 'Difference', key: 'difference' },
    ],
  },
  {
    id: 'exempted-vehicles',
    label: 'Exempted Vehicles',
    description: 'Exempt toll passages in the period.',
    apiSlug: 'exempted-vehicles',
    filters: ['from_date', 'to_date', 'operator'],
    columns: [
      { title: 'Operator', key: 'operator' },
      { title: 'Plate', key: 'plate_no' },
      { title: 'Body Type', key: 'body_type' },
      { title: 'Booth', key: 'booth' },
      { title: 'Shift', key: 'shift' },
      { title: 'Method', key: 'method' },
      { title: 'Amount', key: 'amount' },
      { title: 'Date', key: 'day' },
    ],
  },
  {
    id: 'cancelled-transactions',
    label: 'Cancelled Transactions',
    description: 'Cancelled toll receipts by update date.',
    apiSlug: 'cancelled-transactions',
    filters: ['from_date', 'to_date', 'operator'],
    columns: [
      { title: 'Shift', key: 'shift_name' },
      { title: 'Plate', key: 'plate_no' },
      { title: 'Receipt', key: 'receipt_num' },
      { title: 'Amount', key: 'charged_amount' },
      { title: 'Reason', key: 'reason' },
      { title: 'Operator', key: 'operator' },
      { title: 'Cancelled By', key: 'canceled_by' },
    ],
  },
];

/** Category navigation for Reports Management hub */
export const REPORT_CATEGORIES = [
  {
    key: 'collection',
    label: 'Collection',
    shortLabel: 'Collection',
    description: 'Daily toll, booth, body type, bundle, cashless, and passage reports.',
    reportCount: COLLECTION_REPORTS.length,
    reports: COLLECTION_REPORTS,
  },
  {
    key: 'shift',
    label: 'Shift',
    shortLabel: 'Shift',
    description: 'Per-operator shift totals, transaction detail, and body type summaries.',
    reportCount: SHIFT_REPORTS.length,
    reports: SHIFT_REPORTS,
  },
  {
    key: 'payment',
    label: 'Payment',
    shortLabel: 'Payment',
    description: 'Cross-module payment and reconciliation listings.',
    reportCount: PAYMENT_REPORTS.length,
    reports: PAYMENT_REPORTS,
  },
  {
    key: 'audit',
    label: 'Audit',
    shortLabel: 'Audit',
    description: 'Body type audit, exempted vehicles, and cancelled transactions.',
    reportCount: AUDIT_REPORTS.length,
    reports: AUDIT_REPORTS,
  },
];

export const FILTER_LABELS = {
  from_date: 'Start Date',
  to_date: 'End Date',
  shift_id: 'Shift',
  shift_date: 'Shift Date',
  body_type: 'Body Type',
  body_type_id: 'Body Type',
  lane: 'Lane / Booth',
  lane_id: 'Lane',
  operator: 'Operator (optional)',
  user_id: 'Operator (optional)',
  counter_date: 'Shift Date',
  open_counter: 'Open Counter',
  close_counter: 'Close Counter',
  options: 'Report Option',
  collection_type: 'Collection Type',
  trans_type: 'Transaction Type',
  account_no: 'Account Number',
  year: 'Year',
};
