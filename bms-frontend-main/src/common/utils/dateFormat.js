const MONTHS_SHORT = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

export const formatMonthYear = (month, year, { separator = ' ' } = {}) => {
  const m = Number(month);
  const y = Number(year);
  if (!Number.isFinite(m) || !Number.isFinite(y)) return '';
  const name = MONTHS_SHORT[m - 1] || String(m);
  return `${name}${separator}${y}`;
};

export const formatMonthYearLabel = (month, year) => formatMonthYear(month, year, { separator: '-' });

/** Format API datetime strings (e.g. "2026-07-04 04:03:35") for bill table display. */
export const formatBillDate = (value, { empty = '-' } = {}) => {
  if (value == null || value === '') return empty;
  const d = new Date(String(value).replace(' ', 'T'));
  if (Number.isNaN(d.getTime())) return String(value);
  return d.toLocaleString('en-GB', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

