/** Default currency label for BMS (Tanzania shillings). */
export const DEFAULT_CURRENCY = 'TZS';

/**
 * Coerce API / form values to a finite number, or null if not numeric.
 */
export const toNumber = (value) => {
  if (value === null || value === undefined || value === '') return null;
  if (typeof value === 'number') return Number.isFinite(value) ? value : null;
  const cleaned = String(value).replace(/,/g, '').trim();
  if (cleaned === '' || cleaned === '-') return null;
  const n = Number(cleaned);
  return Number.isFinite(n) ? n : null;
};

export const toNumberOrNull = toNumber;

/** Round to two decimal places (currency / payroll amounts). */
export const round2 = (n) => Math.round(n * 100) / 100;

/**
 * Generic number with thousand separators (non-currency).
 */
export const formatNumber = (value, { minimumFractionDigits = 0, maximumFractionDigits = 0 } = {}) => {
  const n = toNumber(value);
  if (n === null) return '—';
  return new Intl.NumberFormat('en-TZ', { minimumFractionDigits, maximumFractionDigits }).format(n);
};

/**
 * Count / quantity columns (integers, no currency).
 */
export const formatCount = (value) => formatNumber(value, { maximumFractionDigits: 0 });

const NON_MONEY_KEYS = new Set([
  'count',
  'vehicles',
  'vehiclecount',
  'total_vehicle',
  'daily_bundle',
  'weekly_bundle',
  'monthly_bundle',
  'plate_no',
  'receipt_num',
  'receipt_number',
  'control_num',
  'id',
]);

/**
 * Heuristic: should a table column be rendered as money?
 */
export const isMoneyField = (key) => {
  if (key === null || key === undefined) return false;
  const k = String(key);
  const lower = k.toLowerCase();

  if (NON_MONEY_KEYS.has(lower)) return false;
  if (/^daily_bundle$|^weekly_bundle$|^monthly_bundle$/i.test(k)) return false;
  if (lower.endsWith('count') && !lower.includes('amount') && !lower.includes('collection')) return false;

  return (
    /(amount|fee|price|total_amount|total_type|collection|charged|bill_amount|bill|payment|cost|balance|revenue|difference|collected)/i.test(
      k
    ) || k === 'Collection' || k === 'fee' || k === 'AmountCollected'
  );
};

/**
 * Standard BMS money display: `TZS 1,234,567` (no decimal places by default).
 */
export const formatMoney = (value, options = {}) => {
  const {
    currency = DEFAULT_CURRENCY,
    nullLabel = '—',
    decimals = 0,
    showCurrency = true,
  } = options;

  const n = toNumber(value);
  if (n === null) return nullLabel;

  const formatted = new Intl.NumberFormat('en-TZ', {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals,
  }).format(n);

  return showCurrency ? `${currency} ${formatted}` : formatted;
};

/**
 * Payroll journal / report amounts: `TZS 1,234,567.89` (always 2 decimal places).
 */
export const formatPayrollMoney = (value, options = {}) =>
  formatMoney(value, { ...options, decimals: 2 });

/**
 * Intl currency style when locale support is available.
 */
export const formatCurrency = (value, currency = DEFAULT_CURRENCY) => {
  const n = toNumber(value);
  if (n === null) return '—';
  try {
    return new Intl.NumberFormat('en-TZ', {
      style: 'currency',
      currency,
      maximumFractionDigits: 0,
    }).format(n);
  } catch {
    return formatMoney(n, { currency });
  }
};

/**
 * Format a value for Excel export (plain number string with separators).
 */
export const formatMoneyForExport = (value) => {
  const n = toNumber(value);
  if (n === null) return '';
  return formatNumber(n, { maximumFractionDigits: 0 });
};
