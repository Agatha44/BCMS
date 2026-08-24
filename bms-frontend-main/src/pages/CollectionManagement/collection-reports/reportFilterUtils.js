/** Canonical display order for collection report filters. */
const FILTER_ORDER = [
  'from_date',
  'to_date',
  'shift_date',
  'counter_date',
  'shift_id',
  'open_counter',
  'close_counter',
  'year',
  'collection_type',
  'trans_type',
  'account_no',
  'options',
  'body_type',
  'body_type_id',
  'lane',
  'lane_id',
  'operator',
  'user_id',
];

const DATE_RANGE_KEYS = new Set(['from_date', 'to_date']);
const SHIFT_KEYS = new Set(['shift_id', 'shift_date', 'counter_date']);
const SHIFT_WINDOW_KEYS = new Set(['open_counter', 'close_counter']);

export function sortReportFilters(filters = []) {
  if (!Array.isArray(filters) || filters.length === 0) return [];
  const unique = [...new Set(filters)];
  const rank = Object.fromEntries(FILTER_ORDER.map((key, index) => [key, index]));
  return [...unique].sort((a, b) => {
    const ra = rank[a] ?? 999;
    const rb = rank[b] ?? 999;
    if (ra !== rb) return ra - rb;
    return String(a).localeCompare(String(b));
  });
}

/**
 * @returns {{ key: string, title?: string, hint?: string, columns: number, filters: string[] }[]}
 */
export function groupReportFilters(filters = []) {
  const sorted = sortReportFilters(filters);
  const groups = [];
  const used = new Set();

  const take = (keys, config) => {
    const picked = keys.filter((key) => sorted.includes(key) && !used.has(key));
    if (picked.length === 0) return;
    picked.forEach((key) => used.add(key));
    groups.push({ filters: picked, ...config });
  };

  take(['from_date', 'to_date'], {
    key: 'date_range',
    title: 'Date Range',
    hint: 'Start date must be on or before end date.',
    columns: 2,
  });

  take(['shift_date', 'counter_date', 'shift_id'], {
    key: 'shift',
    title: 'Shift',
    columns: 3,
  });

  take(['open_counter', 'close_counter'], {
    key: 'shift_window',
    title: 'Shift Window',
    hint: 'Open counter must be before close counter.',
    columns: 2,
  });

  take(['year'], {
    key: 'period',
    title: 'Period',
    columns: 1,
  });

  take(['account_no'], {
    key: 'account',
    title: 'Account',
    columns: 1,
  });

  take(['collection_type', 'trans_type', 'options'], {
    key: 'report_options',
    title: 'Report Options',
    columns: 2,
  });

  const remaining = sorted.filter((key) => !used.has(key));
  if (remaining.length > 0) {
    groups.push({
      key: 'other',
      title: 'Additional Filters',
      columns: 3,
      filters: remaining,
    });
  }

  return groups;
}

export function validateReportFilters(filters = {}) {
  const errors = {};

  if (filters.from_date && filters.to_date && filters.to_date < filters.from_date) {
    errors.to_date = 'End date must be on or after start date.';
    errors.from_date = 'Start date must be on or before end date.';
  }

  if (filters.open_counter && filters.close_counter && filters.close_counter <= filters.open_counter) {
    errors.close_counter = 'Close counter must be after open counter.';
  }

  return errors;
}

export function normalizeFiltersAfterChange(key, value, prev = {}) {
  const next = {
    ...prev,
    [key]: value,
    ...(key !== 'page' && key !== 'per_page' ? { page: 1 } : {}),
  };

  if (key === 'from_date' && value && next.to_date && next.to_date < value) {
    next.to_date = value;
  }

  if (key === 'to_date' && value && next.from_date && value < next.from_date) {
    next.from_date = value;
  }

  return next;
}
